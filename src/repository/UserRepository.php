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
            'SELECT
                u.id,
                u.email,
                u.full_name,
                u.created_at,
                r.name AS role_name,
                COUNT(i.id) AS incidents_count,
                COUNT(i.id) FILTER (WHERE s.name IN (\'new\', \'in_progress\')) AS active_incidents_count
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN incidents i ON i.reported_by = u.id
             LEFT JOIN incident_statuses s ON s.id = i.status_id
             GROUP BY u.id, r.name
             ORDER BY u.created_at DESC'
        );
    }

    public function getRoles(): array
    {
        return $this->fetchAll(
            'SELECT id, name
             FROM roles
             ORDER BY id'
        );
    }

    public function roleExists(string $roleName): bool
    {
        return $this->fetchOne(
            'SELECT 1
             FROM roles
             WHERE name = :role',
            ['role' => $roleName]
        ) !== null;
    }

    public function countByRole(): array
    {
        return $this->fetchAll(
            'SELECT r.name AS role_name, COUNT(u.id) AS users_count
             FROM roles r
             LEFT JOIN users u ON u.role_id = r.id
             GROUP BY r.id, r.name
             ORDER BY r.id'
        );
    }

    public function countAdmins(): int
    {
        $result = $this->fetchOne(
            'SELECT COUNT(*) AS count
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE r.name = :role',
            ['role' => 'admin']
        );

        return (int) ($result['count'] ?? 0);
    }

    public function updateRole(int $userId, string $roleName): ?array
    {
        $updated = $this->fetchOne(
            'UPDATE users
             SET role_id = (SELECT id FROM roles WHERE name = :role)
             WHERE id = :id
             RETURNING id',
            ['role' => $roleName, 'id' => $userId]
        );

        if ($updated === null) {
            return null;
        }

        return $this->findById($userId);
    }
}
