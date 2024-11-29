<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\ChartMogulService;
use Doctrine\DBAL\DriverManager;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Database connection
$connectionParams = [
    'dbname' => $_ENV['DB_NAME'],
    'user' => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASSWORD'],
    'host' => $_ENV['DB_HOST'],
    'driver' => 'pdo_pgsql',
];

try {
    $connection = DriverManager::getConnection($connectionParams);
    $chartMogulService = new ChartMogulService($connection);
    $chartMogulService->syncTransactions();
    echo "Transactions synchronized successfully\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 