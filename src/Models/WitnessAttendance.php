<?php

declare(strict_types=1);

namespace App\Models;

class WitnessAttendance extends BaseModel
{
    protected string $table = 'witness_attendance';

    public function checkIn(string $witnessId, string $date): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (witness_id, date, check_in_at, status)
             VALUES (:witness_id, :date, NOW(), 'hadir')
             ON DUPLICATE KEY UPDATE check_in_at = CASE WHEN check_in_at IS NULL THEN NOW() ELSE check_in_at END,
                                     status = CASE WHEN status = 'alpa' THEN 'hadir' ELSE status END,
                                     updated_at = NOW()"
        );
        return $stmt->execute(['witness_id' => $witnessId, 'date' => $date]);
    }

    public function checkOut(string $witnessId, string $date): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET check_out_at = NOW(), updated_at = NOW()
             WHERE witness_id = :witness_id AND date = :date"
        );
        return $stmt->execute(['witness_id' => $witnessId, 'date' => $date]);
    }

    /**
     * Ubah status kehadiran (hadir/terlambat/izin/alpa) untuk satu saksi pada satu tanggal.
     */
    public function setStatus(string $witnessId, string $date, string $status, ?string $notes = null): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (witness_id, date, status, notes)
             VALUES (:witness_id, :date, :status, :notes)
             ON DUPLICATE KEY UPDATE status = :status2, notes = :notes2, updated_at = NOW()"
        );
        return $stmt->execute([
            'witness_id' => $witnessId,
            'date' => $date,
            'status' => $status,
            'notes' => $notes,
            'status2' => $status,
            'notes2' => $notes,
        ]);
    }

    public function findByWitnessAndDate(string $witnessId, string $date): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE witness_id = :witness_id AND date = :date LIMIT 1"
        );
        $stmt->execute(['witness_id' => $witnessId, 'date' => $date]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Kehadiran semua saksi pada satu tanggal (detail wilayah + saksi terdaftar).
     * Return array [['witness'=>..., 'attendance'=>..., 'registered'=>bool], ...]
     */
    public function listByDate(string $date): array
    {
        $sql = "SELECT w.id AS witness_id, w.full_name, w.status AS witness_status,
                       t.tps_number, v.name AS village_name, d.name AS district_name,
                       r.name AS regency_name,
                       a.id AS attendance_id, a.check_in_at, a.check_out_at, a.status AS att_status, a.notes,
                       CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END AS registered
                FROM tps_witnesses w
                JOIN tps t ON w.tps_id = t.id
                LEFT JOIN villages v ON t.village_id = v.id
                LEFT JOIN districts d ON v.district_id = d.id
                LEFT JOIN regencies r ON d.regency_id = r.id
                LEFT JOIN {$this->table} a ON a.witness_id = w.id AND a.date = :date
                ORDER BY v.name, t.tps_number";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date' => $date]);
        return $stmt->fetchAll();
    }

    /**
     * Ringkasan kehadiran per tanggal: hadir/terlambat/izin/alpa + total saksi terdaftar.
     */
    public function summaryByDate(string $date): array
    {
        $rows = $this->listByDate($date);
        $total = count($rows);
        $counted = ['hadir' => 0, 'terlambat' => 0, 'izin' => 0, 'alpa' => 0];
        $checkedIn = 0;
        foreach ($rows as $row) {
            if ($row['registered']) {
                $key = $row['att_status'] ?? 'alpa';
                if (!isset($counted[$key])) {
                    $key = 'alpa';
                }
                $counted[$key]++;
                if ($row['check_in_at']) {
                    $checkedIn++;
                }
            } else {
                $counted['alpa']++;
            }
        }
        return [
            'total' => $total,
            'hadir' => $counted['hadir'],
            'terlambat' => $counted['terlambat'],
            'izin' => $counted['izin'],
            'alpa' => $counted['alpa'],
            'checked_in' => $checkedIn,
            'attendance_rate' => $total > 0 ? round(($counted['hadir'] + $counted['terlambat']) / $total * 100) : 0,
        ];
    }
}