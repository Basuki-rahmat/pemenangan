<?php

declare(strict_types=1);

namespace App\Helpers;

class Upload
{
    /**
     * Ambil input request: mendukung JSON body maupun multipart/form-data.
     */
    public static function input(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            return is_array($data) ? $data : [];
        }

        return $_POST;
    }

    /**
     * Base URL aplikasi (tanpa trailing slash). Dipakai untuk mengubah path
     * upload menjadi URL absolut agar bisa ditampilkan di halaman admin.
     */
    public static function baseUrl(): string
    {
        return rtrim((string)(getenv('APP_URL') ?: ''), '/');
    }

    /**
     * Ubah path upload relatif menjadi URL absolut (atau biarkan jika sudah http).
     */
    public static function url(string $path): string
    {
        if ($path === '' || preg_match('~^https?://~i', $path)) {
            return $path;
        }
        return self::baseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * Direktori absolut tempat file upload disimpan.
     * Config UPLOAD_PATH bersifat relatif terhadap root proyek.
     */
    public static function uploadRoot(): string
    {
        $configured = trim((string)(getenv('UPLOAD_PATH') ?: 'uploads'), "\\/");
        return rtrim(__DIR__ . '/../..', "\\/") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $configured);
    }

    /**
     * Proses upload file gambar dari $_FILES.
     *
     * @return array{error: ?string, path: ?string}
     */
    public static function image(string $field, string $subdir): array
    {
        $maxBytes = (int)(getenv('MAX_FILE_SIZE') ?: 5242880);

        if (!isset($_FILES[$field])) {
            return ['error' => "File {$field} tidak ditemukan", 'path' => null];
        }

        $file = $_FILES[$field];

        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['error' => "File {$field} wajib diunggah", 'path' => null];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => "Upload {$field} gagal (kode error " . $file['error'] . ')', 'path' => null];
        }
        if ($file['size'] > $maxBytes) {
            return ['error' => "File {$field} melebihi batas " . round($maxBytes / 1048576, 1) . 'MB', 'path' => null];
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['error' => "File {$field} bukan gambar yang valid", 'path' => null];
        }

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$info['mime']])) {
            return ['error' => "Format gambar {$field} tidak didukung (jpg/png/webp saja)", 'path' => null];
        }

        $subdir = trim($subdir, '/');
        $dir = self::uploadRoot() . DIRECTORY_SEPARATOR . $subdir;

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['error' => 'Gagal membuat direktori upload', 'path' => null];
        }

        $name = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
        $dest = $dir . DIRECTORY_SEPARATOR . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return ['error' => "Gagal menyimpan file {$field}", 'path' => null];
        }

        return ['error' => null, 'path' => 'uploads/' . $subdir . '/' . $name];
    }
}