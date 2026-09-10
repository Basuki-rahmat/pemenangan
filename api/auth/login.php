<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\User;
use App\Helpers\Response;
use App\Helpers\JwtHelper;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    Response::error('Data tidak valid');
}

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    Response::error('Username dan password wajib diisi');
}

$userModel = new User();
$user = $userModel->findByUsername($username);

if (!$user || !$userModel->verifyPassword($password, $user['password'])) {
    Response::error('Username atau password salah', 401);
}

if (!$user['is_active']) {
    Response::error('Akun tidak aktif', 403);
}

$userModel->updateLastLogin((string)$user['id']);

$token = JwtHelper::generateToken([
    'id' => $user['id'],
    'username' => $user['username'],
    'role' => $user['role'],
]);

Response::success([
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ],
], 'Login berhasil');
