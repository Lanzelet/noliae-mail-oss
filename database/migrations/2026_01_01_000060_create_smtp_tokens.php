<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mail_account_id')->constrained('mail_accounts')->cascadeOnDelete();
            $t->string('label', 100);                    // ex: « iPhone Mail », « Thunderbird laptop »
            $t->string('password_hash', 255);            // {BLF-CRYPT}…
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->index('mail_account_id');
        });
    }
    public function down(): void { Schema::dropIfExists('smtp_tokens'); }
};
