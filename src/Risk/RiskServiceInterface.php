<?php

declare(strict_types=1);

namespace Platform\Risk;

interface RiskServiceInterface
{
    // Risk Profiles
    public function listRiskProfiles(array $filters, int $page, int $perPage): array;

    public function createRiskProfile(array $data): array;

    public function getRiskProfile(string $id): ?array;

    public function updateRiskProfile(string $id, array $data): array;

    // Risk Limits
    public function listRiskLimits(string $portfolioId): array;

    public function setRiskLimit(string $portfolioId, array $data): array;

    public function updateRiskLimit(string $limitId, array $data): array;

    public function removeRiskLimit(string $limitId): array;

    // Risk Assessments
    public function listRiskAssessments(string $portfolioId, int $page, int $perPage): array;

    public function triggerAssessment(string $portfolioId, array $data): array;

    public function getRiskAssessment(string $id): ?array;

    public function getLatestAssessment(string $portfolioId): ?array;

    // Risk Events
    public function listRiskEvents(array $filters, int $page, int $perPage): array;

    public function listPortfolioRiskEvents(string $portfolioId): array;

    public function getRiskEvent(string $id): ?array;

    public function getActiveRiskEvents(string $portfolioId): array;

    public function acknowledgeRiskEvent(string $id): array;

    public function resolveRiskEvent(string $id, string $resolution): array;

    // Utility
    public function checkLimits(string $portfolioId, array $proposedTrade): array;
}
