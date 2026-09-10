<?php

declare(strict_types=1);

const BASE_URL = 'https://raw.githubusercontent.com/razanfawwaz/pilkada-scrap/main/';
const PROV_KODE = '18';

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'sipemenang';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';
        $pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function fetch_json(string $url, int $tries = 3): array
{
    for ($i = 1; $i <= $tries; $i++) {
        $ctx = stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'Sipemenang-Import/1.0']]);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data)) return $data;
        }
        echo "  retry {$i}/{$tries} gagal mengambil {$url}\n";
        sleep(2 * $i);
    }
    throw new RuntimeException("Gagal fetch: {$url}");
}

function ensure_tables(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `kpu_calon` (
        `id` VARCHAR(10) PRIMARY KEY,
        `jenis` ENUM('pkwkp','pkwkk') NOT NULL,
        `region_kode` CHAR(4) NULL,
        `nama` VARCHAR(255) NOT NULL,
        `nomor_urut` INT NOT NULL,
        `warna` VARCHAR(7) NULL,
        `ts` VARCHAR(30) NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `kpu_kecamatan` (
        `region_kode` CHAR(6) NOT NULL,
        `jenis` ENUM('pkwkp','pkwkk') NOT NULL,
        `total_tps` INT NOT NULL DEFAULT 0,
        `progres_tps` INT NOT NULL DEFAULT 0,
        `persen` DECIMAL(6,2) NULL,
        `ts` VARCHAR(30) NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`region_kode`,`jenis`),
        KEY `idx_kpu_kec_jenis` (`jenis`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `kpu_rekap` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `region_kode` CHAR(6) NOT NULL,
        `jenis` ENUM('pkwkp','pkwkk') NOT NULL,
        `calon_kode` VARCHAR(10) NOT NULL,
        `suara` INT NOT NULL DEFAULT 0,
        UNIQUE KEY `uniq_kpu_rekap` (`region_kode`,`jenis`,`calon_kode`),
        KEY `idx_kpu_rekap_jenis` (`jenis`),
        CONSTRAINT `fk_kpu_rekap_kec` FOREIGN KEY (`region_kode`,`jenis`) REFERENCES `kpu_kecamatan`(`region_kode`,`jenis`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = $pdo->query('SHOW COLUMNS FROM districts')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('kode', $columns, true)) {
        $pdo->exec("ALTER TABLE `districts` ADD COLUMN `kode` CHAR(6) NULL AFTER `name`");
    }
    $columns = $pdo->query('SHOW COLUMNS FROM villages')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('kode', $columns, true)) {
        $pdo->exec("ALTER TABLE `villages` ADD COLUMN `kode` CHAR(10) NULL AFTER `name`");
    }
}

function norm_name(string $n): string
{
    $n = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $n) ?: $n;
    return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $n));
}

