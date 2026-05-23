<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * File d'envoi planifié — Redis sorted set par score = epoch d'envoi.
 *
 *   ZADD noliae:mail:schedule  <epoch>  <json payload>
 *
 * Un tick du worker (mail:work) toutes les 60 s récupère les éléments
 * dont le score est ≤ now() et les promeut vers l'outbox immédiate.
 */
class ScheduleService
{
    public const ZSET = 'noliae:mail:schedule';

    public function enqueue(array $payload, int $sendAtEpoch): void
    {
        Redis::zadd(self::ZSET, $sendAtEpoch, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /** Récupère + retire les éléments arrivés à échéance (atomic). */
    public function pop(int $nowEpoch): array
    {
        $items = Redis::zrangebyscore(self::ZSET, 0, $nowEpoch);
        foreach ($items as $i) Redis::zrem(self::ZSET, $i);
        return array_map(fn ($j) => json_decode($j, true) ?: [], $items);
    }
}
