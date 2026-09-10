<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user'])) {
    header('Location: /pemenangan/admin/login.php');
    exit;
}

// Role check: only admin and superadmin allowed
$role = $_SESSION['user']['role'] ?? '';
if (!in_array($role, ['admin', 'superadmin'], true)) {
    session_destroy();
    header('Location: /pemenangan/admin/login.php?error=1');
    exit;
}