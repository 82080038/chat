<?php

declare(strict_types=1);

namespace Platform\Core\Http;

trait RequestParamsTrait
{
    private array $params = [];

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
