# Checklist Pekerjaan Sipemenang

Referensi: [Issue #1 - Sistem Informasi Pemenangan Pilkada/Pileg Digital (Sipemenang)](https://github.com/Basuki-rahmat/pemenangan/issues/1)

---

## 2. Fitur Dynamic Theme (Warna Partai)

- [x] Buat CSS Variables `:root` untuk warna partai
- [x] Buat helper class `.bg-party-primary`, `.text-party-primary`, `.btn-party`
- [x] Buat admin panel untuk konfigurasi warna per partai (`admin/theme.php`)

## 3. Database Schema

- [x] Buat migration script SQL untuk semua tabel (10 tabel)
- [x] Setup foreign key relationships

## 4. Seeding Data

- [x] Download dataset Wilayah Administrasi Indonesia (Kemendagri)
- [x] Buat script seeder provinsi → kabupaten → kecamatan → desa
- [x] Download dump data TPS KPU Sirekap 2024 (`import_kpu.php`)
- [x] Buat batch insert script untuk TPS
- [ ] Validasi data setelah seeding — **Belum ada script validasi**

## 5. Registrasi Saksi TPS (GPS + Camera)

- [x] HTML5 Geolocation API untuk ambil posisi GPS
- [ ] Camera capture selfie + foto KTP — **Form ada, upload tidak berfungsi**
- [ ] Client-side Canvas Watermarking — **Belum diimplementasi**
- [x] Validasi radius Haversine (saksi harus di lokasi TPS)
- [x] Form registrasi dengan validasi NIK unik
- [x] Status workflow: `pending` → `verified` / `rejected`
- [ ] **File upload handler (KTP + selfie) — 0% belum ada**

## 6. Dashboard & Modul Utama

### 6.1 Peta Kontestasi & Heatmap

- [x] Integrasi Leaflet.js
- [ ] Pemetaan basis massa per wilayah — **Belum ada data basis massa**
- [ ] Heatmap perolehan suara real-time — **Belum diimplementasi**

### 6.2 Quick Count & Real Count

- [x] Grafik pergerakan suara masuk per jam
- [x] Filter: saksi terverifikasi saja
- [ ] Upload foto form C1 Plano — **Form ada, upload tidak berfungsi**
- [x] Auto-hitung rekap per wilayah

### 6.3 Logistik & Honorarium

- [ ] Tracking kehadiran saksi — **Belum diimplementasi**
- [ ] Rekap distribusi dana saksi TPS — **Belum diimplementasi**
- [ ] Export laporan pembayaran — **Belum diimplementasi**

### 6.4 Export Report

- [ ] Export PDF per kelurahan — **Belum diimplementasi**
- [x] Export CSV/Excel per kecamatan/kabupaten — **CSV saja, belum XLSX**
- [ ] Rekapitulasi berjenjang hingga provinsi — **Belum diimplementasi**

## 7. API Design (RESTful)

### Auth

- [x] `POST /api/auth/login`
- [ ] `POST /api/auth/register` — **Belum ada (user management)**
- [ ] `POST /api/auth/logout` — **Belum ada**

### Witness

- [ ] `GET /api/witnesses` — **Belum ada**
- [x] `POST /api/witnesses` (register)
- [ ] `GET /api/witnesses/:id` — **Belum ada**
- [ ] `PUT /api/witnesses/:id` — **Belum ada**
- [ ] `DELETE /api/witnesses/:id` — **Belum ada**

### Vote Results

- [ ] `GET /api/votes` — **Belum ada**
- [x] `POST /api/votes` (submit)
- [ ] `GET /api/votes/summary/:tps_id` — **Belum ada**
- [x] `GET /api/votes/quick-count` — **Tersedia via model**

### Dashboard

- [x] `GET /api/dashboard/stats`
- [ ] `GET /api/dashboard/heatmap` — **Belum ada**
- [ ] `GET /api/dashboard/realtime` — **Belum ada**

### Regions

- [x] `GET /api/provinces`
- [x] `GET /api/regencies/:province_id`
- [x] `GET /api/districts/:regency_id`
- [x] `GET /api/villages/:district_id`
- [x] `GET /api/tps/:village_id`

## 8. Deployment Checklist

- [ ] Setup VPS / Shared Hosting
- [ ] Konfigurasi MySQL 8.0
- [ ] Setup SSL certificate (HTTPS)
- [x] Environment variables (`.env`) — **Ada tapi belum dikustomisasi**
- [ ] Database backup automation
- [ ] Monitoring & logging

---

## Bugs & Issues Kritis

- [ ] **Admin panel tidak ada autentikasi** — semua halaman admin bisa diakses tanpa login
- [ ] **API admin tidak ada auth check** — siapa saja bisa verify/reject data
- [ ] **Photo upload tidak berfungsi** — form kirim JSON, bukan multipart
- [ ] **`.htaccess` kosong** — tidak ada URL rewriting
- [ ] **Activity logs tidak terpakai** — tabel ada, tidak ada kode yang menulis
- [ ] **`config/app.php` tidak pernah di-load** — semua file baca `getenv()` langsung
- [ ] **SQL injection risk di BaseModel** — `$orderBy` tidak di-escape
- [ ] **Tidak ada CORS headers** di API endpoints
- [ ] **Tidak ada rate limiting** di endpoint publik

---

## Ringkasan Status

| Komponen | Status |
|---|---|
| Database Schema & Seeding | ✅ Selesai |
| Dynamic Theme | ✅ Selesai |
| Model ORM | ✅ Selesai |
| Helper Classes | ✅ Selesai |
| Public API | ⚠️ 70% (beberapa endpoint missing) |
| Admin Panel Frontend | ✅ Selesai |
| Autentikasi & Security | ❌ 20% (kritis) |
| File Upload | ❌ 0% |
| Dashboard Heatmap | ❌ 0% |
| Logistik & Honorarium | ❌ 0% |
| Export Report (PDF/XLSX) | ❌ 0% |
| Deployment | ❌ 0% |
