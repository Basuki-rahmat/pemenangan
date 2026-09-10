<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\PartySettings;
use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();

$db = Database::getConnection();

$provinces = $db->query("SELECT * FROM provinces ORDER BY name")->fetchAll();
$totalProvinces = count($provinces);
$totalRegencies = (int)$db->query("SELECT COUNT(*) FROM regencies")->fetchColumn();
$totalDistricts = (int)$db->query("SELECT COUNT(*) FROM districts")->fetchColumn();
$totalVillages = (int)$db->query("SELECT COUNT(*) FROM villages")->fetchColumn();
$totalTps = (int)$db->query("SELECT COUNT(*) FROM tps")->fetchColumn();

$selectedProvince = $_GET['province'] ?? '';
$selectedRegency = $_GET['regency'] ?? '';
$selectedDistrict = $_GET['district'] ?? '';

$regencies = [];
$districts = [];
$villages = [];
$tpsList = [];

if ($selectedProvince) {
    $stmt = $db->prepare("SELECT * FROM regencies WHERE province_id = :id ORDER BY name");
    $stmt->execute(['id' => $selectedProvince]);
    $regencies = $stmt->fetchAll();
}

if ($selectedRegency) {
    $stmt = $db->prepare("SELECT * FROM districts WHERE regency_id = :id ORDER BY name");
    $stmt->execute(['id' => $selectedRegency]);
    $districts = $stmt->fetchAll();
}

if ($selectedDistrict) {
    $stmt = $db->prepare("SELECT * FROM villages WHERE district_id = :id ORDER BY name");
    $stmt->execute(['id' => $selectedDistrict]);
    $villages = $stmt->fetchAll();
}

