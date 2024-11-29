<?php
// Start the built-in PHP server
// Run this with: php serve.php
$host = 'localhost';
$port = 3000;

echo "Starting test website server at http://{$host}:{$port}\n";
echo "Press Ctrl+C to stop\n";

// Start the server
exec("php -S {$host}:{$port}");
?> 