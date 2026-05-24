<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil utilisateur étendu façon OWA "Mon compte" :
 *   Prénom / Nom / Initiales / Téléphone / Adresse postale / Région / Langue
 *
 * display_name reste séparé (déjà existant) : c'est le "Nom affiché" libre
 * utilisé dans From: des mails sortants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            $t->string('first_name', 80)->nullable()->after('display_name');
            $t->string('last_name', 80)->nullable()->after('first_name');
            $t->string('initials', 8)->nullable()->after('last_name');
            $t->string('phone', 32)->nullable()->after('initials');
            $t->string('mobile', 32)->nullable()->after('phone');
            $t->string('job_title', 120)->nullable()->after('mobile');
            $t->string('company', 120)->nullable()->after('job_title');
            $t->string('street', 255)->nullable()->after('company');
            $t->string('city', 120)->nullable()->after('street');
            $t->string('state', 120)->nullable()->after('city');
            $t->string('postal_code', 20)->nullable()->after('state');
            $t->string('country', 80)->nullable()->after('postal_code');
            $t->string('timezone', 64)->default('UTC')->after('country');
            $t->string('language', 8)->default('fr')->after('timezone');
        });
    }
    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $t) {
            $t->dropColumn([
                'first_name','last_name','initials','phone','mobile','job_title',
                'company','street','city','state','postal_code','country','timezone','language',
            ]);
        });
    }
};
