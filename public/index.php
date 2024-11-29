<?php

use DI\Container;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Slim\Factory\AppFactory;
use App\Models\Partner;
use App\Services\EmailService;
use App\Controllers\AuthController;
use App\Controllers\ApiController;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create Container Builder
$containerBuilder = new ContainerBuilder();

// Set up dependencies
$containerBuilder->addDefinitions([
    Connection::class => function () {
        return DriverManager::getConnection([
            'dbname' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASSWORD'],
            'host' => $_ENV['DB_HOST'],
            'driver' => 'pdo_pgsql',
        ]);
    },
    Partner::class => function (Container $container) {
        return new Partner($container->get(Connection::class));
    },
    EmailService::class => function () {
        return new EmailService();
    },
    AuthController::class => function (Container $container) {
        return new AuthController(
            $container->get(Partner::class),
            $container->get(EmailService::class)
        );
    },
    ApiController::class => function (Container $container) {
        return new ApiController(
            $container->get(Connection::class),
            $container->get(Partner::class)
        );
    },
]);

// Create Container
$container = $containerBuilder->build();

// Create App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware
$app->addRoutingMiddleware();

// Add raw body parsing middleware
$app->add(function (Request $request, RequestHandler $handler) {
    $contentType = $request->getHeaderLine('Content-Type');
    
    if (strpos($contentType, 'application/json') !== false) {
        $contents = $request->getBody()->getContents();
        $data = json_decode($contents, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $request = $request->withParsedBody($data);
        }
    }
    
    return $handler->handle($request);
});

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorMiddleware->setDefaultErrorHandler(function ($request, \Throwable $exception, bool $displayErrorDetails) use ($app) {
    $response = $app->getResponseFactory()->createResponse();
    $response->getBody()->write(json_encode([
        'error' => $exception->getMessage(),
        'trace' => $displayErrorDetails ? $exception->getTraceAsString() : null
    ]));
    
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(500);
});

// Enable CORS
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Handle CORS preflight requests
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Register routes
require __DIR__ . '/../config/routes.php';

// Run app
$app->run(); 