<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─────────── Mailing lists (listes de diffusion) ───────────
        Schema::create('mail_lists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('domain_id')->constrained('mail_domains')->cascadeOnDelete();
            $t->string('address', 320)->unique();          // list@domain
            $t->string('display_name', 200)->nullable();
            // Qui peut envoyer à cette liste :
            //  - 'internal'        = uniquement les membres du domaine de la liste
            //  - 'internal_domains'= n'importe quel @<domaine_servi_localement>
            //  - 'any'             = ouvert au monde
            $t->enum('scope', ['internal', 'internal_domains', 'any'])->default('internal');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('mail_list_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('list_id')->constrained('mail_lists')->cascadeOnDelete();
            $t->string('member_email', 320);
            $t->timestamps();
            $t->unique(['list_id', 'member_email']);
        });

        // ─────────── Shared mailboxes (boîtes partagées) ───────────
        // Une boîte partagée a sa propre adresse mail (donc son propre Maildir
        // côté Dovecot), mais aucun mot de passe utilisateur — on y accède
        // uniquement via une ACL qui mappe des comptes individuels vers cette
        // boîte avec un rôle (read, send, manage).
        Schema::create('shared_mailboxes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('domain_id')->constrained('mail_domains')->cascadeOnDelete();
            $t->string('email', 320)->unique();            // ex: support@domain
            $t->string('display_name', 200);                // ex: « Support Client »
            $t->unsignedBigInteger('quota_bytes')->default(10 * 1024 * 1024 * 1024);
            $t->string('maildir', 255);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('shared_mailbox_acls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('shared_mailbox_id')->constrained('shared_mailboxes')->cascadeOnDelete();
            $t->string('user_email', 320);                  // user qui a accès
            $t->enum('role', ['read', 'send', 'manage'])->default('send');
            $t->timestamps();
            $t->unique(['shared_mailbox_id', 'user_email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shared_mailbox_acls');
        Schema::dropIfExists('shared_mailboxes');
        Schema::dropIfExists('mail_list_members');
        Schema::dropIfExists('mail_lists');
    }
};
