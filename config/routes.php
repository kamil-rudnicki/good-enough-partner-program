<?php

use App\Controllers\AuthController;
use App\Controllers\ApiController;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Middleware to verify JWT token
$authMiddleware = function (Request $request, $handler) {
    $response = new \Slim\Psr7\Response();
    $header = $request->getHeaderLine('Authorization');
    
    if (!$header || !preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => 'No token provided']));
    }

    try {
        $token = $matches[1];
        $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'] ?? 'your-secret-key', 'HS256'));
        return $handler->handle($request->withAttribute('partner_id', $decoded->partner_id));
    } catch (\Exception $e) {
        return $response
            ->withStatus(401)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => 'Invalid token']));
    }
};

// API routes
$app->group('/api', function (RouteCollectorProxy $group) use ($authMiddleware) {
    // Public routes
    $group->post('/auth/request-code', [AuthController::class, 'requestCode']);
    $group->post('/auth/verify-code', [AuthController::class, 'verifyCode']);
    $group->post('/track-visit', [ApiController::class, 'trackVisit']);
    $group->post('/leads', [ApiController::class, 'createLead']);

    // Protected routes
    $group->group('/partner', function (RouteCollectorProxy $group) {
        $group->get('/dashboard', [ApiController::class, 'getDashboard']);
        $group->post('/request-payout', [ApiController::class, 'requestPayout']);
    })->add($authMiddleware);
});

// Static file routes
$app->get('/tracking.js', function (Request $request, Response $response) {
    $response->getBody()->write(file_get_contents(__DIR__ . '/../public/tracking.js'));
    return $response
        ->withHeader('Content-Type', 'application/javascript')
        ->withHeader('Cache-Control', 'public, max-age=3600');
});

// Serve index.html for all other routes
$app->any('[/{params:.*}]', function (Request $request, Response $response) {
    $response->getBody()->write(file_get_contents(__DIR__ . '/../public/index.html'));
    return $response
        ->withHeader('Content-Type', 'text/html')
        ->withHeader('Cache-Control', 'no-cache');
}); 