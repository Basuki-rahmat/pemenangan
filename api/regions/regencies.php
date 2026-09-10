<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Helpers\Response;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$provinceId = $_GET['province_id'] ?? '';

if (empty($provinceId)) {
    Response::error('province_id wajib diisi');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM regencies WHERE province_id = :province_id ORDER BY name");
$stmt->execute(['province_id' => $provinceId]);
$regencies = $stmt->fetchAll();

Response::success($regencies);
