<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Noliae Mail OSS — installation initiale (idempotent).
 *
 *   docker compose exec web php artisan mail:install
 *
 * Crée :
 *  - le domaine principal (MAIL_DOMAIN du .env)
 *  - le compte admin (ADMIN_EMAIL / ADMIN_PASSWORD du .env) avec rôle admin
 *  - le seed app_settings.admin_email
 *
 * Si le compte existe déjà : ne touche à rien sauf si --force-password
 * passé en argument, auquel cas le mot de passe est mis à jour.
 *
 * Indispensable parce que le volume Postgres peut être recréé entre deux
 * `docker compose down -v` — sans seeder, on perd le compte admin et plus
 * personne ne peut se connecter.
 */
class MailInstallCommand extends Command
{
    protected $signature   = 'mail:install {--force-password : Force la mise à jour du mot de passe admin même si le compte existe}';
    protected $description = 'Crée le domaine + compte admin Noliae Mail à partir du .env';

    public function handle(): int
    {
        $domain    = (string) env('MAIL_DOMAIN');
        $adminMail = (string) env('ADMIN_EMAIL');
        $adminPass = (string) env('ADMIN_PASSWORD');

        if (! $domain) {
            $this->error('MAIL_DOMAIN non défini dans .env');
            return self::FAILURE;
        }
        if (! $adminMail) {
            $this->error('ADMIN_EMAIL non défini dans .env');
            return self::FAILURE;
        }
        if (str_ends_with($adminMail, '@' . $domain) === false
            && ! str_contains($adminMail, '@')) {
            $adminMail .= '@' . $domain;
        }

        if (! Schema::hasTable('mail_accounts')) {
            $this->error('Schéma mail pas migré — `php artisan migrate` d\'abord.');
            return self::FAILURE;
        }

        // 1. Organisation par défaut (id=1) si la table existe (migration 90+)
        $orgId = null;
        if (Schema::hasTable('organizations')) {
            $orgId = DB::table('organizations')->orderBy('id')->value('id');
            if (! $orgId) {
                $orgId = DB::table('organizations')->insertGetId([
                    'name'       => 'Default Organization',
                    'slug'       => 'default',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info("✓ Organisation créée : Default Organization (id=$orgId)");
            }
        }

        // 2. Domaine
        $domainId = DB::table('mail_domains')->where('name', $domain)->value('id');
        if (! $domainId) {
            $domainId = DB::table('mail_domains')->insertGetId([
                'name'            => $domain,
                'active'          => true,
                'organization_id' => $orgId,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            $this->info("✓ Domaine créé : $domain (id=$domainId)");
        } else {
            // Backfill : rattache à l'orga si pas déjà fait
            if ($orgId && ! DB::table('mail_domains')->where('id', $domainId)->value('organization_id')) {
                DB::table('mail_domains')->where('id', $domainId)->update(['organization_id' => $orgId]);
            }
            $this->line("• Domaine existe déjà : $domain (id=$domainId)");
        }

        // 2. Compte admin
        $existing = DB::table('mail_accounts')->where('email', $adminMail)->first();
        $needPasswordUpdate = $this->option('force-password') || ! $existing;

        if (! $adminPass && $needPasswordUpdate) {
            // Génère un mdp solide si l'admin n'en a pas mis dans .env
            $adminPass = bin2hex(random_bytes(10));
            $this->warn('ADMIN_PASSWORD absent du .env → mot de passe généré : ' . $adminPass);
            $this->warn('(Note-le maintenant, il ne sera plus jamais affiché.)');
        }

        if ($needPasswordUpdate) {
            // Hash BCRYPT compatible Dovecot {BLF-CRYPT}
            $hash = '{BLF-CRYPT}' . password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);

            if ($existing) {
                DB::table('mail_accounts')->where('id', $existing->id)->update([
                    'password'   => $hash,
                    'updated_at' => now(),
                ]);
                $this->info("✓ Mot de passe admin mis à jour : $adminMail");
            } else {
                [$local] = explode('@', $adminMail, 2);
                $localDomain = explode('@', $adminMail, 2)[1] ?? $domain;
                // ATTENTION : maildir = chemin RELATIF (Dovecot prefixe /var/vmail/
                // dans la SQL user_query). Doit terminer par /
                $maildir = "$localDomain/$local/";
                DB::table('mail_accounts')->insert([
                    'email'        => $adminMail,
                    'password'     => $hash,
                    'display_name' => 'Admin',
                    'domain_id'    => $domainId,
                    'quota_bytes'  => (int) env('MAIL_QUOTA_BYTES', 5368709120),
                    'maildir'      => $maildir,
                    'active'       => true,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $this->info("✓ Compte admin créé : $adminMail (maildir=$maildir)");
            }
        } else {
            $this->line("• Compte admin existe déjà : $adminMail (utilise --force-password pour reset)");
        }

        // 3. Seed admin_email dans app_settings (utilisé par la garde admin)
        $cur = DB::table('app_settings')->where('key', 'admin_email')->value('value');
        if ($cur !== $adminMail) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => 'admin_email'],
                ['value' => $adminMail, 'updated_at' => now()]
            );
            $this->info("✓ app_settings.admin_email = $adminMail");
        }

        $this->newLine();
        // 4. Promotion en owner de l'organisation (idempotent)
        if ($orgId && Schema::hasTable('organization_members')) {
            $accId = DB::table('mail_accounts')->where('email', $adminMail)->value('id');
            if ($accId) {
                $alreadyOwner = DB::table('organization_members')
                    ->where('organization_id', $orgId)
                    ->where('mail_account_id', $accId)
                    ->where('role', 'owner')->exists();
                if (! $alreadyOwner) {
                    DB::table('organization_members')->updateOrInsert(
                        ['organization_id' => $orgId, 'mail_account_id' => $accId],
                        ['role' => 'owner', 'created_at' => now(), 'updated_at' => now()]
                    );
                    $this->info("✓ $adminMail promu owner de l'organisation");
                }
            }
        }

        $this->info("✅ Installation OK. Connecte-toi sur https://$domain/login");
        return self::SUCCESS;
    }
}
