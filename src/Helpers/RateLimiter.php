<?php

declare(strict_types=1);

namespace App\Helpers;

class RateLimiter
{
    private static string $dir = '';

    private static function getDir(): string
    {
        if (self::$dir === '') {
            self::$dir = sys_get_temp_dir() . '/sipemenang_ratelimit';
            if (!is_dir(self::$dir)) {
                mkdir(self::$dir, 0770, true);
            }
        }
        return self::$dir;
    }

    private static function getIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function check(string $endpoint, int $maxAttempts = 10, int $windowSeconds = 60): bool
    {
        $key = md5($endpoint . '_' . self::getIp());
        $file = self::getDir() . '/' . $key;
        $now = time();

        $attempts = [];
        if (file_exists($file)) {
            $data = file_get_contents($file);
            $attempts = json_decode($data, true) ?? [];
        }

        // Remove expired entries
        $attempts = array_filter($attempts, fn($t) => $t > $now - $windowSeconds);

        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        $attempts[] = $now;
        file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);

        return true;
    }

    public static function remaining(string $endpoint, int $maxAttempts = 10, int $windowSeconds = 60): int
    {
        $key = md5($endpoint . '_' . self::getIp());
        $file = self::getDir() . '/' . $key;
        $now = time();

        $attempts = [];
        if (file_exists($file)) {
            $data = file_get_contents($file);
            $attempts = json_decode($data, true) ?? [];
        }

        $attempts = array_filter($attempts, fn($t) => $t > $now - $windowSeconds);
        return max(0, $maxAttempts - count($attempts));
    }
}