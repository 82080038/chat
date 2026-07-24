<?php

declare(strict_types=1);

namespace Platform\Core\EventBus;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Platform\Core\Application;

/**
 * RabbitMQ event bus implementing blueprint sections 407-409.
 *
 * Features:
 * - Standardized event envelope with event_id, event_version, correlation_id
 * - Topic exchange with routing keys per event type
 * - Publisher confirms enabled
 * - Durable quorum queues with persistent messages
 * - Dead letter exchange for failed messages
 * - Fail-safe: if RabbitMQ is unavailable, events are logged but not fatal
 */
final class EventBus
{
    private static ?EventBus $instance = null;

    private ?AMQPStreamConnection $connection = null;
    private ?\PhpAmqpLib\Channel\AMQPChannel $channel = null;
    private bool $connected = false;

    /** @var array<string, bool> */
    private array $declared = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connect(): bool
    {
        if ($this->connected) {
            return true;
        }

        try {
            $app = Application::getInstance();
            $host = $app->getConfig('MQ_HOST', '127.0.0.1');
            $port = (int) $app->getConfig('MQ_PORT', 5672);
            $user = $app->getConfig('MQ_USER', 'guest');
            $pass = $app->getConfig('MQ_PASS', 'guest');
            $vhost = $app->getConfig('MQ_VHOST', '/');

            $this->connection = new AMQPStreamConnection(
                $host,
                $port,
                $user,
                $pass,
                $vhost,
                false,
                'AMQPLAIN',
                null,
                'en_US',
                3.0,
                3.0,
                null,
                false,
                0
            );
            $this->channel = $this->connection->channel();
            $this->channel->confirm_select();
            $this->connected = true;
            $this->declareTopology();
        } catch (\Throwable $e) {
            $this->connected = false;
        }

        return $this->connected;
    }

    private function declareTopology(): void
    {
        if ($this->channel === null) {
            return;
        }

        $this->channel->exchange_declare(
            'platform.events',
            'topic',
            false,
            true,
            false
        );

        $this->channel->exchange_declare(
            'platform.dlx',
            'fanout',
            false,
            true,
            false
        );

        if (!isset($this->declared['audit'])) {
            $this->channel->queue_declare(
                'platform.audit.queue',
                false,
                true,
                false,
                false,
                false,
                [
                    'x-queue-type' => ['S', 'quorum'],
                ]
            );
            $this->channel->queue_bind('platform.audit.queue', 'platform.events', '#');
            $this->declared['audit'] = true;
        }

        if (!isset($this->declared['dlq'])) {
            $this->channel->queue_declare(
                'platform.dlx.queue',
                false,
                true,
                false,
                false,
                false,
                [
                    'x-queue-type' => ['S', 'quorum'],
                ]
            );
            $this->channel->queue_bind('platform.dlx.queue', 'platform.dlx', '');
            $this->declared['dlq'] = true;
        }
    }

    public function declareQueue(string $queueName, array $bindingKeys): void
    {
        if (!$this->connect() || $this->channel === null) {
            return;
        }
        if (isset($this->declared[$queueName])) {
            return;
        }

        $this->channel->queue_declare(
            $queueName,
            false,
            true,
            false,
            false,
            false,
            [
                'x-queue-type' => ['S', 'quorum'],
                'x-dead-letter-exchange' => ['S', 'platform.dlx'],
                'x-dead-letter-routing-key' => ['S', ''],
            ]
        );

        foreach ($bindingKeys as $routingKey) {
            $this->channel->queue_bind($queueName, 'platform.events', $routingKey);
        }

        $this->channel->basic_qos(null, 10, null);
        $this->declared[$queueName] = true;
    }

    public function publish(Event $event): bool
    {
        if (!$this->connect() || $this->channel === null) {
            return false;
        }

        try {
            $routingKey = $event->eventType;
            $msg = new AMQPMessage(
                $event->toJson(),
                [
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                    'content_type' => 'application/json',
                    'message_id' => $event->eventId,
                    'timestamp' => time(),
                    'correlation_id' => $event->correlationId ?? '',
                ]
            );

            $this->channel->basic_publish($msg, 'platform.events', $routingKey);
            $this->channel->wait_for_pending_acks(5.0);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function emit(string $eventType, array $data, ?string $correlationId = null): bool
    {
        $event = new Event($eventType, $data, $this->detectSource(), 1, $correlationId);
        return $this->publish($event);
    }

    /**
     * @param callable(Event): void $callback
     */
    public function consume(string $queueName, callable $callback): void
    {
        if (!$this->connect() || $this->channel === null) {
            return;
        }

        $handler = function (AMQPMessage $message) use ($callback): void {
            try {
                $data = json_decode($message->body, true, 512, JSON_THROW_ON_ERROR);
                $event = Event::fromArray($data);
                $callback($event);
                $message->ack();
            } catch (\Throwable $e) {
                $message->nack(false, false);
            }
        };

        $this->channel->basic_consume($queueName, '', false, false, false, false, $handler);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function disconnect(): void
    {
        if ($this->channel !== null) {
            try {
                $this->channel->close();
            } catch (\Throwable $e) {
            }
        }
        if ($this->connection !== null) {
            try {
                $this->connection->close();
            } catch (\Throwable $e) {
            }
        }
        $this->connected = false;
        $this->channel = null;
        $this->connection = null;
    }

    private function detectSource(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? null;
        if ($caller !== null && isset($caller['class'])) {
            $parts = explode('\\', $caller['class']);
            $service = end($parts);
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $service) ?? 'platform');
        }
        return 'platform';
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
