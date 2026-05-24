<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix : totp_secret était dimensionné en VARCHAR(64) pour stocker le secret
 * base32 brut (16 chars suffisent). MAIS on stocke en réalité l'envelope
 * Crypt::encryptString qui pèse ~280 chars (IV + value + MAC, base64-encodés
 * et JSON-emballés). Conséquence : SQLSTATE[22001] sur l'activation du 2FA.
 *
 * → on passe la colonne en TEXT (illimité côté Postgres).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            $t->text('totp_secret')->nullable()->change();
        });
    }
    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            $t->string('totp_secret', 64)->nullable()->change();
        });
    }
};
