<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'API is working!',
    'server' => phpversion(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'request_uri' => $_SERVER['REQUEST_URI']
]);
