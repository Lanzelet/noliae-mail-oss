<?php

namespace App\Services;

/**
 * TOTP (RFC 6238) implementation pure PHP — pas de dépendance externe.
 * Compatible Google Authenticator / Authy / 1Password / Bitwarden.
 */
class Totp
{
    public const DIGITS = 6;
    public const PERIOD = 30;
    public const ALGO   = 'sha1';

    /** Génère un secret aléatoire (Base32, 32 chars = 160 bits). */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** Calcule le code TOTP courant pour ce secret. */
    public static function code(string $secret, ?int $time = null): string
    {
        $time = $time ?? time();
        $counter = pack('N*', 0) . pack('N*', (int) floor($time / self::PERIOD));
        $key = self::base32Decode($secret);
        $hash = hash_hmac(self::ALGO, $counter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0xf;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
               | ((ord($hash[$offset + 1]) & 0xff) << 16)
               | ((ord($hash[$offset + 2]) & 0xff) << 8)
               | (ord($hash[$offset + 3]) & 0xff);
        $modulo = 10 ** self::DIGITS;
        return str_pad((string) ($value % $modulo), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Vérifie un code avec tolérance ±1 fenêtre (anti-désync horloge). */
    public static function verify(string $secret, string $code, int $tolerance = 1): bool
    {
        $now = time();
        for ($i = -$tolerance; $i <= $tolerance; $i++) {
            if (hash_equals(self::code($secret, $now + $i * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    /** URI otpauth:// pour QR code (Google Authenticator compatible). */
    public static function uri(string $secret, string $label, string $issuer = 'Noliae Mail'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($label),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD,
        );
    }

    /** Base32 encode (RFC 4648 sans padding). */
    public static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0');
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded));
        $bits = '';
        foreach (str_split($encoded) as $c) {
            $pos = strpos($alphabet, $c);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }
}
