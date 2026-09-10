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
}
