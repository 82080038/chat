<?php

declare(strict_types=1);

namespace Platform\Fundamental;

interface FundamentalServiceInterface
{
    // Financial Statements
    public function listFinancialStatements(array $filters, int $page, int $perPage): array;

    public function createFinancialStatement(array $data): array;

    public function getFinancialStatement(string $id): ?array;

    public function getFinancialStatementLines(string $id): array;

    public function getFinancialStatementRevisions(string $id): array;

    public function reviseFinancialStatement(string $id, array $data): array;

    public function getLatestFinancialStatement(string $issuerId, string $type): ?array;

    // Financial Metrics
    public function listFinancialMetrics(array $filters, int $page, int $perPage): array;

    public function getFinancialMetric(string $id): ?array;

    public function getIssuerMetrics(string $issuerId, ?string $metricType): array;

    public function createFinancialMetric(array $data): array;

    // Economic Indicators
    public function listEconomicIndicators(array $filters, int $page, int $perPage): array;

    public function getEconomicIndicator(string $id): ?array;

    public function createEconomicIndicator(array $data): array;

    public function getEconomicIndicators(string $country, string $indicatorType): array;

    // News
    public function listNews(array $filters, int $page, int $perPage): array;

    public function getNewsItem(string $id): ?array;

    public function createNewsItem(array $data): array;

    public function getNewsByInstrument(string $instrumentId, int $limit): array;
}