function build_kode_maps(string $dataFile): array
{
    // Map "regency_id|norm_name" => kode 6-digit, dalam urutan prioritas: fix, csv, dtxt
    $fix = [];
    $csv = [];
    $dtxt = [];

    // Relasi resmi BPS-Kemendagri (nama dagri + nama bps)
    $ctx = stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'Sipemenang-Import/1.0']]);
    $csvRaw = @file_get_contents('https://raw.githubusercontent.com/zakiego/Kode-Wilayah-Administrasi-Indonesia-Relasi-BPS-Kemendagri/main/csv/kecamatan.csv', false, $ctx);
    if ($csvRaw !== false) {
        $lines = explode("\n", trim($csvRaw));
        foreach ($lines as $i => $ln) {
            if ($i === 0) continue;
            $c = str_getcsv($ln);
            if (isset($c[1], $c[2], $c[3]) && substr($c[2], 0, 2) === PROV_KODE && preg_match('/^\d{6}$/', $c[2])) {
                $reg = substr($c[2], 0, 4);
                $csv[$reg . '|' . norm_name($c[3])] = $c[2]; // nama dagri
                $csv[$reg . '|' . norm_name($c[1])] = $c[2]; // nama bps
            }
        }
        echo "  kode kecamatan dari relasi BPS-Kemendagri: " . count($csv) . "\n";
    } else {
        echo "  peringatan: gagal ambil CSV relasi BPS-Kemendagri\n";
    }

    // Koreksi manual (ejaan/ejaan lama + pemekaran baru)
    $manual = [
        '1808|UMPUSEMENGUK'    => '180815', // pemekaran baru, belum ada di CSV (CSV menganggap 180801=Blambangan Umpu)
        '1813|BENGKUNAT'       => '181311', // CSV menulis "BANGKUNAT"
        '1807|BANDARSRIBAWONO' => '180715', // CSV menulis "BANDAR SRIBHAWONO" (salah eja)
    ];
    foreach ($manual as $k => $kode) $fix[$k] = $kode;

    // Fallback dari config/data.txt (cukup untuk yang tidak ada di CSV)
    if (is_file($dataFile)) {
        $raw = file_get_contents($dataFile);
        preg_match('/INSERT INTO `kecamatan`[\s\S]*?\bVALUES\s+([\s\S]+?);/i', $raw, $mKec);
        if (!empty($mKec[1])) {
            foreach (preg_split('/,\s*\(/i', $mKec[1]) as $ln) {
                $ln = trim($ln, " ()\r\n");
                if (preg_match('/^(\d+),\s*(\d+),\s*\x27([^\x27]+)\x27,\s*\x27(\d{6})\x27/i', $ln, $mt)) {
                    $dtxt[$mt[2] . '|' . norm_name($mt[3])] = $mt[4];
                }
            }
            echo "  kode kecamatan pendukung dari data.txt: " . count($dtxt) . "\n";
        }
    }

    return [$fix, $csv, $dtxt];
}

function sync_kode(PDO $pdo, string $dataFile): void
{
    [$fix, $csv, $dtxt] = build_kode_maps($dataFile);

    $rekapSet = $pdo->query("SELECT DISTINCT region_kode FROM kpu_kecamatan WHERE jenis='pkwkp'")->fetchAll(PDO::FETCH_COLUMN);
    $rekapSet = array_flip($rekapSet);

    // District pemekaran yang ada di rekap pilkada tapi belum ada di data.txt (snapshot lama)
    $add = [
        ['181310', '1813', 'Ngaras'], // pemekaran Pesisir Barat, ada di rekap pilkada (18 TPS)
    ];
    foreach ($add as [$id, $reg, $name]) {
        $exists = $pdo->query("SELECT COUNT(*) FROM districts WHERE id = '{$id}'")->fetchColumn();
        if (!$exists) {
            $pdo->prepare("INSERT INTO districts (id, regency_id, name) VALUES (?, ?, ?)")->execute([$id, $reg, $name]);
            echo "  district baru dibuat: {$id} {$name}\n";
        }
    }

    $rows = $pdo->query('SELECT id, regency_id, name FROM districts')->fetchAll();
    $matchedKec = 0;
    $unmatched = [];
    foreach ($rows as $r) {
        $key = $r['regency_id'] . '|' . norm_name($r['name']);
        $kode = $fix[$key] ?? $csv[$key] ?? $dtxt[$key] ?? null;
        if ($kode !== null && isset($rekapSet[$kode])) {
            $pdo->prepare('UPDATE districts SET kode = ? WHERE id = ?')->execute([$kode, $r['id']]);
            $matchedKec++;
        } else {
            $pdo->prepare('UPDATE districts SET kode = NULL WHERE id = ?')->execute([$r['id']]);
            $unmatched[] = $r['name'] . '(' . $r['regency_id'] . ')';
        }
    }
    echo "kecamatan ter-match kode: {$matchedKec}/" . count($rows) . "\n";
    if ($unmatched) {
        echo "  TIDAK match: " . implode(' | ', $unmatched) . "\n";
    }

    // Desa: hanya kode resmi 10 digit dari data.txt (key: norm(kec)|norm(desa))
    $districtNames = [];
    foreach ($rows as $r) $districtNames[$r['id']] = $r['name'];
    $desaMap = []; // norm(kecName).'|'.norm(desaName) => kode
    if (is_file($dataFile)) {
        $raw = file_get_contents($dataFile);
        preg_match('/INSERT INTO `desa`[\s\S]*?\bVALUES\s+([\s\S]+?);/i', $raw, $mDesa);
        if (!empty($mDesa[1])) {
            foreach (preg_split('/,\s*\(/i', $mDesa[1]) as $ln) {
                $ln = trim($ln, " ()\r\n");
                if (preg_match('/^(\d+),\s*(\d+),\s*\x27([^\x27]+)\x27,\s*\x27(\d{10})\x27/i', $ln, $mt)) {
                    if (!isset($districtNames[$mt[2]])) continue;
                    $desaMap[norm_name($districtNames[$mt[2]]) . '|' . norm_name($mt[3])] = $mt[4];
                }
            }
            echo "  desa kode resmi (10 digit) dari data.txt: " . count($desaMap) . "\n";
        }
    }

    $rowsV = $pdo->query('SELECT id, district_id, name FROM villages')->fetchAll();
    $matchedDesa = 0;
    foreach ($rowsV as $r) {
        $key = norm_name($districtNames[$r['district_id']] ?? '') . '|' . norm_name($r['name']);
        $kode = $desaMap[$key] ?? null;
        $stmt = $pdo->prepare('UPDATE villages SET kode = ? WHERE id = ?');
        if ($kode !== null) {
            $stmt->execute([$kode, $r['id']]);
            $matchedDesa++;
        } else {
            $stmt->execute([null, $r['id']]);
        }
    }

    // Bersihkan sisa kode desa 9-digit (format lama) agar kolom hanya berisi kode resmi 10 digit
    $pdo->exec("UPDATE villages SET kode = NULL WHERE kode IS NOT NULL AND CHAR_LENGTH(kode) = 9");
    echo "desa ter-match kode resmi: {$matchedDesa}/" . count($rowsV) . "\n";
}

