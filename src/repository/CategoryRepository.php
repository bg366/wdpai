<?php

require_once __DIR__ . '/Repository.php';

class CategoryRepository extends Repository
{
    public function findAll(): array
    {
        return $this->fetchAll(
            'SELECT id, name, icon, color
             FROM incident_categories
             ORDER BY name'
        );
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT id, name, icon, color
             FROM incident_categories
             WHERE id = :id',
            ['id' => $id]
        );
    }

    public function exists(int $id): bool
    {
        return $this->fetchOne(
            'SELECT 1
             FROM incident_categories
             WHERE id = :id',
            ['id' => $id]
        ) !== null;
    }
}
