<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\PartySettings;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$partyModel = new PartySettings();
$activeParty = $partyModel->getActive();
$cssVariables = $partyModel->getCssVariables();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Saksi TPS - SIPEMENANG</title>
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
        .gradient-party { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-party-primary shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <a href="/pemenangan/" class="text-white text-xl font-bold">🗳️ SIPEMENANG</a>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">📝 Registrasi Saksi TPS</h1>
            <p class="text-gray-600 mt-2">Lengkapi data diri Anda sebagai saksi TPS</p>
        </div>

        <!-- Registration Form -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <form id="registrationForm" enctype="multipart/form-data">
                <!-- TPS Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Wilayah *</label>
                    <div class="grid grid-cols-2 gap-4">
                        <select id="province" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" required>
                            <option value="">Pilih Provinsi</option>
                        </select>
                        <select id="regency" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" required disabled>
                            <option value="">Pilih Kabupaten</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <select id="district" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" required disabled>
                            <option value="">Pilih Kecamatan</option>
                        </select>
                        <select id="village" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" required disabled>
                            <option value="">Pilih Desa/Kelurahan</option>
                        </select>
                    </div>
                    <div class="mt-4">
                        <select id="tps" class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" required disabled>
                            <option value="">Pilih TPS</option>
                        </select>
                    </div>
                </div>

                <!-- Personal Data -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap *</label>
                    <input type="text" id="full_name" name="full_name" 
                           class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" 
                           placeholder="Masukkan nama lengkap" required>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">NIK (16 digit) *</label>
                    <input type="text" id="nik" name="nik" maxlength="16" pattern="[0-9]{16}"
                           class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" 
                           placeholder="0000000000000000" required>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nomor HP *</label>
                    <input type="tel" id="phone_number" name="phone_number"
                           class="w-full border-gray-300 rounded-lg focus:ring-primary focus:border-primary" 
                           placeholder="08xxxxxxxxxx" required>
                </div>

                <!-- Photo Upload -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Foto KTP *</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-primary transition">
                        <input type="file" id="photo_ktp" accept="image/*" capture="environment" class="hidden" required>
                        <label for="photo_ktp" class="cursor-pointer">
                            <div class="text-gray-500" id="ktp-preview">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <p class="mt-1 text-sm">Klik untuk foto KTP</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Foto Selfie *</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-primary transition">
                        <input type="file" id="photo_selfie" accept="image/*" capture="user" class="hidden" required>
                        <label for="photo_selfie" class="cursor-pointer">
                            <div class="text-gray-500" id="selfie-preview">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <p class="mt-1 text-sm">Klik untuk selfie</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- GPS Location -->
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="font-medium text-blue-800">Lokasi GPS</span>
                    </div>
                    <p id="gps-status" class="text-sm text-gray-600">Mengambil lokasi...</p>
                    <input type="hidden" id="latitude" name="latitude">
                    <input type="hidden" id="longitude" name="longitude">
                    <input type="hidden" id="accuracy" name="accuracy">
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submitBtn" 
                        class="w-full btn-party py-3 px-4 rounded-lg font-semibold text-lg disabled:opacity-50 disabled:cursor-not-allowed">
                    📤 Registrasi Sekarang
                </button>
            </form>
        </div>
    </div>

    <script>
        // GPS Location
        let gpsPosition = null;

        function getGPSLocation() {
            const status = document.getElementById('gps-status');
            
            if (!navigator.geolocation) {
                status.textContent = 'GPS tidak didukung oleh browser ini';
                status.classList.add('text-red-600');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    gpsPosition = position.coords;
                    document.getElementById('latitude').value = position.coords.latitude;
                    document.getElementById('longitude').value = position.coords.longitude;
                    document.getElementById('accuracy').value = position.coords.accuracy;
                    
                    status.textContent = `✅ Lokasi terdeteksi (${position.coords.accuracy.toFixed(0)}m akurasi)`;
                    status.classList.add('text-green-600');
                },
                (error) => {
                    status.textContent = '❌ Gagal mengambil lokasi. Aktifkan GPS di HP Anda.';
                    status.classList.add('text-red-600');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        getGPSLocation();

        // Photo preview
        function setupPhotoPreview(inputId, previewId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            input.addEventListener('change', function () {
                const file = this.files[0];
                if (!file) {
                    preview.innerHTML = '<p class="mt-1 text-sm">Klik untuk foto</p>';
                    preview.querySelector('svg')?.classList.remove('hidden');
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="mx-auto max-h-48 rounded-lg">`;
                    preview.querySelector('svg')?.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            });
        }

        setupPhotoPreview('photo_ktp', 'ktp-preview');
        setupPhotoPreview('photo_selfie', 'selfie-preview');

        // Client-side Canvas Watermarking (koordinat + timestamp)
        function watermarkFile(file) {
            return new Promise((resolve) => {
                const image = new Image();
                const objectUrl = URL.createObjectURL(file);
                image.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = image.width;
                    canvas.height = image.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(image, 0, 0);

                    const now = new Date();
                    const pad = (n) => String(n).padStart(2, '0');
                    const timestamp = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
                    const coords = gpsPosition
                        ? `${gpsPosition.latitude.toFixed(6)}, ${gpsPosition.longitude.toFixed(6)}`
                        : 'Lokasi tidak tersedia';

                    const footerH = Math.max(50, Math.round(canvas.height * 0.06));
                    ctx.fillStyle = 'rgba(0,0,0,0.65)';
                    ctx.fillRect(0, canvas.height - footerH, canvas.width, footerH);

                    const fontSize = Math.max(16, Math.round(canvas.width * 0.025));
                    ctx.font = `bold ${fontSize}px Arial`;
                    ctx.fillStyle = '#ffffff';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(`SIPEMENANG | ${timestamp}`, 12, canvas.height - footerH * 0.68);
                    ctx.fillText(`GPS: ${coords}`, 12, canvas.height - footerH * 0.28);

                    URL.revokeObjectURL(objectUrl);
                    canvas.toBlob((blob) => resolve(blob || file), 'image/jpeg', 0.85);
                };
                image.onerror = () => resolve(file);
                image.src = objectUrl;
            });
        }

        // Load Regions
        async function loadProvinces() {
            const response = await fetch('/pemenangan/api/regions/provinces.php');
            const data = await response.json();
            const select = document.getElementById('province');
            
            if (data.success) {
                data.data.forEach(p => {
                    select.innerHTML += `<option value="${p.id}">${p.name}</option>`;
                });
            }
        }

        loadProvinces();

        async function loadOptions(selectId, url, placeholder) {
            const select = document.getElementById(selectId);
            select.innerHTML = `<option value="">${placeholder}</option>`;
            select.disabled = true;

            const response = await fetch(url);
            const data = await response.json();
            if (data.success && data.data.length > 0) {
                data.data.forEach(item => {
                    select.innerHTML += `<option value="${item.id}">${item.name}</option>`;
                });
                select.disabled = false;
            }
        }

        document.getElementById('province').addEventListener('change', function() {
            resetChildren('regency', ['district', 'village', 'tps']);
            if (this.value) loadOptions('regency', `/pemenangan/api/regions/regencies.php?province_id=${this.value}`, 'Pilih Kabupaten');
        });

        document.getElementById('regency').addEventListener('change', function() {
            resetChildren('district', ['village', 'tps']);
            if (this.value) loadOptions('district', `/pemenangan/api/regions/districts.php?regency_id=${this.value}`, 'Pilih Kecamatan');
        });

        document.getElementById('district').addEventListener('change', function() {
            resetChildren('village', ['tps']);
            if (this.value) loadOptions('village', `/pemenangan/api/regions/villages.php?district_id=${this.value}`, 'Pilih Desa/Kelurahan');
        });

        document.getElementById('village').addEventListener('change', function() {
            resetChildren('tps', []);
            if (this.value) loadOptions('tps', `/pemenangan/api/regions/tps.php?village_id=${this.value}`, 'Pilih TPS');
        });

        function resetChildren(firstId, restIds) {
            const select = document.getElementById(firstId);
            select.innerHTML = `<option value="">Pilih</option>`;
            select.disabled = true;
            restIds.forEach(id => {
                const el = document.getElementById(id);
                el.innerHTML = `<option value="">Pilih</option>`;
                el.disabled = true;
            });
        }

        // Form submission
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Memproses...';

            const ktpInput = document.getElementById('photo_ktp');
            const selfieInput = document.getElementById('photo_selfie');
            const formData = new FormData();
            formData.append('tps_id', document.getElementById('tps').value);
            formData.append('full_name', document.getElementById('full_name').value);
            formData.append('nik', document.getElementById('nik').value);
            formData.append('phone_number', document.getElementById('phone_number').value);
            formData.append('latitude', document.getElementById('latitude').value);
            formData.append('longitude', document.getElementById('longitude').value);
            formData.append('accuracy', document.getElementById('accuracy').value);

            if (!ktpInput.files[0] || !selfieInput.files[0]) {
                alert('❌ Foto KTP dan selfie wajib diunggah');
                submitBtn.disabled = false;
                submitBtn.textContent = '📤 Registrasi Sekarang';
                return;
            }

            try {
                const watermarkKtp = await watermarkFile(ktpInput.files[0]);
                const watermarkSelfie = await watermarkFile(selfieInput.files[0]);
                formData.append('photo_ktp', watermarkKtp, `ktp_${Date.now()}.jpg`);
                formData.append('photo_selfie', watermarkSelfie, `selfie_${Date.now()}.jpg`);

                const response = await fetch('/pemenangan/api/witness/register.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('✅ Registrasi berhasil! Menunggu verifikasi.');
                    window.location.href = '/pemenangan/';
                } else {
                    alert('❌ ' + (result.message || 'Registrasi gagal'));
                }
            } catch (error) {
                alert('❌ Terjadi kesalahan. Silakan coba lagi.');
            }

            submitBtn.disabled = false;
            submitBtn.textContent = '📤 Registrasi Sekarang';
        });
    </script>
</body>
</html>
