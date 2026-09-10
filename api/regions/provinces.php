<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Helpers\Response;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$db = Database::getConnection();
$stmt = $db->query("SELECT * FROM provinces ORDER BY name");
$provinces = $stmt->fetchAll();

Response::success($provinces);
