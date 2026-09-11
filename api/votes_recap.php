<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Models\VoteResult;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

header('Content-Type: application/json');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    Response::error('Method not allowed', 405);
}

AuthMiddleware::requireAuth();

$level = $_GET['level'] ?? 'province';
$parentId = $_GET['id'] ?? '';

$allowed = ['tps', 'village', 'district', 'regency', 'province'];
if (!in_array($level, $allowed, true)) {
    Response::error('Level tidak valid. Gunakan: tps, village, district, regency, province', 422);
}

$voteModel = new VoteResult();

try {
    $recap = $voteModel->recapByLevel($level, $parentId === '' ? null : $parentId);
} catch (\InvalidArgumentException $e) {
    Response::error($e->getMessage(), 422);
}

Response::success($recap);