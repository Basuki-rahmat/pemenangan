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

// Perolehan suara per kandidat (dari data verified terbaru per TPS)
$stmt = $db->query(
    "SELECT vr.candidate_votes, vr.total_votes, vr.invalid_votes
     FROM vote_results vr
     JOIN (
         SELECT tps_id, MAX(id) AS mid
         FROM vote_results
         WHERE status = 'verified'
         GROUP BY tps_id
     ) m ON vr.id = m.mid"
);

$candidateTotals = [];
$sumVerified = 0;
$sumInvalid = 0;
foreach ($stmt->fetchAll() as $row) {
    $sumVerified += (int)$row['total_votes'];
    $sumInvalid += (int)$row['invalid_votes'];

    $decoded = json_decode((string)($row['candidate_votes'] ?? '[]'), true);
    if (!is_array($decoded)) {
        continue;
    }
    foreach ($decoded as $entry) {
        $name = (string)($entry['name'] ?? $entry['candidate_name'] ?? 'Tidak diketahui');
        $votes = max(0, (int)($entry['votes'] ?? $entry['total_votes'] ?? 0));
        $candidateTotals[$name] = ($candidateTotals[$name] ?? 0) + $votes;
    }
}

$candidates = [];
foreach ($candidateTotals as $name => $votes) {
    $candidates[] = ['name' => $name, 'votes' => $votes];
}
usort($candidates, fn($a, $b) => $b['votes'] <=> $a['votes']);

$denom = array_sum(array_column($candidates, 'votes')) ?: 1;
foreach ($candidates as &$c) {
    $c['pct'] = round(($c['votes'] / $denom) * 100, 1);
}
unset($c);

// Cakupan TPS
$totalTps = (int)$db->query("SELECT COUNT(*) AS c FROM tps")->fetch()['c'];
$verifiedTps = $stmt->rowCount();
$pendingTps = (int)$db->query(
    "SELECT COUNT(DISTINCT tps_id) AS c FROM vote_results WHERE status = 'pending'"
)->fetch()['c'];

// Pergerakan 24 jam terakhir (jumlah submit per jam)
$movement = $db->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS hour, COUNT(*) AS submissions
     FROM vote_results
     WHERE created_at >= NOW() - INTERVAL 24 HOUR
     GROUP BY hour
     ORDER BY hour"
)->fetchAll();

// Submisi terbaru
$recent = $db->query(
    "SELECT vr.id, vr.total_votes, vr.invalid_votes, vr.status, vr.created_at,
            t.tps_number, v.name AS village_name, d.name AS district_name,
            w.full_name AS witness_name
     FROM vote_results vr
     JOIN tps t ON vr.tps_id = t.id
     JOIN villages v ON t.village_id = v.id
     JOIN districts d ON v.district_id = d.id
     LEFT JOIN tps_witnesses w ON vr.witness_id = w.id
     ORDER BY vr.created_at DESC
     LIMIT 8"
)->fetchAll();

Response::success([
    'generated_at' => gmdate('c'),
    'candidates'   => $candidates,
    'coverage'     => [
        'total_tps'    => $totalTps,
        'verified_tps' => $verifiedTps,
        'pending_tps'  => $pendingTps,
        'pct'          => $totalTps > 0 ? round(($verifiedTps / $totalTps) * 100, 1) : 0,
    ],
    'votes' => [
        'total_rows' => (int)$db->query("SELECT COUNT(*) AS c FROM vote_results")->fetch()['c'],
        'sum_verified' => $sumVerified,
        'invalid'      => $sumInvalid,
    ],
    'movement_24h' => $movement,
    'recent'       => $recent,
]);