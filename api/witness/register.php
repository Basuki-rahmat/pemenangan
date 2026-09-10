<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Models\TpsWitness;
use App\Models\Tps;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\GeoHelper;
use App\Helpers\Upload;
use App\Helpers\RateLimiter;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

if (!RateLimiter::check('witness_register', 30, 60)) {
    Response::error('Terlalu banyak permintaan. Coba lagi sebentar.', 429);
}

$input = Upload::input();

if (!$input) {
    Response::error('Data tidak valid');
}

// Validate required fields
$validator = new Validator();
$validator->required('tps_id', $input['tps_id'] ?? '')
          ->required('full_name', $input['full_name'] ?? '')
          ->required('nik', $input['nik'] ?? '')
          ->required('phone_number', $input['phone_number'] ?? '')
          ->required('latitude', $input['latitude'] ?? '')
          ->required('longitude', $input['longitude'] ?? '');

if (!$validator->isValid()) {
    Response::error('Validasi gagal', 422, $validator->getErrors());
}

// Validate NIK length
if (strlen($input['nik']) !== 16) {
    Response::error('NIK harus 16 digit', 422);
}

$tpsModel = new Tps();
$tps = $tpsModel->findById($input['tps_id']);

if (!$tps) {
    Response::error('TPS tidak ditemukan', 404);
}

// Check if TPS has coordinates
if (!$tps['latitude'] || !$tps['longitude']) {
    Response::error('Koordinat TPS belum diatur', 400);
}

// Validate distance using Haversine
$distance = GeoHelper::haversineDistance(
    (float)$input['latitude'],
    (float)$input['longitude'],
    (float)$tps['latitude'],
    (float)$tps['longitude']
);

if ($distance > 500) {
    Response::error(
        'Anda berada terlalu jauh dari TPS. Jarak: ' . GeoHelper::formatDistance($distance) . 
        '. Maksimal jarak yang diperbolehkan adalah 500 meter.',
        422
    );
}

// Check duplicate NIK
$witnessModel = new TpsWitness();
$existing = $witnessModel->findByNik($input['nik']);
if ($existing) {
    Response::error('NIK sudah terdaftar', 409);
}

// Upload foto KTP & selfie (multipart/form-data: $_FILES['photo_ktp'], $_FILES['photo_selfie'])
$photoKtp = Upload::image('photo_ktp', 'ktp');
if ($photoKtp['error']) {
    Response::error($photoKtp['error'], 422);
}

$photoSelfie = Upload::image('photo_selfie', 'selfie');
if ($photoSelfie['error']) {
    if ($photoKtp['path']) {
        @unlink(Upload::uploadRoot() . '/' . ltrim(substr($photoKtp['path'], strlen('uploads/')), '/'));
    }
    Response::error($photoSelfie['error'], 422);
}

// Create witness
$witnessId = $witnessModel->create([
    'tps_id' => $input['tps_id'],
    'full_name' => $input['full_name'],
    'nik' => $input['nik'],
    'phone_number' => $input['phone_number'],
    'photo_ktp_url' => $photoKtp['path'],
    'photo_selfie_url' => $photoSelfie['path'],
    'register_lat' => $input['latitude'],
    'register_long' => $input['longitude'],
    'register_accuracy' => $input['accuracy'] ?? null,
    'status' => 'pending',
]);

$witness = $witnessModel->findById($witnessId);

Response::success($witness, 'Registrasi saksi berhasil', 201);
