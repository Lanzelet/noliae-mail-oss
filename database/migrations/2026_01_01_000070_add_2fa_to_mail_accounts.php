<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            $t->string('totp_secret', 64)->nullable();       // Base32, chiffré via APP_KEY
            $t->boolean('totp_enabled')->default(false);
            $t->text('totp_recovery')->nullable();           // JSON liste de codes one-shot
            $t->timestamp('totp_enabled_at')->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            $t->dropColumn(['totp_secret', 'totp_enabled', 'totp_recovery', 'totp_enabled_at']);
        });
    }
};
