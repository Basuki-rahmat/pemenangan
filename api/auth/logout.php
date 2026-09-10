<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Database;
use App\Helpers\Response;
use App\Helpers\JwtHelper;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// JWT bersifat stateless — token invalid bila dihapus di sisi client.
// Endpoint ini ada untuk simetri REST & pencatatan aktivitas logout.
$user = JwtHelper::getAuthUser();

if ($user) {
    try {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
             VALUES (:user_id, 'logout', :desc, :ip, :ua)"
        );
        $stmt->execute([
            'user_id' => $user['id'],
            'desc' => 'User logout: ' . ($user['username'] ?? ''),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (\Throwable $e) {
        // Jangan gagalkan logout karena log; cukup abaikan jika tabel logging bermasalah.
    }
}

Response::success(null, 'Logout berhasil');