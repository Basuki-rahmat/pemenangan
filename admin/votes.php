<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\PartySettings;
use App\Models\VoteResult;
use App\Helpers\Upload;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

$db = Database::getConnection();
$voteModel = new VoteResult();
$stats = $voteModel->getStats();

$filter = $_GET['status'] ?? 'all';
if (!in_array($filter, ['all', 'pending', 'verified', 'rejected'])) {
    $filter = 'all';
}

$sql = "SELECT vr.*, w.full_name AS witness_name, t.tps_number, v.name AS village_name, d.name AS district_name 
    FROM vote_results vr 
    LEFT JOIN tps_witnesses w ON vr.witness_id = w.id 
    LEFT JOIN tps t ON vr.tps_id = t.id 
    LEFT JOIN villages v ON t.village_id = v.id 
    LEFT JOIN districts d ON v.district_id = d.id ";
if ($filter !== 'all') {
    $sql .= "WHERE vr.status = :status ";
}
$sql .= "ORDER BY vr.created_at DESC LIMIT 200";

if ($filter === 'all') {
    $votes = $db->query($sql)->fetchAll();
} else {
    $stmt = $db->prepare($sql);
    $stmt->execute(['status' => $filter]);
    $votes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Suara - Admin SIPEMENANG</title>
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
            <a href="/pemenangan/admin/votes.php" class="block px-4 py-3 bg-white/10 rounded-lg">🗳️ Hasil Suara</a>
            <a href="/pemenangan/admin/regions.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗺️ Data Wilayah</a>
            <a href="/pemenangan/admin/theme.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🎨 Theme Settings</a>
            <a href="/pemenangan/admin/reports.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📄 Laporan</a>
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
                <h1 class="text-2xl font-bold text-gray-800">🗳️ Hasil Suara (Real Count)</h1>
                <p class="text-gray-600">Verifikasi data real count dari saksi TPS</p>
            </div>
        </header>

        <div class="p-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <a href="/pemenangan/admin/votes.php" class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500 hover:shadow-md transition">
                    <div class="text-2xl font-bold text-blue-600"><?= $stats['total'] ?></div>
                    <div class="text-xs text-gray-500">Total Data Masuk</div>
                </a>
                <a href="/pemenangan/admin/votes.php?status=pending" class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500 hover:shadow-md transition">
                    <div class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?></div>
                    <div class="text-xs text-gray-500">Menunggu</div>
                </a>
                <a href="/pemenangan/admin/votes.php?status=verified" class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500 hover:shadow-md transition">
                    <div class="text-2xl font-bold text-green-600"><?= $stats['verified'] ?></div>
                    <div class="text-xs text-gray-500">Terverifikasi</div>
                </a>
                <a href="/pemenangan/admin/votes.php?status=rejected" class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500 hover:shadow-md transition">
                    <div class="text-2xl font-bold text-red-600"><?= $stats['rejected'] ?></div>
                    <div class="text-xs text-gray-500">Ditolak</div>
                </a>
            </div>

            <!-- Filter Tabs -->
            <div class="flex gap-2 mb-6">
                <a href="/pemenangan/admin/votes.php" class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter === 'all' ? 'btn-party' : 'bg-white text-gray-600 hover:bg-gray-50' ?>">Semua</a>
                <a href="/pemenangan/admin/votes.php?status=pending" class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter === 'pending' ? 'btn-party' : 'bg-white text-gray-600 hover:bg-gray-50' ?>">Menunggu</a>
                <a href="/pemenangan/admin/votes.php?status=verified" class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter === 'verified' ? 'btn-party' : 'bg-white text-gray-600 hover:bg-gray-50' ?>">Terverifikasi</a>
                <a href="/pemenangan/admin/votes.php?status=rejected" class="px-4 py-2 rounded-lg text-sm font-medium <?= $filter === 'rejected' ? 'btn-party' : 'bg-white text-gray-600 hover:bg-gray-50' ?>">Ditolak</a>
            </div>

            <!-- Votes Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <?php if (empty($votes)): ?>
                    <div class="p-8 text-center text-gray-500">Belum ada data real count.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TPS</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saksi</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Perolehan Suara</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suara Tidak Sah</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($votes as $v):
                                    $statusColor = [
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'verified' => 'bg-green-100 text-green-700',
                                        'rejected' => 'bg-red-100 text-red-700',
                                    ][$v['status']];
                                    $candidateVotes = json_decode($v['candidate_votes'], true) ?? [];
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-800">TPS <?= $v['tps_number'] ?? '-' ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($v['village_name'] ?? '') ?></div>
                                            <div class="text-xs text-gray-400"><?= htmlspecialchars($v['district_name'] ?? '') ?></div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($v['witness_name'] ?? '-') ?></td>
                                        <td class="px-4 py-3">
                                            <?php foreach ($candidateVotes as $candidate): ?>
                                                <div class="text-xs text-gray-700">
                                                    <span class="font-medium"><?= htmlspecialchars($candidate['name'] ?? $candidate['candidate_name'] ?? 'Kandidat') ?>:</span>
                                                    <?= number_format((int)($candidate['votes'] ?? $candidate['total_votes'] ?? 0)) ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (isset($v['c1_photo_url']) && $v['c1_photo_url']): ?>
                                                <a href="#" onclick="showPhoto('<?= htmlspecialchars(Upload::url($v['c1_photo_url'])) ?>','Foto C1 TPS <?= $v['tps_number'] ?>')" class="text-primary text-xs hover:underline">📄 C1</a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 text-gray-600"><?= number_format((int)$v['invalid_votes']) ?></td>
                                        <td class="px-4 py-3 font-semibold text-gray-800"><?= number_format((int)$v['total_votes']) ?></td>
                                        <td class="px-4 py-3 text-gray-500 text-xs"><?= date('d M Y H:i', strtotime($v['created_at'])) ?></td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusColor ?>"><?= ucfirst($v['status']) ?></span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php if ($v['status'] === 'pending'): ?>
                                                <button onclick="verifyVote(<?= $v['id'] ?>, 'verified')" class="bg-green-500 text-white px-3 py-1 rounded-lg text-xs font-medium hover:bg-green-600">Verifikasi</button>
                                                <button onclick="verifyVote(<?= $v['id'] ?>, 'rejected')" class="bg-red-500 text-white px-3 py-1 rounded-lg text-xs font-medium hover:bg-red-600">Tolak</button>
                                            <?php else: ?>
                                                <span class="text-gray-400 text-xs"><?= $v['verified_at'] ? 'diverifikasi ' . date('d M', strtotime($v['verified_at'])) : '' ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Photo Modal -->
    <div id="photo-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onclick="this.classList.add('hidden')">
        <div class="bg-white rounded-xl max-w-lg w-full" onclick="event.stopPropagation()">
            <div class="p-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-gray-800" id="photo-title"></h3>
                <button onclick="document.getElementById('photo-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>
            <div class="p-4">
                <img id="photo-img" src="" alt="Foto C1" class="w-full rounded-lg">
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

        function showPhoto(url, title) {
            document.getElementById('photo-title').textContent = title;
            document.getElementById('photo-img').src = url;
            document.getElementById('photo-modal').classList.remove('hidden');
        }

        async function verifyVote(id, status) {
            if (!confirm('Verifikasi data suara ini sebagai "' + status.toUpperCase() + '"?')) return;
            try {
                const response = await fetch('/pemenangan/api/admin/vote.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, status: status })
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Gagal memverifikasi');
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>