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

        // Ticks d'arrière-plan : scheduler + purge corbeille 30 j
        register_tick_function(function () use ($sched, $queue) {
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
            // Purge des corbeilles toutes les 6 h
            if ($now >= $nextPurge) {
                $nextPurge = $now + 21600;
                try {
                    $this->autoPurgeTrash();
                } catch (\Throwable $e) {
                    $this->error('[purge] ' . $e->getMessage());
                }
            }
        });
        declare(ticks=1);

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
        // Postgres direct (les mail_accounts ne sont pas dans SQLite Laravel)
        $accounts = [];
        try {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s',
                env('PG_MAIL_HOST'), (int) env('PG_MAIL_PORT', 5432), env('PG_MAIL_DB'));
            $pdo = new \PDO($dsn, env('PG_MAIL_USER'), env('PG_MAIL_PASS'));
            $accounts = $pdo->query('SELECT email FROM mail_accounts WHERE active=true')->fetchAll(\PDO::FETCH_COLUMN);
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
