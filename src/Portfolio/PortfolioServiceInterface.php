<?php

declare(strict_types=1);

namespace Platform\Portfolio;

interface PortfolioServiceInterface
{
    // Portfolios
    public function listPortfolios(array $filters, int $page, int $perPage): array;

    public function createPortfolio(array $data): array;

    public function getPortfolio(string $id): ?array;

    public function updatePortfolio(string $id, array $data): array;

    public function archivePortfolio(string $id): array;

    public function getPortfolioSummary(string $id): array;

    // Positions
    public function getPositions(string $portfolioId, int $page, int $perPage): array;

    public function getPosition(string $portfolioId, string $instrumentId): ?array;

    public function getPositionHistory(string $portfolioId, string $instrumentId, string $from, string $to): array;

    public function openPosition(array $data): array;

    public function updatePosition(string $positionId, array $data): array;

    public function closePosition(string $positionId, array $data): array;

    // Cash
    public function getCashBalances(string $portfolioId): array;

    public function getCashTransactions(string $portfolioId, int $page, int $perPage): array;

    public function recordCashTransaction(string $portfolioId, array $data): array;

    // Targets
    public function getPortfolioTargets(string $portfolioId): array;

    public function setPortfolioTarget(string $portfolioId, array $data): array;

    public function updatePortfolioTarget(string $targetId, array $data): array;

    public function removePortfolioTarget(string $targetId): array;

    // Accounts
    public function getPortfolioAccounts(string $portfolioId): array;

    public function linkPortfolioAccount(string $portfolioId, array $data): array;
}
