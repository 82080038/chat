<?php

declare(strict_types=1);

namespace Platform\Core\EventBus;

/**
 * Immutable event envelope following blueprint section 407.
 *
 * @readonly
 */
final class Event
{
    public string $eventId;
    public string $eventType;
    public int $eventVersion;
    public string $source;
    public string $timestamp;
    public ?string $correlationId;
    public array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $eventType,
        array $data,
        string $source = 'platform',
        int $eventVersion = 1,
        ?string $correlationId = null,
        ?string $eventId = null,
        ?string $timestamp = null
    ) {
        $this->eventId = $eventId ?? \Ramsey\Uuid\Uuid::uuid7()->toString();
        $this->eventType = $eventType;
        $this->eventVersion = $eventVersion;
        $this->source = $source;
        $this->timestamp = $timestamp ?? gmdate('Y-m-d\TH:i:s.u\Z');
        $this->correlationId = $correlationId;
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'event_type' => $this->eventType,
            'event_version' => $this->eventVersion,
            'source' => $this->source,
            'timestamp' => $this->timestamp,
            'correlation_id' => $this->correlationId,
            'data' => $this->data,
        ];
    }

    /**
     * @param array<string, mixed> $arr
     */
    public static function fromArray(array $arr): self
    {
        return new self(
            $arr['event_type'],
            $arr['data'] ?? [],
            $arr['source'] ?? 'platform',
            $arr['event_version'] ?? 1,
            $arr['correlation_id'] ?? null,
            $arr['event_id'] ?? null,
            $arr['timestamp'] ?? null
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
