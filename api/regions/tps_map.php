<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\Response;
use App\Database;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$db = Database::getConnection();

$provinceId = $_GET['province_id'] ?? '';
$regencyId = $_GET['regency_id'] ?? '';
$districtId = $_GET['district_id'] ?? '';
$villageId = $_GET['village_id'] ?? '';

$where = [];
$params = [];

if ($villageId) {
    $where[] = "t.village_id = :village_id";
    $params['village_id'] = $villageId;
}
if ($districtId) {
    $where[] = "v.district_id = :district_id";
    $params['district_id'] = $districtId;
}
if ($regencyId) {
    $where[] = "d.regency_id = :regency_id";
    $params['regency_id'] = $regencyId;
}
if ($provinceId) {
    $where[] = "r.province_id = :province_id";
    $params['province_id'] = $provinceId;
}

$sql = "SELECT 
            t.id, t.tps_number, t.total_dpt, t.latitude, t.longitude,
            v.id AS village_id, v.name AS village_name,
            d.id AS district_id, d.name AS district_name,
            r.id AS regency_id, r.name AS regency_name,
            p.id AS province_id, p.name AS province_name,
            CASE WHEN w.tps_id IS NULL THEN 0 ELSE 1 END AS has_witness,
            CASE WHEN w.status = 'verified' THEN 1 ELSE 0 END AS witness_verified,
            CASE WHEN vr.id IS NULL THEN 0 ELSE 1 END AS has_vote
        FROM tps t
        JOIN villages v ON t.village_id = v.id
        JOIN districts d ON v.district_id = d.id
        JOIN regencies r ON d.regency_id = r.id
        JOIN provinces p ON r.province_id = p.id
        LEFT JOIN (SELECT tps_id, MAX(status) AS status, COUNT(*) AS cnt FROM tps_witnesses GROUP BY tps_id) w
            ON w.tps_id = t.id
        LEFT JOIN vote_results vr ON vr.tps_id = t.id AND vr.status = 'verified'";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

$sql .= " ORDER BY d.name, v.name, t.tps_number LIMIT 500";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$tpsList = $stmt->fetchAll();

// Bounds
$bounds = null;
foreach ($tpsList as $tps) {
    if ($tps['latitude'] !== null && $tps['longitude'] !== null) {
        $bounds['min_lat'] = $bounds['min_lat'] ?? PHP_FLOAT_MAX;
        $bounds['max_lat'] = $bounds['max_lat'] ?? -PHP_FLOAT_MAX;
        $bounds['min_lng'] = $bounds['min_lng'] ?? PHP_FLOAT_MAX;
        $bounds['max_lng'] = $bounds['max_lng'] ?? -PHP_FLOAT_MAX;
        $bounds['min_lat'] = min($bounds['min_lat'], (float)$tps['latitude']);
        $bounds['max_lat'] = max($bounds['max_lat'], (float)$tps['latitude']);
        $bounds['min_lng'] = min($bounds['min_lng'], (float)$tps['longitude']);
        $bounds['max_lng'] = max($bounds['max_lng'], (float)$tps['longitude']);
    }
}

Response::success([
    'total' => count($tpsList),
    'bounds' => $bounds,
    'tps' => $tpsList,
]);