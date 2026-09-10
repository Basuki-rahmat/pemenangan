<?php

declare(strict_types=1);

namespace App\Models;

class PartySettings extends BaseModel
{
    protected string $table = 'party_settings';

    public function getActive(): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE is_active = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function setActive(int $id): bool
    {
        $this->db->exec("UPDATE {$this->table} SET is_active = 0");
        $stmt = $this->db->prepare("UPDATE {$this->table} SET is_active = 1 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getCssVariables(): string
    {
        $party = $this->getActive();
        if (!$party) {
            $party = [
                'primary_color' => '#1E40AF',
                'secondary_color' => '#F97316',
                'accent_color' => '#F3F4F6',
            ];
        }

        return ":root {
            --primary-color: {$party['primary_color']};
            --secondary-color: {$party['secondary_color']};
            --accent-color: {$party['accent_color']};
        }";
    }
}
