<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            // Chemin relatif sous storage/app/private/avatars/, ou null.
            $t->string('avatar_path', 255)->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            $t->dropColumn('avatar_path');
        });
    }
};
