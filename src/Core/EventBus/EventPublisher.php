<?php

declare(strict_types=1);

namespace Platform\Core\EventBus;

/**
 * Trait for services that need to publish events.
 * Fail-safe: if RabbitMQ is unavailable, publishing is silently skipped.
 */
trait EventPublisher
{
    protected ?string $correlationId = null;

    /**
     * Set correlation ID for the current request context.
     */
    public function setCorrelationId(?string $id): void
    {
        $this->correlationId = $id;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Emit an event to the bus.
     *
     * @param string $eventType e.g. "trading.order.submitted"
     * @param array<string, mixed> $data
     */
    protected function emitEvent(string $eventType, array $data): void
    {
        EventBus::getInstance()->emit($eventType, $data, $this->correlationId);
    }
}
