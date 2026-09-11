<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Models\VoteResult;
use App\Helpers\Response;
use App\Helpers\Upload;
use App\Middleware\AuthMiddleware;

header('Content-Type: application/json');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['_method'] ?? '') !== '') {
    $method = strtoupper($_GET['_method']);
}

// ---------- Rute: GET /api/votes/summary/{tps_id} ----------
$tpsId = $_GET['tps_id'] ?? '';
if ($tpsId !== '') {
    if ($method !== 'GET') {
        Response::error('Method not allowed', 405);
    }

    AuthMiddleware::requireAuth();

    $voteModel = new VoteResult();
    $summary = $voteModel->summaryByTps((string)$tpsId);

    if (!$summary) {
        Response::error('Belum ada data suara untuk TPS ini', 404);
    }

    $summary['latest']['c1_photo_url'] = Upload::url((string)$summary['latest']['c1_photo_url']);
    Response::success($summary);
}

// ---------- Rute koleksi: GET /api/votes ----------
if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

AuthMiddleware::requireAuth();

$status = $_GET['status'] ?? '';
if ($status !== '' && !in_array($status, ['pending', 'verified', 'rejected'], true)) {
    Response::error('Status tidak valid', 422);
}

$tpsFilter = $_GET['tps_id'] ?? '';
$witnessId = $_GET['witness_id'] ?? '';
$q = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

$filters = [];
if ($status !== '') {
    $filters['status'] = $status;
}
if ($tpsFilter !== '') {
    $filters['tps_id'] = $tpsFilter;
}
if ($witnessId !== '') {
    $filters['witness_id'] = $witnessId;
}
if ($q !== '') {
    $filters['q'] = $q;
}

$voteModel = new VoteResult();
$rows = $voteModel->listWithDetails($filters, $perPage, ($page - 1) * $perPage);
$total = $voteModel->countFiltered($filters);

foreach ($rows as &$row) {
    $row['candidate_votes'] = json_decode((string)($row['candidate_votes'] ?? '[]'), true) ?: [];
    $row['c1_photo_url'] = Upload::url((string)$row['c1_photo_url']);
}
unset($row);

Response::paginated($rows, $total, $page, $perPage);