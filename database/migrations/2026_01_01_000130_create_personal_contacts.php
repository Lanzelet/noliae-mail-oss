<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnet de contacts PERSONNEL par utilisateur.
 *
 * Différent de organization_contacts (partagé orga). Chaque user a sa
 * propre liste, alimentée :
 *   - auto-capture : à chaque envoi réussi, les destinataires (To+Cc)
 *     pas encore connus de l'user sont ajoutés (source=auto)
 *   - manuelle  : via /contacts page (source=manual)
 *
 * L'autocomplete /api/contacts/suggest fusionne perso + orga (perso first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->string('email', 320);
            $t->string('display_name', 120)->nullable();
            $t->string('company', 120)->nullable();
            $t->string('job_title', 120)->nullable();
            $t->string('phone', 32)->nullable();
            $t->text('notes')->nullable();
            $t->enum('source', ['auto', 'manual', 'imported'])->default('manual');
            $t->unsignedInteger('hit_count')->default(0); // pour trier par fréquence d'envoi
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamps();
            $t->unique(['mail_account_id', 'email']);
            $t->index('hit_count');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('personal_contacts');
    }
};
