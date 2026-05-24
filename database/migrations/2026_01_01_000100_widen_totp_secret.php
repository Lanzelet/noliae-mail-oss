<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Raw SQL pour éviter la dépendance doctrine/dbal (->change()).
        // Postgres ALTER COLUMN TYPE accepte la conversion VARCHAR(64) → TEXT
        // sans cast explicite (le contenu est compatible).
        DB::statement('ALTER TABLE mail_accounts ALTER COLUMN totp_secret TYPE TEXT');
    }
    public function down(): void
    {
        DB::statement('ALTER TABLE mail_accounts ALTER COLUMN totp_secret TYPE VARCHAR(64) USING substring(totp_secret, 1, 64)');
    }
};
