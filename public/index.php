<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

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

$app = Application::getInstance();

// Register services
$app->registerService('identity', new IdentityService());
$app->registerService('config', new ConfigService());
$app->registerService('governance', new GovernanceService());
$app->registerService('market_master', new MarketMasterService());
$app->registerService('fundamental', new FundamentalService());

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
GovernanceRoutes::register($router);

// Dispatch
$request = new Request();
$router->dispatch($request);
