<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/auth_check.php';

use App\Models\PartySettings;
use App\Models\TpsWitness;
use App\Models\VoteResult;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

// Get stats
$witnessModel = new TpsWitness();
$voteModel = new VoteResult();
$witnessStats = $witnessModel->getStats();
$voteStats = $voteModel->getStats();

$db = Database::getConnection();
$tpsCount = $db->query("SELECT COUNT(*) as total FROM tps")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - SIPEMENANG</title>
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
            <a href="/pemenangan/admin/" class="block px-4 py-3 bg-white/10 rounded-lg">📊 Dashboard</a>
            <a href="/pemenangan/admin/witnesses.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">👥 Manajemen Saksi</a>
            <a href="/pemenangan/admin/votes.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗳️ Hasil Suara</a>
            <a href="/pemenangan/admin/attendance.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📅 Kehadiran Saksi</a>
            <a href="/pemenangan/admin/funds.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">💰 Dana Saksi</a>
            <a href="/pemenangan/admin/regions.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🗺️ Data Wilayah</a>
            <a href="/pemenangan/admin/theme.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">🎨 Theme Settings</a>
            <a href="/pemenangan/admin/reports.php" class="block px-4 py-3 hover:bg-white/10 rounded-lg">📄 Laporan</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="lg:ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm p-6 flex items-center gap-4">
            <button onclick="toggleSidebar()" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Dashboard Admin</h1>
                <p class="text-gray-600">Selamat datang di panel administrasi SIPEMENANG</p>
            </div>
        </header>

        <!-- Stats -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                    <div class="text-3xl font-bold text-blue-600"><?= $tpsCount ?></div>
                    <div class="text-gray-600">Total TPS</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                    <div class="text-3xl font-bold text-green-600"><?= $witnessStats['verified'] ?></div>
                    <div class="text-gray-600">Saksi Terverifikasi</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500">
                    <div class="text-3xl font-bold text-yellow-600"><?= $witnessStats['pending'] ?></div>
                    <div class="text-gray-600">Menunggu Verifikasi</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
                    <div class="text-3xl font-bold text-purple-600"><?= $voteStats['total'] ?></div>
                    <div class="text-gray-600">Data Suara Masuk</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <a href="/pemenangan/admin/witnesses.php" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                    <div class="text-4xl mb-4">👥</div>
                    <h3 class="font-semibold text-lg">Manajemen Saksi</h3>
                    <p class="text-gray-600 text-sm mt-2">Verifikasi dan kelola data saksi TPS</p>
                </a>
                <a href="/pemenangan/admin/votes.php" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                    <div class="text-4xl mb-4">🗳️</div>
                    <h3 class="font-semibold text-lg">Hasil Suara</h3>
                    <p class="text-gray-600 text-sm mt-2">Lihat dan verifikasi data real count</p>
                </a>
                <a href="/pemenangan/admin/theme.php" class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                    <div class="text-4xl mb-4">🎨</div>
                    <h3 class="font-semibold text-lg">Theme Settings</h3>
                    <p class="text-gray-600 text-sm mt-2">Atur warna dan branding partai</p>
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const isOpen = !sidebar.classList.contains('-translate-x-full') || window.innerWidth >= 1024;
            if (window.innerWidth < 1024) {
                sidebar.classList.toggle('-translate-x-full');
                overlay.classList.toggle('hidden');
            }
        }
    </script>
</body>
</html>
