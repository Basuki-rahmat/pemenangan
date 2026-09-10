<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Models\PartySettings;
use App\Helpers\Response;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['id'])) {
    Response::error('ID partai wajib diisi');
}

$partyModel = new PartySettings();
$party = $partyModel->findById((string)$input['id']);

if (!$party) {
    Response::error('Partai tidak ditemukan', 404);
}

$partyModel->setActive((int)$input['id']);

Response::success(null, 'Tema berhasil diaktifkan');
