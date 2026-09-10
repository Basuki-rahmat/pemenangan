<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers\Response;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$db = Database::getConnection();

$party = $_GET['party'] ?? '';
$regencyId = $_GET['regency_id'] ?? '';

$where = ["t.latitude IS NOT NULL", "t.longitude IS NOT NULL"];
$params = [];

if ($party !== '' && $party !== 'all') {
    $where[] = "bm.party_name = :party";
    $params['party'] = $party;
}

if ($regencyId !== '') {
    $where[] = "r.id = :regency_id";
    $params['regency_id'] = $regencyId;
}

$whereSql = implode(' AND ', $where);

// Heatmap data: agregasi per desa (rata-rata koordinat TPS × jumlah pendukung)
$sql = "
    SELECT 
        v.id AS village_id,
        v.name AS village_name,
        d.name AS district_name,
        r.name AS regency_name,
        AVG(t.latitude) AS lat,
        AVG(t.longitude) AS lng,
        COUNT(DISTINCT t.id) AS tps_count,
        COALESCE(SUM(bm.estimated_supporters), 0) AS supporters,
        COALESCE(SUM(bm.dpt_total), 0) AS dpt,
        COALESCE(AVG(bm.support_index), 0) AS avg_support_index
    FROM tps t
    JOIN villages v ON t.village_id = v.id
    JOIN districts d ON v.district_id = d.id
    JOIN regencies r ON d.regency_id = r.id
    LEFT JOIN basis_massa bm ON bm.village_id = v.id
    WHERE {$whereSql}
    GROUP BY v.id, v.name, d.name, r.name
    HAVING tps_count > 0
    ORDER BY supporters DESC
    LIMIT 500
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Format untuk Leaflet.heat: [lat, lng, intensity]
// intensity = supporters (dibagi max agar proporsional)
$maxSupporters = max(array_map(fn($r) => (int)$r['supporters'], $rows) ?: [1]);

$points = [];
foreach ($rows as $row) {
    $lat = (float)$row['lat'];
    $lng = (float)$row['lng'];
    if ($lat == 0 && $lng == 0) continue;

    $points[] = [
        'lat'   => $lat,
        'lng'   => $lng,
        'intensity' => (int)$row['supporters'],
        'label' => $row['village_name'] . ', ' . $row['district_name'],
        'regency' => $row['regency_name'],
        'tps'   => (int)$row['tps_count'],
        'dpt'   => (int)$row['dpt'],
        'supporters' => (int)$row['supporters'],
        'index' => round((float)$row['avg_support_index'], 1),
    ];
}

// Summary stats
$summary = [
    'total_villages' => count($points),
    'total_supporters' => array_sum(array_column($points, 'supporters')),
    'total_dpt' => array_sum(array_column($points, 'dpt')),
    'max_village_supporters' => $maxSupporters,
];

Response::success([
    'points' => $points,
    'summary' => $summary,
]);
