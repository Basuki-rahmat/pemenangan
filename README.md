# SIPEMENANG

Sistem Informasi Pemenangan Pilkada/Pileg Digital

## Features

- 📊 Dashboard Real-time
- 👥 Manajemen Saksi TPS
- 🗳️ Real Count & Quick Count
- 🗺️ Heatmap Peta
- 🎨 Dynamic Theme (Warna Partai)
- 📱 Mobile Friendly (GPS + Camera)

## Tech Stack

- **Backend:** PHP 8.x + PDO
- **Database:** MySQL 8.0+
- **Frontend:** Tailwind CSS + Leaflet.js
- **API:** RESTful

## Installation

### 1. Clone Repository

```bash
git clone https://github.com/Basuki-rahmat/pemenangan.git
cd pemenangan
```

### 2. Setup Environment

```bash
cp .env.example .env
# Edit .env sesuai konfigurasi
```

### 3. Install Dependencies

```bash
composer install
```

### 4. Setup Database

```bash
# Buat database
mysql -u root -e "CREATE DATABASE sipemenang"

# Import schema
mysql -u root sipemenang < database/migration.sql

# Jalankan seeder
php database/seeders/provinces.php
```

### 5. Setup Web Server

Untuk Laragon, letakkan folder `pemenangan` di `C:\laragon\www\`

Akses: `http://localhost/pemenangan`

## Default Login

- **Username:** admin
- **Password:** admin123

## API Endpoints

### Auth
- `POST /api/auth/login` - Login

### Witness
- `POST /api/witness/register.php` - Registrasi saksi

### Vote Results
- `POST /api/votes/submit.php` - Submit hasil suara

### Regions
- `GET /api/regions/provinces.php` - List provinsi

### Dashboard
- `GET /api/dashboard/stats.php` - Statistik dashboard

## Project Structure

```
pemenangan/
├── config/           # Configuration files
├── database/         # Migration & seeders
├── public/           # Web root
│   ├── api/          # API endpoints
│   ├── admin/        # Admin pages
│   └── index.php     # Main page
├── src/              # PHP source code
│   ├── Helpers/      # Helper classes
│   └── Models/       # Database models
└── vendor/           # Composer dependencies
```

## License

Private - Basuki Rahmat
