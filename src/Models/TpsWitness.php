<?php

declare(strict_types=1);

namespace App\Models;

class TpsWitness extends BaseModel
{
    protected string $table = 'tps_witnesses';

    public function findByNik(string $nik): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE nik = :nik");
        $stmt->execute(['nik' => $nik]);
        return $stmt->fetch() ?: null;
    }

    public function findByTps(string $tpsId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE tps_id = :tps_id ORDER BY created_at DESC");
        $stmt->execute(['tps_id' => $tpsId]);
        return $stmt->fetchAll();
    }

    public function verify(string $id, string $verifiedBy, string $status = 'verified'): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET status = :status, verified_by = :verified_by, verified_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'status' => $status, 'verified_by' => $verifiedBy]);
    }

    public function getStats(): array
    {
        $stmt = $this->db->query("SELECT status, COUNT(*) as total FROM {$this->table} GROUP BY status");
        $result = ['pending' => 0, 'verified' => 0, 'rejected' => 0, 'total' => 0];
        while ($row = $stmt->fetch()) {
            $result[$row['status']] = (int)$row['total'];
            $result['total'] += (int)$row['total'];
        }
        return $result;
    }
}
