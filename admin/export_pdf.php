<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/region_helpers.php';

use App\Models\PartySettings;
use App\Models\VoteResult;
use App\Database;
use Dompdf\Dompdf;
use Dompdf\Options;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();

$db = Database::getConnection();
$voteModel = new VoteResult();

$level = $_GET['level'] ?? 'village';
if (!in_array($level, ['village', 'district', 'regency', 'province'], true)) {
    http_response_code(400);
    exit('Level tidak valid.');
}
$id = $_GET['id'] ?? '';
if ($id === '') {
    http_response_code(400);
    exit('Parameter id wajib diisi.');
}

$child = levelChildOf($level);
$chain = regionChain($db, $level, $id);
$unit = end($chain);
if (!$unit) {
    http_response_code(404);
    exit('Wilayah tidak ditemukan.');
}

$recap = $voteModel->recapByLevel($child, $id);
$groups = $recap['groups'];
$candidates = $recap['candidates'];
$totals = $recap['totals'];

$crumb = implode(' » ', array_map(fn($c) => $c['name'], $chain));

// Build HTML
$candidateHeader = '';
foreach ($candidates as $c) {
    $candidateHeader .= '<th class="num">' . htmlspecialchars((string)$c['name']) . '</th>';
}

$rows = '';
if ($groups) {
    foreach ($groups as $i => $g) {
        $candMap = array_column($g['candidates'], 'votes', 'name');
        $candCells = '';
        foreach ($candidates as $c) {
            $candCells .= '<td class="num">' . number_format($candMap[$c['name']] ?? 0) . '</td>';
        }
        $sub = $g['sub'] !== '' ? '<div class="sub">' . htmlspecialchars((string)$g['sub']) . '</div>' : '';
        $rows .= '<tr>'
            . '<td class="num">' . ($i + 1) . '</td>'
            . '<td>' . htmlspecialchars((string)$g['name']) . $sub . '</td>'
            . '<td class="num">' . $g['votes_in'] . '/' . $g['tps_total'] . '</td>'
            . '<td class="num">' . number_format($g['dpt']) . '</td>'
            . $candCells
            . '<td class="num strong">' . number_format($g['sah']) . '</td>'
            . '<td class="num">' . number_format($g['invalid']) . '</td>'
            . '</tr>';
    }

    $candTotals = '';
    foreach ($candidates as $c) {
        $candTotals .= '<td class="num strong">' . number_format($c['votes']) . '</td>';
    }
    $rows .= '<tr class="total">'
        . '<td class="num" colspan="4">TOTAL</td>'
        . $candTotals
        . '<td class="num strong">' . number_format($totals['sah']) . '</td>'
        . '<td class="num">' . number_format($totals['invalid']) . '</td>'
        . '</tr>';
} else {
    $rows = '<tr><td colspan="100" class="empty">Belum ada data suara terverifikasi.</td></tr>';
}

$colspan = 4 + count($candidates) + 2;

$html = '<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Rekap ' . levelLabelOf($level) . ' - SIPEMENANG</title>
<style>
    * { font-family: Helvetica, Arial, sans-serif; }
    body { color: #111; font-size: 11px; }
    .brand { font-size: 20px; font-weight: bold; color: var(--primary-color, #b91c1c); margin-bottom: 2px; }
    .title { font-size: 14px; font-weight: bold; margin-top: 6px; }
    .crumb { color: #555; font-size: 10px; margin: 4px 0 12px; }
    .meta { font-size: 10px; color: #333; margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f1f1f1; border: 1px solid #999; padding: 5px 6px; text-align: left; font-size: 10px; }
    td { border: 1px solid #bbb; padding: 4px 6px; }
    .num { text-align: right; }
    .strong { font-weight: bold; }
    .sub { color: #666; font-size: 9px; }
    tr.total td { background: #f7f7f7; font-weight: bold; }
    .empty { text-align: center; padding: 20px; color: #666; }
    .footer { margin-top: 22px; font-size: 9px; color: #777; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
</style>
</head>
<body>
    <div class="brand">🗳️ SIPEMENANG</div>
    <div class="title">REKAPITULASI PEROLEHAN SUARA — ' . levelLabelOf($level) . '</div>
    <div class="crumb">' . htmlspecialchars($crumb) . '</div>
    <div class="meta">Dicetak: ' . date('d-m-Y H:i') . ' · Sumber: data verified per TPS · ' . ($activeParty['party_name'] ?? '') . '</div>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>' . levelLabelOf($child) . '</th>
                <th class="num">TPS Terisi</th>
                <th class="num">DPT</th>
                ' . $candidateHeader . '
                <th class="num">Suara Sah</th>
                <th class="num">Tidak Sah</th>
            </tr>
        </thead>
        <tbody>' . $rows . '</tbody>
    </table>
    <div class="footer">Dokumen dihasilkan otomatis oleh Sistem Informasi Pemenangan Digital (SIPEMENANG) pada ' . date('d-m-Y H:i') . '.</div>
</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('isPhpEnabled', false);
$options->set('defaultFont', 'Helvetica');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream(
    'rekap_' . strtolower($level) . '_' . preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string)$unit['name'])) . '_' . date('Ymd_His') . '.pdf',
    ['Attachment' => true]
);