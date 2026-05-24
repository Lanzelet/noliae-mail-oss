<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Brute-force protection sur /login.
 *
 * 3 compteurs séparés (Redis via Cache facade) :
 *
 *   UNKNOWN(ip)    — POST avec un email qui n'existe PAS
 *                    5 tentatives  → block 10 min
 *                    Chaque nouvelle tentative au-delà DOUBLE la durée
 *                    (10 → 20 → 40 → 80 min, cap 24h)
 *
 *   ACCOUNT(email) — POST avec un compte EXISTANT mais mauvais mot de passe
 *                    3 tentatives  → block 5 min (seul ce compte)
 *
 *   IP(ip)         — IP qui a fait des mauvais passwords sur des comptes valides
 *                    3 tentatives  → block 10 min (cette IP, tous comptes)
 *
 * Les 3 sont vérifiés AVANT d'appeler verifyPassword(). Un login réussi
 * RESET ACCOUNT(email) + IP(ip) (UNKNOWN reste car c'est sur un email
 * différent).
 */
class LoginRateLimiter
{
    private const UNKNOWN_THRESHOLD = 5;
    private const UNKNOWN_INITIAL_LOCK = 600;   // 10 min
    private const UNKNOWN_MAX_LOCK     = 86400; // 24 h

    private const ACCOUNT_THRESHOLD = 3;
    private const ACCOUNT_LOCK      = 300; // 5 min

    private const IP_THRESHOLD = 3;
    private const IP_LOCK      = 600; // 10 min

    private const HIT_WINDOW = 900; // les hits expirent après 15 min sans nouvelle tentative

    /**
     * Avant verifyPassword : retourne ['blocked' => bool, 'retry_in' => int|null, 'reason' => string]
     */
    public static function check(Request $request, string $email): array
    {
        $ip = self::clientIp($request);
        $email = strtolower(trim($email));

        // 1. UNKNOWN locked ?
        $lock = (int) Cache::get(self::unknownLockKey($ip), 0);
        if ($lock > time()) {
            return ['blocked' => true, 'retry_in' => $lock - time(),
                    'reason'  => 'too_many_unknown'];
        }
        // 2. ACCOUNT locked ?
        $lock = (int) Cache::get(self::accountLockKey($email), 0);
        if ($lock > time()) {
            return ['blocked' => true, 'retry_in' => $lock - time(),
                    'reason'  => 'account_locked'];
        }
        // 3. IP locked ?
        $lock = (int) Cache::get(self::ipLockKey($ip), 0);
        if ($lock > time()) {
            return ['blocked' => true, 'retry_in' => $lock - time(),
                    'reason'  => 'ip_locked'];
        }

        return ['blocked' => false, 'retry_in' => null, 'reason' => 'ok'];
    }

    /** Tentative ratée parce que le compte n'existe pas. */
    public static function hitUnknown(Request $request): void
    {
        $ip = self::clientIp($request);
        $hits = (int) Cache::increment(self::unknownHitKey($ip));
        Cache::put(self::unknownHitKey($ip), $hits, self::HIT_WINDOW);

        if ($hits >= self::UNKNOWN_THRESHOLD) {
            // Backoff exponentiel : 10, 20, 40, 80 min … capé 24h
            $multiplier = 1 << max(0, $hits - self::UNKNOWN_THRESHOLD);
            $lockDuration = min(self::UNKNOWN_INITIAL_LOCK * $multiplier, self::UNKNOWN_MAX_LOCK);
            Cache::put(self::unknownLockKey($ip), time() + $lockDuration, $lockDuration);
        }
    }

    /** Tentative ratée sur un compte existant (mauvais mot de passe). */
    public static function hitWrongPassword(Request $request, string $email): void
    {
        $ip = self::clientIp($request);
        $email = strtolower(trim($email));

        $aHits = (int) Cache::increment(self::accountHitKey($email));
        Cache::put(self::accountHitKey($email), $aHits, self::HIT_WINDOW);
        if ($aHits >= self::ACCOUNT_THRESHOLD) {
            Cache::put(self::accountLockKey($email), time() + self::ACCOUNT_LOCK, self::ACCOUNT_LOCK);
        }

        $ipHits = (int) Cache::increment(self::ipHitKey($ip));
        Cache::put(self::ipHitKey($ip), $ipHits, self::HIT_WINDOW);
        if ($ipHits >= self::IP_THRESHOLD) {
            Cache::put(self::ipLockKey($ip), time() + self::IP_LOCK, self::IP_LOCK);
        }
    }

    /** Sur login réussi, on RESET ACCOUNT + IP (mais pas UNKNOWN). */
    public static function clearOnSuccess(Request $request, string $email): void
    {
        $ip = self::clientIp($request);
        $email = strtolower(trim($email));
        Cache::forget(self::accountHitKey($email));
        Cache::forget(self::accountLockKey($email));
        Cache::forget(self::ipHitKey($ip));
        Cache::forget(self::ipLockKey($ip));
    }

    /** Utilisé par l'admin pour débloquer manuellement (futur). */
    public static function reset(string $email = null, string $ip = null): void
    {
        if ($email) {
            $email = strtolower($email);
            Cache::forget(self::accountHitKey($email));
            Cache::forget(self::accountLockKey($email));
        }
        if ($ip) {
            Cache::forget(self::unknownHitKey($ip));
            Cache::forget(self::unknownLockKey($ip));
            Cache::forget(self::ipHitKey($ip));
            Cache::forget(self::ipLockKey($ip));
        }
    }

    private static function clientIp(Request $request): string
    {
        // trustProxies(*) est déjà configuré → on récupère la vraie IP client.
        return (string) ($request->ip() ?: '0.0.0.0');
    }
    private static function unknownHitKey(string $ip):    string { return "login:unknown:hit:$ip"; }
    private static function unknownLockKey(string $ip):   string { return "login:unknown:lock:$ip"; }
    private static function accountHitKey(string $email): string { return "login:acct:hit:$email"; }
    private static function accountLockKey(string $email):string { return "login:acct:lock:$email"; }
    private static function ipHitKey(string $ip):         string { return "login:ip:hit:$ip"; }
    private static function ipLockKey(string $ip):        string { return "login:ip:lock:$ip"; }
}
