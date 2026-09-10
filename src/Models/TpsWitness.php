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

    /**
     * Satu baris saksi beserta info TPS + wilayah (kabupaten/kecamatan/desa).
     */
    public function findWithDetails(string $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT w.*, t.tps_number, v.name AS village_name, d.name AS district_name, r.name AS regency_name
             FROM tps_witnesses w
             LEFT JOIN tps t ON w.tps_id = t.id
             LEFT JOIN villages v ON t.village_id = v.id
             LEFT JOIN districts d ON v.district_id = d.id
             LEFT JOIN regencies r ON d.regency_id = r.id
             WHERE w.id = :id"
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Daftar saksi (dengan info wilayah), mendukung filter status/tps_id/pencarian.
     */
    public function listWithDetails(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilter($filters);

        $sql = "SELECT w.*, t.tps_number, v.name AS village_name, d.name AS district_name, r.name AS regency_name
                FROM tps_witnesses w
                LEFT JOIN tps t ON w.tps_id = t.id
                LEFT JOIN villages v ON t.village_id = v.id
                LEFT JOIN districts d ON v.district_id = d.id
                LEFT JOIN regencies r ON d.regency_id = r.id"
            . $where
            . " ORDER BY w.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Jumlah saksi sesuai filter (dipakai untuk pagination).
     */
    public function countFiltered(array $filters = []): int
    {
        [$where, $params] = $this->buildFilter($filters);

        $sql = "SELECT COUNT(*) FROM tps_witnesses w" . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    private function buildFilter(array $filters): array
    {
        $wheres = [];
        $params = [];

        if (!empty($filters['status'])) {
            $wheres[] = 'w.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['tps_id'])) {
            $wheres[] = 'w.tps_id = :tps_id';
            $params['tps_id'] = $filters['tps_id'];
        }
        if (!empty($filters['q'])) {
            $wheres[] = '(w.full_name LIKE :q1 OR w.nik LIKE :q2)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = $params['q1'];
        }

        $where = '';
        if ($wheres) {
            $where = ' WHERE ' . implode(' AND ', $wheres);
        }
        return [$where, $params];
    }
}