function import_calons(PDO $pdo): void
{
    echo "Import daftar calon...\n";
    $pkwkp = fetch_json(BASE_URL . 'paslon/pkwkp.json');
    $count = 0;
    $stmt = $pdo->prepare("INSERT INTO kpu_calon (id, jenis, region_kode, nama, nomor_urut, warna, ts)
        VALUES (:id, 'pkwkp', NULL, :nama, :no, :warna, :ts)
        ON DUPLICATE KEY UPDATE nama=VALUES(nama), nomor_urut=VALUES(nomor_urut), warna=VALUES(warna), ts=VALUES(ts)");
    foreach (($pkwkp[PROV_KODE] ?? []) as $kode => $c) {
        $stmt->execute(['id' => (string)$kode, 'nama' => $c['nama'], 'no' => (int)$c['nomor_urut'], 'warna' => $c['warna'] ?? null, 'ts' => $c['ts'] ?? null]);
        $count++;
    }
    echo "  calon gubernur: {$count}\n";

    $pkwkk = fetch_json(BASE_URL . 'paslon/pkwkk.json');
    $countkab = 0;
    $stmtK = $pdo->prepare("INSERT INTO kpu_calon (id, jenis, region_kode, nama, nomor_urut, warna, ts)
        VALUES (:id, 'pkwkk', :region, :nama, :no, :warna, :ts)
        ON DUPLICATE KEY UPDATE nama=VALUES(nama), nomor_urut=VALUES(nomor_urut), warna=VALUES(warna), ts=VALUES(ts)");
    $regencies = $pdo->query("SELECT id FROM regencies WHERE province_id = '" . PROV_KODE . "'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($regencies as $kab) {
        foreach (($pkwkk[$kab] ?? []) as $kode => $c) {
            $stmtK->execute(['id' => (string)$kode, 'region' => $kab, 'nama' => $c['nama'], 'no' => (int)$c['nomor_urut'], 'warna' => $c['warna'] ?? null, 'ts' => $c['ts'] ?? null]);
            $countkab++;
        }
    }
    echo "  calon bupati/walikota: {$countkab}\n";
}

function import_rekap(PDO $pdo): void
{
    $regencies = $pdo->query("SELECT id FROM regencies WHERE province_id = '" . PROV_KODE . "'")->fetchAll(PDO::FETCH_COLUMN);
    $stmtKec = $pdo->prepare("INSERT INTO kpu_kecamatan (region_kode, jenis, total_tps, progres_tps, persen, ts)
        VALUES (:kode, :jenis, :total, :progres, :persen, :ts)
        ON DUPLICATE KEY UPDATE total_tps=VALUES(total_tps), progres_tps=VALUES(progres_tps), persen=VALUES(persen), ts=VALUES(ts)");
    $stmtRkp = $pdo->prepare("INSERT INTO kpu_rekap (region_kode, jenis, calon_kode, suara)
        VALUES (:kode, :jenis, :calon, :suara)
        ON DUPLICATE KEY UPDATE suara=VALUES(suara)");

    foreach (['pkwkp', 'pkwkk'] as $jenis) {
        foreach ($regencies as $kab) {
            echo "Fetch rekap {$jenis}/{$kab}.json...\n";
            $data = fetch_json(BASE_URL . "{$jenis}/" . PROV_KODE . "/{$kab}.json");
            $table = $data['tungsura']['table'] ?? [];
            foreach ($table as $kecKode => $entry) {
                $prog = $entry['progres'] ?? [];
                $stmtKec->execute([
                    'kode' => $kecKode,
                    'jenis' => $jenis,
                    'total' => (int)($prog['total'] ?? 0),
                    'progres' => (int)($prog['progres'] ?? 0),
                    'persen' => isset($prog['persen']) ? (float)$prog['persen'] : null,
                    'ts' => $data['ts'] ?? null,
                ]);
                foreach ($entry as $calon => $suara) {
                    if (in_array($calon, ['psu', 'status_progress', 'progres'], true)) continue;
                    $stmtRkp->execute(['kode' => $kecKode, 'jenis' => $jenis, 'calon' => $calon, 'suara' => (int)$suara]);
                }
            }
        }
    }
}

function pad_tps(PDO $pdo): void
{
    $rows = $pdo->query("SELECT d.id AS district_id, d.regency_id, k.total_tps FROM districts d
        JOIN kpu_kecamatan k ON k.region_kode = d.kode
        WHERE k.jenis = 'pkwkp'")->fetchAll();
    echo "Kecamatan dengan data resmi: " . count($rows) . "\n";

    $inserted = 0;
    foreach ($rows as $row) {
        $current = (int)$pdo->query("SELECT COUNT(*) FROM tps t JOIN villages v ON v.id = t.village_id WHERE v.district_id = '" . $row['district_id'] . "'")->fetchColumn();
        $official = (int)$row['total_tps'];
        if ($official <= $current) continue;

        $diff = $official - $current;
        $villages = $pdo->query("SELECT v.id FROM villages v WHERE v.district_id = '{$row['district_id']}'")->fetchAll(PDO::FETCH_COLUMN);
        if (!$villages) {
            // Kecamatan baru (mis. pemekaran) belum punya desa di DB -> buat desa placeholder agar TPS bisa tersebar
            $maxVid = (int)$pdo->query("SELECT MAX(CAST(id AS UNSIGNED)) FROM villages")->fetchColumn();
            $newVid = (string)($maxVid + 1);
            $kecName = $pdo->query("SELECT name FROM districts WHERE id = '{$row['district_id']}'")->fetchColumn();
            $pdo->prepare("INSERT INTO villages (id, district_id, name, kode) VALUES (?, ?, ?, NULL)")->execute([$newVid, $row['district_id'], $kecName ?: 'Desa']);
            $villages = [$newVid];
            echo "  {$row['district_id']}: desa placeholder dibuat ({$kecName})\n";
        }

        $avg = $pdo->query("SELECT AVG(t.latitude) AS lat, AVG(t.longitude) AS lng FROM tps t JOIN villages v ON v.id = t.village_id WHERE v.district_id = '{$row['district_id']}'")->fetch();
        if (!$avg || $avg['lat'] === null) {
            // Kecamatan baru belum punya TPS -> pakai rata-rata koordinat satu kabupaten agar posisinya wajar
            $rage = $pdo->query("SELECT AVG(t.latitude) AS lat, AVG(t.longitude) AS lng
                FROM tps t JOIN villages v ON v.id = t.village_id JOIN districts d ON d.id = v.district_id
                WHERE d.regency_id = '{$row['regency_id']}'")->fetch();
            if ($rage && $rage['lat'] !== null) $avg = $rage;
        }
        $lat = ($avg && $avg['lat'] !== null) ? (float)$avg['lat'] : -5.0;
        $lng = ($avg && $avg['lng'] !== null) ? (float)$avg['lng'] : 105.0;

        $maxId = (int)$pdo->query("SELECT MAX(CAST(id AS UNSIGNED)) FROM tps")->fetchColumn();

        foreach (range(1, $diff) as $i) {
            $maxId++;
            $village = $villages[$i % count($villages)];
            $tpsNo = (int)$pdo->query("SELECT COALESCE(MAX(tps_number),0)+1 FROM tps WHERE village_id = '" . $village . "'")->fetchColumn();
            $jl = ($lat + (mt_rand(-1000, 1000) / 1000000));
            $jng = ($lng + (mt_rand(-1000, 1000) / 1000000));
            $stmt = $pdo->prepare("INSERT INTO tps (id, village_id, tps_number, total_dpt, latitude, longitude)
                VALUES (:id, :village, :no, 0, :lat, :lng)");
            $stmt->execute(['id' => (string)$maxId, 'village' => $village, 'no' => $tpsNo, 'lat' => $jl, 'lng' => $jng]);
            $inserted++;
        }
        echo "  {$row['district_id']}: {$current} -> {$official}\n";
    }
    echo "TPS baru ditambahkan: {$inserted}\n";
}

function exact_tps(PDO $pdo): void
{
    // 1) Persiskan tiap kecamatan berdata resmi: buang TPS sintetis kelebihan
    $rows = $pdo->query("SELECT d.id AS district_id, k.total_tps FROM districts d
        JOIN kpu_kecamatan k ON k.region_kode = d.kode
        WHERE k.jenis = 'pkwkp'")->fetchAll();
    $deleted = 0;
    foreach ($rows as $row) {
        $current = (int)$pdo->query("SELECT COUNT(*) FROM tps t JOIN villages v ON v.id = t.village_id WHERE v.district_id = '" . $row['district_id'] . "'")->fetchColumn();
        $official = (int)$row['total_tps'];
        if ($current <= $official) continue;
        $excess = $current - $official;
        // Hapus TPS id terbesar (baru/padded); saksi & suara ikut terhapus via ON DELETE CASCADE
        $pdo->exec("DELETE FROM tps WHERE id IN (
            SELECT id FROM (
                SELECT t.id FROM tps t JOIN villages v ON v.id = t.village_id
                WHERE v.district_id = '" . $row['district_id'] . "'
                ORDER BY CAST(t.id AS UNSIGNED) DESC LIMIT {$excess}
            ) x)");
        $deleted += $excess;
        echo "  {$row['district_id']}: {$current} -> {$official} (-{$excess})\n";
    }
    echo "TPS berlebih dihapus: {$deleted}\n";

    // 2) Kecamatan tanpa data resmi (mis. Bengkunat Belimbing, digabung dgn Bengkunat saat pilkada):
    //    hapus semua TPS sintetisnya agar total persis sama dengan angka resmi.
    $noKode = $pdo->query("SELECT d.id FROM districts d
        LEFT JOIN kpu_kecamatan k ON k.region_kode = d.kode AND k.jenis = 'pkwkp'
        WHERE k.region_kode IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    $removed = 0;
    foreach ($noKode as $did) {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM tps t JOIN villages v ON v.id = t.village_id WHERE v.district_id = '" . $did . "'")->fetchColumn();
        if ($cnt === 0) continue;
        $pdo->exec("DELETE FROM tps WHERE id IN (
            SELECT id FROM (
                SELECT t.id FROM tps t JOIN villages v ON v.id = t.village_id WHERE v.district_id = '" . $did . "'
            ) x)");
        $removed += $cnt;
        echo "  {$did}: tanpa data resmi -> semua TPS hapus (-{$cnt})\n";
    }
    if ($removed > 0) echo "TPS tanpa data resmi dihapus: {$removed}\n";
}

function report(PDO $pdo): void
{
    echo "\n=== Ringkasan ===\n";
    echo "Kecamatan dengan kode resmi: " . $pdo->query("SELECT COUNT(*) FROM districts WHERE kode IS NOT NULL")->fetchColumn() . "/" . $pdo->query("SELECT COUNT(*) FROM districts")->fetchColumn() . "\n";
    echo "Desa dengan kode resmi (10 digit): " . $pdo->query("SELECT COUNT(*) FROM villages WHERE kode IS NOT NULL AND CHAR_LENGTH(kode)=10")->fetchColumn() . "/" . $pdo->query("SELECT COUNT(*) FROM villages")->fetchColumn() . "\n";
    echo "Calon gubernur: " . $pdo->query("SELECT COUNT(*) FROM kpu_calon WHERE jenis='pkwkp'")->fetchColumn() . "\n";
    echo "Calon bupati/walikota: " . $pdo->query("SELECT COUNT(*) FROM kpu_calon WHERE jenis='pkwkk'")->fetchColumn() . "\n";
    echo "Kecamatan rekap gubernur: " . $pdo->query("SELECT COUNT(*) FROM kpu_kecamatan WHERE jenis='pkwkp'")->fetchColumn() . "\n";
    echo "Kecamatan rekap bupati: " . $pdo->query("SELECT COUNT(*) FROM kpu_kecamatan WHERE jenis='pkwkk'")->fetchColumn() . "\n";
    echo "Baris rekap suara: " . $pdo->query("SELECT COUNT(*) FROM kpu_rekap")->fetchColumn() . "\n";
    $totalResmi = (int)$pdo->query("SELECT SUM(total_tps) FROM kpu_kecamatan WHERE jenis='pkwkp'")->fetchColumn();
    $totalDb = (int)$pdo->query("SELECT COUNT(*) FROM tps")->fetchColumn();
    echo "TPS resmi KPU Pilkada 2024 (gubernur): " . number_format($totalResmi) . " | TPS di DB: " . number_format($totalDb) . "\n";
    $rows = $pdo->query("SELECT d.name AS kec, r.name AS kab, COUNT(t.id) AS tps, COALESCE(SUM(t.total_dpt),0) AS dpt, k.total_tps AS resmi
        FROM districts d JOIN regencies r ON r.id = d.regency_id
        JOIN kpu_kecamatan k ON k.region_kode = d.kode AND k.jenis = 'pkwkp'
        LEFT JOIN villages v ON v.district_id = d.id
        LEFT JOIN tps t ON t.village_id = v.id
        GROUP BY d.id ORDER BY r.id, d.id")->fetchAll();
    foreach ($rows as $r) {
        $flag = $r['tps'] >= $r['resmi'] ? 'OK' : "KURANG " . ((int)$r['resmi'] - (int)$r['tps']);
        printf("  %-6s %-24s %-4d TPS (resmi %-4d) DPT %10s [%s]\n", $r['kab'], $r['kec'], $r['tps'], $r['resmi'], number_format((int)$r['dpt']), $flag);
    }
}

$args = $argv[1] ?? '';
$flags = [];
foreach ($argv as $a) {
    if ($a[0] === '-') $flags[] = $a;
}

$pdo = db();
echo "== Import Data Resmi KPU (Pilkada 2024) ==\n";
ensure_tables($pdo);

if (in_array('--sync-kode', $flags, true) || !$flags) {
    echo "[1/4] Sync kode wilayah dari config/data.txt\n";
    sync_kode($pdo, __DIR__ . '/../config/data.txt');
}
if (in_array('--calons', $flags, true) || !$flags) {
    echo "[2/4] Import daftar calon\n";
    import_calons($pdo);
}
if (in_array('--rekap', $flags, true) || !$flags) {
    echo "[3/4] Import rekap suara per kecamatan\n";
    import_rekap($pdo);
}
if (in_array('--pad-tps', $flags, true) || !$flags) {
    echo "[4/4] Susun ulang jumlah TPS per kecamatan\n";
    pad_tps($pdo);
}
if (in_array('--exact', $flags, true) || in_array('--pad-tps', $flags, true) || !$flags) {
    echo "[5/5] Persiskan jumlah TPS sesuai data resmi\n";
    exact_tps($pdo);
}
report($pdo);