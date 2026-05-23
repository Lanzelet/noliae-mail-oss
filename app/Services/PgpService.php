<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Déchiffrement PGP côté serveur via le binaire `gpg`.
 *
 * Sécurité :
 *   • La clé privée n'est PAS persistée sur le serveur — chaque appel
 *     crée un keyring GPG jetable dans /tmp puis le supprime.
 *   • La passphrase n'est PAS stockée non plus — fournie par requête.
 *   • Aucun déchiffrement ne se fait dans le navigateur de l'utilisateur :
 *     plus de surface d'attaque liée à JS / extensions / supply-chain.
 */
class PgpService
{
    /**
     * @param  string  $armoredPriv   bloc PRIVATE KEY armored
     * @param  string  $passphrase    mot de passe protégeant la clé
     * @param  string  $armoredCipher bloc PGP MESSAGE armored
     * @return string  texte clair décodé
     */
    public function decrypt(string $armoredPriv, string $passphrase, string $armoredCipher): string
    {
        if (! str_contains($armoredPriv, 'BEGIN PGP PRIVATE KEY')) {
            throw new \InvalidArgumentException('Clé privée PGP manquante.');
        }
        if (! str_contains($armoredCipher, 'BEGIN PGP MESSAGE')) {
            throw new \InvalidArgumentException('Bloc PGP MESSAGE introuvable.');
        }

        $home = sys_get_temp_dir() . '/gpg-' . bin2hex(random_bytes(8));
        if (! mkdir($home, 0700, true)) {
            throw new \RuntimeException('Impossible de créer le keyring temporaire.');
        }

        try {
            // 1. Importer la clé privée dans le keyring jetable
            $keyFile = $home . '/key.asc';
            file_put_contents($keyFile, $armoredPriv);
            $this->gpg($home, $passphrase, ['--import', $keyFile]);

            // 2. Déchiffrer le message
            $cipherFile = $home . '/c.asc';
            file_put_contents($cipherFile, $armoredCipher);
            return $this->gpg($home, $passphrase, ['--decrypt', $cipherFile]);
        } finally {
            // 3. Effacer le keyring (sécurité)
            $this->rmrf($home);
        }
    }

    /** Signature inline (clearsign) — ajoute un bloc PGP SIGNATURE au texte. */
    public function clearsign(string $armoredPriv, string $passphrase, string $text): string
    {
        if (! str_contains($armoredPriv, 'BEGIN PGP PRIVATE KEY')) {
            throw new \InvalidArgumentException('Clé privée manquante.');
        }
        $home = sys_get_temp_dir() . '/gpg-' . bin2hex(random_bytes(8));
        if (! mkdir($home, 0700, true)) throw new \RuntimeException('Keyring temp KO');
        try {
            file_put_contents("$home/key.asc", $armoredPriv);
            $this->gpg($home, $passphrase, ['--import', "$home/key.asc"]);
            file_put_contents("$home/msg.txt", $text);
            return $this->gpg($home, $passphrase, ['--clearsign', '--armor', '-o', '-', "$home/msg.txt"]);
        } finally {
            $this->rmrf($home);
        }
    }

    private function gpg(string $home, string $passphrase, array $args): string
    {
        $cmd = array_merge([
            'gpg', '--homedir', $home,
            '--batch', '--no-tty', '--yes', '--quiet',
            '--pinentry-mode', 'loopback',
            '--passphrase-fd', '0',
        ], $args);

        $p = new Process($cmd);
        $p->setInput($passphrase);
        $p->setTimeout(20);
        $p->run();

        if (! $p->isSuccessful()) {
            throw new \RuntimeException('GPG: ' . trim($p->getErrorOutput() ?: $p->getOutput()));
        }
        return $p->getOutput();
    }

    private function rmrf(string $dir): void
    {
        if (! is_dir($dir)) return;
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        ) as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
