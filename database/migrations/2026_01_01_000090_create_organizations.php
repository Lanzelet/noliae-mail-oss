<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Noliae Mail OSS — système d'Organisations façon Microsoft 365.
 *
 * Une Organisation =
 *   - 1+ domaine(s) mail (mail_domains.organization_id)
 *   - 1+ membre(s) (organization_members) avec un rôle RBAC
 *   - 1 carnet d'adresses partagé (organization_contacts)
 *
 * RBAC (4 rôles) :
 *   - owner   : tout. Seul à pouvoir supprimer l'organisation / changer les owners
 *   - admin   : gère domaines, comptes, paramètres ; pas billing/delete-org
 *   - support : voir audit, lire les comptes (read-only sauf reset pwd) ; pas de domaines
 *   - member  : utilisateur mail standard, voit /people + /contacts seulement
 *
 * 2FA OBLIGATOIRE pour owner / admin / support (enforcement via middleware).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('slug', 64)->unique();
            $t->text('logo_path')->nullable();
            $t->timestamps();
        });

        Schema::create('organization_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->enum('role', ['owner', 'admin', 'support', 'member'])->default('member');
            $t->timestamps();
            $t->unique(['organization_id', 'mail_account_id']);
            $t->index(['organization_id', 'role']);
        });

        // Lien domaine ↔ orga (un domaine appartient à exactement une orga).
        Schema::table('mail_domains', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->after('id')
                ->constrained('organizations')->nullOnDelete();
            $t->index('organization_id');
        });

        // Carnet d'adresses partagé. Source = manual (saisie admin),
        // member (auto-sync depuis organization_members), imported (CSV).
        Schema::create('organization_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('email', 320);
            $t->string('display_name', 120)->nullable();
            $t->string('company', 120)->nullable();
            $t->string('job_title', 120)->nullable();
            $t->string('phone', 32)->nullable();
            $t->text('notes')->nullable();
            $t->enum('source', ['manual', 'member', 'imported'])->default('manual');
            $t->text('avatar_path')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'email']);
            $t->index('display_name');
        });

        // Backfill : crée une "Default Organization" et rattache tous les domaines
        // + comptes existants. Le premier compte admin (app_settings.admin_email)
        // devient owner.
        $orgId = DB::table('organizations')->insertGetId([
            'name'       => 'Default Organization',
            'slug'       => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('mail_domains')->update(['organization_id' => $orgId]);

        $adminEmail = strtolower((string) (DB::table('app_settings')
            ->where('key', 'admin_email')->value('value') ?: env('ADMIN_EMAIL', '')));
        if ($adminEmail) {
            $adminAcc = DB::table('mail_accounts')->whereRaw('LOWER(email) = ?', [$adminEmail])->first();
            if ($adminAcc) {
                DB::table('organization_members')->insert([
                    'organization_id' => $orgId,
                    'mail_account_id' => $adminAcc->id,
                    'role'            => 'owner',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('mail_domains', function (Blueprint $t) {
            $t->dropForeign(['organization_id']);
            $t->dropColumn('organization_id');
        });
        Schema::dropIfExists('organization_contacts');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
