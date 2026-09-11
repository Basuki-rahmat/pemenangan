<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/region_helpers.php';

use App\Models\PartySettings;
use App\Models\VoteResult;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

$db = Database::getConnection();
$voteModel = new VoteResult();

// Level yang sedang ditampilkan (list = child dari unit induk)
$level = $_GET['level'] ?? 'province';
if (!in_array($level, ['province', 'regency', 'district', 'village', 'tps'], true)) {
    $level = 'province';
}
$parentId = $_GET['id'] ?? '';

$recap = $voteModel->recapByLevel($level, $parentId === '' ? null : $parentId);
$groups = $recap['groups'];
$totals = $recap['totals'];
$grandCandidates = $recap['candidates'];

// Rantai breadcrumb
if ($level === 'province' && $parentId === '') {
    $chain = [['level' => 'province', 'id' => '', 'name' => 'Semua Provinsi']];
} else {
    $unitLevel = $parentId === '' ? $level : levelParentOf($level);
    $chain = regionChain($db, $unitLevel, $parentId === '' ? '' : $parentId);
    if ($parentId !== '') {
        $chain[] = ['level' => $level, 'id' => $parentId, 'name' => levelLabelOf($level) . ' »'];
    } else {
        $chain = [['level' => $level, 'id' => '', 'name' => 'Semua ' . levelLabelOf($level)]];
    }
}

