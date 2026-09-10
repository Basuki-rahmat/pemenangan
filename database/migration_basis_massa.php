<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

$db = Database::getConnection();

echo "=== Migration: basis_massa ===\n";

$db->exec("CREATE TABLE IF NOT EXISTS `basis_massa` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `village_id` CHAR(10) NOT NULL,
    `party_name` VARCHAR(100) NOT NULL,
    `estimated_supporters` INT DEFAULT 0 COMMENT 'Estimasi jumlah pendukung/voter',
    `dpt_total` INT DEFAULT 0 COMMENT 'Total DPT di desa tersebut',
    `support_index` DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Persentase estimasi (0-100)',
    `confidence` ENUM('high','medium','low') DEFAULT 'medium',
    `source` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`village_id`) REFERENCES `villages`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_bmassa_village_party` (`village_id`, `party_name`),
    INDEX `idx_bmassa_village` (`village_id`),
    INDEX `idx_bmassa_party` (`party_name`),
    INDEX `idx_bmassa_support` (`estimated_supporters` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "✅ Tabel basis_massa berhasil dibuat.\n\n";

// === Seed data ===
echo "=== Seeding basis_massa (dari data DPT + proporsi KPU) ===\n";

$parties = ['PDI-P', 'Golkar', 'Gerindra', 'PKB', 'NasDem', 'PKS', 'Demokrat'];

// Ambil data KPU jika tersedia (proporsi suara per kecamatan)
$hasKPU = false;
try {
    $kpuProportions = $db->query("
        SELECT 
            kr.calon_kode,
            SUM(kr.suara) AS total_suara
        FROM kpu_rekap kr
        WHERE kr.jenis = 'pkwkp'
        GROUP BY kr.calon_kode
        ORDER BY total_suara DESC
    ")->fetchAll();
    $hasKPU = count($kpuProportions) > 0;
    if ($hasKPU) {
        $totalKpuSuara = array_sum(array_column($kpuProportions, 'total_suara'));
        echo "  Data KPU ditemukan: {$totalKpuSuara} total suara\n";
    }
} catch (\Throwable $e) {
    echo "  Info: Tabel kpu_rekap tidak ada, pakai proporsi sampel\n";
}

// Ambil semua desa + DPT
$villages = $db->query("
    SELECT v.id AS village_id, v.name AS village_name, v.district_id,
           COALESCE(SUM(t.total_dpt), 0) AS total_dpt
    FROM villages v
    LEFT JOIN tps t ON t.village_id = v.id
    GROUP BY v.id
    HAVING total_dpt > 0
    ORDER BY v.district_id
")->fetchAll();

echo "  Desa dengan DPT: " . count($villages) . "\n";

if (empty($villages)) {
    echo "  ❌ Tidak ada desa dengan DPT. Jalankan seeder TPS terlebih dahulu.\n";
    exit(1);
}

// Proporsi suara per partai berdasarkan data KPU (jika ada)
// Mapping KPU calon → partai (order by nomor urut → party index)
$partyProportions = [
    'PDI-P'   => 0.18,
    'Golkar'  => 0.16,
    'Gerindra'=> 0.17,
    'PKB'     => 0.12,
    'NasDem'  => 0.14,
    'PKS'     => 0.11,
    'Demokrat'=> 0.12,
];

if ($hasKPU && count($kpuProportions) === count($parties)) {
    $total = (float)$totalKpuSuara;
    foreach ($kpuProportions as $i => $row) {
        if ($i < count($parties)) {
            $partyProportions[$parties[$i]] = $total > 0 ? $row['total_suara'] / $total : (1.0 / count($parties));
        }
    }
}

echo "  Proporsi suara per partai:\n";
foreach ($partyProportions as $p => $prop) {
    printf("    %-12s %5.1f%%\n", $p, $prop * 100);
}
echo "\n";

// Set proporsi + confidence berdasarkan posisi di ranking
$sortedParties = $partyProportions;
arsort($sortedParties);
$partyRanks = array_flip(array_keys($sortedParties));

$hashedParties = [
    'PDI-P'    => 'high',
    'Golkar'   => 'high',
    'Gerindra' => 'high',
    'PKB'      => 'medium',
    'NasDem'   => 'medium',
    'PKS'      => 'medium',
    'Demokrat' => 'medium',
];

$stmtInsert = $db->prepare("
    INSERT INTO basis_massa (village_id, party_name, estimated_supporters, dpt_total, support_index, confidence, source)
    VALUES (:village, :party, :est, :dpt, :idx, :conf, :source)
    ON DUPLICATE KEY UPDATE 
        estimated_supporters = VALUES(estimated_supporters),
        dpt_total = VALUES(dpt_total),
        support_index = VALUES(support_index),
        confidence = VALUES(confidence),
        updated_at = CURRENT_TIMESTAMP
");

$count = 0;
$batchSize = 500;
$batch = 0;

foreach ($villages as $village) {
    $dpt = (int)$village['total_dpt'];
    if ($dpt <= 0) continue;

    foreach ($parties as $party) {
        $prop = $partyProportions[$party] ?? (1.0 / count($parties));

        // Jumlah pendukung = DPT × proporsi × faktor noise (±10%)
        $noise = 1.0 + (mt_rand(-100, 100) / 1000.0);
        $est = (int)round($dpt * $prop * $noise);
        $est = max(0, $est);
        $idx = $dpt > 0 ? round(($est / $dpt) * 100, 2) : 0;
        $conf = $partyRanks[$party] ?? 2;
        $confidence = $conf <= 1 ? 'high' : ($conf <= 3 ? 'medium' : 'low');
        $source = $hasKPU ? 'KPU Pilkada 2024 + proporsi' : 'Estimasi sampel';

        $stmtInsert->execute([
            'village' => $village['village_id'],
            'party'   => $party,
            'est'     => $est,
            'dpt'     => $dpt,
            'idx'     => $idx,
            'conf'    => $confidence,
            'source'  => $source,
        ]);
        $count++;
    }
}

echo "✅ {$count} baris basis_massa berhasil di-seed.\n";

// Summary
$summary = $db->query("
    SELECT party_name, 
           COUNT(*) AS villages,
           SUM(estimated_supporters) AS total_supporters,
           ROUND(AVG(support_index),1) AS avg_index
    FROM basis_massa
    GROUP BY party_name
    ORDER BY total_supporters DESC
")->fetchAll();

echo "\n=== Ringkasan ===\n";
printf("  %-12s %6s  %-15s %s\n", "Partai", "Desa", "Total Pendukung", "Avg Index");
echo "  " . str_repeat("-", 55) . "\n";
foreach ($summary as $row) {
    printf("  %-12s %6s  %-15s %s%%\n", 
        $row['party_name'],
        number_format((int)$row['villages']),
        number_format((int)$row['total_supporters']),
        $row['avg_index']
    );
}
echo "\n";
