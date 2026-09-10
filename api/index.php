<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Helpers\Response;

header('Content-Type: application/json');

// Get route from _route param (from .htaccess) or parse from URI
$route = $_GET['_route'] ?? null;

if ($route === null) {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = rtrim($uri, '/');
    // Remove leading slash
    $route = ltrim($uri, '/');
} else {
    $route = rtrim($route, '/');
}

$routes = [
    'api/auth/login' => __DIR__ . '/auth/login.php',
    'api/auth/register' => __DIR__ . '/auth/register.php',
    'api/auth/logout' => __DIR__ . '/auth/logout.php',
    'api/witness/register' => __DIR__ . '/witness/register.php',
    'api/votes/submit' => __DIR__ . '/votes/submit.php',
    'api/regions/provinces' => __DIR__ . '/regions/provinces.php',
    'api/regions/regencies' => __DIR__ . '/regions/regencies.php',
    'api/regions/districts' => __DIR__ . '/regions/districts.php',
    'api/regions/villages' => __DIR__ . '/regions/villages.php',
    'api/regions/tps' => __DIR__ . '/regions/tps.php',
    'api/dashboard/stats' => __DIR__ . '/dashboard/stats.php',
    'api/dashboard/heatmap' => __DIR__ . '/dashboard/heatmap.php',
    'api/admin/theme' => __DIR__ . '/admin/theme.php',
];

if (isset($routes[$route])) {
    require $routes[$route];
} else {
    Response::error('Endpoint not found: ' . $route, 404);
}
