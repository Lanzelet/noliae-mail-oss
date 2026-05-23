<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stockage des pièces jointes mail sur Scaleway Object Storage (S3-compatible).
 * Les pièces ne voyagent plus en pièce-jointe SMTP : le mail contient
 * un lien signé valable 30 jours vers chaque fichier.
 */
class AttachmentStore
{
    // S3 v4 (utilisé par Scaleway) plafonne les URL signées à 7 jours.
    public const TTL_DAYS = 7;
    public const TTL_MAX  = 7;

    /** Téléverse une pièce jointe et renvoie ses métadonnées + URL signée. */
    public function upload(UploadedFile $file, string $userEmail, int $ttlDays = self::TTL_DAYS): array
    {
        $ttlDays = max(1, min(self::TTL_MAX, $ttlDays));
        $name = $file->getClientOriginalName() ?: 'fichier';
        $safe = preg_replace('/[^A-Za-z0-9._\-]/', '_', $name) ?: 'fichier';

        $key = sprintf(
            'mail-attachments/%s/%s/%s/%s',
            substr(md5($userEmail), 0, 12),
            date('Y/m'),
            Str::random(16),
            $safe
        );

        $disk = Storage::disk('s3');
        $disk->put($key, file_get_contents($file->getRealPath()), [
            'visibility'  => 'private',
            'ContentType' => $file->getMimeType() ?: 'application/octet-stream',
        ]);

        $expires = now()->addDays($ttlDays);

        return [
            'name'       => $name,
            'size'       => (int) $file->getSize(),
            'ttl_days'   => $ttlDays,
            'mime'       => $file->getMimeType() ?: 'application/octet-stream',
            'url'        => $disk->temporaryUrl($key, $expires),
            'key'        => $key,
            'expires_at' => $expires->toIso8601String(),
        ];
    }
}
