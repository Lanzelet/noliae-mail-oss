<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_migrations', function (Blueprint $t) {
            $t->id();
            $t->string('source_host', 253);
            $t->unsignedSmallInteger('source_port')->default(993);
            $t->boolean('source_ssl')->default(true);
            $t->string('source_user', 320);
            $t->text('source_pass_enc');                // Crypt::encryptString
            $t->foreignId('dest_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->enum('status', ['pending', 'running', 'success', 'failed', 'cancelled'])->default('pending');
            $t->integer('pid')->nullable();
            $t->integer('total_messages')->default(0);
            $t->integer('copied_messages')->default(0);
            $t->text('log_tail')->nullable();           // 200 dernières lignes
            $t->string('error', 500)->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('mail_migrations'); }
};
