<?php

class Validator
{
    private array $errors = [];

    public function required(string $field, mixed $value): static
    {
        if (!isset($this->errors[$field]) && trim((string) $value) === '') {
            $this->errors[$field] = 'Pole jest wymagane.';
        }
        return $this;
    }

    public function email(string $field, string $value): static
    {
        if (!isset($this->errors[$field]) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Nieprawidłowy adres email.';
        }
        return $this;
    }

    public function minLength(string $field, string $value, int $min): static
    {
        if (!isset($this->errors[$field]) && mb_strlen($value) < $min) {
            $this->errors[$field] = "Minimalna długość to {$min} znaków.";
        }
        return $this;
    }

    public function maxLength(string $field, string $value, int $max): static
    {
        if (!isset($this->errors[$field]) && mb_strlen($value) > $max) {
            $this->errors[$field] = "Maksymalna długość to {$max} znaków.";
        }
        return $this;
    }

    public function matches(string $field, string $value, string $other): static
    {
        if (!isset($this->errors[$field]) && $value !== $other) {
            $this->errors[$field] = 'Hasła nie są zgodne.';
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
