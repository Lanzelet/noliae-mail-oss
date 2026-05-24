<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
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

    /** Champs chiffrés au repos (Laravel Crypt = AES-256-CBC + HMAC, clé APP_KEY). */
    private const ENCRYPTED = ['pgp_private_key'];

    public function get(string $email): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($this->path($email))) {
            return $this->defaults();
        }
        try {
            $data = json_decode($disk->get($this->path($email)), true) ?: [];
            // Déchiffre les champs sensibles s'ils sont en forme chiffrée.
            foreach (self::ENCRYPTED as $k) {
                if (! empty($data[$k]) && str_starts_with((string) $data[$k], 'enc:')) {
                    try {
                        $data[$k] = Crypt::decryptString(substr($data[$k], 4));
                    } catch (\Throwable $e) {
                        // Si le déchiffrement échoue (APP_KEY changée, corruption),
                        // on retourne une chaîne vide plutôt que de cracher.
                        $data[$k] = '';
                    }
                }
            }
            return array_merge($this->defaults(), $data);
        } catch (\Throwable $e) {
            return $this->defaults();
        }
    }

    public function save(string $email, array $data): array
    {
        $merged = array_merge($this->get($email), $data);
        $merged['_email'] = strtolower(trim($email)); // pour l'annuaire PGP
        // Chiffre les champs sensibles avant d'écrire sur disque.
        $toWrite = $merged;
        foreach (self::ENCRYPTED as $k) {
            if (! empty($toWrite[$k]) && ! str_starts_with((string) $toWrite[$k], 'enc:')) {
                $toWrite[$k] = 'enc:' . Crypt::encryptString((string) $toWrite[$k]);
            }
        }
        Storage::disk('local')->put($this->path($email), json_encode($toWrite, JSON_UNESCAPED_UNICODE));
        // Le retour reste en clair (consommé par le controller en mémoire).
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
