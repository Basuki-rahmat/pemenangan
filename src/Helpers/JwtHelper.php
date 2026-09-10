<?php

declare(strict_types=1);

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    private static string $secret = '';
    private static int $expiry = 86400;

    public static function init(string $secret, int $expiry = 86400): void
    {
        self::$secret = $secret;
        self::$expiry = $expiry;
    }

    public static function generateToken(array $payload): string
    {
        $now = time();
        
        $data = [
            'iat' => $now,
            'exp' => $now + self::$expiry,
            'data' => $payload,
        ];

        return JWT::encode($data, self::$secret, 'HS256');
    }

    public static function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::$secret, 'HS256'));
            return (array)$decoded->data;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (preg_match('/Bearer\s+(.+)$/i', $auth, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function getAuthUser(): ?array
    {
        $token = self::getTokenFromHeader();
        if (!$token) {
            return null;
        }
        return self::validateToken($token);
    }
}
