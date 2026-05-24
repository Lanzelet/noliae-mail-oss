<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-domaines : un domaine "primaire" par organisation, affiché par
 * défaut sur les pages /login et /admin/login (placeholder + suffixe).
 *
 * Backfill : marque le 1er domaine de chaque orga comme primaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_domains', function (Blueprint $t) {
            $t->boolean('is_primary')->default(false)->after('active');
        });
        // Backfill : 1er domaine de chaque orga = primaire
        $orgs = DB::table('organizations')->pluck('id');
        foreach ($orgs as $orgId) {
            $first = DB::table('mail_domains')
                ->where('organization_id', $orgId)
                ->orderBy('id')->value('id');
            if ($first) {
                DB::table('mail_domains')->where('id', $first)->update(['is_primary' => true]);
            }
        }
    }
    public function down(): void
    {
        Schema::table('mail_domains', function (Blueprint $t) {
            $t->dropColumn('is_primary');
        });
    }
};
