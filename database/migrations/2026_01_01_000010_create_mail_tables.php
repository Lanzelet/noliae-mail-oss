<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_domains', function (Blueprint $t) {
            $t->id();
            $t->string('name', 253)->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('mail_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('domain_id')->constrained('mail_domains')->cascadeOnDelete();
            $t->string('email', 320)->unique();
            $t->string('password', 255);          // {BLF-CRYPT}…
            $t->string('display_name', 200)->nullable();
            $t->unsignedBigInteger('quota_bytes')->default(5 * 1024 * 1024 * 1024);
            $t->string('maildir', 255);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->index('domain_id');
        });

        Schema::create('mail_aliases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('domain_id')->constrained('mail_domains')->cascadeOnDelete();
            $t->string('source', 320);            // alias@domain
            $t->string('destination', 320);       // real@domain
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['source', 'destination']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('mail_aliases');
        Schema::dropIfExists('mail_accounts');
        Schema::dropIfExists('mail_domains');
    }
};
