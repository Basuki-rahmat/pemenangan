<?php

declare(strict_types=1);

namespace App\Models;

class WitnessFund extends BaseModel
{
    protected string $table = 'witness_funds';

    public function create(array $data): string
    {
        return parent::create($data);
    }

    /**
     * Daftar transaksi dana saksi (detail wilayah), mendukung filter status/kecamatan + pencarian.
     */
    public function listWithDetails(array $filters = [], int $limit = 200, int $offset = 0): array
    {
        [$where, $params] = $this->buildFundFilter($filters);

        $sql = "SELECT f.*, w.full_name, w.nik, w.phone_number, w.tps_id,
                       t.tps_number, v.name AS village_name, d.name AS district_name,
                       r.name AS regency_name
                FROM witness_funds f
                JOIN tps_witnesses w ON f.witness_id = w.id
                LEFT JOIN tps t ON w.tps_id = t.id
                LEFT JOIN villages v ON t.village_id = v.id
                LEFT JOIN districts d ON v.district_id = d.id
                LEFT JOIN regencies r ON d.regency_id = r.id"
            . $this->whereOnly($where)
            . " ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countFiltered(array $filters = []): int
    {
        [$where, $params] = $this->buildFundFilter($filters);

        $sql = "SELECT COUNT(*) FROM witness_funds f
                JOIN tps_witnesses w ON f.witness_id = w.id
                LEFT JOIN tps t ON w.tps_id = t.id
                LEFT JOIN villages v ON t.village_id = v.id
                LEFT JOIN districts d ON v.district_id = d.id"
            . $this->whereOnly($where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Rekap dana per kecamatan: total dibayar, menunggu, dibatalkan.
     */
    public function getRecap(array $filters = []): array
    {
        [$where, $params] = $this->buildFundFilter($filters);

        $sql = "SELECT d.name AS district_name,
                       COUNT(DISTINCT w.id) AS witness_count,
                       COUNT(f.id) AS payment_count,
                       COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END), 0) AS total_paid,
                       COALESCE(SUM(CASE WHEN f.status = 'pending' THEN f.amount ELSE 0 END), 0) AS total_pending,
                       COALESCE(SUM(f.amount), 0) AS total_all
                FROM witness_funds f
                JOIN tps_witnesses w ON f.witness_id = w.id
                LEFT JOIN tps t ON w.tps_id = t.id
                LEFT JOIN villages v ON t.village_id = v.id
                LEFT JOIN districts d ON v.district_id = d.id"
            . $this->whereOnly($where)
            . " GROUP BY d.name ORDER BY total_paid DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTotals(array $filters = []): array
    {
        [$where, $params] = $this->buildFundFilter($filters);

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount ELSE 0 END), 0) AS total_paid,
                    COALESCE(SUM(CASE WHEN f.status = 'pending' THEN f.amount ELSE 0 END), 0) AS total_pending,
                    COALESCE(SUM(f.amount), 0) AS total_all,
                    COUNT(DISTINCT w.id) AS witnesses_paid
                FROM witness_funds f
                JOIN tps_witnesses w ON f.witness_id = w.id
                LEFT JOIN tps t ON w.tps_id = t.id
                LEFT JOIN villages v ON t.village_id = v.id
                LEFT JOIN districts d ON v.district_id = d.id"
            . $this->whereOnly($where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];
        return [
            'total_paid' => (float)($row['total_paid'] ?? 0),
            'total_pending' => (float)($row['total_pending'] ?? 0),
            'total_all' => (float)($row['total_all'] ?? 0),
            'witnesses_paid' => (int)($row['witnesses_paid'] ?? 0),
        ];
    }

    private function buildFundFilter(array $filters): array
    {
        $wheres = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], ['paid', 'pending', 'cancelled'], true)) {
            $wheres[] = 'f.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['witness_id'])) {
            $wheres[] = 'f.witness_id = :witness_id';
            $params['witness_id'] = $filters['witness_id'];
        }
        if (!empty($filters['q'])) {
            $wheres[] = '(d.name LIKE :q1 OR w.full_name LIKE :q2 OR w.nik LIKE :q3)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = $params['q1'];
            $params['q3'] = $params['q1'];
        }

        return [$wheres, $params];
    }

    private function whereOnly(array $wheres): string
    {
        return $wheres ? ' WHERE ' . implode(' AND ', $wheres) : '';
    }
}