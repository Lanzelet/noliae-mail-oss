<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Audit des actions admin — qui a fait quoi, quand, sur qui.
 * Stocké en DB, lisible depuis /admin/audit.
 *
 *   AuditLog::record($request, 'account.create', 'user@domain', ['quota'=>5120]);
 */
class AuditLog
{
    public static function record(Request $request, string $action, ?string $target = null, array $metadata = []): void
    {
        try {
            DB::table('audit_log')->insert([
                'actor_email' => strtolower((string) $request->session()->get('mail_user', 'unknown')),
                'action'      => $action,
                'target'      => $target,
                'metadata'    => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
                'ip'          => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 500),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Loguer ne doit jamais casser l'action — on ignore l'erreur.
        }
    }
}
