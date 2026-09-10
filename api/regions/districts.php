<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers\Response;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$regencyId = $_GET['regency_id'] ?? '';

if (empty($regencyId)) {
    Response::error('regency_id wajib diisi');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM districts WHERE regency_id = :regency_id ORDER BY name");
$stmt->execute(['regency_id' => $regencyId]);
$districts = $stmt->fetchAll();

Response::success($districts);
