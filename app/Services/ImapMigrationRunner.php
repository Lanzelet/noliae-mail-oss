<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Lance imapsync en arrière-plan, suit la progression via parsing stdout,
 * écrit dans mail_migrations.{status,copied_messages,total_messages,log_tail}.
 *
 * Pré-requis : `imapsync` installé dans le container web.
 * Si manquant : le run échoue immédiatement avec un message clair.
 */
class ImapMigrationRunner
{
    /** Démarre le job en background (nohup + redirection log). Retourne le PID. */
    public function start(int $migrationId): void
    {
        $job = DB::table('mail_migrations')
            ->join('mail_accounts', 'mail_accounts.id', '=', 'mail_migrations.dest_account_id')
            ->where('mail_migrations.id', $migrationId)
            ->select('mail_migrations.*', 'mail_accounts.email as dest_email', 'mail_accounts.password as dest_pass')
            ->first();
        if (! $job) return;

        if (! $this->imapsyncAvailable()) {
            DB::table('mail_migrations')->where('id', $migrationId)->update([
                'status' => 'failed',
                'error'  => 'imapsync n\'est pas installé sur le serveur. Ajouter `apt install imapsync` dans docker/web/Dockerfile puis rebuild.',
                'finished_at' => now(),
            ]);
            return;
        }

        // Master password pour s'authentifier comme l'user destination
        // via le master_user Dovecot (le mot de passe DB est BCRYPT, on
        // ne peut pas le repasser à imapsync — on impersonne via master).
        $masterUser = config('mail.master_user');
        $masterPass = config('mail.master_pass');
        $destAuth   = $job->dest_email . '*' . $masterUser;

        $sourcePass = Crypt::decryptString($job->source_pass_enc);

        // Fichier log dédié pour ce job
        $logPath = storage_path("logs/migration-$migrationId.log");
        @file_put_contents($logPath, "Démarrage imapsync pour migration #$migrationId\n");

        $cmd = [
            'imapsync',
            '--host1', $job->source_host,
            '--port1', (string) $job->source_port,
            '--user1', $job->source_user,
            '--password1', $sourcePass,
            $job->source_ssl ? '--ssl1' : '--nossl1',
            '--host2', config('mail.imap_host'),
            '--port2', (string) config('mail.imap_port'),
            '--user2', $destAuth,
            '--password2', $masterPass,
            config('mail.imap_encryption') ? '--ssl2' : '--nossl2',
            '--automap',
            '--no-modulesversion',
            '--logdir', storage_path('logs'),
            '--logfile', "migration-$migrationId.log",
            '--noreleasecheck',
        ];

        // Lance en background
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->start();
        DB::table('mail_migrations')->where('id', $migrationId)->update([
            'status'     => 'running',
            'pid'        => $process->getPid(),
            'started_at' => now(),
            'updated_at' => now(),
        ]);

        // Note : on ne wait() pas ici (l'appelant retourne tout de suite).
        // Un cron OU l'appel suivant à la page status doit relire le log
        // et mettre à jour copied/total. C'est fait par refreshStatus().
    }

    /** Lit la queue de logs + état pid et met à jour DB. À appeler à chaque load page. */
    public function refreshStatus(int $migrationId): void
    {
        $job = DB::table('mail_migrations')->where('id', $migrationId)->first();
        if (! $job) return;
        if (! in_array($job->status, ['pending', 'running'])) return;

        $logPath = storage_path("logs/migration-$migrationId.log");
        $tail = '';
        $copied = $job->copied_messages;
        $total  = $job->total_messages;
        if (is_readable($logPath)) {
            $lines = @file($logPath, FILE_IGNORE_NEW_LINES) ?: [];
            $tail = implode("\n", array_slice($lines, -100));
            // Parse "Total messages copied: N"
            foreach (array_reverse($lines) as $l) {
                if (preg_match('/(\d+)\s+messages? copied/i', $l, $m)) { $copied = max($copied, (int) $m[1]); break; }
            }
            foreach (array_reverse($lines) as $l) {
                if (preg_match('/Host1 messages\s*:\s*(\d+)/i', $l, $m)) { $total = (int) $m[1]; break; }
            }
        }

        // Process toujours en vie ?
        $alive = $job->pid && posix_kill($job->pid, 0);
        $status = $job->status;
        $error  = $job->error;
        $finishedAt = $job->finished_at;
        if (! $alive && $job->pid) {
            $finishedAt = now();
            // Cherche un marqueur de succès / échec
            if (str_contains($tail, 'Detected 0 errors') || preg_match('/Reconnect on Host\d : tries=1/', $tail) === false) {
                if (preg_match('/Detected (\d+) errors/i', $tail, $m) && (int) $m[1] === 0) {
                    $status = 'success';
                } elseif (preg_match('/Tail: (\d+) errors/', $tail, $m) && (int) $m[1] > 0) {
                    $status = 'failed';
                    $error  = 'imapsync a terminé avec ' . $m[1] . ' erreurs (voir log).';
                } else {
                    $status = 'success';
                }
            } else {
                $status = 'failed';
                $error  = 'imapsync s\'est arrêté de manière inattendue.';
            }
        }

        DB::table('mail_migrations')->where('id', $migrationId)->update([
            'status'          => $status,
            'copied_messages' => $copied,
            'total_messages'  => $total,
            'log_tail'        => $tail,
            'error'           => $error,
            'finished_at'     => $finishedAt,
            'updated_at'      => now(),
        ]);
    }

    /** Tue un job en cours (SIGTERM). */
    public function cancel(int $migrationId): void
    {
        $job = DB::table('mail_migrations')->where('id', $migrationId)->first();
        if (! $job || ! $job->pid) return;
        @posix_kill($job->pid, 15);
        DB::table('mail_migrations')->where('id', $migrationId)->update([
            'status' => 'cancelled', 'finished_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function imapsyncAvailable(): bool
    {
        $p = new Process(['which', 'imapsync']);
        $p->run();
        return $p->isSuccessful();
    }
}