if (!empty($villages)) {
    $villageIds = array_column($villages, 'id');
    $placeholders = implode(',', array_fill(0, count($villageIds), '?'));
    $stmt = $db->prepare("SELECT t.*, v.name as village_name FROM tps t JOIN villages v ON t.village_id = v.id WHERE t.village_id IN ($placeholders) ORDER BY v.name, t.tps_number");
    $stmt->execute($villageIds);
    $tpsList = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Wilayah - Admin SIPEMENANG</title>
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
            <a href="/pemenangan/admin/regions.php" class="block px-4 py-3 bg-white/10 rounded-lg">🗺️ Data Wilayah</a>
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
                <h1 class="text-2xl font-bold text-gray-800">🗺️ Data Wilayah</h1>
                <p class="text-gray-600">Browse data provinsi, kabupaten, kecamatan, desa, dan TPS</p>
            </div>
        </header>

        <div class="p-6">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= $totalProvinces ?></div>
                    <div class="text-xs text-gray-500">Provinsi</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-green-600"><?= $totalRegencies ?></div>
                    <div class="text-xs text-gray-500">Kabupaten</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600"><?= $totalDistricts ?></div>
                    <div class="text-xs text-gray-500">Kecamatan</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600"><?= $totalVillages ?></div>
                    <div class="text-xs text-gray-500">Desa/Kelurahan</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                    <div class="text-2xl font-bold text-red-600"><?= $totalTps ?></div>
                    <div class="text-xs text-gray-500">TPS</div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
                <a href="/pemenangan/admin/regions.php" class="text-primary hover:underline font-medium">Provinsi</a>
                <?php if ($selectedProvince): 
                    $pName = '';
                    foreach ($provinces as $p) { if ($p['id'] == $selectedProvince) { $pName = $p['name']; break; } }
                ?>
                    <span>›</span>
                    <a href="/pemenangan/admin/regions.php?province=<?= $selectedProvince ?>" class="text-primary hover:underline font-medium"><?= htmlspecialchars($pName) ?></a>
                <?php endif; ?>
                <?php if ($selectedRegency):
                    $rName = '';
                    foreach ($regencies as $r) { if ($r['id'] == $selectedRegency) { $rName = $r['name']; break; } }
                ?>
                    <span>›</span>
                    <a href="/pemenangan/admin/regions.php?province=<?= $selectedProvince ?>&regency=<?= $selectedRegency ?>" class="text-primary hover:underline font-medium"><?= htmlspecialchars($rName) ?></a>
                <?php endif; ?>
                <?php if ($selectedDistrict):
                    $dName = '';
                    foreach ($districts as $d) { if ($d['id'] == $selectedDistrict) { $dName = $d['name']; break; } }
                ?>
                    <span>›</span>
                    <span class="font-medium text-gray-800"><?= htmlspecialchars($dName) ?></span>
                <?php endif; ?>
            </div>

            <!-- Province List -->
            <?php if (!$selectedProvince): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-semibold text-gray-700">Provinsi (<?= $totalProvinces ?>)</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-0">
                        <?php foreach ($provinces as $prov):
                            $stmt2 = $db->prepare("SELECT COUNT(*) as c FROM regencies WHERE province_id = ?");
                            $stmt2->execute([$prov['id']]);
                            $rcCount = $stmt2->fetch()['c'];
                        ?>
                            <a href="/pemenangan/admin/regions.php?province=<?= $prov['id'] ?>" class="block p-4 border-b border-r hover:bg-gray-50 transition">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($prov['name']) ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?= $rcCount ?> kabupaten</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            <!-- Regency List -->
            <?php elseif (!$selectedRegency): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-semibold text-gray-700">Kabupaten/Kota (<?= count($regencies) ?>)</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-0">
                        <?php foreach ($regencies as $reg):
                            $stmt2 = $db->prepare("SELECT COUNT(*) as c FROM districts WHERE regency_id = ?");
                            $stmt2->execute([$reg['id']]);
                            $dcCount = $stmt2->fetch()['c'];
                        ?>
                            <a href="/pemenangan/admin/regions.php?province=<?= $selectedProvince ?>&regency=<?= $reg['id'] ?>" class="block p-4 border-b border-r hover:bg-gray-50 transition">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($reg['name']) ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?= $dcCount ?> kecamatan</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            <!-- District List -->
            <?php elseif (!$selectedDistrict): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-semibold text-gray-700">Kecamatan (<?= count($districts) ?>)</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-0">
                        <?php foreach ($districts as $dist):
                            $stmt2 = $db->prepare("SELECT COUNT(*) as c FROM villages WHERE district_id = ?");
                            $stmt2->execute([$dist['id']]);
                            $vcCount = $stmt2->fetch()['c'];
                        ?>
                            <a href="/pemenangan/admin/regions.php?province=<?= $selectedProvince ?>&regency=<?= $selectedRegency ?>&district=<?= $dist['id'] ?>" class="block p-4 border-b border-r hover:bg-gray-50 transition">
                                <div class="font-medium text-gray-800"><?= htmlspecialchars($dist['name']) ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?= $vcCount ?> desa</div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            <!-- Village + TPS List -->
            <?php else: ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
                    <div class="p-4 border-b font-semibold text-gray-700">Desa/Kelurahan (<?= count($villages) ?>)</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nama Desa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TPS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($villages as $i => $village):
                                    $stmt2 = $db->prepare("SELECT COUNT(*) as c FROM tps WHERE village_id = ?");
                                    $stmt2->execute([$village['id']]);
                                    $tpsCount = $stmt2->fetch()['c'];
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-500"><?= $i + 1 ?></td>
                                        <td class="px-4 py-3 font-medium text-gray-800"><?= htmlspecialchars($village['name']) ?></td>
                                        <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($village['id']) ?></td>
                                        <td class="px-4 py-3">
                                            <span class="bg-primary/10 text-primary px-2 py-1 rounded-full text-xs font-medium"><?= $tpsCount ?> TPS</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($tpsList)): ?>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b font-semibold text-gray-700">Data TPS</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Desa</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">TPS</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">DPT</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Koordinat</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($tpsList as $i => $tps): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-500"><?= $i + 1 ?></td>
                                        <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars($tps['village_name']) ?></td>
                                        <td class="px-4 py-3 font-medium text-gray-800">TPS <?= $tps['tps_number'] ?></td>
                                        <td class="px-4 py-3 text-gray-600"><?= number_format((int)$tps['total_dpt']) ?></td>
                                        <td class="px-4 py-3 text-gray-500 text-xs"><?= $tps['latitude'] ?>, <?= $tps['longitude'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
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
