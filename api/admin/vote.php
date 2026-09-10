<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\VoteResult;
use App\Helpers\Response;
use App\Helpers\JwtHelper;

header('Content-Type: application/json');

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

$voteModel = new VoteResult();
$vote = $voteModel->findById((string)$input['id']);

if (!$vote) {
    Response::error('Data suara tidak ditemukan', 404);
}

$verifiedBy = 1;
$user = JwtHelper::getAuthUser();
if ($user && !empty($user['id'])) {
    $verifiedBy = (int)$user['id'];
}

$ok = $voteModel->update((string)$input['id'], [
    'status' => $input['status'],
    'verified_by' => $verifiedBy,
    'verified_at' => date('Y-m-d H:i:s'),
]);

if ($ok) {
    Response::success(null, 'Status data suara berhasil diubah');
}

Response::error('Gagal memperbarui status');