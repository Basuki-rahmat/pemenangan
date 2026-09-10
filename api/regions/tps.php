<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers\Response;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$villageId = $_GET['village_id'] ?? '';

if (empty($villageId)) {
    Response::error('village_id wajib diisi');
}

$db = Database::getConnection();
$stmt = $db->prepare("SELECT * FROM tps WHERE village_id = :village_id ORDER BY tps_number");
$stmt->execute(['village_id' => $villageId]);
$tps = $stmt->fetchAll();

Response::success($tps);
