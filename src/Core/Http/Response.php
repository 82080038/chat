<?php

declare(strict_types=1);

namespace Platform\Core\Http;

final class Response
{
    private int $statusCode;
    private array $headers = [];
    private ?array $data = null;
    private ?array $error = null;

    public function __construct(int $statusCode = 200)
    {
        $this->statusCode = $statusCode;
    }

    public static function ok(array $data, array $meta = []): self
    {
        $response = new self(200);
        $response->data = $meta !== [] ? ['data' => $data, 'meta' => $meta] : ['data' => $data];
        return $response;
    }

    public static function created(array $data): self
    {
        $response = new self(201);
        $response->data = ['data' => $data];
        return $response;
    }

    public static function noContent(): self
    {
        return new self(204);
    }

    public static function error(int $statusCode, string $code, string $message, array $fieldErrors = []): self
    {
        $response = new self($statusCode);
        $response->error = [
            'code' => $code,
            'message' => $message,
            'correlation_id' => \Ramsey\Uuid\Uuid::uuid7()->toString(),
        ];
        if ($fieldErrors !== []) {
            $response->error['field_errors'] = $fieldErrors;
        }
        return $response;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        header('Content-Type: application/json');

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        if ($this->statusCode === 204) {
            return;
        }

        $payload = $this->error !== null ? ['error' => $this->error] : $this->data;
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
