<?php

declare(strict_types=1);

namespace Platform\Core\Data;

/**
 * Trait for data provenance validation and trust level enforcement.
 *
 * Blueprint sections 383, N-16, N-18:
 * - N-16: No canonical data without source
 * - N-18: No AI input below trust level TRUSTED
 *
 * Trust levels: UNVERIFIED → VALIDATED → TRUSTED
 */
trait DataProvenance
{
    public const TRUST_UNVERIFIED = 'UNVERIFIED';
    public const TRUST_VALIDATED = 'VALIDATED';
    public const TRUST_TRUSTED = 'TRUSTED';

    private const TRUST_ORDER = [
        'UNVERIFIED' => 0,
        'VALIDATED' => 1,
        'TRUSTED' => 2,
    ];

    /**
     * Validate that a data record has a non-null source field.
     *
     * @param array<string, mixed> $data
     * @param string $sourceField Field name for source (default: 'source')
     * @throws \Platform\Core\Exceptions\ApiException If source is missing
     */
    protected function validateProvenance(array $data, string $sourceField = 'source'): void
    {
        if (!isset($data[$sourceField]) || $data[$sourceField] === '' || $data[$sourceField] === null) {
            throw new \Platform\Core\Exceptions\ApiException(
                422,
                'PROVENANCE_REQUIRED',
                "Field '{$sourceField}' is required — no canonical data without source (N-16)"
            );
        }
    }

    /**
     * Check if a trust level meets the minimum required threshold.
     *
     * @param string $trustLevel The trust level to check
     * @param string $minimum The minimum required trust level
     */
    protected function meetsTrustLevel(string $trustLevel, string $minimum = self::TRUST_TRUSTED): bool
    {
        $levelValue = self::TRUST_ORDER[strtoupper($trustLevel)] ?? 0;
        $minValue = self::TRUST_ORDER[strtoupper($minimum)] ?? 2;
        return $levelValue >= $minValue;
    }

    /**
     * Filter records by minimum trust level.
     *
     * @param array<int, array<string, mixed>> $records
     * @param string $trustField Field name for trust level
     * @param string $minimum Minimum trust level
     * @return array<int, array<string, mixed>>
     */
    protected function filterByTrustLevel(
        array $records,
        string $trustField = 'trust_level',
        string $minimum = self::TRUST_TRUSTED
    ): array {
        return array_values(array_filter($records, function ($record) use ($trustField, $minimum): bool {
            $level = $record[$trustField] ?? self::TRUST_UNVERIFIED;
            return $this->meetsTrustLevel($level, $minimum);
        }));
    }

    /**
     * Assign trust level to a data record based on source.
     *
     * @param string $source Data source identifier
     * @return string Trust level
     */
    protected function deriveTrustLevel(string $source): string
    {
        // Official exchange feeds and regulatory filings are trusted
        $trustedSources = ['IDX', 'BEI', 'OJK', 'KPEI', 'KSEI', 'BLOOMBERG', 'REFINITIV'];
        // Validated sources: reputable data providers
        $validatedSources = ['YAHOO', 'GOOGLE', 'INVESTING', 'TRADINGVIEW', 'MANUAL_VERIFIED'];

        $upper = strtoupper($source);
        if (in_array($upper, $trustedSources, true)) {
            return self::TRUST_TRUSTED;
        }
        if (in_array($upper, $validatedSources, true)) {
            return self::TRUST_VALIDATED;
        }
        return self::TRUST_UNVERIFIED;
    }
}
