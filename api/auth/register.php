<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Models\User;
use App\Database;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\JwtHelper;
use App\Helpers\RateLimiter;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

if (!RateLimiter::check('auth_register', 5, 60)) {
    Response::error('Terlalu banyak percobaan registrasi. Coba lagi dalam 1 menit.', 429);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    Response::error('Data tidak valid');
}

// Validate required fields
$validator = new Validator();
$validator->required('username', $input['username'] ?? '')
          ->required('email', $input['email'] ?? '')
          ->required('password', $input['password'] ?? '')
          ->required('full_name', $input['full_name'] ?? '')
          ->minLength('username', $input['username'] ?? '', 3)
          ->minLength('password', $input['password'] ?? '', 6)
          ->email('email', $input['email'] ?? '');

if (!$validator->isValid()) {
    Response::error('Validasi gagal', 422, $validator->getErrors());
}

$username = trim($input['username']);
$email = trim($input['email']);

// Cek username/email unik
$userModel = new User();
if ($userModel->findByUsername($username)) {
    Response::error('Username sudah digunakan', 409);
}

// Cek email unik (findByUsername tidak menangkap email berbeda)
$stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
$stmt->execute(['email' => $email]);
if ((int)$stmt->fetchColumn() > 0) {
    Response::error('Email sudah terdaftar', 409);
}

// Role default: operator (hindari privilege escalation via API publik)
$role = 'operator';

$userId = $userModel->create([
    'username' => $username,
    'email' => $email,
    'password' => $input['password'],
    'full_name' => trim($input['full_name']),
    'role' => $role,
    'is_active' => 1,
]);

$user = $userModel->findById($userId);

// Langsung login setelah register
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
], 'Registrasi berhasil', 201);