<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Models\TpsWitness;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;

header('Content-Type: application/json');

$user = AuthMiddleware::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id']) || empty($input['status'])) {
    Response::error('ID dan status wajib diisi');
}

if (!in_array($input['status'], ['verified', 'rejected'])) {
    Response::error('Status tidak valid');
}

$witnessModel = new TpsWitness();
$witness = $witnessModel->findById((string)$input['id']);

if (!$witness) {
    Response::error('Saksi tidak ditemukan', 404);
}

$ok = $witnessModel->verify((string)$input['id'], (string)$user['id'], $input['status']);

if ($ok) {
    Response::success(null, 'Status saksi berhasil diubah');
}

Response::error('Gagal memperbarui status');