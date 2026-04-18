<?php

abstract class Repository
{
    private static ?PDO $pdo = null;

    protected function db(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $_ENV['DB_HOST'] ?? 'db',
                $_ENV['DB_PORT'] ?? '5432',
                $_ENV['DB_NAME'] ?? 'db'
            );
            self::$pdo = new PDO(
                $dsn,
                $_ENV['DB_USER'] ?? 'docker',
                $_ENV['DB_PASS'] ?? 'docker',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        return self::$pdo;
    }

    protected function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result ?: null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    protected function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    // SQL musi kończyć się: RETURNING id
    protected function insert(string $sql, array $params = []): int
    {
        $result = $this->fetchOne($sql, $params);
        return $result ? (int) $result['id'] : 0;
    }
}
