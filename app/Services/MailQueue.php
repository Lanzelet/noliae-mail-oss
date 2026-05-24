<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * File d'envoi mail Noliae : la « boîte d'envoi » côté webmail.
 *
 *   Webmail (publish)  →  RabbitMQ (queue: noliae.mailweb.outbox)  →  Worker (consume)  →  SMTP + IMAP
 *
 * Le webmail rend la main à l'utilisateur dès que le message est en file
 * (≈ 5 ms) ; le worker enchaîne SMTP puis appendSent IMAP en arrière-plan.
 */
class MailQueue
{
    public const QUEUE           = 'noliae.mailweb.outbox';
    public const QUEUE_KEYIMPORT = 'noliae.mailweb.keyimport';

    private function connection(): AMQPStreamConnection
    {
        $url = (string) (env('RABBITMQ_URL') ?: 'amqp://noliae:nlR4bb1tMQ_pr0d_x7K2@noliae-rabbitmq:5672/');
        $p   = parse_url($url);
        return new AMQPStreamConnection(
            $p['host'] ?? 'noliae-rabbitmq',
            $p['port'] ?? 5672,
            urldecode($p['user'] ?? 'noliae'),
            urldecode($p['pass'] ?? ''),
            ltrim($p['path'] ?? '/', '/') ?: '/'
        );
    }

    /** Publie un job dans une file (par défaut la file d'envoi mail). */
    public function publish(array $payload, string $queue = self::QUEUE): void
    {
        // OSS : si MAIL_DELIVERY_MODE=sync (ou pas de RABBITMQ_URL configure),
        // on envoie directement via SendService au lieu de mettre en file.
        // Cas d'usage : stack OSS minimale sans worker RabbitMQ.
        $mode = strtolower((string) env('MAIL_DELIVERY_MODE', env('RABBITMQ_URL') ? 'queue' : 'sync'));
        if ($mode === 'sync' && $queue === self::QUEUE) {
            $svc = app(SendService::class);
            $svc->send(
                $payload['from']      ?? '',
                $payload['to']        ?? [],
                $payload['subject']   ?? '',
                $payload['html']      ?? '',
                $payload,
            );
            return;
        }

        $conn = $this->connection();
        $ch   = $conn->channel();
        $ch->queue_declare($queue, false, true, false, false);
        $msg = new AMQPMessage(
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            [
                'delivery_mode' => 2,
                'content_type'  => 'application/json',
            ]
        );
        $ch->basic_publish($msg, '', $queue);
        $ch->close();
        $conn->close();
    }

    /**
     * Boucle de consommation pour la commande worker.
     * $handlers : ['queue.name' => callable, …]
     */
    public function consume(array $handlers): void
    {
        $conn = $this->connection();
        $ch   = $conn->channel();
        $ch->basic_qos(null, 5, null);

        foreach ($handlers as $queue => $handler) {
            $ch->queue_declare($queue, false, true, false, false);
            $ch->basic_consume($queue, '', false, false, false, false,
                function (AMQPMessage $msg) use ($handler, $queue) {
                    $payload = json_decode($msg->body, true) ?: [];
                    try {
                        $handler($payload);
                        $msg->ack();
                    } catch (\Throwable $e) {
                        Log::error("[mail-worker:$queue] " . $e->getMessage());
                        $retry = (int) ($payload['_retry'] ?? 0);
                        if ($retry < 2) {
                            $payload['_retry'] = $retry + 1;
                            $this->publish($payload, $queue);
                        }
                        $msg->ack();
                    }
                }
            );
        }

        while (count($ch->callbacks)) {
            $ch->wait(null, false, 30);
        }
        $ch->close();
        $conn->close();
    }
}
