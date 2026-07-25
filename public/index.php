<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// ─── Security Headers ─────────────────────────────────────────────────
// Load .env early for environment checks
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// HTTPS enforcement (production only)
$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'development');
if (
    $appEnv !== 'development'
    && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
    && (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https' : true)
) {
    header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? ''));
    http_response_code(301);
    exit;
}

// CORS headers
$allowedOrigins = getenv('CORS_ALLOWED_ORIGINS') ?: '*';
header('Access-Control-Allow-Origin: ' . $allowedOrigins);
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Correlation-ID');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight OPTIONS requests
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

use Platform\Alert\AlertRoutes;
use Platform\Alert\AlertService;
use Platform\Analytics\AnalyticsRoutes;
use Platform\Analytics\AnalyticsService;
use Platform\Trading\BrokerAdapterRoutes;
use Platform\Trading\BrokerAdapterService;
use Platform\AIEngine\AIEngineRoutes;
use Platform\AIEngine\AIEngineService;
use Platform\Microstructure\MicrostructureRoutes;
use Platform\Microstructure\MicrostructureService;
use Platform\Backtesting\BacktestRoutes;
use Platform\Backtesting\BacktestService;
use Platform\PaperTrading\PaperTradingRoutes;
use Platform\PaperTrading\PaperTradingService;
use Platform\Config\ConfigRoutes;
use Platform\Config\ConfigService;
use Platform\DataIngestion\DataIngestionRoutes;
use Platform\DataIngestion\DataIngestionService;
use Platform\Valuation\ValuationRoutes;
use Platform\Valuation\ValuationService;
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
$app->registerService('data_ingestion', new DataIngestionService());
$app->registerService('valuation', new ValuationService());
$app->registerService('alert', new AlertService());
$app->registerService('broker_adapter', new BrokerAdapterService());
$app->registerService('backtest', new BacktestService());
$app->registerService('paper_trading', new PaperTradingService());
$app->registerService('ai_engine', new AIEngineService());
$app->registerService('microstructure', new MicrostructureService());

// Create router
$router = new Router();

// Register middleware
$router->addMiddleware('bearer', [AuthMiddleware::class, 'bearer']);
$router->addMiddleware('public', [AuthMiddleware::class, 'public']);
$router->addMiddleware('rate-limit', [\Platform\Core\Middleware\RateLimitMiddleware::class, 'api']);
$router->addMiddleware('rate-limit-auth', [\Platform\Core\Middleware\RateLimitMiddleware::class, 'auth']);

// Health endpoints
$router->get('/health', function (Request $request) use ($app): Response {
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
$router->get('/metrics', function (Request $request) use ($app): Response {
    $app = Application::getInstance();
    return Response::ok([
        'info' => [
            'version' => '1.0.0',
            'environment' => $app->getEnvironment(),
        ],
        'uptime_seconds' => time() - ($_SERVER['REQUEST_TIME'] ?? time()),
        'services_registered' => 18,
    ]);
});

// API info
$router->get('/', function (Request $request) use ($app): Response {
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
DataIngestionRoutes::register($router);
ValuationRoutes::register($router);
AlertRoutes::register($router);
BrokerAdapterRoutes::register($router);
BacktestRoutes::register($router);
PaperTradingRoutes::register($router);
AIEngineRoutes::register($router);
MicrostructureRoutes::register($router);

// Dispatch
$request = new Request();
$router->dispatch($request);
