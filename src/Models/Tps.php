<?php

declare(strict_types=1);

namespace App\Models;

class Tps extends BaseModel
{
    protected string $table = 'tps';

    public function findByVillage(string $villageId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE village_id = :village_id ORDER BY tps_number");
        $stmt->execute(['village_id' => $villageId]);
        return $stmt->fetchAll();
    }

    public function findByDistrict(string $districtId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, v.name as village_name 
            FROM {$this->table} t 
            JOIN villages v ON t.village_id = v.id 
            WHERE v.district_id = :district_id 
            ORDER BY v.name, t.tps_number
        ");
        $stmt->execute(['district_id' => $districtId]);
        return $stmt->fetchAll();
    }
}
