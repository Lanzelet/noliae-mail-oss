<?php

namespace App\Services;

/**
 * Petit client pour l'API controller rspamd.
 * Conf via env :
 *   RSPAMD_URL=http://127.0.0.1:11334  (ou http://rspamd:11334 dans compose)
 *   RSPAMD_PASSWORD=…
 *
 * Endpoints utiles :
 *   /stat       — compteurs globaux (scanned, learned, actions)
 *   /history    — N derniers mails scannés
 *   /symbols    — descriptions des symboles
 *   /graph      — séries de scan_times pour graphique
 *   /actions    — config des seuils (greylist/add header/reject)
 *   /errors     — erreurs récentes
 *   /neighbours — cluster nodes (vide en mono-instance)
 */
class RspamdClient
{
    public function url(string $path): string
    {
        return rtrim((string) env('RSPAMD_URL', 'http://127.0.0.1:11334'), '/') . $path;
    }

    public function get(string $path): ?array
    {
        $ch = curl_init($this->url($path));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER     => [
                'Password: ' . (string) env('RSPAMD_PASSWORD', ''),
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) return null;
        return json_decode((string) $body, true) ?: null;
    }

    public function available(): bool
    {
        return $this->get('/stat') !== null;
    }
}
