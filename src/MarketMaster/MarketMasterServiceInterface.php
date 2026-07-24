<?php

declare(strict_types=1);

namespace Platform\MarketMaster;

interface MarketMasterServiceInterface
{
    // Exchanges
    public function listExchanges(array $filters, int $page, int $perPage): array;

    public function getExchange(string $id): ?array;

    public function createExchange(array $data): array;

    public function updateExchange(string $id, array $data): array;

    public function getExchangeCalendar(string $exchangeId, string $fromDate, string $toDate): array;

    public function getExchangeInstruments(string $exchangeId, int $page, int $perPage): array;

    // Issuers
    public function listIssuers(array $filters, int $page, int $perPage): array;

    public function getIssuer(string $id): ?array;

    public function createIssuer(array $data): array;

    public function updateIssuer(string $id, array $data): array;

    public function getIssuerSecurities(string $issuerId): array;

    // Securities
    public function listSecurities(array $filters, int $page, int $perPage): array;

    public function getSecurity(string $id): ?array;

    // Instruments
    public function listInstruments(array $filters, int $page, int $perPage): array;

    public function createInstrument(array $data): array;

    public function getInstrumentById(string $id): ?array;

    public function updateInstrument(string $id, array $data): array;

    public function getInstrumentByTicker(string $exchangeMic, string $ticker): ?array;

    public function getInstrumentByIsin(string $isin): ?array;

    // Listings
    public function listListings(array $filters, int $page, int $perPage): array;

    public function getListing(string $id): ?array;

    public function getListingByTicker(string $exchangeMic, string $ticker): ?array;

    public function getListingByIsin(string $isin): ?array;

    public function createListing(array $data): array;

    public function getInstrumentListings(string $instrumentId): array;

    // Corporate Actions
    public function listCorporateActions(array $filters, int $page, int $perPage): array;

    public function getCorporateAction(string $id): ?array;

    public function createCorporateAction(array $data): array;

    public function getCorporateActions(
        string $instrumentId,
        string $fromDate,
        string $toDate
    ): array;

    // Index Master
    public function listIndices(array $filters, int $page, int $perPage): array;

    public function getIndex(string $id): ?array;

    public function getIndexMembers(string $indexId, string $asOfDate): array;

    public function createIndex(array $data): array;

    // Market Calendar
    public function getCalendar(string $exchangeId, string $fromDate, string $toDate): array;

    public function createCalendarEntry(array $data): array;

    // Utility
    public function isTradingDay(string $exchangeId, string $date): bool;

    public function getActiveListingsByExchange(string $exchangeId): array;
}
