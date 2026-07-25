<?php

declare(strict_types=1);

namespace Platform\Core\Http;

use Closure;

/**
 * Server-Sent Events (SSE) response.
 *
 * This response does not use the normal JSON envelope. Instead it sends
 * text/event-stream headers and then runs a streaming closure that writes
 * events to the output buffer until the client disconnects.
 */
final class StreamResponse extends Response
{
    private Closure $streamer;
    private int $heartbeatSeconds;

    /**
     * @param Closure(StreamResponse):void $streamer
     */
    public function __construct(Closure $streamer, int $heartbeatSeconds = 15)
    {
        parent::__construct(200);
        $this->streamer = $streamer;
        $this->heartbeatSeconds = $heartbeatSeconds;

        $this->addHeader('Content-Type', 'text/event-stream; charset=utf-8');
        $this->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        $this->addHeader('Connection', 'keep-alive');
        $this->addHeader('X-Accel-Buffering', 'no');
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $values) {
            foreach ($values as $value) {
                header("{$name}: {$value}", $name !== 'Set-Cookie');
            }
        }

        // Disable output buffering and time limits for long-lived stream.
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }
        if (ob_get_level()) {
            ob_end_flush();
        }

        ($this->streamer)($this);

        $this->flush();
    }

    public function event(string $eventName, array $data): self
    {
        echo "event: " . $eventName . "\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->flush();
        return $this;
    }

    public function comment(string $text): self
    {
        echo ": " . $text . "\n\n";
        $this->flush();
        return $this;
    }

    public function flush(): self
    {
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        return $this;
    }

    public function isClientConnected(): bool
    {
        if (!function_exists('connection_aborted')) {
            return true;
        }
        return connection_aborted() === 0;
    }

    public function getHeartbeatSeconds(): int
    {
        return $this->heartbeatSeconds;
    }
}
