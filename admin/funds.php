<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_check.php';

use App\Models\PartySettings;
use App\Models\WitnessFund;
use App\Models\TpsWitness;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

$fundModel = new WitnessFund();

// Filter
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'paid', 'pending', 'cancelled'], true)) {
    $statusFilter = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));

$filters = [];
if ($statusFilter !== 'all') {
    $filters['status'] = $statusFilter;
}
if ($q !== '') {
    $filters['q'] = $q;
}

// Export laporan pembayaran (CSV)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pembayaran_saksi_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM untuk Excel
    $out = fopen('php://output', 'w');
    fputcsv($out, ['No', 'Kabupaten', 'Kecamatan', 'Desa', 'TPS', 'Saksi', 'NIK', 'No HP', 'Nominal', 'Metode', 'Status', 'Tanggal Bayar', 'Keterangan', 'Waktu Input']);
    $rows = $fundModel->listWithDetails($filters, 100000, 0);
    foreach ($rows as $i => $r) {
        fputcsv($out, [
            $i + 1,
            $r['regency_name'] ?? '',
            $r['district_name'] ?? '',
            $r['village_name'] ?? '',
            $r['tps_number'] ?? '',
            $r['full_name'] ?? '',
            $r['nik'] ?? '',
            $r['phone_number'] ?? '',
            number_format((float)$r['amount'], 2, '.', ''),
            strtoupper($r['method'] ?? 'cash'),
            ucfirst((string)$r['status']),
            $r['paid_at'] ? date('d-m-Y', strtotime($r['paid_at'])) : '',
            $r['notes'] ?? '',
            date('Y-m-d H:i', strtotime($r['created_at'])),
        ]);
    }
    fclose($out);
    exit;
}

$db = Database::getConnection();

// Proses simpan transaksi / ubah status (POST)
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $witnessId = $_POST['witness_id'] ?? '';
        $amount = (float)($_POST['amount'] ?? 0);
        $method = in_array($_POST['method'] ?? 'cash', ['cash', 'transfer'], true) ? $_POST['method'] : 'cash';
        $status = in_array($_POST['status'] ?? 'paid', ['paid', 'pending', 'cancelled'], true) ? $_POST['status'] : 'paid';
        $paidAt = ($_POST['paid_at'] ?? '') ?: null;
        $notes = trim((string)($_POST['notes'] ?? '')) ?: null;

        if ($witnessId && ctype_digit((string)$witnessId) && $amount > 0) {
            $fundModel->create([
                'witness_id' => $witnessId,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'method' => $method,
                'status' => $status,
                'notes' => $notes,
                'created_by' => $_SESSION['user']['id'] ?? null,
            ]);
            $flash = 'Transaksi dana disimpan.';
        } else {
            $flash = 'Data tidak valid: pilih saksi dan isi nominal.';
        }
    } elseif ($action === 'update_status') {
        $id = $_POST['id'] ?? '';
        $newStatus = $_POST['status'] ?? '';
        if ($id && ctype_digit((string)$id) && in_array($newStatus, ['paid', 'pending', 'cancelled'], true)) {
            $stmt = $db->prepare("UPDATE witness_funds SET status = :status2, paid_at = CASE WHEN :status = 'paid' THEN COALESCE(paid_at, CURDATE()) ELSE paid_at END WHERE id = :id");
            $stmt->execute(['status' => $newStatus, 'status2' => $newStatus, 'id' => $id]);
            $flash = 'Status pembayaran diperbarui.';
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query(['status' => $statusFilter, 'q' => $q]));
    exit;
}

$transactions = $fundModel->listWithDetails($filters, 500, 0);
$totals = $fundModel->getTotals($filters);
$recap = $fundModel->getRecap($filters);

