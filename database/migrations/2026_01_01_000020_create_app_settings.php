<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $t) {
            $t->string('key', 100)->primary();
            $t->text('value')->nullable();
            $t->timestamp('updated_at')->useCurrent();
        });

        // Valeurs par défaut (overridables via admin)
        $now = now();
        DB::table('app_settings')->insert([
            ['key' => 'allow_registration',     'value' => '0',                'updated_at' => $now],
            ['key' => 'default_quota_mb',       'value' => '5120',             'updated_at' => $now], // 5 Go
            ['key' => 'max_quota_mb',           'value' => '51200',            'updated_at' => $now], // 50 Go cap
            ['key' => 'enable_noliae_ai',       'value' => '0',                'updated_at' => $now], // off par défaut
            ['key' => 'backup_enabled',         'value' => '0',                'updated_at' => $now],
            ['key' => 'backup_hour_utc',        'value' => '0',                'updated_at' => $now],
        ]);
    }
    public function down(): void { Schema::dropIfExists('app_settings'); }
};
