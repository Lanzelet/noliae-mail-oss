<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Annuaire local de clés PGP publiques par contact, propre à un utilisateur.
 *
 *   storage/app/pgp-contacts/{md5(my_email)}.json
 *
 * Le store ne contient JAMAIS de clé privée — uniquement des clés publiques
 * de contacts (importées, récupérées sur keys.openpgp.org, ou jointes par mail).
 */
class KeyStoreService
{
    private const DIR = 'pgp-contacts';

    private function path(string $owner): string
    {
        return self::DIR . '/' . md5(strtolower(trim($owner))) . '.json';
    }

    /** Toutes les clés du store : email_sha256 => {email, armored, imported_at, source}. */
    public function all(string $owner): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($this->path($owner))) {
            return [];
        }
        try {
            return json_decode($disk->get($this->path($owner)), true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function find(string $owner, string $email): ?array
    {
        $hash = hash('sha256', strtolower(trim($email)));
        return $this->all($owner)[$hash] ?? null;
    }

    /**
     * Ajoute / remplace une clé. $email peut être null : on tentera de
     * l'extraire du userID OpenPGP côté frontend (lui sait parser).
     */
    public function save(string $owner, string $email, string $armored, string $source = 'manual'): array
    {
        $all = $this->all($owner);
        $hash = hash('sha256', strtolower(trim($email)));
        $all[$hash] = [
            'email'       => strtolower(trim($email)),
            'armored'     => $armored,
            'imported_at' => now()->toIso8601String(),
            'source'      => $source, // manual | keyserver | inbound-mail
        ];
        Storage::disk('local')->put($this->path($owner), json_encode($all, JSON_UNESCAPED_UNICODE));
        return $all[$hash];
    }

    public function delete(string $owner, string $email): void
    {
        $all = $this->all($owner);
        $hash = hash('sha256', strtolower(trim($email)));
        unset($all[$hash]);
        Storage::disk('local')->put($this->path($owner), json_encode($all, JSON_UNESCAPED_UNICODE));
    }
}
