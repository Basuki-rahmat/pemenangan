<?php

declare(strict_types=1);

/**
 * Validator: Data setelah seeding
 * Jalankan: php database/validate_seed.php
 *
 * Memeriksa:
 *  1. Foreign key integritas antar tabel wilayah
 *  2. Struktur ID (panjang dan prefix hierarchy)
 *  3. Field wajib tidak kosong / tidak null
 *  4. TPS: total_dpt, koordinat, tps_number unik per desa
 *  5. Tabel KPU (jika ada): relasi calon ↔ kecamatan ↔ rekap
 *  6. Ringkasan jumlah data per tabel
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

$db = Database::getConnection();

$errors   = [];
$warnings = [];
$summary  = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $errors;
    if ($ok) return;
    $errors[] = ($detail !== '' ? "[{$detail}] " : '') . $label;
}

function warn(string $label, string $detail = ''): void
{
    global $warnings;
    $warnings[] = ($detail !== '' ? "[{$detail}] " : '') . $label;
}

// ─── 0. Koneksi & tabel dasar ────────────────────────────────────────────────

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach (['provinces', 'regencies', 'districts', 'villages', 'tps', 'party_settings', 'users'] as $t) {
    check("Tabel {$t} tidak ada", in_array($t, $tables, true));
}

// ─── 1. Ringkasan jumlah data ────────────────────────────────────────────────

echo "=== Ringkasan Data ===\n";

$countMap = [];
foreach (['provinces', 'regencies', 'districts', 'villages', 'tps', 'tps_witnesses', 'vote_results', 'party_settings', 'users', 'activity_logs'] as $t) {
    if (!in_array($t, $tables, true)) {
        warn("Tabel {$t} belum ada — lewati");
        $countMap[$t] = null;
        continue;
    }
    $cnt = (int) $db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    $countMap[$t] = $cnt;
    printf("  %-20s %s\n", $t, number_format($cnt));
}

$hasKPU = in_array('kpu_calon', $tables, true) && in_array('kpu_kecamatan', $tables, true) && in_array('kpu_rekap', $tables, true);
if ($hasKPU) {
    foreach (['kpu_calon', 'kpu_kecamatan', 'kpu_rekap'] as $t) {
        $countMap[$t] = (int) $db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        printf("  %-20s %s\n", $t, number_format($countMap[$t]));
    }
}
echo "\n";

// ─── 2. Foreign key integritas ───────────────────────────────────────────────

echo "=== Validasi Foreign Key ===\n";

if ($countMap['regencies'] > 0) {
    $orphans = (int) $db->query("
        SELECT COUNT(*) FROM regencies r
        LEFT JOIN provinces p ON p.id = r.province_id
        WHERE p.id IS NULL
    ")->fetchColumn();
    check("Regencies dengan province_id tidak valid", $orphans === 0, "{$orphans} baris");
}

if ($countMap['districts'] > 0) {
    $orphans = (int) $db->query("
        SELECT COUNT(*) FROM districts d
        LEFT JOIN regencies r ON r.id = d.regency_id
        WHERE r.id IS NULL
    ")->fetchColumn();
    check("Districts dengan regency_id tidak valid", $orphans === 0, "{$orphans} baris");
}

if ($countMap['villages'] > 0) {
    $orphans = (int) $db->query("
        SELECT COUNT(*) FROM villages v
        LEFT JOIN districts d ON d.id = v.district_id
        WHERE d.id IS NULL
    ")->fetchColumn();
    check("Villages dengan district_id tidak valid", $orphans === 0, "{$orphans} baris");
}

if ($countMap['tps'] > 0) {
    $orphans = (int) $db->query("
        SELECT COUNT(*) FROM tps t
        LEFT JOIN villages v ON v.id = t.village_id
        WHERE v.id IS NULL
    ")->fetchColumn();
    check("TPS dengan village_id tidak valid", $orphans === 0, "{$orphans} baris");
}

if ($countMap['tps_witnesses'] > 0) {
    $orphans = (int) $db->query("
        SELECT COUNT(*) FROM tps_witnesses tw
        LEFT JOIN tps t ON t.id = tw.tps_id
        WHERE t.id IS NULL
    ")->fetchColumn();
    check("Witnesses dengan tps_id tidak valid", $orphans === 0, "{$orphans} baris");
}

if ($countMap['vote_results'] > 0) {
    $orphansTps = (int) $db->query("
        SELECT COUNT(*) FROM vote_results vr
        LEFT JOIN tps t ON t.id = vr.tps_id
        WHERE t.id IS NULL
    ")->fetchColumn();
    check("Vote results dengan tps_id tidak valid", $orphansTps === 0, "{$orphansTps} baris");

    $orphansW = (int) $db->query("
        SELECT COUNT(*) FROM vote_results vr
        LEFT JOIN tps_witnesses tw ON tw.id = vr.witness_id
        WHERE tw.id IS NULL
    ")->fetchColumn();
    check("Vote results dengan witness_id tidak valid", $orphansW === 0, "{$orphansW} baris");
}

echo "\n";

// ─── 3. Struktur ID hierarchy ────────────────────────────────────────────────

echo "=== Validasi Struktur ID ===\n";

// Regency ID: 4 digit, harus diawali province_id (2 digit)
if ($countMap['regencies'] > 0) {
    $badReg = $db->query("
        SELECT r.id, r.name, r.province_id FROM regencies r
        WHERE CHAR_LENGTH(r.id) != 4
           OR r.id NOT REGEXP '^[0-9]+$'
           OR LEFT(r.id, 2) != r.province_id
    ")->fetchAll();
    check("Regency ID harus 4 digit angka dan diawali province_id", empty($badReg), count($badReg) . " baris bermasalah");
    foreach (array_slice($badReg, 0, 5) as $r) {
        warn("  Regency bermasalah: {$r['id']} ({$r['name']}) province_id={$r['province_id']}");
    }
}

// District ID: 6 digit, harus diawali regency_id (4 digit)
if ($countMap['districts'] > 0) {
    $badDist = $db->query("
        SELECT d.id, d.name, d.regency_id FROM districts d
        WHERE CHAR_LENGTH(d.id) != 6
           OR d.id NOT REGEXP '^[0-9]+$'
           OR LEFT(d.id, 4) != d.regency_id
    ")->fetchAll();
    check("District ID harus 6 digit angka dan diawali regency_id", empty($badDist), count($badDist) . " baris bermasalah");
    foreach (array_slice($badDist, 0, 5) as $d) {
        warn("  District bermasalah: {$d['id']} ({$d['name']}) regency_id={$d['regency_id']}");
    }
}

// Village ID: 10 digit, harus diawali district_id (6 digit)
if ($countMap['villages'] > 0) {
    $badVil = $db->query("
        SELECT v.id, v.name, v.district_id FROM villages v
        WHERE CHAR_LENGTH(v.id) != 10
           OR v.id NOT REGEXP '^[0-9]+$'
           OR LEFT(v.id, 6) != v.district_id
    ")->fetchAll();
    check("Village ID harus 10 digit angka dan diawali district_id", empty($badVil), count($badVil) . " baris bermasalah");
    foreach (array_slice($badVil, 0, 5) as $v) {
        warn("  Village bermasalah: {$v['id']} ({$v['name']}) district_id={$v['district_id']}");
    }
}

echo "\n";

// ─── 4. Field wajib tidak kosong / null ──────────────────────────────────────

echo "=== Validasi Field Wajib ===\n";

// Nama tidak boleh kosong
foreach (['provinces' => 'name', 'regencies' => 'name', 'districts' => 'name', 'villages' => 'name'] as $tbl => $col) {
    if (($countMap[$tbl] ?? 0) === 0) continue;
    $empty = (int) $db->query("SELECT COUNT(*) FROM `{$tbl}` WHERE `{$col}` IS NULL OR TRIM(`{$col}`) = ''")->fetchColumn();
    check("{$tbl}.{$col} tidak boleh kosong/null", $empty === 0, "{$empty} baris");
}

// Province: id tidak null
if ($countMap['provinces'] > 0) {
    $bad = (int) $db->query("SELECT COUNT(*) FROM provinces WHERE id IS NULL OR id = ''")->fetchColumn();
    check("provinces.id tidak boleh kosong/null", $bad === 0);
}

// ─── 5. TPS validasi ────────────────────────────────────────────────────────

echo "=== Validasi TPS ===\n";

if ($countMap['tps'] > 0) {
    // total_dpt tidak boleh <= 0
    $badDpt = (int) $db->query("SELECT COUNT(*) FROM tps WHERE total_dpt <= 0")->fetchColumn();
    check("TPS total_dpt harus > 0", $badDpt === 0, "{$badDpt} baris");

    // tps_number tidak boleh <= 0
    $badNo = (int) $db->query("SELECT COUNT(*) FROM tps WHERE tps_number <= 0")->fetchColumn();
    check("TPS tps_number harus > 0", $badNo === 0, "{$badNo} baris");

    // tps_number unik per village_id
    $dups = $db->query("
        SELECT village_id, tps_number, COUNT(*) AS cnt
        FROM tps
        GROUP BY village_id, tps_number
        HAVING cnt > 1
    ")->fetchAll();
    check("TPS tps_number harus unik per desa", empty($dups), count($dups) . " duplikasi");
    foreach (array_slice($dups, 0, 5) as $d) {
        warn("  Duplikasi TPS: village_id={$d['village_id']} tps_number={$d['tps_number']} ({$d['cnt']}x)");
    }

    // Koordinat tidak boleh null
    $noCoord = (int) $db->query("SELECT COUNT(*) FROM tps WHERE latitude IS NULL OR longitude IS NULL")->fetchColumn();
    check("TPS harus memiliki koordinat (lat/lng)", $noCoord === 0, "{$noCoord} baris tanpa koordinat");

    // Lampung coordinate bounds: lat roughly -6.8 .. -3.5, lng roughly 103.5 .. 105.8
    $outRange = (int) $db->query("SELECT COUNT(*) FROM tps WHERE latitude NOT BETWEEN -6.8 AND -3.5 OR longitude NOT BETWEEN 103.5 AND 105.8")->fetchColumn();
    if ($outRange > 0) {
        warn("{$outRange} TPS memiliki koordinat di luar rentang Lampung (lat -6.8..-3.5, lng 103.5..105.8)");
    }
}

echo "\n";

// ─── 6. Tabel KPU (opsional) ────────────────────────────────────────────────

if ($hasKPU) {
    echo "=== Validasi Tabel KPU ===\n";

    // kpu_kecamatan.region_kode harus match districts.kode
    $kecMismatch = (int) $db->query("
        SELECT COUNT(*) FROM kpu_kecamatan k
        LEFT JOIN districts d ON d.kode = k.region_kode
        WHERE d.kode IS NULL
    ")->fetchColumn();
    check("kpu_kecamatan.region_kode harus match districts.kode", $kecMismatch === 0, "{$kecMismatch} baris tidak match");

    // kpu_rekap.calon_kode harus match kpu_calon.id
    $rekapOrphan = (int) $db->query("
        SELECT COUNT(*) FROM kpu_rekap kr
        LEFT JOIN kpu_calon kc ON kc.id = kr.calon_kode
        WHERE kc.id IS NULL
    ")->fetchColumn();
    check("kpu_rekap.calon_kode harus match kpu_calon.id", $rekapOrphan === 0, "{$rekapOrphan} baris");

    // kpu_rekap.region_kode + jenis harus match kpu_kecamatan
    $rekapKecOrphan = (int) $db->query("
        SELECT COUNT(*) FROM kpu_rekap kr
        LEFT JOIN kpu_kecamatan k ON k.region_kode = kr.region_kode AND k.jenis = kr.jenis
        WHERE k.region_kode IS NULL
    ")->fetchColumn();
    check("kpu_rekap.region_kode+jenis harus match kpu_kecamatan", $rekapKecOrphan === 0, "{$rekapKecOrphan} baris");

    // Suara tidak boleh negatif
    $negSuara = (int) $db->query("SELECT COUNT(*) FROM kpu_rekap WHERE suara < 0")->fetchColumn();
    check("kpu_rekap.suara tidak boleh negatif", $negSuara === 0, "{$negSuara} baris");

    echo "\n";
}

// ─── 7. Party settings validasi ─────────────────────────────────────────────

echo "=== Validasi Party Settings ===\n";

if ($countMap['party_settings'] > 0) {
    // exactly 1 active party
    $activeCount = (int) $db->query("SELECT COUNT(*) FROM party_settings WHERE is_active = 1")->fetchColumn();
    check("Harus ada tepat 1 partai aktif (is_active=1)", $activeCount === 0 || $activeCount === 1, "{$activeCount} partai aktif");
    if ($activeCount === 0) {
        warn("Tidak ada partai yang diaktifkan — tema default akan digunakan");
    }

    // Color format: #HHHHHH
    $badColor = (int) $db->query("SELECT COUNT(*) FROM party_settings WHERE primary_color NOT REGEXP '^#[0-9A-Fa-f]{6}$'")->fetchColumn();
    check("primary_color harus format #hex6", $badColor === 0, "{$badColor} baris");

    $badColor2 = (int) $db->query("SELECT COUNT(*) FROM party_settings WHERE secondary_color NOT REGEXP '^#[0-9A-Fa-f]{6}$'")->fetchColumn();
    check("secondary_color harus format #hex6", $badColor2 === 0, "{$badColor2} baris");
}

// ─── 8. Users ───────────────────────────────────────────────────────────────

echo "=== Validasi Users ===\n";

if ($countMap['users'] > 0) {
    $noPass = (int) $db->query("SELECT COUNT(*) FROM users WHERE password IS NULL OR password = ''")->fetchColumn();
    check("User tidak boleh tanpa password hash", $noPass === 0);

    $roles = $db->query("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role")->fetchAll();
    echo "  Roles: " . implode(', ', array_map(fn($r) => "{$r['role']}:{$r['cnt']}", $roles)) . "\n";

    $hasSuperadmin = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
    check("Harus ada minimal 1 superadmin", $hasSuperadmin > 0);
}

echo "\n";

// ─── 9. Hierarchy coverage: setiap level harus punya children ────────────────

echo "=== Validasi Coverage Hierarchy ===\n";

if ($countMap['regencies'] > 0) {
    $provWithout = (int) $db->query("
        SELECT COUNT(*) FROM provinces p
        WHERE NOT EXISTS (SELECT 1 FROM regencies r WHERE r.province_id = p.id)
    ")->fetchColumn();
    if ($provWithout > 0) {
        warn("{$provWithout} provinsi tidak memiliki kabupaten/kota");
    }
}

if ($countMap['districts'] > 0) {
    $kabWithout = (int) $db->query("
        SELECT COUNT(*) FROM regencies r
        WHERE NOT EXISTS (SELECT 1 FROM districts d WHERE d.regency_id = r.id)
    ")->fetchColumn();
    if ($kabWithout > 0) {
        warn("{$kabWithout} kabupaten/kota tidak memiliki kecamatan");
    }
}

if ($countMap['villages'] > 0) {
    $kecWithout = (int) $db->query("
        SELECT COUNT(*) FROM districts d
        WHERE NOT EXISTS (SELECT 1 FROM villages v WHERE v.district_id = d.id)
    ")->fetchColumn();
    if ($kecWithout > 0) {
        warn("{$kecWithout} kecamatan tidak memiliki desa/kelurahan");
    }
}

if ($countMap['tps'] > 0) {
    $desaWithout = (int) $db->query("
        SELECT COUNT(*) FROM villages v
        WHERE NOT EXISTS (SELECT 1 FROM tps t WHERE t.village_id = v.id)
    ")->fetchColumn();
    if ($desaWithout > 0) {
        warn("{$desaWithout} desa/kelurahan tidak memiliki TPS");
    }
}

echo "\n";

// ─── Hasil akhir ─────────────────────────────────────────────────────────────

echo str_repeat('=', 50) . "\n";
echo "HASIL VALIDASI\n";
echo str_repeat('=', 50) . "\n";

if (empty($errors)) {
    echo "\n  ✅ Semua validasi lolos — tidak ada error.\n";
} else {
    echo "\n  ❌ " . count($errors) . " error ditemukan:\n\n";
    foreach ($errors as $i => $e) {
        echo "  " . ($i + 1) . ". {$e}\n";
    }
}

if (!empty($warnings)) {
    echo "\n  ⚠️  " . count($warnings) . " peringatan:\n\n";
    foreach ($warnings as $i => $w) {
        echo "  " . ($i + 1) . ". {$w}\n";
    }
}

echo "\n";

exit(empty($errors) ? 0 : 1);
