<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Platform\Analytics\AnalyticsRoutes;
use Platform\Analytics\AnalyticsService;
use Platform\Config\ConfigRoutes;
use Platform\Config\ConfigService;
use Platform\Core\Application;
use Platform\Core\Http\Request;
use Platform\Core\Http\Response;
use Platform\Core\Http\Router;
use Platform\Core\Middleware\AuthMiddleware;
use Platform\Fundamental\FundamentalRoutes;
use Platform\Fundamental\FundamentalService;
use Platform\Governance\GovernanceRoutes;
use Platform\Governance\GovernanceService;
use Platform\Identity\IdentityRoutes;
use Platform\Identity\IdentityService;
use Platform\MarketMaster\MarketMasterRoutes;
use Platform\MarketMaster\MarketMasterService;
use Platform\Portfolio\PortfolioRoutes;
use Platform\Portfolio\PortfolioService;
use Platform\Risk\RiskRoutes;
use Platform\Risk\RiskService;
use Platform\Settlement\SettlementRoutes;
use Platform\Settlement\SettlementService;
use Platform\Trading\TradingRoutes;
use Platform\Trading\TradingService;

$app = Application::getInstance();

// Register services
$app->registerService('identity', new IdentityService());
$app->registerService('config', new ConfigService());
$app->registerService('governance', new GovernanceService());
$app->registerService('market_master', new MarketMasterService());
$app->registerService('fundamental', new FundamentalService());
$app->registerService('analytics', new AnalyticsService());
$app->registerService('risk', new RiskService());
$app->registerService('portfolio', new PortfolioService());
$app->registerService('trading', new TradingService());
$app->registerService('settlement', new SettlementService());

// Create router
$router = new Router();

// Register middleware
$router->addMiddleware('bearer', [AuthMiddleware::class, 'bearer']);
$router->addMiddleware('public', [AuthMiddleware::class, 'public']);

// Health endpoints
$router->get('/health', function (Request $request): Response {
    return Response::ok([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => $app->getConfig('APP_NAME', 'Capital Market Platform'),
    ]);
});

$router->get('/health/ready', function (Request $request): Response {
    try {
        $db = \Platform\Core\Database\MySqlConnection::getInstance();
        $db->query('SELECT 1');
        return Response::ok(['status' => 'ready', 'database' => 'connected']);
    } catch (\Exception $e) {
        return Response::error(503, 'NOT_READY', 'Database connection failed: ' . $e->getMessage());
    }
});

$router->get('/health/live', function (Request $request): Response {
    return Response::ok(['status' => 'alive']);
});

// Metrics endpoint
$router->get('/metrics', function (Request $request): Response {
    $app = Application::getInstance();
    return Response::ok([
        'info' => [
            'version' => '1.0.0',
            'environment' => $app->getEnvironment(),
        ],
        'uptime_seconds' => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
        'services_registered' => 10,
    ]);
});

// API info
$router->get('/', function (Request $request): Response {
    return Response::ok([
        'name' => 'Capital Market Platform API',
        'version' => '1.0.0',
        'environment' => $app->getEnvironment(),
        'timestamp' => date('c'),
    ]);
});

// Register context routes
IdentityRoutes::register($router);
ConfigRoutes::register($router);
MarketMasterRoutes::register($router);
FundamentalRoutes::register($router);
AnalyticsRoutes::register($router);
RiskRoutes::register($router);
PortfolioRoutes::register($router);
TradingRoutes::register($router);
SettlementRoutes::register($router);
GovernanceRoutes::register($router);

// Dispatch
$request = new Request();
$router->dispatch($request);
