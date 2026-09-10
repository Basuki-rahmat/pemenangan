<?php

declare(strict_types=1);

namespace App\Models;

class Village extends BaseModel
{
    protected string $table = 'villages';

    public function findByDistrict(string $districtId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE district_id = :district_id ORDER BY name");
        $stmt->execute(['district_id' => $districtId]);
        return $stmt->fetchAll();
    }
}
