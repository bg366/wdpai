<?php

require_once __DIR__ . '/Repository.php';

class UserRepository extends Repository
{
    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = :email',
            ['email' => $email]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT u.*, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = :id',
            ['id' => $id]
        );
    }

    public function emailExists(string $email): bool
    {
        return $this->fetchOne(
            'SELECT 1 FROM users WHERE email = :email',
            ['email' => $email]
        ) !== null;
    }

    public function create(string $email, string $passwordHash, string $fullName): int
    {
        return $this->insert(
            'INSERT INTO users (email, password_hash, full_name, role_id)
             VALUES (:email, :password_hash, :full_name,
                     (SELECT id FROM roles WHERE name = \'citizen\'))
             RETURNING id',
            ['email' => $email, 'password_hash' => $passwordHash, 'full_name' => $fullName]
        );
    }

    public function findAll(): array
    {
        return $this->fetchAll(
            'SELECT u.id, u.email, u.full_name, u.created_at, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC'
        );
    }

    public function updateRole(int $userId, string $roleName): void
    {
        $this->execute(
            'UPDATE users
             SET role_id = (SELECT id FROM roles WHERE name = :role)
             WHERE id = :id',
            ['role' => $roleName, 'id' => $userId]
        );
    }
}
