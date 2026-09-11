<?php

declare(strict_types=1);

require_once __DIR__ . '/api/bootstrap.php';

use App\Models\PartySettings;
use App\Database;

// Get active party theme
$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPEMENANG - Sistem Informasi Pemenangan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        
        .map-fullscreen {
            position: fixed;
            inset: 0;
            z-index: 60;
            width: 100vw;
            height: 100vh;
            max-width: none;
            overflow: hidden;
            border-radius: 0;
            box-shadow: none;
            margin: 0;
        }
        .map-fullscreen #map {
            height: calc(100vh - 120px) !important;
        }
        
        .bg-party-primary { background-color: var(--primary-color); }
        .text-party-primary { color: var(--primary-color); }
        .border-party-primary { border-color: var(--primary-color); }
        .bg-party-secondary { background-color: var(--secondary-color); }
        .text-party-secondary { color: var(--secondary-color); }
        
        .btn-party {
            background-color: var(--primary-color);
            color: #ffffff;
            transition: all 0.2s;
        }
        .btn-party:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }
        
        .gradient-party {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        .card-hover:hover {
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-party-primary shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-white text-xl font-bold">🗳️ SIPEMENANG</span>
                    </div>
                    <div class="hidden md:ml-10 md:flex md:space-x-4">
                        <a href="/pemenangan/" class="text-white hover:bg-white/10 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="/pemenangan/register.php" class="text-white/80 hover:bg-white/10 px-3 py-2 rounded-md text-sm font-medium">Registrasi Saksi</a>
                        <a href="/pemenangan/quick-count.php" class="text-white/80 hover:bg-white/10 px-3 py-2 rounded-md text-sm font-medium">Real Count</a>
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

    <!-- Hero Section -->
    <div class="gradient-party text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl font-bold mb-4">Sistem Informasi Pemenangan Digital</h1>
            <p class="text-xl text-white/90 mb-8">Monitoring real-time saksi TPS, real count suara, dan analisis pemetaan wilayah</p>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8">
                <div class="bg-white/20 backdrop-blur rounded-lg p-4">
                    <div class="text-3xl font-bold" id="total-tps">-</div>
                    <div class="text-sm text-white/80">Total TPS</div>
                </div>
                <div class="bg-white/20 backdrop-blur rounded-lg p-4">
                    <div class="text-3xl font-bold" id="witness-verified">-</div>
                    <div class="text-sm text-white/80">Saksi Terverifikasi</div>
                </div>
                <div class="bg-white/20 backdrop-blur rounded-lg p-4">
                    <div class="text-3xl font-bold" id="votes-count">-</div>
                    <div class="text-sm text-white/80">Data Masuk</div>
                </div>
                <div class="bg-white/20 backdrop-blur rounded-lg p-4">
                    <div class="text-3xl font-bold" id="percentage">-</div>
                    <div class="text-sm text-white/80">Progress</div>
                </div>
            </div>

            <!-- Realtime Recapitulation -->
            <div id="realtime-panel" class="mt-8 hidden md:block">
                <div class="flex items-center justify-center gap-2 text-sm text-white/80 mb-3">
                    <span class="relative flex h-2.5 w-2.5"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-400"></span></span>
                    <span class="font-semibold">Real Count Suara</span>
                    <span id="realtime-time" class="text-white/50"></span>
                </div>
                <div id="realtime-candidates" class="grid grid-cols-2 md:grid-cols-4 gap-3 max-w-4xl mx-auto"></div>
                <div class="text-xs text-white/70 mt-3" id="realtime-coverage"></div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Map -->
            <div id="map-card" class="bg-white rounded-xl shadow-md p-6 card-hover transition-all">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">🗺️ Peta Persebaran</h2>
                    <button id="map-fullscreen-btn" onclick="toggleMapFullscreen()" title="Mode layar penuh"
                        class="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                        <span id="map-fullscreen-label">Full Screen</span>
                    </button>
                </div>

                <!-- View Mode Toggle -->
                <div class="flex items-center gap-2 mb-3 flex-wrap">
                    <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium">
                        <button id="view-tps" onclick="setMapView('tps')" class="px-3 py-1.5 rounded-md bg-white shadow text-primary font-semibold transition">📍 TPS</button>
                        <button id="view-heatmap" onclick="setMapView('heatmap')" class="px-3 py-1.5 rounded-md text-gray-500 hover:text-gray-700 transition">🔥 Basis Massa</button>
                        <button id="view-votes" onclick="setMapView('votes')" class="px-3 py-1.5 rounded-md text-gray-500 hover:text-gray-700 transition">🗳️ Suara</button>
                    </div>
                    <select id="party-filter" onchange="loadHeatmap(this.value)" 
                        class="text-xs border-gray-200 rounded-lg px-2 py-1.5 hidden focus:ring-primary focus:border-primary">
                        <option value="all">Semua Partai</option>
                        <option value="PDI-P">PDI-P</option>
                        <option value="Golkar">Golkar</option>
                        <option value="Gerindra">Gerindra</option>
                        <option value="PKB">PKB</option>
                        <option value="NasDem">NasDem</option>
                        <option value="PKS">PKS</option>
                        <option value="Demokrat">Demokrat</option>
                    </select>
                </div>

                <!-- Legend: TPS -->
                <div id="legend-tps" class="flex flex-wrap gap-4 mb-3 text-xs text-gray-600">
                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full" style="background:#10b981"></span> Saksi terverifikasi</span>
                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full" style="background:#f59e0b"></span> Menunggu verifikasi</span>
                    <span class="flex items-center gap-1"><span class="inline-block w-3 h-3 rounded-full" style="background:#ef4444"></span> Belum ada saksi</span>
                </div>
                <!-- Legend: Heatmap -->
                <div id="legend-heatmap" class="hidden mb-3 text-xs text-gray-600">
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1">
                            <span class="inline-block w-4 h-2 rounded" style="background:linear-gradient(90deg,#3b82f6,#22c55e,#eab308,#ef4444)"></span>
                            Kepadatan pendukung
                        </span>
                        <span id="heatmap-summary" class="text-gray-400"></span>
                    </div>
                </div>
                <div id="map" class="h-80 rounded-lg bg-gray-100"></div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-md p-6 card-hover transition-all">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">⚡ Aksi Cepat</h2>
                <div class="space-y-4">
                    <a href="/pemenangan/register.php" class="block btn-party rounded-lg p-4 text-center font-medium">
                        📝 Registrasi Saksi Baru
                    </a>
                    <a href="/pemenangan/quick-count.php" class="block bg-secondary text-gray-800 rounded-lg p-4 text-center font-medium hover:opacity-90 transition">
                        📊 Input Real Count
                    </a>
                    <a href="/pemenangan/admin/witnesses.php" class="block bg-gray-100 text-gray-800 rounded-lg p-4 text-center font-medium hover:bg-gray-200 transition">
                        ✅ Verifikasi Saksi
                    </a>
                    <a href="/pemenangan/admin/reports.php" class="block bg-gray-100 text-gray-800 rounded-lg p-4 text-center font-medium hover:bg-gray-200 transition">
                        📄 Export Laporan
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-md p-6 card-hover transition-all lg:col-span-2">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">📋 Aktivitas Terbaru</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Detail</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody id="activity-list" class="divide-y divide-gray-200">
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
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

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
    <script>
        // Initialize Map (center Lampung)
        const map = L.map('map').setView([-5.0, 105.0], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Custom pin icons by status
        function makeIcon(color) {
            return L.divIcon({
                className: '',
                html: `<div style="background:${color};width:18px;height:18px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3)"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 18],
                popupAnchor: [0, -18]
            });
        }

        // Fullscreen toggle untuk card peta
        function toggleMapFullscreen() {
            const card = document.getElementById('map-card');
            const mapEl = document.getElementById('map');
            const btn = document.getElementById('map-fullscreen-btn');
            const label = document.getElementById('map-fullscreen-label');
            const isFull = card.classList.contains('map-fullscreen');

            if (!isFull) {
                card.classList.add('map-fullscreen');
                mapEl.classList.add('h-full');
                label.textContent = 'Keluar';
                btn.classList.remove('bg-gray-100', 'hover:bg-gray-200');
                btn.classList.add('bg-white', 'hover:bg-gray-100');
            } else {
                card.classList.remove('map-fullscreen');
                mapEl.classList.remove('h-full');
                label.textContent = 'Full Screen';
                btn.classList.add('bg-gray-100', 'hover:bg-gray-200');
                btn.classList.remove('bg-white', 'hover:bg-gray-100');
            }

            setTimeout(() => map.invalidateSize(), 100);
        }

        // === Map View: TPS markers vs Heatmap ===
        const tpsLayer = L.layerGroup();
        const heatmapLayer = L.heatLayer([], {
            radius: 20,
            blur: 20,
            maxZoom: 12,
            max: 1.0,
            gradient: {0.2: '#3b82f6', 0.4: '#22c55e', 0.6: '#eab308', 0.8: '#f97316', 1.0: '#ef4444'}
        });
        const heatmapMarkers = L.layerGroup();
        let currentView = 'tps';

        function setMapView(mode) {
            currentView = mode;
            const btns = {
                'tps': document.getElementById('view-tps'),
                'heatmap': document.getElementById('view-heatmap'),
                'votes': document.getElementById('view-votes')
            };
            const legendTps = document.getElementById('legend-tps');
            const legendHeat = document.getElementById('legend-heatmap');
            const partyFilter = document.getElementById('party-filter');

            Object.entries(btns).forEach(([key, btn]) => {
                if (key === mode) {
                    btn.classList.add('bg-white', 'shadow', 'text-primary', 'font-semibold');
                    btn.classList.remove('text-gray-500');
                } else {
                    btn.classList.remove('bg-white', 'shadow', 'text-primary', 'font-semibold');
                    btn.classList.add('text-gray-500');
                }
            });

            map.removeLayer(tpsLayer);
            map.removeLayer(heatmapMarkers);
            map.removeLayer(heatmapLayer);

            if (mode === 'tps') {
                legendTps.classList.remove('hidden');
                legendHeat.classList.add('hidden');
                partyFilter.classList.add('hidden');
                tpsLayer.addTo(map);
            } else if (mode === 'votes') {
                legendTps.classList.add('hidden');
                legendHeat.classList.remove('hidden');
                partyFilter.classList.add('hidden');
                heatmapLayer.addTo(map);
                loadVotesHeatmap('');
            } else {
                legendTps.classList.add('hidden');
                legendHeat.classList.remove('hidden');
                partyFilter.classList.remove('hidden');
                heatmapLayer.addTo(map);
                loadHeatmap(document.getElementById('party-filter').value);
            }
        }

        async function loadVotesHeatmap(candidate) {
            try {
                const qs = candidate ? `&candidate=${encodeURIComponent(candidate)}` : '';
                const url = `/pemenangan/api/dashboard/heatmap.php?source=votes${qs}`;
                const res = await fetch(url);
                const json = await res.json();
                if (!json.success) return;

                const pts = json.data.points;
                const heatData = pts.map(p => [p.lat, p.lng, p.intensity]);
                heatmapLayer.setLatLngs(heatData);

                if (pts.length > 0) {
                    const max = json.data.summary.max_intensity;
                    heatmapLayer.options.max = max > 0 ? max : 1;
                }

                document.getElementById('heatmap-summary').textContent =
                    json.data.summary.total_villages.toLocaleString() + ' desa · ' +
                    json.data.summary.tps_votes_in.toLocaleString() + ' TPS masuk · ' +
                    json.data.summary.total_votes.toLocaleString() + ' suara';

                heatmapMarkers.clearLayers();
                const max = json.data.summary.max_intensity || 1;
                pts.forEach(p => {
                    const ratio = p.intensity / max;
                    const r = Math.max(2500, ratio * 7000);
                    const color = ratio > 0.7 ? '#ef4444' : ratio > 0.4 ? '#f97316' : '#eab308';
                    const rows = p.candidates.slice(0, 5).map(c =>
                        `<div>${c.name}: <b>${c.votes.toLocaleString()}</b></div>`).join('');
                    const circle = L.circle([p.lat, p.lng], {
                        radius: r, color: color, fillColor: color,
                        fillOpacity: 0.18, weight: 0
                    });
                    circle.bindPopup(`
                        <div style="font-size:13px;min-width:150px">
                            <div style="font-weight:700;margin-bottom:4px">${p.label}</div>
                            <div style="color:#666">${p.regency}</div>
                            <div style="margin-top:4px">Suara masuk: <b>${p.votes_in}</b>/${p.tps} TPS</div>
                            <div>Total suara sah: <b>${p.total_votes.toLocaleString()}</b></div>
                            <div style="margin-top:4px">${rows}</div>
                        </div>
                    `);
                    heatmapMarkers.addLayer(circle);
                });
                heatmapMarkers.addTo(map);
            } catch (err) {
                console.error('Error loading votes heatmap:', err);
            }
        }

        async function loadHeatmap(party) {
            try {
                const url = `/pemenangan/api/dashboard/heatmap.php?party=${encodeURIComponent(party)}`;
                const res = await fetch(url);
                const json = await res.json();
                if (!json.success) return;

                const pts = json.data.points;
                const heatData = pts.map(p => [p.lat, p.lng, p.intensity]);
                heatmapLayer.setLatLngs(heatData);

                if (pts.length > 0) {
                    const max = json.data.summary.max_village_supporters;
                    heatmapLayer.options.max = max > 0 ? max : 1;
                }

                document.getElementById('heatmap-summary').textContent =
                    json.data.summary.total_villages.toLocaleString() + ' desa · ' +
                    json.data.summary.total_supporters.toLocaleString() + ' pendukung';

                // Tambahkan clickable markers di atas heatmap
                heatmapMarkers.clearLayers();
                const max = json.data.summary.max_village_supporters || 1;
                pts.forEach(p => {
                    const ratio = p.supporters / max;
                    const r = Math.max(3000, ratio * 8000);
                    const color = ratio > 0.7 ? '#ef4444' : ratio > 0.4 ? '#f59e0b' : '#3b82f6';
                    const circle = L.circle([p.lat, p.lng], {
                        radius: r, color: color, fillColor: color,
                        fillOpacity: 0.15, weight: 0
                    });
                    circle.bindPopup(`
                        <div style="font-size:13px;min-width:140px">
                            <div style="font-weight:700;margin-bottom:4px">${p.label}</div>
                            <div style="color:#666">${p.regency}</div>
                            <div style="margin-top:4px">Pendukung: <b>${p.supporters.toLocaleString()}</b></div>
                            <div>DPT: ${p.dpt.toLocaleString()} · TPS: ${p.tps}</div>
                            <div>Indeks: ${p.index}%</div>
                        </div>
                    `);
                    heatmapMarkers.addLayer(circle);
                });
                heatmapMarkers.addTo(map);
            } catch (err) {
                console.error('Error loading heatmap:', err);
            }
        }

        async function loadTpsMap() {
            try {
                const response = await fetch('/pemenangan/api/regions/tps_map.php');
                const data = await response.json();
                if (!data.success) return;

                const markers = [];
                data.data.tps.forEach(tps => {
                    if (tps.latitude === null || tps.longitude === null) return;

                    const color = tps.witness_verified == 1 ? '#10b981' 
                        : tps.has_witness == 1 ? '#f59e0b' : '#ef4444';

                    const marker = L.marker([parseFloat(tps.latitude), parseFloat(tps.longitude)], {
                        icon: makeIcon(color)
                    });

                    const statusText = tps.witness_verified == 1 ? '✅ Saksi terverifikasi'
                        : tps.has_witness == 1 ? '🟡 Saksi menunggu verifikasi' : '🔴 Belum ada saksi';
                    const voteText = tps.has_vote == 1 ? '🗳️ Data suara masuk' : '';

                    marker.bindPopup(`
                        <div style="font-size:13px;min-width:160px">
                            <div style="font-weight:700;margin-bottom:4px">TPS ${tps.tps_number}</div>
                            <div>${tps.village_name}</div>
                            <div style="color:#666">${tps.district_name}</div>
                            <div style="color:#666">${tps.regency_name}</div>
                            <div style="margin-top:4px">DPT: <b>${Number(tps.total_dpt).toLocaleString()}</b></div>
                            <div style="margin-top:2px">${statusText} ${voteText}</div>
                        </div>
                    `);
                    markers.push(marker);
                    tpsLayer.addLayer(marker);
                });

                // Fit view ke semua pin jika ada
                if (data.data.bounds && markers.length > 0) {
                    const bounds = [[data.data.bounds.min_lat, data.data.bounds.min_lng],
                                    [data.data.bounds.max_lat, data.data.bounds.max_lng]];
                    map.fitBounds(bounds, { padding: [30, 30] });
                }

                // Default: tampilkan TPS markers
                tpsLayer.addTo(map);
            } catch (error) {
                console.error('Error loading Tps map:', error);
            }
        }

        // Load Dashboard Stats
        async function loadStats() {
            try {
                const token = localStorage.getItem('token');
                const response = await fetch('/pemenangan/api/dashboard/stats.php', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('total-tps').textContent = data.data.tps.total.toLocaleString();
                    document.getElementById('witness-verified').textContent = data.data.witnesses.verified.toLocaleString();
                    document.getElementById('votes-count').textContent = data.data.votes.total.toLocaleString();
                    
                    const progress = data.data.tps.total > 0 
                        ? Math.round((data.data.votes.verified / data.data.tps.total) * 100) 
                        : 0;
                    document.getElementById('percentage').textContent = progress + '%';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Real-time Recapitulation (poll setiap 30 detik)
        async function loadRealtime() {
            try {
                const response = await fetch('/pemenangan/api/dashboard/realtime.php');
                const data = await response.json();
                if (!data.success) return;

                document.getElementById('realtime-time').textContent =
                    '· Diperbarui ' + new Date(data.data.generated_at).toLocaleTimeString('id-ID');

                const box = document.getElementById('realtime-candidates');
                box.innerHTML = '';
                data.data.candidates.slice(0, 8).forEach(c => {
                    const el = document.createElement('div');
                    el.className = 'bg-white/10 backdrop-blur rounded-lg p-3 text-left';
                    const name = document.createElement('div');
                    name.className = 'flex items-center justify-between gap-2';
                    const n = document.createElement('span');
                    n.className = 'truncate text-sm font-medium';
                    n.textContent = c.name;
                    const v = document.createElement('span');
                    v.className = 'font-bold text-base';
                    v.textContent = c.votes.toLocaleString();
                    name.append(n, v);
                    const bar = document.createElement('div');
                    bar.className = 'mt-1 h-1.5 bg-white/20 rounded overflow-hidden';
                    const fill = document.createElement('div');
                    fill.className = 'h-full bg-green-400 rounded';
                    fill.style.width = Math.min(100, c.pct) + '%';
                    bar.appendChild(fill);
                    const pct = document.createElement('div');
                    pct.className = 'text-xs text-white/60 mt-0.5';
                    pct.textContent = c.pct + '% suara masuk';
                    el.append(name, bar, pct);
                    box.appendChild(el);
                });

                const cov = data.data.coverage;
                document.getElementById('realtime-coverage').textContent =
                    'Cakupan: ' + cov.verified_tps + '/' + cov.total_tps + ' TPS terverifikasi (' + cov.pct + '%) · ' +
                    'Suara sah: ' + data.data.votes.sum_verified.toLocaleString() + ' · ' +
                    'Tidak sah: ' + data.data.votes.invalid.toLocaleString();
            } catch (error) {
                console.error('Error loading realtime:', error);
            }
        }

        loadTpsMap();
        loadStats();
        loadRealtime();
        setInterval(loadRealtime, 30000);
    </script>
</body>
</html>
