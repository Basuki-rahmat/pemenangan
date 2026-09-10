<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';

use App\Models\User;
use App\Models\PartySettings;
use App\Helpers\JwtHelper;

session_start();

// Redirect if already logged in
if (!empty($_SESSION['user'])) {
    header('Location: /pemenangan/admin/index.php');
    exit;
}

$error = '';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi';
    } else {
        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if (!$user || !$userModel->verifyPassword($password, $user['password'])) {
            $error = 'Username atau password salah';
        } elseif (!$user['is_active']) {
            $error = 'Akun tidak aktif';
        } else {
            $userModel->updateLastLogin((string)$user['id']);

            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'role' => $user['role'],
                'email' => $user['email'],
            ];

            // Token API untuk autentikasi admin endpoints
            $_SESSION['api_token'] = JwtHelper::generateToken([
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
            ]);

            header('Location: /pemenangan/admin/index.php');
            exit;
        }
    }
}

$partyModel = new PartySettings();
$cssVariables = $partyModel->getCssVariables();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Sipemenang Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style><?= $cssVariables ?></style>
</head>
<body class="min-h-screen flex items-center justify-center" style="background: linear-gradient(135deg, var(--party-primary, #1e40af), var(--party-secondary, #1e3a5f))">
    <div class="w-full max-w-md mx-4">
        <div class="bg-white rounded-xl shadow-xl p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold" style="color: var(--party-primary, #1e40af)">Sipemenang</h1>
                <p class="text-gray-500 mt-1">Panel Admin</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username atau Email</label>
                    <input type="text" name="username" required
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:border-transparent"
                           style="--tw-ring-color: var(--party-primary, #1e40af)"
                           placeholder="Masukkan username atau email">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:border-transparent"
                           style="--tw-ring-color: var(--party-primary, #1e40af)"
                           placeholder="Masukkan password">
                </div>
                <button type="submit"
                        class="w-full text-white font-semibold py-2.5 rounded-lg transition duration-200"
                        style="background-color: var(--party-primary, #1e40af)"
                        onmouseover="this.style.backgroundColor='var(--party-secondary, #1e3a5f)'"
                        onmouseout="this.style.backgroundColor='var(--party-primary, #1e40af)'">
                    Masuk
                </button>
            </form>
        </div>
    </div>
</body>
</html>