<?php

declare(strict_types=1);

namespace App\Models;

class District extends BaseModel
{
    protected string $table = 'districts';

    public function findByRegency(string $regencyId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE regency_id = :regency_id ORDER BY name");
        $stmt->execute(['regency_id' => $regencyId]);
        return $stmt->fetchAll();
    }
}
