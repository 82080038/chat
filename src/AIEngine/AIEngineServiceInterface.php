<?php

declare(strict_types=1);

namespace Platform\AIEngine;

interface AIEngineServiceInterface
{
    public function analyzeSentiment(array $data): array;
    public function recognizePattern(array $data): array;
    public function detectAnomaly(array $data): array;
    public function getAnalysis(string $analysisId): ?array;
    public function listAnalyses(array $filters, int $page, int $perPage): array;
    public function createModelRun(array $data): array;
    public function updateModelRun(string $runId, array $data): array;
}
