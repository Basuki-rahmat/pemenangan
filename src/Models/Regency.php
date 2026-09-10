<?php

declare(strict_types=1);

namespace App\Models;

class Regency extends BaseModel
{
    protected string $table = 'regencies';

    public function findByProvince(string $provinceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE province_id = :province_id ORDER BY name");
        $stmt->execute(['province_id' => $provinceId]);
        return $stmt->fetchAll();
    }
}
