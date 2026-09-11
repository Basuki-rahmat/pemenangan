<?php

declare(strict_types=1);

/**
 * Helper rekapitulasi berjenjang (dipakai admin/recap.php & admin/export_pdf.php).
 */

function levelChildOf(string $level): string
{
    return [
        'province' => 'regency',
        'regency'  => 'district',
        'district' => 'village',
        'village'  => 'tps',
    ][$level] ?? '';
}

function levelParentOf(string $level): string
{
    return [
        'regency'  => 'province',
        'district' => 'regency',
        'village'  => 'district',
        'tps'      => 'village',
    ][$level] ?? '';
}

function levelLabelOf(string $level): string
{
    return [
        'province' => 'Provinsi',
        'regency'  => 'Kabupaten',
        'district' => 'Kecamatan',
        'village'  => 'Kelurahan/Desa',
        'tps'      => 'TPS',
    ][$level] ?? 'Wilayah';
}

/**
 * Rangkaian nama wilayah dari sebuah unit ke atas (sampai provinsi).
 * Mengembalikan array urut: province -> ... -> unit.
 */
function regionChain(PDO $db, string $unitLevel, string $unitId): array
{
    $tables = [
        'province' => ['provinces', 'id', 'name'],
        'regency'  => ['regencies', 'id', 'name'],
        'district' => ['districts', 'id', 'name'],
        'village'  => ['villages', 'id', 'name'],
    ];
    $upOf = [
        'regency'  => ['level' => 'province', 'col' => 'province_id'],
        'district' => ['level' => 'regency',  'col' => 'regency_id'],
        'village'  => ['level' => 'district', 'col' => 'district_id'],
    ];

    $chain = [];
    $level = $unitLevel;
    $id = $unitId;

    while (isset($tables[$level]) && $id !== '') {
        [$table, $idCol, $nameCol] = $tables[$level];
        $parentCol = $upOf[$level]['col'] ?? null;
        $select = "`$idCol` AS id, `$nameCol` AS name";
        if ($parentCol !== null) {
            $select .= ", `$parentCol` AS parent";
        }
        $stmt = $db->prepare("SELECT $select FROM `$table` WHERE `$idCol` = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            break;
        }
        array_unshift($chain, ['level' => $level, 'id' => (string)$row['id'], 'name' => (string)$row['name']]);
        if (isset($upOf[$level])) {
            $level = $upOf[$level]['level'];
            $id = (string)($row['parent'] ?? '');
        } else {
            break;
        }
        if (count($chain) > 4) {
            break;
        }
    }

    return $chain;
}