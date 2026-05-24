<?php

namespace App\Console\Commands;

use App\Services\KeyStoreService;
use App\Services\MailQueue;
use App\Services\ScheduleService;
use App\Services\SendService;
use Illuminate\Console\Command;

/**
 * Worker à 2 files :
 *   - noliae.mailweb.outbox    → envoi SMTP + copie IMAP
 *   - noliae.mailweb.keyimport → import auto de clés PGP reçues par mail
 */
class MailWorkCommand extends Command
{
    protected $signature   = 'mail:work';
    protected $description = "Consomme les files RabbitMQ du webmail (envois + auto-import PGP).";

    public function handle(MailQueue $queue, SendService $sender, KeyStoreService $store, ScheduleService $sched): int
    {
        $this->info('[mail:work] worker démarré (outbox + keyimport + scheduler)');

        // Scheduler intégré : registers une closure que le consumer appelle
        // périodiquement entre chaque ack. declare(ticks=N) ne fonctionnait
        // pas (doit être au top-level + déprécié PHP 8.1+). On utilise un
        // signal handler sur SIGALRM à la place pour ticker toutes les 60s.
        $tick = function () use ($sched, $queue) {
            static $nextSched = 0, $nextPurge = 0;
            $now = time();
            if ($now >= $nextSched) {
                $nextSched = $now + 60;
                try {
                    foreach ($sched->pop($now) as $payload) {
                        unset($payload['send_at']);
                        $queue->publish($payload);
                        $this->line('[scheduler] promu vers outbox → ' . implode(', ', (array) ($payload['to'] ?? [])));
                    }
                } catch (\Throwable $e) {
                    $this->error('[scheduler] ' . $e->getMessage());
                }
            }
            if ($now >= $nextPurge) {
                $nextPurge = $now + 21600;
                try {
                    $this->autoPurgeTrash();
                } catch (\Throwable $e) {
                    $this->error('[purge] ' . $e->getMessage());
                }
            }
        };
        if (function_exists('pcntl_signal') && function_exists('pcntl_alarm')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGALRM, function () use ($tick) { $tick(); pcntl_alarm(60); });
            pcntl_alarm(60);
        }

        $queue->consume([
            MailQueue::QUEUE => function (array $p) use ($sender) {
                if (empty($p['from']) || empty($p['to'])) {
                    throw new \RuntimeException('Payload outbox invalide.');
                }
                $sender->send(
                    (string) $p['from'],
                    (array)  $p['to'],
                    (string) ($p['subject'] ?? ''),
                    (string) ($p['html'] ?? ''),
                    [
                        'from_name'         => (string) ($p['from_name']   ?? ''),
                        'cc'                => (array)  ($p['cc']          ?? []),
                        'in_reply_to'       => $p['in_reply_to']           ?? null,
                        's3_links'          => (array)  ($p['s3_links']    ?? []),
                        'attachments_inline'=> (array)  ($p['attachments_inline'] ?? []),
                        'ask_receipt'       => (bool)   ($p['ask_receipt'] ?? false),
                        'pgp_encrypted'     => (bool)   ($p['pgp_encrypted'] ?? false),
                        'pgp_public_key'    => (string) ($p['pgp_public_key'] ?? ''),
                        'pgp_attach_public' => (bool)   ($p['pgp_attach_public'] ?? false),
                    ]
                );
                $this->line('[outbox] envoyé → ' . implode(', ', (array) $p['to']));
            },
            MailQueue::QUEUE_KEYIMPORT => function (array $p) use ($store) {
                if (empty($p['owner']) || empty($p['sender_email']) || empty($p['armored'])) {
                    throw new \RuntimeException('Payload keyimport invalide.');
                }
                if (! str_contains($p['armored'], 'BEGIN PGP PUBLIC KEY BLOCK')) {
                    return; // pas une clé publique → ignore
                }
                $store->save((string) $p['owner'], (string) $p['sender_email'],
                    (string) $p['armored'], 'inbound-mail');
                $this->line('[keyimport] clé PGP importée pour ' . $p['sender_email']);
            },
        ]);

        return self::SUCCESS;
    }

    /**
     * Purge les mails de la Corbeille de chaque utilisateur > 30 jours.
     * Boucle sur les comptes mail actifs en base.
     */
    private function autoPurgeTrash(): void
    {
        $mailbox = app(\App\Services\MailboxService::class);
        $accounts = [];
        try {
            // OSS : la table mail_accounts est dans la DB Laravel par défaut.
            // Si elle est ailleurs (SaaS), on retombe sur des creds explicites.
            if (\Schema::hasTable('mail_accounts')) {
                $accounts = \DB::table('mail_accounts')->where('active', true)->pluck('email')->all();
            } else {
                $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s',
                    env('DB_HOST'), (int) env('DB_PORT', 5432), env('DB_DATABASE'));
                $pdo = new \PDO($dsn, env('DB_USERNAME'), env('DB_PASSWORD'));
                $accounts = $pdo->query('SELECT email FROM mail_accounts WHERE active=true')->fetchAll(\PDO::FETCH_COLUMN);
            }
        } catch (\Throwable $e) { return; }
        $cutoff = time() - 30 * 86400;
        $purged = 0;
        foreach ($accounts as $email) {
            try {
                foreach ($mailbox->folders($email) as $f) {
                    if (($f['rank'] ?? 99) !== 5) continue; // 5 = Trash
                    foreach (($mailbox->messages($email, $f['path'], 200)['messages'] ?? []) as $m) {
                        if (! empty($m['date']) && strtotime($m['date']) < $cutoff) {
                            $mailbox->deleteMessage($email, $f['path'], (int) $m['uid']);
                            $purged++;
                        }
                    }
                    break; // un seul dossier Trash par boîte
                }
            } catch (\Throwable $e) {}
        }
        if ($purged) $this->line("[purge] $purged messages > 30 j supprimés des corbeilles");
    }
}
