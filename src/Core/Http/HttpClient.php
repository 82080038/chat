<?php

declare(strict_types=1);

namespace Platform\Core\Http;

use Platform\Core\Exceptions\ApiException;

final class HttpClient
{
    private const DEFAULT_TIMEOUT = 20;
    private const DEFAULT_RETRIES = 2;
    private const INITIAL_BACKOFF_MS = 500;

    public function getJson(
        string $url,
        array $headers = [],
        int $maxRetries = self::DEFAULT_RETRIES,
        int $timeout = self::DEFAULT_TIMEOUT
    ): array {
        $attempts = 0;
        $lastError = null;
        $lastHttpCode = 0;

        while ($attempts <= $maxRetries) {
            [$data, $error, $httpCode] = $this->execute($url, $headers, $timeout);

            if ($error === null && $httpCode < 400) {
                if (!is_array($data)) {
                    throw new ApiException(
                        502,
                        'EXTERNAL_API_ERROR',
                        "External API returned invalid JSON (HTTP {$httpCode})"
                    );
                }
                return $data;
            }

            $lastError = $error;
            $lastHttpCode = $httpCode;

            // Client errors other than rate-limit are not retried.
            if ($httpCode >= 400 && $httpCode < 500 && $httpCode !== 429) {
                break;
            }

            if ($attempts < $maxRetries) {
                $this->sleep($attempts);
            }

            $attempts++;
        }

        if ($lastError !== null) {
            throw new ApiException(
                502,
                'EXTERNAL_API_ERROR',
                "Failed to connect to external API: {$lastError}"
            );
        }

        throw new ApiException(
            502,
            'EXTERNAL_API_ERROR',
            "External API returned HTTP {$lastHttpCode}"
        );
    }

    private function execute(string $url, array $headers, int $timeout): array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $headers !== [] ? $headers : ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [null, $error, $httpCode];
        }

        $data = json_decode($response, true);
        return [$data, null, $httpCode];
    }

    private function sleep(int $attempt): void
    {
        $ms = self::INITIAL_BACKOFF_MS * (2 ** $attempt);
        usleep($ms * 1000);
    }
}
