<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment. phpdotenv v5+ populates $_ENV/$_SERVER but NOT getenv(),
// so propagate to getenv() for getenv()-based code (Database, config, etc.).
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();
foreach ($_ENV as $key => $value) {
    if (!getenv($key)) {
        putenv("{$key}={$value}");
        $_SERVER[$key] = $value;
    }
}

// Load app config
$config = require __DIR__ . '/../config/app.php';

// Initialize JWT with configured secret
App\Helpers\JwtHelper::init($config['jwt_secret'], $config['jwt_expiry']);

// CORS headers
header('Access-Control-Allow-Origin: ' . ($config['cors_origin'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle CORS preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}