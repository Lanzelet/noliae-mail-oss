<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Réglages par utilisateur du webmail : signature HTML, accusé de réception.
 * Stocké en JSON sur le disque local (storage/app/webmail-settings/).
 */
class SettingsService
{
    private const DIR = 'webmail-settings';

    private function path(string $email): string
    {
        return self::DIR . '/' . md5(strtolower(trim($email))) . '.json';
    }

    public function get(string $email): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($this->path($email))) {
            return $this->defaults();
        }
        try {
            $data = json_decode($disk->get($this->path($email)), true) ?: [];
            return array_merge($this->defaults(), $data);
        } catch (\Throwable $e) {
            return $this->defaults();
        }
    }

    public function save(string $email, array $data): array
    {
        $merged = array_merge($this->get($email), $data);
        $merged['_email'] = strtolower(trim($email)); // pour l'annuaire PGP
        Storage::disk('local')->put($this->path($email), json_encode($merged, JSON_UNESCAPED_UNICODE));
        return $merged;
    }

    private function defaults(): array
    {
        return [
            'signature_html'      => '',
            'ask_receipts'        => false, // demande systématique d'accusé de réception
            // PGP — chiffrement de bout en bout
            'pgp_public_key'      => '',    // armored ASCII (peut être attaché aux mails)
            'pgp_private_key'     => '',    // armored ASCII (chiffré par la passphrase utilisateur)
            'pgp_fingerprint'     => '',    // empreinte SHA-1 pour affichage
            'pgp_attach_public'   => false, // joindre auto la clé publique aux envois
            // Affichage
            'view_mode'           => 'list',   // 'list' | 'thread'
            // Règles utilisateur : [{id, name, match:{from?,subject?}, action:{color?,star?}}]
            'rules'               => [],
            // Alias d'envoi additionnels (l'utilisateur peut envoyer depuis ces adresses)
            'aliases'             => [],   // ['alias@noliae.net', 'me@mondomaine.com']
            // Réponse automatique d'absence
            'vacation'            => null, // { enabled: bool, message: string, from: iso, to: iso }
        ];
    }
}
