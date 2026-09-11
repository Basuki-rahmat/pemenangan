<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_check.php';

use App\Models\PartySettings;
use App\Models\WitnessAttendance;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

$attendanceModel = new WitnessAttendance();

// Ambil tanggal dari query atau hari ini
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Proses tindakan (POST)
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $witnessId = $_POST['witness_id'] ?? '';
    $dateInput = $_POST['date'] ?? $date;

    if ($action && $witnessId && ctype_digit((string)$witnessId)) {
        switch ($action) {
            case 'hadir':
            case 'terlambat':
            case 'izin':
            case 'alpa':
                $attendanceModel->setStatus($witnessId, $dateInput, $action, trim((string)($_POST['notes'] ?? '')) ?: null);
                $flash = 'Status kehadiran diperbarui.';
                break;
            case 'checkin':
                $attendanceModel->checkIn($witnessId, $dateInput);
                $flash = 'Check-in tersimpan.';
                break;
            case 'checkout':
                $attendanceModel->checkOut($witnessId, $dateInput);
                $flash = 'Check-out tersimpan.';
                break;
        }
    } else {
        $flash = 'Aksi tidak valid.';
    }

    // Agar form tidak terkirim ulang saat refresh
    header('Location: ' . $_SERVER['PHP_SELF'] . '?date=' . urlencode($dateInput));
    exit;
}

$rows = $attendanceModel->listByDate($date);
$summary = $attendanceModel->summaryByDate($date);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kehadiran Saksi - Admin SIPEMANANG</title>
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
            <a href="/pemenangan/admin/recap.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗂️ Rekap Berjenjang</a>
            <a href="/pemenangan/admin/attendance.php" class="block px-4 py-3 bg-white/10 rounded-lg">📅 Kehadiran Saksi</a>
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
            <div>
                <h1 class="text-2xl font-bold text-gray-800">📅 Tracking Kehadiran Saksi</h1>
                <p class="text-gray-600">Presensi saksi pada hari pemungutan suara</p>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <?php if ($flash): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>

            <!-- Date Filter -->
            <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Tanggal</label>
                    <input type="date" name="date" value="<?= $date ?>" onchange="location.href='?date=' + this.value" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <a href="?date=<?= date('Y-m-d') ?>" class="text-sm text-primary hover:underline self-center">Hari ini</a>
                <div class="ml-auto text-sm text-gray-500"><?= date('d M Y', strtotime($date)) ?></div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                    <div class="text-2xl font-bold text-blue-600"><?= $summary['total'] ?></div>
                    <div class="text-xs text-gray-500">Saksi Terdaftar</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                    <div class="text-2xl font-bold text-green-600"><?= $summary['hadir'] ?></div>
                    <div class="text-xs text-gray-500">Hadir</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-orange-500">
                    <div class="text-2xl font-bold text-orange-600"><?= $summary['terlambat'] ?></div>
                    <div class="text-xs text-gray-500">Terlambat</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-purple-500">
                    <div class="text-2xl font-bold text-purple-600"><?= $summary['izin'] ?></div>
                    <div class="text-xs text-gray-500">Izin</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-red-500">
                    <div class="text-2xl font-bold text-red-600"><?= $summary['alpa'] ?></div>
                    <div class="text-xs text-gray-500">Alpa</div>
                    <div class="text-[10px] text-gray-400 mt-1">Hadir rate: <?= $summary['attendance_rate'] ?>%</div>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b flex justify-between items-center">
                    <h2 class="font-semibold text-gray-700">Daftar Saksi</h2>
                    <span class="text-xs text-gray-500"><?= $summary['checked_in'] ?> sudah check-in</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saksi</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wilayah</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TPS</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-in</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-out</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($rows as $row):
                                $status = $row['registered'] ? ($row['att_status'] ?? 'alpa') : 'alpa';
                                $badge = [
                                    'hadir' => 'bg-green-100 text-green-700',
                                    'terlambat' => 'bg-orange-100 text-orange-700',
                                    'izin' => 'bg-purple-100 text-purple-700',
                                    'alpa' => 'bg-red-100 text-red-700',
                                ][$status];
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($row['full_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($row['notes'] ?: '') ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <div><?= htmlspecialchars($row['village_name'] ?? '') ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($row['district_name'] ?? '') ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">TPS <?= $row['tps_number'] ?? '-' ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $badge ?>"><?= ucfirst($status) ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 text-xs"><?= $row['check_in_at'] ? date('H:i', strtotime($row['check_in_at'])) : '-' ?></td>
                                    <td class="px-4 py-3 text-gray-600 text-xs"><?= $row['check_out_at'] ? date('H:i', strtotime($row['check_out_at'])) : '-' ?></td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <form method="POST" class="flex flex-wrap gap-1">
                                            <input type="hidden" name="action" value="">
                                            <input type="hidden" name="witness_id" value="<?= $row['witness_id'] ?>">
                                            <input type="hidden" name="date" value="<?= $date ?>">
                                            <?php foreach (['hadir', 'terlambat', 'izin', 'alpa'] as $s): ?>
                                                <button type="submit" name="action" value="<?= $s ?>" class="px-2 py-1 rounded text-[11px] font-medium <?= $status === $s ? 'btn-party' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= ucfirst($s) ?></button>
                                            <?php endforeach; ?>
                                            <?php if (!$row['check_in_at']): ?>
                                                <button type="submit" name="action" value="checkin" class="px-2 py-1 rounded text-[11px] font-medium bg-blue-500 text-white hover:bg-blue-600">⏰ Check-in</button>
                                            <?php elseif (!$row['check_out_at']): ?>
                                                <button type="submit" name="action" value="checkout" class="px-2 py-1 rounded text-[11px] font-medium bg-gray-700 text-white hover:bg-gray-800">🏁 Check-out</button>
                                            <?php endif; ?>
                                        </form>
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