<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_check.php';

use App\Models\PartySettings;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$parties = $partyModel->findAll([], 'party_name');
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Settings - SIPEMENANG</title>
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
    <script>
        window.ADMIN_TOKEN = '<?= htmlspecialchars($_SESSION['api_token'] ?? '', ENT_QUOTES) ?>';
    </script>
    <style>
        <?= $cssVariables ?>
        .bg-party-primary { background-color: var(--primary-color); }
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
            <a href="/pemenangan/admin/attendance.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📅 Kehadiran Saksi</a>
            <a href="/pemenangan/admin/funds.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">💰 Dana Saksi</a>
            <a href="/pemenangan/admin/regions.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗺️ Data Wilayah</a>
            <a href="/pemenangan/admin/theme.php" class="block px-4 py-3 bg-white/10 rounded-lg">🎨 Theme Settings</a>
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
                <h1 class="text-2xl font-bold text-gray-800">🎨 Theme Settings</h1>
                <p class="text-gray-600">Konfigurasi warna dan branding partai</p>
            </div>
        </header>

        <div class="p-6">
            <!-- Current Theme Preview -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <h2 class="text-lg font-semibold mb-4">Preview Theme Aktif</h2>
                <div class="flex items-center space-x-4">
                    <div class="w-20 h-20 rounded-lg" style="background-color: <?= $activeParty ? $activeParty['primary_color'] : '#1E40AF' ?>"></div>
                    <div class="w-20 h-20 rounded-lg" style="background-color: <?= $activeParty ? $activeParty['secondary_color'] : '#F97316' ?>"></div>
                    <div class="w-20 h-20 rounded-lg border" style="background-color: <?= $activeParty ? $activeParty['accent_color'] : '#F3F4F6' ?>"></div>
                </div>
                <div class="mt-4">
                    <button class="btn-party px-6 py-2 rounded-lg">Button Preview</button>
                </div>
            </div>

            <!-- Party List -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold mb-4">Daftar Partai</h2>
                <div class="space-y-4">
                    <?php foreach ($parties as $party): ?>
                    <div class="flex items-center justify-between p-4 border rounded-lg <?= $party['is_active'] ? 'border-green-500 bg-green-50' : '' ?>">
                        <div class="flex items-center space-x-4">
                            <div class="flex space-x-2">
                                <div class="w-10 h-10 rounded" style="background-color: <?= $party['primary_color'] ?>"></div>
                                <div class="w-10 h-10 rounded" style="background-color: <?= $party['secondary_color'] ?>"></div>
                            </div>
                            <div>
                                <div class="font-semibold"><?= htmlspecialchars($party['party_name']) ?></div>
                                <div class="text-sm text-gray-500">Primary: <?= $party['primary_color'] ?> | Secondary: <?= $party['secondary_color'] ?></div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($party['is_active']): ?>
                            <span class="px-3 py-1 bg-green-500 text-white text-sm rounded-full">Aktif</span>
                            <?php else: ?>
                            <button onclick="setActive(<?= $party['id'] ?>)" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-sm rounded-lg">Aktifkan</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
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

        async function setActive(id) {
            if (!confirm('Aktifkan tema partai ini?')) return;
            
            try {
                const response = await fetch('/pemenangan/api/admin/theme.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + window.ADMIN_TOKEN
                    },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();
                
                if (result.success) {
                    alert('✅ Tema berhasil diaktifkan');
                    location.reload();
                } else {
                    alert('❌ ' + result.message);
                }
            } catch (error) {
                alert('❌ Terjadi kesalahan');
            }
        }
    </script>
</body>
</html>
