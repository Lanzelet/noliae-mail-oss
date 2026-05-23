<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Settings d'application persistées en DB (table app_settings).
 * Cache mémoire 60s pour éviter une requête à chaque appel.
 */
class AppSettings
{
    private const CACHE_KEY = 'app_settings:all';
    private const TTL       = 60;

    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL, function () {
            return DB::table('app_settings')->pluck('value', 'key')->toArray();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) return $default;
        return in_array(strtolower((string) $v), ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int) $v;
    }

    public static function set(string $key, mixed $value): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => (string) $value, 'updated_at' => now()],
        );
        Cache::forget(self::CACHE_KEY);
    }
}
