<?php

declare(strict_types=1);

namespace App\Models;

class VoteResult extends BaseModel
{
    protected string $table = 'vote_results';

    public function findByTps(string $tpsId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE tps_id = :tps_id ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['tps_id' => $tpsId]);
        return $stmt->fetch() ?: null;
    }

    public function getQuickCount(): array
    {
        $sql = "SELECT 
                    vr.*,
                    t.tps_number,
                    v.name as village_name,
                    d.name as district_name
                FROM {$this->table} vr
                JOIN tps t ON vr.tps_id = t.id
                JOIN villages v ON t.village_id = v.id
                JOIN districts d ON v.district_id = d.id
                WHERE vr.status = 'verified'
                ORDER BY vr.created_at DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getSummaryByDistrict(): array
    {
        $sql = "SELECT 
                    d.id as district_id,
                    d.name as district_name,
                    COUNT(DISTINCT vr.tps_id) as tps_count,
                    SUM(vr.total_votes) as total_votes
                FROM {$this->table} vr
                JOIN tps t ON vr.tps_id = t.id
                JOIN villages v ON t.village_id = v.id
                JOIN districts d ON v.district_id = d.id
                WHERE vr.status = 'verified'
                GROUP BY d.id, d.name
                ORDER BY total_votes DESC";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
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
     * Daftar hasil suara beserta saksi + info wilayah, mendukung filter status/tps/witness/pencarian.
     */
    public function listWithDetails(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->buildVoteFilter($filters);

        $sql = "SELECT vr.*, w.full_name AS witness_name, t.tps_number,
                       v.name AS village_name, d.name AS district_name, r.name AS regency_name
                FROM {$this->table} vr
                LEFT JOIN tps_witnesses w ON vr.witness_id = w.id
                LEFT JOIN tps t ON vr.tps_id = t.id
                LEFT JOIN villages v ON t.village_id = v.id
                LEFT JOIN districts d ON v.district_id = d.id
                LEFT JOIN regencies r ON d.regency_id = r.id"
            . $where
            . " ORDER BY vr.created_at DESC LIMIT :limit OFFSET :offset";

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
        [$where, $params] = $this->buildVoteFilter($filters);

        $sql = "SELECT COUNT(*) FROM {$this->table} vr" . $where;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Ringkasan suara untuk satu TPS: agregasi perolehan per kandidat dari data yang
     * paling bisa dipercaya (verified -> pending -> rejected).
     */
    public function summaryByTps(string $tpsId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT vr.*, t.tps_number, v.name AS village_name, d.name AS district_name, r.name AS regency_name,
                    t.latitude, t.longitude
             FROM {$this->table} vr
             JOIN tps t ON vr.tps_id = t.id
             LEFT JOIN villages v ON t.village_id = v.id
             LEFT JOIN districts d ON v.district_id = d.id
             LEFT JOIN regencies r ON d.regency_id = r.id
             WHERE vr.tps_id = :tps_id
             ORDER BY vr.created_at DESC"
        );
        $stmt->execute(['tps_id' => $tpsId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return null;
        }

        // Pilih subset data dengan prioritas: verified > pending > rejected
        $preferred = 'verified';
        $statuses = array_column($rows, 'status');
        if (!in_array($preferred, $statuses, true)) {
            $preferred = in_array('pending', $statuses, true) ? 'pending' : 'rejected';
        }

        $considered = [];
        foreach ($rows as $row) {
            if ($row['status'] === $preferred) {
                $considered[] = $row;
            }
        }

        $candidates = [];
        foreach ($considered as $row) {
            $decoded = json_decode((string)($row['candidate_votes'] ?? '[]'), true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $entry) {
                $name = (string)($entry['name'] ?? $entry['candidate_name'] ?? 'Tidak diketahui');
                $votes = max(0, (int)($entry['votes'] ?? $entry['total_votes'] ?? 0));
                $candidates[$name] = ($candidates[$name] ?? 0) + $votes;
            }
        }

        // Agar urutan kandidat konsisten, susun ulang menjadi array list
        $candidateVotes = [];
        foreach ($candidates as $name => $votes) {
            $candidateVotes[] = ['name' => $name, 'votes' => $votes];
        }

        $first = $considered[0];
        return [
            'tps' => [
                'id' => (string)$first['tps_id'],
                'tps_number' => $first['tps_number'],
                'village_name' => $first['village_name'],
                'district_name' => $first['district_name'],
                'regency_name' => $first['regency_name'],
                'latitude' => $first['latitude'],
                'longitude' => $first['longitude'],
            ],
            'status' => $preferred,
            'candidate_votes' => $candidateVotes,
            'invalid_votes' => array_sum(array_map(fn($r) => (int)$r['invalid_votes'], $considered)),
            'total_votes' => array_sum(array_map(fn($r) => (int)$r['total_votes'], $considered)),
            'submissions' => count($considered),
            'latest' => [
                'id' => $first['id'],
                'created_at' => $first['created_at'],
                'verified_at' => $first['verified_at'],
                'c1_photo_url' => $first['c1_photo_url'],
            ],
        ];
    }

    private function buildVoteFilter(array $filters): array
    {
        $wheres = [];
        $params = [];

        if (!empty($filters['status'])) {
            $wheres[] = 'vr.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['tps_id'])) {
            $wheres[] = 'vr.tps_id = :tps_id';
            $params['tps_id'] = $filters['tps_id'];
        }
        if (!empty($filters['witness_id'])) {
            $wheres[] = 'vr.witness_id = :witness_id';
            $params['witness_id'] = $filters['witness_id'];
        }
        if (!empty($filters['q'])) {
            $wheres[] = '(w.full_name LIKE :q1 OR v.name LIKE :q2 OR d.name LIKE :q3)';
            $params['q1'] = '%' . $filters['q'] . '%';
            $params['q2'] = $params['q1'];
            $params['q3'] = $params['q1'];
        }

        $where = '';
        if ($wheres) {
            $where = ' WHERE ' . implode(' AND ', $wheres);
        }
        return [$where, $params];
    }
}
