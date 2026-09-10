<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Models\VoteResult;
use App\Models\TpsWitness;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\JwtHelper;
use App\Helpers\GeoHelper;
use App\Helpers\Upload;
use App\Helpers\RateLimiter;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

if (!RateLimiter::check('votes_submit', 30, 60)) {
    Response::error('Terlalu banyak permintaan. Coba lagi sebentar.', 429);
}

// Require authentication
$user = JwtHelper::getAuthUser();
if (!$user) {
    Response::error('Unauthorized', 401);
}

$input = Upload::input();

if (empty($input) && empty($_FILES)) {
    Response::error('Data tidak valid');
}

// Validate required fields
$validator = new Validator();
$validator->required('tps_id', $input['tps_id'] ?? '')
          ->required('witness_id', $input['witness_id'] ?? '')
          ->required('candidate_votes', $input['candidate_votes'] ?? '')
          ->required('c1_photo', $_FILES['c1_photo']['name'] ?? '')
          ->required('latitude', $input['latitude'] ?? '')
          ->required('longitude', $input['longitude'] ?? '');

if (!$validator->isValid()) {
    Response::error('Validasi gagal', 422, $validator->getErrors());
}

// Verify witness is verified
$witnessModel = new TpsWitness();
$witness = $witnessModel->findById($input['witness_id']);

if (!$witness) {
    Response::error('Saksi tidak ditemukan', 404);
}

if ($witness['status'] !== 'verified') {
    Response::error('Saksi belum terverifikasi', 403);
}

// Validate distance
$tpsModel = new \App\Models\Tps();
$tps = $tpsModel->findById($input['tps_id']);

if ($tps && $tps['latitude'] && $tps['longitude']) {
    $distance = GeoHelper::haversineDistance(
        (float)$input['latitude'],
        (float)$input['longitude'],
        (float)$tps['latitude'],
        (float)$tps['longitude']
    );

    if ($distance > 500) {
        Response::error(
            'Anda berada terlalu jauh dari TPS. Jarak: ' . GeoHelper::formatDistance($distance),
            422
        );
    }
}

// Calculate total votes
$candidateVotes = $input['candidate_votes'];
$totalCandidateVotes = array_sum($candidateVotes);
$invalidVotes = (int)($input['invalid_votes'] ?? 0);
$totalVotes = $totalCandidateVotes + $invalidVotes;

// Upload foto C1 Plano (multipart/form-data: $_FILES['c1_photo'])
$photoC1 = Upload::image('c1_photo', 'c1');
if ($photoC1['error']) {
    Response::error($photoC1['error'], 422);
}

// Create vote result
$voteModel = new VoteResult();
$voteId = $voteModel->create([
    'tps_id' => $input['tps_id'],
    'witness_id' => $input['witness_id'],
    'candidate_votes' => json_encode($candidateVotes),
    'invalid_votes' => $invalidVotes,
    'total_votes' => $totalVotes,
    'c1_photo_url' => $photoC1['path'],
    'submit_lat' => $input['latitude'],
    'submit_long' => $input['longitude'],
    'submit_accuracy' => $input['accuracy'] ?? null,
    'status' => 'pending',
]);

$vote = $voteModel->findById($voteId);

Response::success($vote, 'Hasil suara berhasil dikirim', 201);
