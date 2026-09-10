<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Models\TpsWitness;
use App\Helpers\Response;
use App\Helpers\Upload;
use App\Middleware\AuthMiddleware;

header('Content-Type: application/json');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST' && ($_POST['_method'] ?? '') !== '') {
    $method = strtoupper($_POST['_method']);
}

$id = $_GET['id'] ?? '';

// ---------- Rute per-item: /api/witnesses/{id} ----------
if ($id !== '') {
    if ($id === '' || !ctype_digit((string)$id)) {
        Response::error('ID saksi tidak valid', 422);
    }

    // Autentikasi dulu berdasarkan method, sebelum memeriksa eksistensi record
    $user = null;
    if ($method === 'GET') {
        AuthMiddleware::requireAuth();
    } elseif ($method === 'PUT' || $method === 'POST' || $method === 'DELETE') {
        $user = AuthMiddleware::requireAdmin();
    } else {
        Response::error('Method not allowed', 405);
    }

    $witnessModel = new TpsWitness();
    $witness = $witnessModel->findById((string)$id);

    if (!$witness) {
        Response::error('Saksi tidak ditemukan', 404);
    }

    // DELETE /api/witnesses/{id}
    if ($method === 'DELETE') {
        foreach (['photo_ktp_url', 'photo_selfie_url'] as $field) {
            $path = (string)($witness[$field] ?? '');
            if ($path === '' || preg_match('~^https?://~i', $path)) {
                continue;
            }
            $rel = ltrim(substr($path, strlen('uploads/')), '/');
            if ($rel !== '') {
                $full = Upload::uploadRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                @unlink($full);
            }
        }

        $witnessModel->delete((string)$id);
        Response::success(null, 'Saksi berhasil dihapus');
    }

    // PUT /api/witnesses/{id}
    if ($method === 'PUT' || $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $data = [];
        foreach (['full_name', 'phone_number', 'notes'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string)$input[$field]);
            }
        }

        if (array_key_exists('status', $input)) {
            $status = (string)$input['status'];
            if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
                Response::error('Status tidak valid', 422);
            }
            $data['status'] = $status;
            if ($status !== 'pending') {
                $data['verified_by'] = $user['id'];
                $data['verified_at'] = date('Y-m-d H:i:s');
            } else {
                $data['verified_by'] = null;
                $data['verified_at'] = null;
            }
        }

        if (empty($data)) {
            Response::error('Tidak ada field yang diubah', 422);
        }

        $witnessModel->update((string)$id, $data);

        $updated = $witnessModel->findWithDetails((string)$id);
        $updated['photo_ktp_url'] = Upload::url((string)$updated['photo_ktp_url']);
        $updated['photo_selfie_url'] = Upload::url((string)$updated['photo_selfie_url']);
        Response::success($updated, 'Data saksi berhasil diubah');
    }

    // GET /api/witnesses/{id}
    if ($method === 'GET') {
        $detail = $witnessModel->findWithDetails((string)$id);
        $detail['photo_ktp_url'] = Upload::url((string)$detail['photo_ktp_url']);
        $detail['photo_selfie_url'] = Upload::url((string)$detail['photo_selfie_url']);
        Response::success($detail);
    }
}

// ---------- Rute koleksi: GET /api/witnesses ----------
if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

AuthMiddleware::requireAuth();

$status = $_GET['status'] ?? '';
if ($status !== '' && !in_array($status, ['pending', 'verified', 'rejected'], true)) {
    Response::error('Status tidak valid', 422);
}

$tpsId = $_GET['tps_id'] ?? '';
$q = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));

$filters = [];
if ($status !== '') {
    $filters['status'] = $status;
}
if ($tpsId !== '') {
    $filters['tps_id'] = $tpsId;
}
if ($q !== '') {
    $filters['q'] = $q;
}

$witnessModel = new TpsWitness();
$rows = $witnessModel->listWithDetails($filters, $perPage, ($page - 1) * $perPage);
$total = $witnessModel->countFiltered($filters);

foreach ($rows as &$row) {
    $row['photo_ktp_url'] = Upload::url((string)$row['photo_ktp_url']);
    $row['photo_selfie_url'] = Upload::url((string)$row['photo_selfie_url']);
}
unset($row);

Response::paginated($rows, $total, $page, $perPage);