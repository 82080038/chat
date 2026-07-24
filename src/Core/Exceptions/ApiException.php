<?php

declare(strict_types=1);

namespace Platform\Core\Exceptions;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $errorCode,
        string $message,
        private readonly array $fieldErrors = []
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
