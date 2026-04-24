<?php

require_once __DIR__ . '/Repository.php';

class IncidentRepository extends Repository
{
    public function findStatuses(): array
    {
        return $this->fetchAll(
            'SELECT id, name
             FROM incident_statuses
             ORDER BY id'
        );
    }

    public function findStatusByName(string $name): ?array
    {
        return $this->fetchOne(
            'SELECT id, name
             FROM incident_statuses
             WHERE name = :name',
            ['name' => $name]
        );
    }

    public function findStatusById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT id, name
             FROM incident_statuses
             WHERE id = :id',
            ['id' => $id]
        );
    }

    public function listIncidents(string $role, int $userId, array $filters = []): array
    {
        [$whereSql, $params] = $this->buildIncidentFilters($role, $userId, $filters);

        return $this->fetchAll(
            "SELECT *
             FROM incidents_summary
             WHERE {$whereSql}
             ORDER BY created_at DESC, id DESC",
            $params
        );
    }

    public function findById(int $id, string $role, int $userId): ?array
    {
        $params = ['id' => $id];
        $visibility = $this->visibilityCondition($role, $userId, $params);

        return $this->fetchOne(
            "SELECT *
             FROM incidents_summary
             WHERE id = :id
               AND {$visibility}",
            $params
        );
    }

    public function findRecentIncidents(string $role, int $userId, int $limit = 5): array
    {
        $limit = max(1, $limit);
        $params = [];
        $visibility = $this->visibilityCondition($role, $userId, $params);

        return $this->fetchAll(
            "SELECT *
             FROM incidents_summary
             WHERE {$visibility}
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}",
            $params
        );
    }

    public function getDashboardStats(string $role, int $userId): array
    {
        if ($role === 'admin') {
            $stats = $this->fetchOne(
                'SELECT
                    new_count,
                    in_progress_count,
                    resolved_count,
                    rejected_count,
                    total
                 FROM dashboard_stats'
            );

            return $stats ?? [
                'new_count' => 0,
                'in_progress_count' => 0,
                'resolved_count' => 0,
                'rejected_count' => 0,
                'total' => 0,
            ];
        }

        $stats = $this->fetchOne(
            'SELECT
                COUNT(*) FILTER (WHERE status_name = :new_status) AS new_count,
                COUNT(*) FILTER (WHERE status_name = :in_progress_status) AS in_progress_count,
                COUNT(*) FILTER (WHERE status_name = :resolved_status) AS resolved_count,
                COUNT(*) FILTER (WHERE status_name = :rejected_status) AS rejected_count,
                COUNT(*) AS total
             FROM incidents_summary
             WHERE reporter_id = :user_id',
            [
                'user_id' => $userId,
                'new_status' => 'new',
                'in_progress_status' => 'in_progress',
                'resolved_status' => 'resolved',
                'rejected_status' => 'rejected',
            ]
        );

        return $stats ?? [
            'new_count' => 0,
            'in_progress_count' => 0,
            'resolved_count' => 0,
            'rejected_count' => 0,
            'total' => 0,
        ];
    }

    public function getCategoryBreakdown(string $role, int $userId, int $limit = 6): array
    {
        $limit = max(1, $limit);
        $params = [];
        $visibility = $this->visibilityCondition($role, $userId, $params);

        return $this->fetchAll(
            "SELECT
                COALESCE(category_name, 'Bez kategorii') AS category_name,
                COUNT(*) AS incidents_count
             FROM incidents_summary
             WHERE {$visibility}
             GROUP BY category_name
             ORDER BY incidents_count DESC, category_name ASC
             LIMIT {$limit}",
            $params
        );
    }

    public function getRecentActivity(int $limit = 8): array
    {
        $limit = max(1, $limit);

        return $this->fetchAll(
            'SELECT
                h.id,
                h.incident_id,
                h.note,
                h.created_at,
                from_status.name AS from_status_name,
                to_status.name AS to_status_name,
                actor.full_name AS actor_name,
                incident.title AS incident_title
             FROM incident_status_history h
             JOIN incidents incident ON incident.id = h.incident_id
             LEFT JOIN incident_statuses from_status ON from_status.id = h.from_status_id
             JOIN incident_statuses to_status ON to_status.id = h.to_status_id
             LEFT JOIN users actor ON actor.id = h.changed_by
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT ' . $limit
        );
    }

    public function getHistory(int $incidentId): array
    {
        return $this->fetchAll(
            'SELECT
                h.id,
                h.note,
                h.created_at,
                from_status.name AS from_status_name,
                to_status.name AS to_status_name,
                actor.full_name AS actor_name
             FROM incident_status_history h
             LEFT JOIN incident_statuses from_status ON from_status.id = h.from_status_id
             JOIN incident_statuses to_status ON to_status.id = h.to_status_id
             LEFT JOIN users actor ON actor.id = h.changed_by
             WHERE h.incident_id = :incident_id
             ORDER BY h.created_at DESC, h.id DESC',
            ['incident_id' => $incidentId]
        );
    }

    public function createIncident(array $payload, int $reportedBy): array
    {
        $incidentId = $this->transaction(function () use ($payload, $reportedBy) {
            $incidentId = $this->insert(
                'INSERT INTO incidents (title, description, location, category_id, reported_by, status_id)
                 VALUES (
                    :title,
                    :description,
                    :location,
                    :category_id,
                    :reported_by,
                    (SELECT id FROM incident_statuses WHERE name = :status_name)
                 )
                 RETURNING id',
                [
                    'title' => $payload['title'],
                    'description' => $payload['description'],
                    'location' => $payload['location'],
                    'category_id' => $payload['category_id'],
                    'reported_by' => $reportedBy,
                    'status_name' => 'new',
                ]
            );

            $newStatus = $this->findStatusByName('new');
            if ($newStatus !== null) {
                $this->recordHistory($incidentId, null, (int) $newStatus['id'], $reportedBy, 'Zgłoszenie zostało utworzone.');
            }

            return $incidentId;
        });

        return $this->findByIdUnrestricted($incidentId) ?? [];
    }

    public function updateIncident(array $currentIncident, array $payload, array $actor): array
    {
        $incidentId = (int) $currentIncident['id'];

        $this->transaction(function () use ($currentIncident, $payload, $actor, $incidentId) {
            $fields = [];
            $params = ['id' => $incidentId];

            foreach (['title', 'description', 'location', 'category_id', 'status_id'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $fields[] = "{$field} = :{$field}";
                    $params[$field] = $payload[$field];
                }
            }

            if ($fields !== []) {
                $this->execute(
                    'UPDATE incidents
                     SET ' . implode(', ', $fields) . '
                     WHERE id = :id',
                    $params
                );
            }

            $currentStatusId = (int) $currentIncident['status_id'];
            $nextStatusId = isset($payload['status_id']) ? (int) $payload['status_id'] : $currentStatusId;
            $note = trim((string) ($payload['admin_note'] ?? ''));

            if ($nextStatusId !== $currentStatusId || $note !== '') {
                $this->recordHistory(
                    $incidentId,
                    $currentStatusId,
                    $nextStatusId,
                    (int) $actor['id'],
                    $note !== '' ? $note : null
                );
            }
        });

        return $this->findByIdUnrestricted($incidentId) ?? [];
    }

    public function deleteIncident(int $incidentId): bool
    {
        return $this->execute(
            'DELETE FROM incidents
             WHERE id = :id',
            ['id' => $incidentId]
        ) > 0;
    }

    private function buildIncidentFilters(string $role, int $userId, array $filters): array
    {
        $params = [];
        $conditions = [$this->visibilityCondition($role, $userId, $params)];

        if (!empty($filters['status'])) {
            $params['status'] = $filters['status'];
            $conditions[] = 'status_name = :status';
        }

        if (!empty($filters['category_id'])) {
            $params['category_id'] = (int) $filters['category_id'];
            $conditions[] = 'category_id = :category_id';
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string) $filters['search']) . '%';
            $params['search_title'] = $search;
            $params['search_location'] = $search;
            $params['search_description'] = $search;
            $conditions[] = '(title ILIKE :search_title OR location ILIKE :search_location OR description ILIKE :search_description)';
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function visibilityCondition(string $role, int $userId, array &$params): string
    {
        if ($role === 'admin') {
            return '1 = 1';
        }

        $params['reporter_id'] = $userId;
        return 'reporter_id = :reporter_id';
    }

    private function findByIdUnrestricted(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT *
             FROM incidents_summary
             WHERE id = :id',
            ['id' => $id]
        );
    }

    private function recordHistory(int $incidentId, ?int $fromStatusId, int $toStatusId, ?int $changedBy, ?string $note): void
    {
        $this->execute(
            'INSERT INTO incident_status_history (incident_id, from_status_id, to_status_id, changed_by, note)
             VALUES (:incident_id, :from_status_id, :to_status_id, :changed_by, :note)',
            [
                'incident_id' => $incidentId,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatusId,
                'changed_by' => $changedBy,
                'note' => $note,
            ]
        );
    }
}
