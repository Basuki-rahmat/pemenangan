<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Sipemenang',
    'env' => getenv('APP_ENV') ?: 'development',
    'url' => getenv('APP_URL') ?: 'http://localhost/pemenangan',
    'upload_path' => getenv('UPLOAD_PATH') ?: 'uploads',
    'max_file_size' => (int)(getenv('MAX_FILE_SIZE') ?: 5242880),
    'jwt_secret' => getenv('JWT_SECRET') ?: 'default-secret',
    'jwt_expiry' => (int)(getenv('JWT_EXPIRY') ?: 86400),
    'cors_origin' => getenv('CORS_ORIGIN') ?: '*',
];
