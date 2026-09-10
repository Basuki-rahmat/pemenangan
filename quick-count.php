<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\PartySettings;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

$db = Database::getConnection();

// Stats
$tpsCount = (int)$db->query("SELECT COUNT(*) FROM tps")->fetchColumn();
$verifiedVotes = (int)$db->query("SELECT COUNT(*) FROM vote_results WHERE status = 'verified'")->fetchColumn();
$progress = $tpsCount > 0 ? round(($verifiedVotes / $tpsCount) * 100) : 0;

// Vote ranking (verified only)
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
$maxVotes = !empty($candidateTotals) ? max(array_column($candidateTotals, 'votes')) : 0;
$totalAllVotes = 0;
foreach ($candidateTotals as $c) { $totalAllVotes += $c['votes']; }

// District summary (verified votes only)
$districtSummary = $db->query("SELECT 
        d.name AS district_name,
        COUNT(DISTINCT t.id) AS total_tps,
        COUNT(DISTINCT vr.tps_id) AS votes_in
    FROM districts d
    LEFT JOIN villages v ON v.district_id = d.id
    LEFT JOIN tps t ON t.village_id = v.id
    LEFT JOIN vote_results vr ON vr.tps_id = t.id AND vr.status = 'verified'
    GROUP BY d.id, d.name
    ORDER BY votes_in DESC, d.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Real Count - SIPEMENANG</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--primary-color)',
                        secondary: 'var(--secondary-color)',
                        accent: 'var(--accent-color)',
                    }
                }
            }
        }
    </script>
    <style>
        <?= $cssVariables ?>
        .bg-party-primary { background-color: var(--primary-color); }
        .text-party-primary { color: var(--primary-color); }
        .gradient-party { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-party-primary shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-4">
                    <button onclick="toggleMobileMenu()" class="md:hidden text-white p-2 rounded-lg hover:bg-white/10">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="flex-shrink-0 flex items-center">
                        <a href="/pemenangan/" class="text-white text-xl font-bold">🗳️ SIPEMENANG</a>
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-4">
                        <a href="/pemenangan/" class="text-white/80 hover:bg-white/10 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="/pemenangan/register.php" class="text-white/80 hover:bg-white/10 px-3 py-2 rounded-md text-sm font-medium">Registrasi Saksi</a>
                        <a href="/pemenangan/quick-count.php" class="text-white hover:bg-white/10 px-3 py-2 rounded-md text-sm font-medium">Real Count</a>
                        <a href="/pemenangan/admin/" class="text-white/80 hover:bg-white/10 px-3 py-2 rounded-md text-sm font-medium">Admin</a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($activeParty): ?>
                    <span class="text-white/80 text-sm"><?= htmlspecialchars($activeParty['party_name']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div id="mobile-menu" class="hidden md:hidden bg-party-primary text-white">
        <div class="px-4 py-3 space-y-1">
            <a href="/pemenangan/" class="block px-3 py-2 rounded-md text-sm font-medium hover:bg-white/10">Dashboard</a>
            <a href="/pemenangan/register.php" class="block px-3 py-2 rounded-md text-sm font-medium hover:bg-white/10">Registrasi Saksi</a>
            <a href="/pemenangan/quick-count.php" class="block px-3 py-2 rounded-md text-sm font-medium bg-white/10">Real Count</a>
            <a href="/pemenangan/admin/" class="block px-3 py-2 rounded-md text-sm font-medium hover:bg-white/10">Admin</a>
        </div>
    </div>

    <!-- Hero -->
    <div class="gradient-party text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">📊 Real Count</h1>
                    <p class="text-white/90">Perolehan suara sementara dari seluruh TPS</p>
                </div>
                <div class="mt-6 md:mt-0 flex items-center gap-4">
                    <div class="bg-white/20 backdrop-blur rounded-lg px-6 py-3">
                        <div class="text-3xl font-bold"><?= $progress ?>%</div>
                        <div class="text-sm text-white/80">Progress</div>
                    </div>
                    <div class="bg-white/20 backdrop-blur rounded-lg px-6 py-3">
                        <div class="text-3xl font-bold"><?= number_format($verifiedVotes) ?><span class="text-lg font-normal">/<?= number_format($tpsCount) ?></span></div>
                        <div class="text-sm text-white/80">TPS Terverifikasi</div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="mt-8">
                <div class="flex justify-between text-xs text-white/80 mb-1">
                    <span>Data masuk</span>
                    <span><?= number_format($totalAllVotes) ?> suara</span>
                </div>
                <div class="h-3 bg-white/20 rounded-full overflow-hidden">
                    <div class="h-full bg-white rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Ranking -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-md p-6 card-hover transition-all">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">🏆 Perolehan Sementara</h2>
                    <?php if (empty($candidateTotals)): ?>
                        <p class="text-gray-500 text-center py-8">Belum ada data suara terverifikasi.</p>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php $rank = 1; ?>
                            <?php foreach ($candidateTotals as $name => $data):
                                $pct = $totalAllVotes > 0 ? round(($data['votes'] / $totalAllVotes) * 100, 1) : 0;
                            ?>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <div class="flex items-center gap-3">
                                            <span class="bg-party-primary text-white w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold"><?= $rank ?></span>
                                            <span class="font-semibold text-gray-800"><?= htmlspecialchars($name) ?></span>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-gray-900 text-lg"><?= number_format($data['votes']) ?></div>
                                            <div class="text-xs text-gray-400"><?= $pct ?>%</div>
                                        </div>
                                    </div>
                                    <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-party-primary rounded-full transition-all" style="width: <?= $maxVotes > 0 ? ($data['votes'] / $maxVotes) * 100 : 0 ?>%"></div>
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1"><?= $data['tps'] ?> TPS</div>
                                </div>
                                <?php $rank++; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Per District -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">📋 Progress per Kecamatan</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kecamatan</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TPS Masuk</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total TPS</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coverage</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($districtSummary as $s):
                                    $coverage = $s['total_tps'] > 0 ? round(((int)$s['votes_in'] / (int)$s['total_tps']) * 100) : 0;
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($s['district_name']) ?></td>
                                        <td class="px-4 py-3 text-gray-600"><?= number_format((int)$s['votes_in']) ?></td>
                                        <td class="px-4 py-3 text-gray-600"><?= number_format((int)$s['total_tps']) ?></td>
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
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <p class="text-gray-400">© 2024 SIPEMENANG - Sistem Informasi Pemenangan Pilkada/Pileg Digital</p>
        </div>
    </footer>

    <script>
        function toggleMobileMenu() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        }
    </script>
</body>
</html>