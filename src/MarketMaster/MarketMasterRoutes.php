<?php

declare(strict_types=1);

namespace Platform\MarketMaster;

use Platform\Core\Application;
use Platform\Core\Exceptions\ApiException;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;

final class MarketMasterRoutes
{
    public static function register(Router $router): void
    {
        // Exchanges
        $router->get('/exchanges', [self::class, 'listExchanges'], ['bearer']);
        $router->post('/exchanges', [self::class, 'createExchange'], ['bearer']);
        $router->get('/exchanges/{id}', [self::class, 'getExchange'], ['bearer']);
        $router->put('/exchanges/{id}', [self::class, 'updateExchange'], ['bearer']);
        $router->get('/exchanges/{id}/calendar', [self::class, 'exchangeCalendar'], ['bearer']);
        $router->get('/exchanges/{id}/instruments', [self::class, 'exchangeInstruments'], ['bearer']);

        // Issuers
        $router->get('/issuers', [self::class, 'listIssuers'], ['bearer']);
        $router->post('/issuers', [self::class, 'createIssuer'], ['bearer']);
        $router->get('/issuers/{id}', [self::class, 'getIssuer'], ['bearer']);
        $router->put('/issuers/{id}', [self::class, 'updateIssuer'], ['bearer']);
        $router->get('/issuers/{id}/securities', [self::class, 'issuerSecurities'], ['bearer']);

        // Securities
        $router->get('/securities', [self::class, 'listSecurities'], ['bearer']);
        $router->get('/securities/{id}', [self::class, 'getSecurity'], ['bearer']);

        // Instruments
        $router->get('/instruments', [self::class, 'listInstruments'], ['bearer']);
        $router->post('/instruments', [self::class, 'createInstrument'], ['bearer']);
        $router->get('/instruments/{id}', [self::class, 'getInstrument'], ['bearer']);
        $router->put('/instruments/{id}', [self::class, 'updateInstrument'], ['bearer']);
        $router->get('/instruments/{id}/listings', [self::class, 'instrumentListings'], ['bearer']);
        $router->get(
            '/instruments/{id}/corporate-actions',
            [self::class, 'instrumentCorporateActions'],
            ['bearer']
        );

        // Listings
        $router->get('/listings', [self::class, 'listListings'], ['bearer']);
        $router->post('/listings', [self::class, 'createListing'], ['bearer']);
        $router->get('/listings/{id}', [self::class, 'getListing'], ['bearer']);
        $router->get(
            '/listings/by-ticker/{exchange}/{ticker}',
            [self::class, 'getListingByTicker'],
            ['bearer']
        );
        $router->get('/listings/by-isin/{isin}', [self::class, 'getListingByIsin'], ['bearer']);

        // Corporate Actions
        $router->get('/corporate-actions', [self::class, 'listCorporateActions'], ['bearer']);
        $router->post('/corporate-actions', [self::class, 'createCorporateAction'], ['bearer']);
        $router->get('/corporate-actions/{id}', [self::class, 'getCorporateAction'], ['bearer']);

        // Index Master
        $router->get('/indices', [self::class, 'listIndices'], ['bearer']);
        $router->post('/indices', [self::class, 'createIndex'], ['bearer']);
        $router->get('/indices/{id}', [self::class, 'getIndex'], ['bearer']);
        $router->get('/indices/{id}/members', [self::class, 'indexMembers'], ['bearer']);

        // Market Calendar
        $router->get('/calendar', [self::class, 'getCalendar'], ['bearer']);
        $router->get('/calendar/{exchangeId}', [self::class, 'getCalendarByExchange'], ['bearer']);
        $router->post('/calendar', [self::class, 'createCalendarEntry'], ['bearer']);
    }

    // ─── Exchanges ───────────────────────────────────────────────────────

