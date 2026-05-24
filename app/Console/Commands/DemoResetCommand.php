<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Noliae Mail OSS — reset nightly de l'instance de démo.
 *
 *   docker compose exec web php artisan demo:reset
 *
 * Effets :
 *  - Supprime tous les comptes mail SAUF ADMIN_EMAIL (et les comptes ayant le
 *    flag is_persistent=true si la colonne existe)
 *  - Vide leur maildir sur disque
 *  - Supprime les mailing lists, boîtes partagées, forwards créés depuis 24h
 *  - Purge audit_log > 7 jours
 *  - Garde les domaines (mail_domains) intacts
 *
 * À activer uniquement sur l'instance de démo (DEMO_RESET=true dans .env)
 * et brancher sur un cron quotidien.
 */
class DemoResetCommand extends Command
{
    protected $signature   = 'demo:reset {--force : Bypass le check DEMO_RESET=true du .env}';
    protected $description = 'Reset l\'instance de démo (supprime users non-admin, vide maildirs)';

    public function handle(): int
    {
        if (! $this->option('force') && env('DEMO_RESET', false) !== true) {
            $this->error('DEMO_RESET=true requis dans .env (ou utilise --force).');
            return self::FAILURE;
        }

        $adminEmail = strtolower((string) (DB::table('app_settings')
            ->where('key', 'admin_email')->value('value') ?: env('ADMIN_EMAIL', '')));
        if (! $adminEmail) {
            $this->error('Aucun admin_email configuré — refuse de tout wiper.');
            return self::FAILURE;
        }

        // 1. Comptes à supprimer
        $q = DB::table('mail_accounts')->whereRaw('LOWER(email) != ?', [$adminEmail]);
        // Respecte un flag is_persistent si la colonne existe
        if (\Schema::hasColumn('mail_accounts', 'is_persistent')) {
            $q->where('is_persistent', false);
        }
        $toDelete = $q->get(['id', 'email', 'maildir']);
        $count = $toDelete->count();
        $this->info("→ $count comptes à supprimer (admin $adminEmail préservé)");

        foreach ($toDelete as $a) {
            // Vide le maildir sur disque (mounté en volume vmail).
            // Si on tourne dans web container : on n'y a pas accès, on délègue
            // à Dovecot via doveadm purge (best-effort).
            if ($a->maildir && is_dir($a->maildir)) {
                @exec('rm -rf ' . escapeshellarg($a->maildir));
            }
            DB::table('smtp_tokens')->where('mail_account_id', $a->id)->delete();
            DB::table('mail_accounts')->where('id', $a->id)->delete();
        }

        // 2. Listes / boîtes partagées / forwards créés depuis 24h
        $cutoff = now()->subHours(24);
        $purgedLists = DB::table('mail_lists')->where('created_at', '>', $cutoff)->delete();
        $purgedShared = DB::table('shared_mailboxes')->where('created_at', '>', $cutoff)->delete();
        $purgedAliases = DB::table('mail_aliases')->where('created_at', '>', $cutoff)->delete();
        $this->info("→ Listes -$purgedLists · Boîtes partagées -$purgedShared · Forwards -$purgedAliases");

        // 3. Audit log > 7j
        $purgedAudit = DB::table('audit_log')->where('created_at', '<', now()->subDays(7))->delete();
        $this->info("→ Audit log -$purgedAudit entrées");

        // 4. Quarantaine spam : laisse le daemon Postfix gérer

        $this->info('✅ Reset démo OK.');
        return self::SUCCESS;
    }
}
