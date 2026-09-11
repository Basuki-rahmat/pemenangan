<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_check.php';

use App\Models\PartySettings;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

$db = Database::getConnection();

// Export CSV
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    
    header('Content-Type: text/csv; charset=utf-8');
    
    if ($type === 'witnesses') {
        header('Content-Disposition: attachment; filename="saksi_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF"; // BOM for Excel
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Nama', 'NIK', 'No HP', 'Provinsi', 'Kabupaten', 'Kecamatan', 'Desa', 'TPS', 'Status', 'Waktu Daftar']);
        $rows = $db->query("SELECT w.*, t.tps_number, v.name AS village_name, d.name AS district_name, r.name AS regency_name, p.name AS province_name
            FROM tps_witnesses w
            LEFT JOIN tps t ON w.tps_id = t.id
            LEFT JOIN villages v ON t.village_id = v.id
            LEFT JOIN districts d ON v.district_id = d.id
            LEFT JOIN regencies r ON d.regency_id = r.id
            LEFT JOIN provinces p ON r.province_id = p.id
            ORDER BY w.created_at DESC")->fetchAll();
        foreach ($rows as $i => $w) {
            fputcsv($out, [$i + 1, $w['full_name'], $w['nik'], $w['phone_number'], $w['province_name'] ?? '', $w['regency_name'] ?? '', $w['district_name'] ?? '', $w['village_name'] ?? '', $w['tps_number'] ?? '', $w['status'], date('Y-m-d H:i', strtotime($w['created_at']))]);
        }
        fclose($out);
        exit;
    }
    
    if ($type === 'votes') {
        header('Content-Disposition: attachment; filename="hasilsuara_' . date('Ymd_His') . '.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['No', 'Kabupaten', 'Kecamatan', 'Desa', 'TPS', 'Saksi', 'Detail Suara', 'Suara Tidak Sah', 'Total', 'Status', 'Waktu Input']);
        $rows = $db->query("SELECT vr.*, w.full_name AS witness_name, t.tps_number, v.name AS village_name, d.name AS district_name, r.name AS regency_name
            FROM vote_results vr
            LEFT JOIN tps_witnesses w ON vr.witness_id = w.id
            LEFT JOIN tps t ON vr.tps_id = t.id
            LEFT JOIN villages v ON t.village_id = v.id
            LEFT JOIN districts d ON v.district_id = d.id
            LEFT JOIN regencies r ON d.regency_id = r.id
            ORDER BY vr.created_at DESC")->fetchAll();
        foreach ($rows as $i => $v) {
            $candidateVotes = json_decode($v['candidate_votes'], true) ?? [];
            $detail = implode('; ', array_map(function ($c) {
                return ($c['name'] ?? '') . ': ' . ($c['votes'] ?? 0);
            }, $candidateVotes));
            fputcsv($out, [$i + 1, $v['regency_name'] ?? '', $v['district_name'] ?? '', $v['village_name'] ?? '', $v['tps_number'] ?? '', $v['witness_name'] ?? '', $detail, $v['invalid_votes'], $v['total_votes'], $v['status'], date('Y-m-d H:i', strtotime($v['created_at']))]);
        }
        fclose($out);
        exit;
    }
}

// Summary per district
$districtSummary = $db->query("SELECT 
        d.id, d.name AS district_name,
        COUNT(DISTINCT t.id) AS total_tps,
        COUNT(DISTINCT CASE WHEN w.id IS NOT NULL THEN w.id END) AS total_witnesses,
        COUNT(DISTINCT CASE WHEN w.status = 'verified' THEN w.id END) AS verified_witnesses,
        COUNT(DISTINCT CASE WHEN vr.status = 'verified' THEN vr.tps_id END) AS votes_in
    FROM districts d
    LEFT JOIN villages v ON v.district_id = d.id
    LEFT JOIN tps t ON t.village_id = v.id
    LEFT JOIN tps_witnesses w ON w.tps_id = t.id
    LEFT JOIN vote_results vr ON vr.tps_id = t.id AND vr.status = 'verified'
    GROUP BY d.id, d.name
    ORDER BY d.name")->fetchAll();

// Vote ranking per candidate
$voteRows = $db->query("SELECT candidate_votes FROM vote_results WHERE status = 'verified'")->fetchAll();
$candidateTotals = [];
foreach ($voteRows as $row) {
    $candidateVotes = json_decode($row['candidate_votes'], true) ?? [];
    foreach ($candidateVotes as $candidate) {
        $name = $candidate['name'] ?? $candidate['candidate_name'] ?? 'Kandidat';
        $votes = (int)($candidate['votes'] ?? $candidate['total_votes'] ?? 0);
        if (!isset($candidateTotals[$name])) {
            $candidateTotals[$name] = ['votes' => 0, 'tps' => 0];
        }
        $candidateTotals[$name]['votes'] += $votes;
        $candidateTotals[$name]['tps']++;
    }
}
arsort($candidateTotals);

$totalDistricts = count($districtSummary);
$totalVotesIn = 0;
foreach ($districtSummary as $s) {
    $totalVotesIn += (int)$s['votes_in'];
}
$totalAllVotes = 0;
foreach ($candidateTotals as $c) {
    $totalAllVotes += $c['votes'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Admin SIPEMENANG</title>
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
    <!-- Mobile Overlay -->
    <div id="overlay" class="hidden fixed inset-0 bg-black/50 z-40 lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
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
            <a href="/pemenangan/admin/recap.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗂️ Rekap Berjenjang</a>
            <a href="/pemenangan/admin/attendance.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📅 Kehadiran Saksi</a>
            <a href="/pemenangan/admin/funds.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">💰 Dana Saksi</a>
            <a href="/pemenangan/admin/regions.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗺️ Data Wilayah</a>
            <a href="/pemenangan/admin/theme.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🎨 Theme Settings</a>
            <a href="/pemenangan/admin/reports.php" class="block px-4 py-3 bg-white/10 rounded-lg">📄 Laporan</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <header class="bg-white shadow-sm p-6 flex items-center gap-4">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">📄 Laporan</h1>
                <p class="text-gray-600">Ringkasan per kecamatan, perolehan suara, dan unduh data</p>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <!-- Export Buttons -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-gray-700 mb-4">📥 Export Data</h2>
                <div class="flex flex-wrap gap-3">
                    <a href="/pemenangan/admin/reports.php?export=witnesses" class="btn-party px-4 py-2 rounded-lg text-sm font-medium">⬇️ Export Saksi (CSV)</a>
                    <a href="/pemenangan/admin/reports.php?export=votes" class="bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-600">⬇️ Export Hasil Suara (CSV)</a>
                </div>
            </div>

            <!-- Ranking -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="font-semibold text-gray-700 mb-4">🏆 Perolehan Suara Sementara (Real Count)</h2>
                <?php if (empty($candidateTotals)): ?>
                    <p class="text-gray-500 text-center py-6">Belum ada data suara terverifikasi.</p>
                <?php else: ?>
                    <?php $rank = 1; $maxVotes = max(array_column($candidateTotals, 'votes')); ?>
                    <div class="space-y-4">
                        <?php foreach ($candidateTotals as $name => $data): ?>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="bg-party-primary text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"><?= $rank ?></span>
                                        <span class="font-medium text-gray-800"><?= htmlspecialchars($name) ?></span>
                                    </div>
                                    <div class="text-gray-600">
                                        <span class="font-bold text-gray-900"><?= number_format($data['votes']) ?></span>
                                        <span class="text-xs text-gray-400 ml-2">(<?= $data['tps'] ?> TPS, <?= $maxVotes > 0 ? round(($data['votes'] / $maxVotes) * 100) : 0 ?>%)</span>
                                    </div>
                                </div>
                                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-party-primary rounded-full" style="width: <?= $maxVotes > 0 ? ($data['votes'] / $maxVotes) * 100 : 0 ?>%"></div>
                                </div>
                            </div>
                            <?php $rank++; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Summary per District -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h2 class="font-semibold text-gray-700">📊 Ringkasan per Kecamatan</h2>
                    <span class="text-xs text-gray-500"><?= $totalDistricts ?> kecamatan • <?= number_format($totalVotesIn) ?> TPS data masuk</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kecamatan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TPS</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saksi Terdaftar</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saksi Verified</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Masuk</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coverage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($districtSummary as $s): 
                                $coverage = $s['total_tps'] > 0 ? round(((int)$s['votes_in'] / (int)$s['total_tps']) * 100) : 0;
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($s['district_name']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= number_format((int)$s['total_tps']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= number_format((int)$s['total_witnesses']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= number_format((int)$s['verified_witnesses']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= number_format((int)$s['votes_in']) ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-20 bg-gray-100 rounded-full overflow-hidden">
                                                <div class="h-full <?= $coverage >= 80 ? 'bg-green-500' : ($coverage >= 40 ? 'bg-yellow-500' : 'bg-red-500') ?> rounded-full" style="width: <?= $coverage ?>%"></div>
                                            </div>
                                            <span class="text-xs text-gray-500"><?= $coverage ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (window.innerWidth < 1024) {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            }
        }
    </script>
</body>
</html>