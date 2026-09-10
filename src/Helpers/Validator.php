<?php

declare(strict_types=1);

namespace App\Helpers;

class Validator
{
    private array $errors = [];

    public function required(string $field, mixed $value): self
    {
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->errors[$field] = "{$field} wajib diisi";
        }
        return $this;
    }

    public function minLength(string $field, string $value, int $min): self
    {
        if (strlen($value) < $min) {
            $this->errors[$field] = "{$field} minimal {$min} karakter";
        }
        return $this;
    }

    public function maxLength(string $field, string $value, int $max): self
    {
        if (strlen($value) > $max) {
            $this->errors[$field] = "{$field} maksimal {$max} karakter";
        }
        return $this;
    }

    public function email(string $field, string $value): self
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$field} harus berupa email yang valid";
        }
        return $this;
    }

    public function numeric(string $field, mixed $value): self
    {
        if (!is_numeric($value)) {
            $this->errors[$field] = "{$field} harus berupa angka";
        }
        return $this;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
