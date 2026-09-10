<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\TpsWitness;
use App\Models\VoteResult;
use App\Database;
use App\Helpers\Response;
use App\Helpers\JwtHelper;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Auth optional for dashboard stats
$user = JwtHelper::getAuthUser();

$db = Database::getConnection();

// Get TPS count
$tpsCount = $db->query("SELECT COUNT(*) as total FROM tps")->fetch()['total'];

// Get Witness stats
$witnessModel = new TpsWitness();
$witnessStats = $witnessModel->getStats();

// Get Vote stats
$voteModel = new VoteResult();
$voteStats = $voteModel->getStats();

// Get top districts
$stmt = $db->query("
    SELECT 
        d.name as district_name,
        COUNT(DISTINCT t.id) as tps_count,
        COUNT(DISTINCT w.id) as witness_count
    FROM districts d
    LEFT JOIN villages v ON v.district_id = d.id
    LEFT JOIN tps t ON t.village_id = v.id
    LEFT JOIN tps_witnesses w ON w.tps_id = t.id
    GROUP BY d.id, d.name
    ORDER BY witness_count DESC
    LIMIT 10
");
$topDistricts = $stmt->fetchAll();

Response::success([
    'tps' => [
        'total' => (int)$tpsCount,
    ],
    'witnesses' => $witnessStats,
    'votes' => $voteStats,
    'top_districts' => $topDistricts,
]);
