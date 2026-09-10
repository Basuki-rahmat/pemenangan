<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Database;
use App\Helpers\Response;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$districtId = $_GET['district_id'] ?? '';

if (empty($districtId)) {
    Response::error('district_id wajib diisi');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM villages WHERE district_id = :district_id ORDER BY name");
$stmt->execute(['district_id' => $districtId]);
$villages = $stmt->fetchAll();

Response::success($villages);