// Export CSV current listing
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekap_' . $level . '_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    $header = ['No', levelLabelOf($level), 'Wilayah Induk', 'TPS Terisi', 'DPT', 'Suara Sah', 'Suara Tidak Sah'];
    foreach ($grandCandidates as $c) {
        $header[] = 'Sah ' . $c['name'];
    }
    $header[] = 'Total Suara';
    fputcsv($out, $header);

    foreach ($groups as $i => $g) {
        $row = [$i + 1, $g['name'], $g['sub'], $g['votes_in'] . '/' . $g['tps_total'], $g['dpt'], $g['sah'], $g['invalid']];
        $candMap = [];
        foreach ($g['candidates'] as $c) {
            $candMap[$c['name']] = $c['votes'];
        }
        foreach ($grandCandidates as $c) {
            $row[] = $candMap[$c['name']] ?? 0;
        }
        $row[] = array_sum(array_column($g['candidates'], 'votes'));
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$childLevel = levelChildOf($level);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Berjenjang - Admin SIPEMANANG</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--primary-color)',
                        secondary: 'var(--secondary-color)',
                    }
                }
            }
        }
    </script>
    <style>
        <?= $cssVariables ?>
        .bg-party-primary { background-color: var(--primary-color); }
        .text-party-primary { color: var(--primary-color); }
        .btn-party { background-color: var(--primary-color); color: #ffffff; }
        .btn-party:hover { filter: brightness(0.9); }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div id="overlay" class="hidden fixed inset-0 bg-black/50 z-40 lg:hidden" onclick="toggleSidebar()"></div>

    <aside id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-party-primary text-white transform lg:translate-x-0 -translate-x-full transition-transform z-50">
        <div class="p-6 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold">🗳️ SIPEMENANG</h2>
                <p class="text-sm text-white/70 mt-1">Admin Panel</p>
            </div>
            <button onclick="toggleSidebar()" class="lg:hidden text-white/80 hover:text-white text-xl">✕</button>
        </div>
        <nav class="px-4 space-y-2">
            <a href="/pemenangan/admin/" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📊 Dashboard</a>
            <a href="/pemenangan/admin/witnesses.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">👥 Manajemen Saksi</a>
            <a href="/pemenangan/admin/votes.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗳️ Hasil Suara</a>
            <a href="/pemenangan/admin/recap.php" class="block px-4 py-3 bg-white/10 rounded-lg">🗂️ Rekap Berjenjang</a>
            <a href="/pemenangan/admin/attendance.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📅 Kehadiran Saksi</a>
            <a href="/pemenangan/admin/funds.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">💰 Dana Saksi</a>
            <a href="/pemenangan/admin/regions.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗺️ Data Wilayah</a>
            <a href="/pemenangan/admin/theme.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🎨 Theme Settings</a>
            <a href="/pemenangan/admin/reports.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📄 Laporan</a>
        </nav>
    </aside>

    <div class="lg:ml-64 min-h-screen">
        <header class="bg-white shadow-sm p-6 flex items-center gap-4">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-800">🗂️ Rekapitulasi Berjenjang</h1>
                <p class="text-gray-600">Perolehan suara verified per <?= levelLabelOf($level) ?></p>
            </div>
            <a href="?export=csv&level=<?= htmlspecialchars($level) ?>&id=<?= htmlspecialchars($parentId) ?>" class="btn-party px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap">⬇️ Export CSV</a>
        </header>

        <div class="p-6 space-y-6">
            <!-- Breadcrumb -->
            <nav class="flex flex-wrap items-center gap-1 text-sm text-gray-600 bg-white rounded-xl shadow-sm px-4 py-3">
                <?php foreach ($chain as $i => $c): ?>
                    <?php if ($i > 0): ?><span class="text-gray-400">/</span><?php endif; ?>
                    <?php if ($c['id'] !== '' && $childLevel !== ''): ?>
                        <a href="?level=<?= $childLevel ?>&id=<?= urlencode($c['id']) ?>" class="text-primary hover:underline font-medium"><?= htmlspecialchars($c['name']) ?></a>
                    <?php else: ?>
                        <span class="font-medium"><?= htmlspecialchars($c['name']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                    <div class="text-2xl font-bold text-blue-600"><?= number_format($totals['votes_in']) ?>/<?= number_format($totals['tps_total']) ?></div>
                    <div class="text-xs text-gray-500">TPS Terisi / Total (level ini)</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                    <div class="text-2xl font-bold text-green-600"><?= number_format($totals['sah'] ?? ($totals['total_votes'] - $totals['invalid'])) ?></div>
                    <div class="text-xs text-gray-500">Total Suara Sah</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                    <div class="text-2xl font-bold text-red-600"><?= number_format($totals['invalid']) ?></div>
                    <div class="text-xs text-gray-500">Suara Tidak Sah</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-gray-400">
                    <div class="text-2xl font-bold text-gray-700"><?= number_format($totals['dpt']) ?></div>
                    <div class="text-xs text-gray-500">DPT (level ini)</div>
                </div>
            </div>

            <!-- Per-Kandidat Strip -->
            <?php if ($grandCandidates): ?>
                <div class="bg-white rounded-xl shadow-sm p-5">
                    <h2 class="font-semibold text-gray-700 mb-3">Perolehan per Kandidat</h2>
                    <div class="grid md:grid-cols-<?= min(4, max(1, count($grandCandidates))) ?> gap-4">
                        <?php $lead = ($grandCandidates[0]['votes'] ?? 1) ?: 1; ?>
                        <?php foreach ($grandCandidates as $c): ?>
                            <div>
                                <div class="flex items-center justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700"><?= htmlspecialchars($c['name']) ?></span>
                                    <span class="font-bold text-gray-800"><?= number_format($c['votes']) ?></span>
                                </div>
                                <div class="h-2 bg-gray-100 rounded overflow-hidden">
                                    <?php $pct = $lead > 0 ? round($c['votes'] / $lead * 100) : 0; ?>
                                    <div class="h-full rounded" style="width:<?= max(2, $pct) ?>%; background:var(--primary-color)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 border-b">
                            <th class="text-left px-4 py-3">No</th>
                            <th class="text-left px-4 py-3"><?= levelLabelOf($level) ?></th>
                            <th class="text-left px-4 py-3 hidden md:table-cell">Induk</th>
                            <th class="text-center px-4 py-3">TPS Terisi</th>
                            <?php foreach ($grandCandidates as $c): ?>
                                <th class="text-right px-4 py-3"><?= htmlspecialchars($c['name']) ?></th>
                            <?php endforeach; ?>
                            <th class="text-right px-4 py-3">Sah</th>
                            <th class="text-right px-4 py-3">Tidak Sah</th>
                            <th class="text-center px-4 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$groups): ?>
                            <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">Belum ada data suara terverifikasi.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($groups as $i => $g): ?>
                            <?php $candMap = array_column($g['candidates'], 'votes', 'name'); ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-500"><?= $i + 1 ?></td>
                                <td class="px-4 py-3 font-semibold text-gray-800">
                                    <?= htmlspecialchars($g['name']) ?>
                                    <div class="text-xs text-gray-400 font-normal">DPT <?= number_format($g['dpt']) ?></div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 hidden md:table-cell"><?= htmlspecialchars($g['sub']) ?></td>
                                <td class="px-4 py-3 text-center"><?= number_format($g['votes_in']) ?>/<?= number_format($g['tps_total']) ?></td>
                                <?php foreach ($grandCandidates as $c): ?>
                                    <td class="px-4 py-3 text-right font-medium"><?= number_format($candMap[$c['name']] ?? 0) ?></td>
                                <?php endforeach; ?>
                                <td class="px-4 py-3 text-right font-semibold text-green-600"><?= number_format($g['sah']) ?></td>
                                <td class="px-4 py-3 text-right text-red-600"><?= number_format($g['invalid']) ?></td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <?php if ($childLevel !== ''): ?>
                                        <a href="?level=<?= $childLevel ?>&id=<?= urlencode($g['id']) ?>" class="text-primary hover:underline font-medium">Detail</a>
                                    <?php endif; ?>
                                    <?php if ($level !== 'tps'): ?>
                                        <a href="/pemenangan/admin/export_pdf.php?level=<?= $level ?>&id=<?= urlencode($g['id']) ?>" class="text-gray-500 hover:text-red-600 ml-2" title="Export PDF <?= levelLabelOf($level) ?>">📄</a>
                                    <?php else: ?>
                                        <a href="votes.php?tps_id=<?= urlencode($g['id']) ?>" class="text-gray-500 hover:text-primary ml-2" title="Lihat hasil suara TPS">🔎</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>