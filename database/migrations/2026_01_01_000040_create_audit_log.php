<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $t) {
            $t->id();
            $t->string('actor_email', 320)->index();   // qui (admin@…)
            $t->string('action', 60);                   // ex: account.create
            $t->string('target', 320)->nullable();      // ex: email cible
            $t->json('metadata')->nullable();           // détails (avant/après, etc.)
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }
    public function down(): void { Schema::dropIfExists('audit_log'); }
};
