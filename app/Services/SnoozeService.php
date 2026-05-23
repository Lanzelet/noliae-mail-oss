<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Snooze : par utilisateur, une table {folder|uid => until_epoch}.
 *
 *   HSET noliae:mail:snooze:<md5(email)>  "<folder>|<uid>"  <epoch>
 *
 * Les UID snoozés sont masqués de la liste tant que now < epoch.
 */
class SnoozeService
{
    private function key(string $email): string
    {
        return 'noliae:mail:snooze:' . md5(strtolower(trim($email)));
    }

    public function snooze(string $email, string $folder, int $uid, int $untilEpoch): void
    {
        Redis::hset($this->key($email), "$folder|$uid", $untilEpoch);
    }

    public function wake(string $email, string $folder, int $uid): void
    {
        Redis::hdel($this->key($email), "$folder|$uid");
    }

    /** @return array {folder|uid => until_epoch}, après nettoyage des échus. */
    public function active(string $email): array
    {
        $all = Redis::hgetall($this->key($email)) ?: [];
        $now = time(); $out = [];
        foreach ($all as $k => $v) {
            if ((int) $v <= $now) {
                Redis::hdel($this->key($email), $k); // expiré → on retire
            } else {
                $out[$k] = (int) $v;
            }
        }
        return $out;
    }
}
