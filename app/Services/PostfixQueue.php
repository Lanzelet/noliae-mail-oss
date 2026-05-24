<?php

namespace App\Services;

/**
 * Client TCP du daemon queue-daemon.py qui tourne sur le host à côté de Postfix.
 *
 * Le web container n'a pas accès direct à postqueue/postsuper (Postfix vit
 * sur le host ou dans un autre container). On parle au daemon via le bridge
 * docker (172.18.0.1) avec un token partagé.
 *
 * Config .env :
 *   POSTFIX_QUEUE_HOST=172.18.0.1
 *   POSTFIX_QUEUE_PORT=10032
 *   POSTFIX_QUEUE_TOKEN=<secret aléatoire>
 */
class PostfixQueue
{
    public function call(string $action, array $extra = []): array
    {
        $host  = env('POSTFIX_QUEUE_HOST', '172.18.0.1');
        $port  = (int) env('POSTFIX_QUEUE_PORT', 10032);
        $token = (string) env('POSTFIX_QUEUE_TOKEN', '');
        if (! $token) {
            return ['ok' => false, 'error' => 'POSTFIX_QUEUE_TOKEN non configuré dans .env'];
        }

        $payload = array_merge(['action' => $action, 'token' => $token], $extra);
        $sock = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 3);
        if (! $sock) {
            return ['ok' => false, 'error' => "queue-daemon injoignable ($host:$port) : $errstr"];
        }
        fwrite($sock, json_encode($payload) . "\n");
        $line = trim((string) fgets($sock, 8 * 1024 * 1024));
        fclose($sock);
        $r = json_decode($line, true);
        return $r ?: ['ok' => false, 'error' => 'parse error'];
    }

    public function holdList(): array
    {
        $r = $this->call('hold-list');
        return $r['ok'] ? ($r['data'] ?? []) : [];
    }

    public function release(string $queueId): bool
    {
        return (bool) ($this->call('release', ['queue_id' => $queueId])['ok'] ?? false);
    }

    public function delete(string $queueId): bool
    {
        return (bool) ($this->call('delete', ['queue_id' => $queueId])['ok'] ?? false);
    }

    public function releaseAll(): bool
    {
        return (bool) ($this->call('release-all')['ok'] ?? false);
    }

    public function showMail(string $queueId): ?string
    {
        $r = $this->call('show', ['queue_id' => $queueId]);
        return $r['ok'] ? ($r['data'] ?? null) : null;
    }

    public function available(): bool
    {
        $r = $this->call('hold-list');
        return ! isset($r['error']) || ! str_contains((string) $r['error'], 'injoignable');
    }
}