    public static function listExchanges(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listExchanges(self::exchangeFilters($request), $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createExchange(Request $request): Response
    {
        return Response::created(self::service()->createExchange($request->getAllBody()));
    }

    public static function getExchange(Request $request): Response
    {
        $row = self::service()->getExchange((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'EXCHANGE_NOT_FOUND', 'Exchange was not found'));
    }

    public static function updateExchange(Request $request): Response
    {
        return Response::ok(
            self::service()->updateExchange((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    public static function exchangeCalendar(Request $request): Response
    {
        $rows = self::service()->getExchangeCalendar(
            (string) $request->getParam('id'),
            (string) $request->getQuery('from', date('Y-01-01')),
            (string) $request->getQuery('to', date('Y-12-31'))
        );
        return Response::ok($rows);
    }

    public static function exchangeInstruments(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->getExchangeInstruments(
            (string) $request->getParam('id'),
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    // ─── Issuers ─────────────────────────────────────────────────────────

    public static function listIssuers(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listIssuers(self::issuerFilters($request), $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createIssuer(Request $request): Response
    {
        return Response::created(self::service()->createIssuer($request->getAllBody()));
    }

    public static function getIssuer(Request $request): Response
    {
        $row = self::service()->getIssuer((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'ISSUER_NOT_FOUND', 'Issuer was not found'));
    }

    public static function updateIssuer(Request $request): Response
    {
        return Response::ok(
            self::service()->updateIssuer((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    public static function issuerSecurities(Request $request): Response
    {
        return Response::ok(self::service()->getIssuerSecurities((string) $request->getParam('id')));
    }

    // ─── Securities ──────────────────────────────────────────────────────

    public static function listSecurities(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listSecurities(
            [
                'issuer_id' => $request->getQuery('issuer_id'),
                'security_type' => $request->getQuery('security_type'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function getSecurity(Request $request): Response
    {
        $row = self::service()->getSecurity((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'SECURITY_NOT_FOUND', 'Security was not found'));
    }

    // ─── Instruments ─────────────────────────────────────────────────────

    public static function listInstruments(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listInstruments(self::instrumentFilters($request), $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createInstrument(Request $request): Response
    {
        return Response::created(self::service()->createInstrument($request->getAllBody()));
    }

    public static function getInstrument(Request $request): Response
    {
        $row = self::service()->getInstrumentById((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'INSTRUMENT_NOT_FOUND', 'Instrument was not found'));
    }

    public static function updateInstrument(Request $request): Response
    {
        return Response::ok(
            self::service()->updateInstrument((string) $request->getParam('id'), $request->getAllBody())
        );
    }

    public static function instrumentListings(Request $request): Response
    {
        return Response::ok(
            self::service()->getInstrumentListings((string) $request->getParam('id'))
        );
    }

    public static function instrumentCorporateActions(Request $request): Response
    {
        $rows = self::service()->getCorporateActions(
            (string) $request->getParam('id'),
            (string) $request->getQuery('from', '1900-01-01'),
            (string) $request->getQuery('to', date('Y-m-d'))
        );
        return Response::ok($rows);
    }

    // ─── Listings ────────────────────────────────────────────────────────

    public static function listListings(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listListings(self::listingFilters($request), $page, $perPage);
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createListing(Request $request): Response
    {
        return Response::created(self::service()->createListing($request->getAllBody()));
    }

    public static function getListing(Request $request): Response
    {
        $row = self::service()->getListing((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'LISTING_NOT_FOUND', 'Listing was not found'));
    }

    public static function getListingByTicker(Request $request): Response
    {
        $row = self::service()->getListingByTicker(
            (string) $request->getParam('exchange'),
            (string) $request->getParam('ticker')
        );
        return Response::ok(self::required($row, 'LISTING_NOT_FOUND', 'Listing was not found'));
    }

    public static function getListingByIsin(Request $request): Response
    {
        $row = self::service()->getListingByIsin((string) $request->getParam('isin'));
        return Response::ok(self::required($row, 'LISTING_NOT_FOUND', 'Listing was not found'));
    }

    // ─── Corporate Actions ───────────────────────────────────────────────

    public static function listCorporateActions(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listCorporateActions(
            [
                'instrument_id' => $request->getQuery('instrument_id'),
                'action_type' => $request->getQuery('action_type'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createCorporateAction(Request $request): Response
    {
        return Response::created(self::service()->createCorporateAction($request->getAllBody()));
    }

    public static function getCorporateAction(Request $request): Response
    {
        $row = self::service()->getCorporateAction((string) $request->getParam('id'));
        return Response::ok(
            self::required($row, 'CORPORATE_ACTION_NOT_FOUND', 'Corporate action was not found')
        );
    }

    // ─── Index Master ────────────────────────────────────────────────────

    public static function listIndices(Request $request): Response
    {
        [$page, $perPage] = self::pagination($request);
        $result = self::service()->listIndices(
            [
                'exchange_id' => $request->getQuery('exchange_id'),
                'status' => $request->getQuery('status'),
            ],
            $page,
            $perPage
        );
        return Response::ok($result['data'], $result['meta']);
    }

    public static function createIndex(Request $request): Response
    {
        return Response::created(self::service()->createIndex($request->getAllBody()));
    }

    public static function getIndex(Request $request): Response
    {
        $row = self::service()->getIndex((string) $request->getParam('id'));
        return Response::ok(self::required($row, 'INDEX_NOT_FOUND', 'Index was not found'));
    }

    public static function indexMembers(Request $request): Response
    {
        $rows = self::service()->getIndexMembers(
            (string) $request->getParam('id'),
            (string) $request->getQuery('as_of', date('Y-m-d'))
        );
        return Response::ok($rows);
    }

    // ─── Market Calendar ─────────────────────────────────────────────────

    public static function getCalendar(Request $request): Response
    {
        $exchangeId = (string) $request->getQuery('exchange_id', '');
        if ($exchangeId === '') {
            throw new ApiException(400, 'VALIDATION_ERROR', 'exchange_id query parameter is required');
        }
        $rows = self::service()->getCalendar(
            $exchangeId,
            (string) $request->getQuery('from', date('Y-01-01')),
            (string) $request->getQuery('to', date('Y-12-31'))
        );
        return Response::ok($rows);
    }

    public static function getCalendarByExchange(Request $request): Response
    {
        $rows = self::service()->getCalendar(
            (string) $request->getParam('exchangeId'),
            (string) $request->getQuery('from', date('Y-01-01')),
            (string) $request->getQuery('to', date('Y-12-31'))
        );
        return Response::ok($rows);
    }

    public static function createCalendarEntry(Request $request): Response
    {
        return Response::created(self::service()->createCalendarEntry($request->getAllBody()));
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    private static function service(): MarketMasterServiceInterface
    {
        $service = Application::getInstance()->getService('market_master');
        if (!$service instanceof MarketMasterServiceInterface) {
            throw new ApiException(
                503,
                'MARKET_MASTER_UNAVAILABLE',
                'Market Master service is unavailable'
            );
        }
        return $service;
    }

    private static function pagination(Request $request): array
    {
        return [
            max(1, (int) $request->getQuery('page', 1)),
            min(200, max(1, (int) $request->getQuery('per_page', 50))),
        ];
    }

    private static function required(?array $row, string $code, string $message): array
    {
        if ($row === null) {
            throw new ApiException(404, $code, $message);
        }
        return $row;
    }

    private static function exchangeFilters(Request $request): array
    {
        return [
            'country' => $request->getQuery('country'),
            'status' => $request->getQuery('status'),
        ];
    }

    private static function issuerFilters(Request $request): array
    {
        return [
            'country' => $request->getQuery('country'),
            'status' => $request->getQuery('status'),
            'search' => $request->getQuery('search'),
        ];
    }

    private static function instrumentFilters(Request $request): array
    {
        return [
            'asset_class' => $request->getQuery('filter[asset_class]'),
            'status' => $request->getQuery('filter[status]'),
            'search' => $request->getQuery('search'),
        ];
    }

    private static function listingFilters(Request $request): array
    {
        return [
            'exchange_id' => $request->getQuery('exchange_id'),
            'status' => $request->getQuery('status'),
            'ticker' => $request->getQuery('ticker'),
        ];
    }
}
