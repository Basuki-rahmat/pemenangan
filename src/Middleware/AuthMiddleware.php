<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\JwtHelper;
use App\Helpers\Response;

class AuthMiddleware
{
    public static function requireAuth(): array
    {
        $user = JwtHelper::getAuthUser();
        if (!$user || empty($user['id'])) {
            Response::error('Tidak terautentikasi', 401);
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireAuth();
        if (!in_array($user['role'] ?? '', ['admin', 'superadmin'], true)) {
            Response::error('Tidak memiliki akses', 403);
        }
        return $user;
    }

    public static function optionalAuth(): ?array
    {
        return JwtHelper::getAuthUser();
    }
}