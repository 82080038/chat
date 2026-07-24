<?php

declare(strict_types=1);

namespace Platform\Core\Data;

/**
 * Trait for Point-in-Time (PIT) query support.
 *
 * Blueprint section 382, 441: PIT queries filter data by `available_time`
 * to ensure only data that was known at a given point in time is returned.
 * This prevents look-ahead bias in backtesting and analytics.
 */
trait PointInTimeQuery
{
    /**
     * Build a WHERE clause for PIT filtering.
     *
     * @param string $alias Table alias (e.g. "fs" for financial_statement)
     * @param string|null $asOfDate Date in Y-m-d format. If null, no PIT filter applied.
     * @return array{clause: string, param: array<string, string>|null}
     */
    protected function buildPitClause(string $alias, ?string $asOfDate): array
    {
        if ($asOfDate === null) {
            return ['clause' => '', 'param' => null];
        }

        return [
            'clause' => "AND {$alias}.available_time <= :as_of_date",
            'param' => [':as_of_date' => $asOfDate . ' 23:59:59'],
        ];
    }

    /**
     * Filter an array of records by available_time for PIT queries.
     *
     * @param array<int, array<string, mixed>> $records
     * @param string|null $asOfDate Y-m-d format
     * @param string $dateField Field name for the available_time column
     * @return array<int, array<string, mixed>>
     */
    protected function filterByPit(array $records, ?string $asOfDate, string $dateField = 'available_time'): array
    {
        if ($asOfDate === null) {
            return $records;
        }

        $cutoff = $asOfDate . ' 23:59:59';
        return array_values(array_filter($records, function ($record) use ($cutoff, $dateField): bool {
            $available = $record[$dateField] ?? null;
            if ($available === null) {
                return true; // If no available_time, include by default
            }
            return $available <= $cutoff;
        }));
    }
}
