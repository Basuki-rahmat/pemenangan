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

$source = $_GET['source'] ?? 'basis';
$party = $_GET['party'] ?? '';
$regencyId = $_GET['regency_id'] ?? '';
$candidate = $_GET['candidate'] ?? '';

// === Heatmap perolehan suara real-time (sumber: vote_results verified) ===
if ($source === 'votes') {
    $where = ["t.latitude IS NOT NULL", "t.longitude IS NOT NULL"];
    $params = [];

    if ($regencyId !== '') {
        $where[] = 'r.id = :regency_id';
        $params['regency_id'] = $regencyId;
    }

    $whereSql = implode(' AND ', $where);

    $sql = "
        SELECT
            v.id AS village_id, v.name AS village_name,
            d.name AS district_name, r.name AS regency_name,
            t.id AS tps_id, t.tps_number, t.latitude, t.longitude,
            CAST(t.total_dpt AS UNSIGNED) AS total_dpt,
            vr.total_votes, vr.invalid_votes, vr.candidate_votes
        FROM vote_results vr
        JOIN (
            SELECT tps_id, MAX(id) AS mid
            FROM vote_results
            WHERE status = 'verified'
            GROUP BY tps_id
        ) m ON vr.id = m.mid
        JOIN tps t ON vr.tps_id = t.id
        JOIN villages v ON t.village_id = v.id
        JOIN districts d ON v.district_id = d.id
        JOIN regencies r ON d.regency_id = r.id
        WHERE {$whereSql}
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $candidateTotals = [];
    $villageAgg = [];

    foreach ($rows as $row) {
        $lat = (float)$row['latitude'];
        $lng = (float)$row['longitude'];
        if ($lat == 0 && $lng == 0) continue;

        $key = (string)$row['village_id'];
        if (!isset($villageAgg[$key])) {
            $villageAgg[$key] = [
                'village_id'    => $key,
                'village_name'  => $row['village_name'],
                'district_name' => $row['district_name'],
                'regency_name'  => $row['regency_name'],
                'lat_sum'       => 0.0,
                'lng_sum'       => 0.0,
                'tps_count'     => 0,
                'votes_in'      => 0,
                'total_votes'   => 0,
                'invalid_votes' => 0,
                'dpt'           => 0,
                'candidates'    => [],
            ];
        }

        $agg = &$villageAgg[$key];
        $agg['lat_sum'] += $lat;
        $agg['lng_sum'] += $lng;
        $agg['tps_count']++;
        $agg['votes_in']++;
        $agg['total_votes'] += (int)$row['total_votes'];
        $agg['invalid_votes'] += (int)$row['invalid_votes'];
        $agg['dpt'] += (int)$row['total_dpt'];

        $decoded = json_decode((string)($row['candidate_votes'] ?? '[]'), true);
        if (is_array($decoded)) {
            foreach ($decoded as $entry) {
                $name = (string)($entry['name'] ?? $entry['candidate_name'] ?? 'Tidak diketahui');
                $votes = max(0, (int)($entry['votes'] ?? $entry['total_votes'] ?? 0));
                $agg['candidates'][$name] = ($agg['candidates'][$name] ?? 0) + $votes;
                $candidateTotals[$name] = ($candidateTotals[$name] ?? 0) + $votes;
            }
        }
        unset($agg);
    }

    $points = [];
    foreach ($villageAgg as $agg) {
        $lat = $agg['lat_sum'] / $agg['tps_count'];
        $lng = $agg['lng_sum'] / $agg['tps_count'];

        $candList = [];
        foreach ($agg['candidates'] as $name => $v) {
            $candList[] = ['name' => $name, 'votes' => $v];
        }
        usort($candList, fn($a, $b) => $b['votes'] <=> $a['votes']);

        $intensity = (int)$agg['total_votes'];
        if ($candidate !== '') {
            $intensity = (int)($agg['candidates'][$candidate] ?? 0);
        }

        $points[] = [
            'lat'           => $lat,
            'lng'           => $lng,
            'intensity'     => $intensity,
            'label'         => $agg['village_name'] . ', ' . $agg['district_name'],
            'regency'       => $agg['regency_name'],
            'tps'           => $agg['tps_count'],
            'votes_in'      => $agg['votes_in'],
            'dpt'           => $agg['dpt'],
            'total_votes'   => $agg['total_votes'],
            'invalid_votes' => $agg['invalid_votes'],
            'candidates'    => $candList,
        ];
    }
    usort($points, fn($a, $b) => $b['total_votes'] <=> $a['total_votes']);

    $candidateList = [];
    foreach ($candidateTotals as $name => $v) {
        $candidateList[] = ['name' => $name, 'votes' => $v];
    }
    usort($candidateList, fn($a, $b) => $b['votes'] <=> $a['votes']);

    $summary = [
        'total_villages' => count($points),
        'total_tps'      => array_sum(array_column($points, 'tps')),
        'tps_votes_in'   => array_sum(array_column($points, 'votes_in')),
        'total_votes'    => array_sum(array_column($points, 'total_votes')),
        'total_invalid'  => array_sum(array_column($points, 'invalid_votes')),
        'max_intensity'  => max(array_map(fn($p) => $p['intensity'], $points) ?: [0]),
    ];

    Response::success([
        'source'     => 'votes',
        'candidates' => $candidateList,
        'points'     => $points,
        'summary'    => $summary,
    ]);
}

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