$witnessModel = new TpsWitness();
$witnessOptions = $witnessModel->listWithDetails([], 1000, 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dana Saksi - Admin SIPEMANANG</title>
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
            <a href="/pemenangan/admin/attendance.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📅 Kehadiran Saksi</a>
            <a href="/pemenangan/admin/funds.php" class="block px-4 py-3 bg-white/10 rounded-lg">💰 Dana Saksi</a>
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
                <h1 class="text-2xl font-bold text-gray-800">💰 Rekap Distribusi Dana Saksi TPS</h1>
                <p class="text-gray-600">Honorarium saksi per kecamatan & transaksi pembayaran</p>
            </div>
        </header>

        <div class="p-6 space-y-6">
            <?php if ($flash): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm"><?= htmlspecialchars($flash) ?></div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-green-500">
                    <div class="text-2xl font-bold text-green-600">Rp <?= number_format($totals['total_paid'], 0, ',', '.') ?></div>
                    <div class="text-xs text-gray-500">Total Dibayar</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-yellow-500">
                    <div class="text-2xl font-bold text-yellow-600">Rp <?= number_format($totals['total_pending'], 0, ',', '.') ?></div>
                    <div class="text-xs text-gray-500">Menunggu</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-blue-500">
                    <div class="text-2xl font-bold text-blue-600"><?= number_format($totals['witnesses_paid']) ?></div>
                    <div class="text-xs text-gray-500">Saksi Sudah Dibayar</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4 border-gray-400">
                    <div class="text-2xl font-bold text-gray-700"><?= count($transactions) ?></div>
                    <div class="text-xs text-gray-500">Transaksi (filter)</div>
                </div>
            </div>

            <!-- Form Input & Filter -->
            <div class="grid md:grid-cols-2 gap-4">
                <form method="POST" class="bg-white rounded-xl shadow-sm p-5 space-y-3">
                    <input type="hidden" name="action" value="create">
                    <h2 class="font-semibold text-gray-700 mb-1">➕ Catat Pembayaran</h2>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Saksi</label>
                        <select name="witness_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="">-- Pilih saksi --</option>
                            <?php foreach ($witnessOptions as $w): ?>
                                <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['full_name']) ?> — TPS <?= $w['tps_number'] ?? '' ?> (<?= htmlspecialchars($w['village_name'] ?? '') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Nominal (Rp)</label>
                            <input type="number" name="amount" min="0" step="50000" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Tanggal Bayar</label>
                            <input type="date" name="paid_at" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Metode</label>
                            <select name="method" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="cash">Tunai</option>
                                <option value="transfer">Transfer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
                            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="paid">Dibayar</option>
                                <option value="pending">Menunggu</option>
                                <option value="cancelled">Dibatalkan</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Keterangan</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                    </div>
                    <button type="submit" class="btn-party px-4 py-2 rounded-lg text-sm font-medium">💾 Simpan</button>
                </form>

                <div class="bg-white rounded-xl shadow-sm p-5 space-y-3">
                    <h2 class="font-semibold text-gray-700 mb-1">📥 Export & Filter</h2>
                    <div class="flex flex-wrap gap-3">
                        <a href="/pemenangan/admin/funds.php?export=csv<?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= $statusFilter !== 'all' ? '&status=' . $statusFilter : '' ?>" class="btn-party px-4 py-2 rounded-lg text-sm font-medium">⬇️ Export Laporan Pembayaran (CSV)</a>
                    </div>
                    <form method="GET" class="flex flex-wrap gap-2 items-end">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Status</label>
                            <select name="status" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua</option>
                                <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Dibayar</option>
                                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                                <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Cari (kecamatan/nama/NIK)</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        </div>
                        <button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Filter</button>
                    </form>
                </div>
            </div>

            <!-- Recap per District -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b">
                    <h2 class="font-semibold text-gray-700">📊 Rekap per Kecamatan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kecamatan</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saksi</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaksi</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dibayar</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Menunggu</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recap as $r): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($r['district_name'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= number_format((int)$r['witness_count']) ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= number_format((int)$r['payment_count']) ?></td>
                                    <td class="px-4 py-3 text-green-600 font-medium">Rp <?= number_format((float)$r['total_paid'], 0, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-yellow-600 font-medium">Rp <?= number_format((float)$r['total_pending'], 0, ',', '.') ?></td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">Rp <?= number_format((float)$r['total_all'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recap)): ?>
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Belum ada data pembayaran.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Transactions -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b">
                    <h2 class="font-semibold text-gray-700">🧾 Transaksi Pembayaran</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Saksi</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Wilayah</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TPS</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nominal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($transactions as $tr):
                                $statusBadge = [
                                    'paid' => 'bg-green-100 text-green-700',
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                    'cancelled' => 'bg-red-100 text-red-700',
                                ][$tr['status']] ?? 'bg-gray-100 text-gray-600';
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-800"><?= htmlspecialchars($tr['full_name']) ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($tr['nik'] ?? '') ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">
                                        <div><?= htmlspecialchars($tr['village_name'] ?? '') ?></div>
                                        <div class="text-xs text-gray-400"><?= htmlspecialchars($tr['district_name'] ?? '') ?></div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">TPS <?= $tr['tps_number'] ?? '-' ?></td>
                                    <td class="px-4 py-3 font-semibold text-gray-800">Rp <?= number_format((float)$tr['amount'], 0, ',', '.') ?></td>
                                    <td class="px-4 py-3 text-gray-600"><?= ucfirst($tr['method']) ?></td>
                                    <td class="px-4 py-3 text-gray-500 text-xs"><?= $tr['paid_at'] ? date('d M Y', strtotime($tr['paid_at'])) : '-' ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusBadge ?>"><?= ucfirst($tr['status']) ?></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($tr['status'] !== 'paid'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?= $tr['id'] ?>">
                                                <input type="hidden" name="status" value="paid">
                                                <button type="submit" class="bg-green-500 text-white px-2 py-1 rounded text-xs font-medium hover:bg-green-600">Bayar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($tr['status'] !== 'cancelled'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?= $tr['id'] ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" class="bg-red-500 text-white px-2 py-1 rounded text-xs font-medium hover:bg-red-600">Batal</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Belum ada transaksi. Catat pembayaran di form di atas.</td></tr>
                            <?php endif; ?>
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