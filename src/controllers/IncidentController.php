<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/CategoryRepository.php';
require_once __DIR__ . '/../repository/IncidentRepository.php';
require_once __DIR__ . '/../utils/Validator.php';

class IncidentController extends AppController
{
    private IncidentRepository $incidents;
    private CategoryRepository $categories;

    public function __construct()
    {
        $this->incidents = new IncidentRepository();
        $this->categories = new CategoryRepository();
    }

    public function listPage(array $params): void
    {
        $this->requireLogin();
        $this->render('incidents/index.html');
    }

    public function reportPage(array $params): void
    {
        $this->requireLogin();
        $this->render('incidents/report.html');
    }

    public function previewPage(array $params): void
    {
        $this->requireLogin();

        $incidentId = $this->extractIncidentId($params);
        if ($incidentId === null) {
            $this->renderStatusPage(404, 'Nie znaleziono zgłoszenia', 'Podany identyfikator zgłoszenia jest nieprawidłowy.');
            return;
        }

        $user = $this->getCurrentUser();
        $incident = $this->incidents->findById($incidentId, (string) $user['role'], (int) $user['id']);

        if ($incident === null) {
            $this->renderStatusPage(404, 'Nie znaleziono zgłoszenia', 'To zgłoszenie nie istnieje lub nie masz do niego dostępu.');
            return;
        }

        $this->render('incidents/detail.html');
    }

    public function submitReport(array $params): void
    {
        $this->requireLogin();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Nieprawidłowy token CSRF.');
            $this->redirect('/incidents/report');
        }

        [$status, $response] = $this->createIncidentFromPayload($this->getBody());

        if ($status >= 400) {
            $this->flash('error', $this->firstErrorMessage($response, 'Nie udało się dodać zgłoszenia.'));
            $this->redirect('/incidents/report');
        }

        $this->flash('success', 'Zgłoszenie zostało zapisane.');
        $this->redirect('/incidents/' . $response['incident']['id']);
    }

    public function updateFromPage(array $params): void
    {
        $this->requireLogin();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Nieprawidłowy token CSRF.');
            $this->redirect('/incidents');
        }

        $incidentId = $this->extractIncidentId($params);
        if ($incidentId === null) {
            $this->flash('error', 'Nieprawidłowy identyfikator zgłoszenia.');
            $this->redirect('/incidents');
        }

        [$status, $response] = $this->updateIncidentFromPayload($incidentId, $this->getBody());

        if ($status >= 400) {
            $this->flash('error', $this->firstErrorMessage($response, 'Nie udało się zaktualizować zgłoszenia.'));
            $this->redirect('/incidents/' . $incidentId);
        }

        $this->flash('success', 'Zgłoszenie zostało zaktualizowane.');
        $this->redirect('/incidents/' . $incidentId);
    }

    public function deleteFromPage(array $params): void
    {
        $this->requireLogin();

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Nieprawidłowy token CSRF.');
            $this->redirect('/incidents');
        }

        $incidentId = $this->extractIncidentId($params);
        if ($incidentId === null) {
            $this->flash('error', 'Nieprawidłowy identyfikator zgłoszenia.');
            $this->redirect('/incidents');
        }

        [$status, $response] = $this->deleteIncidentById($incidentId);

        if ($status >= 400) {
            $this->flash('error', $response['error'] ?? 'Nie udało się usunąć zgłoszenia.');
            $this->redirect('/incidents/' . $incidentId);
        }

        $this->flash('success', 'Zgłoszenie zostało usunięte.');
        $this->redirect('/incidents');
    }

    public function list(array $params): void
    {
        $this->requireLogin();

        $user = $this->getCurrentUser();
        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'category_id' => $_GET['category_id'] ?? null,
            'search' => trim((string) ($_GET['search'] ?? '')),
        ];

        if ($filters['category_id'] === '' || $filters['category_id'] === null) {
            unset($filters['category_id']);
        } else {
            $filters['category_id'] = (int) $filters['category_id'];
        }

        $this->jsonResponse([
            'incidents' => $this->incidents->listIncidents((string) $user['role'], (int) $user['id'], $filters),
            'filters' => [
                'status' => $filters['status'] ?? '',
                'category_id' => $filters['category_id'] ?? null,
                'search' => $filters['search'] ?? '',
            ],
            'meta' => [
                'categories' => $this->categories->findAll(),
                'statuses' => $this->incidents->findStatuses(),
            ],
        ]);
    }

    public function create(array $params): void
    {
        $this->requireLogin();

        if (!$this->validateCsrf()) {
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        [$status, $response] = $this->createIncidentFromPayload($this->getBody());
        $this->jsonResponse($response, $status);
    }

    public function get(array $params): void
    {
        $this->requireLogin();

        $user = $this->getCurrentUser();
        $incidentId = $this->extractIncidentId($params);
        if ($incidentId === null) {
            $this->jsonResponse(['error' => 'Nieprawidłowy identyfikator zgłoszenia.'], 400);
            return;
        }

        $incident = $this->incidents->findById($incidentId, (string) $user['role'], (int) $user['id']);
        if ($incident === null) {
            $this->jsonResponse(['error' => 'Zgłoszenie nie zostało znalezione.'], 404);
            return;
        }

        $this->jsonResponse([
            'incident' => $incident,
            'history' => $this->incidents->getHistory($incidentId),
            'meta' => [
                'categories' => $this->categories->findAll(),
                'statuses' => $this->incidents->findStatuses(),
                'permissions' => [
                    'can_edit' => $this->canEditIncident($incident, $user),
                    'can_delete' => $this->canDeleteIncident($incident, $user),
                    'can_change_status' => (string) $user['role'] === 'admin',
                ],
            ],
        ]);
    }

    public function update(array $params): void
    {
        $this->requireLogin();

        if (!$this->validateCsrf()) {
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        $incidentId = $this->extractIncidentId($params);
        if ($incidentId === null) {
            $this->jsonResponse(['error' => 'Nieprawidłowy identyfikator zgłoszenia.'], 400);
            return;
        }

        [$status, $response] = $this->updateIncidentFromPayload($incidentId, $this->getBody());
        $this->jsonResponse($response, $status);
    }

    public function remove(array $params): void
    {
        $this->requireLogin();

        if (!$this->validateCsrf()) {
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        $incidentId = $this->extractIncidentId($params);
        if ($incidentId === null) {
            $this->jsonResponse(['error' => 'Nieprawidłowy identyfikator zgłoszenia.'], 400);
            return;
        }

        [$status, $response] = $this->deleteIncidentById($incidentId);
        $this->jsonResponse($response, $status);
    }

    private function createIncidentFromPayload(array $payload): array
    {
        $user = $this->getCurrentUser();
        $validated = $this->validateCreatePayload($payload);

        if (!$validated['valid']) {
            return [422, ['errors' => $validated['errors']]];
        }

        $incident = $this->incidents->createIncident($validated['payload'], (int) $user['id']);

        return [201, [
            'message' => 'Zgłoszenie zostało utworzone.',
            'redirect' => '/incidents/' . ($incident['id'] ?? ''),
            'incident' => $incident,
        ]];
    }

    private function updateIncidentFromPayload(int $incidentId, array $payload): array
    {
        $user = $this->getCurrentUser();
        $role = (string) ($user['role'] ?? 'citizen');
        $currentIncident = $this->incidents->findById($incidentId, $role, (int) $user['id']);

        if ($currentIncident === null) {
            return [404, ['error' => 'Zgłoszenie nie zostało znalezione.']];
        }

        if (!$this->canEditIncident($currentIncident, $user)) {
            return [403, ['error' => 'Nie masz uprawnień do edycji tego zgłoszenia.']];
        }

        if ($role !== 'admin' && (string) $currentIncident['status_name'] !== 'new') {
            return [409, ['error' => 'Możesz edytować tylko zgłoszenia ze statusem new.']];
        }

        $validated = $this->validateUpdatePayload($payload, $role);
        if (!$validated['valid']) {
            return [422, ['errors' => $validated['errors']]];
        }

        if ($validated['payload'] === []) {
            return [422, ['errors' => ['general' => 'Brak danych do aktualizacji.']]];
        }

        $updated = $this->incidents->updateIncident($currentIncident, $validated['payload'], $user);

        return [200, [
            'message' => 'Zgłoszenie zostało zaktualizowane.',
            'incident' => $updated,
        ]];
    }

    private function deleteIncidentById(int $incidentId): array
    {
        $user = $this->getCurrentUser();
        $role = (string) ($user['role'] ?? 'citizen');
        $incident = $this->incidents->findById($incidentId, $role, (int) $user['id']);

        if ($incident === null) {
            return [404, ['error' => 'Zgłoszenie nie zostało znalezione.']];
        }

        if (!$this->canDeleteIncident($incident, $user)) {
            return [403, ['error' => 'Nie masz uprawnień do usunięcia tego zgłoszenia.']];
        }

        if (!$this->incidents->deleteIncident($incidentId)) {
            return [500, ['error' => 'Nie udało się usunąć zgłoszenia.']];
        }

        return [200, [
            'message' => 'Zgłoszenie zostało usunięte.',
            'redirect' => '/incidents',
        ]];
    }

    private function validateCreatePayload(array $payload): array
    {
        $validator = (new Validator())
            ->required('title', $payload['title'] ?? '')
            ->maxLength('title', trim((string) ($payload['title'] ?? '')), 255)
            ->required('description', $payload['description'] ?? '')
            ->required('location', $payload['location'] ?? '')
            ->maxLength('location', trim((string) ($payload['location'] ?? '')), 255);

        $errors = $validator->errors();
        $categoryId = filter_var($payload['category_id'] ?? null, FILTER_VALIDATE_INT);

        if ($categoryId === false || $categoryId === null) {
            $errors['category_id'] = 'Kategoria jest wymagana.';
        } elseif (!$this->categories->exists((int) $categoryId)) {
            $errors['category_id'] = 'Wybrana kategoria nie istnieje.';
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        return [
            'valid' => true,
            'payload' => [
                'title' => trim((string) $payload['title']),
                'description' => trim((string) $payload['description']),
                'location' => trim((string) $payload['location']),
                'category_id' => (int) $categoryId,
            ],
        ];
    }

    private function validateUpdatePayload(array $payload, string $role): array
    {
        $errors = [];
        $normalized = [];

        if (array_key_exists('title', $payload)) {
            $title = trim((string) $payload['title']);
            if ($title === '') {
                $errors['title'] = 'Tytuł jest wymagany.';
            } elseif (mb_strlen($title) > 255) {
                $errors['title'] = 'Maksymalna długość to 255 znaków.';
            } else {
                $normalized['title'] = $title;
            }
        }

        if (array_key_exists('description', $payload)) {
            $description = trim((string) $payload['description']);
            if ($description === '') {
                $errors['description'] = 'Opis jest wymagany.';
            } else {
                $normalized['description'] = $description;
            }
        }

        if (array_key_exists('location', $payload)) {
            $location = trim((string) $payload['location']);
            if ($location === '') {
                $errors['location'] = 'Lokalizacja jest wymagana.';
            } elseif (mb_strlen($location) > 255) {
                $errors['location'] = 'Maksymalna długość to 255 znaków.';
            } else {
                $normalized['location'] = $location;
            }
        }

        if (array_key_exists('category_id', $payload)) {
            $categoryId = filter_var($payload['category_id'], FILTER_VALIDATE_INT);
            if ($categoryId === false || !$this->categories->exists((int) $categoryId)) {
                $errors['category_id'] = 'Wybrana kategoria nie istnieje.';
            } else {
                $normalized['category_id'] = (int) $categoryId;
            }
        }

        if ($role === 'admin' && array_key_exists('status_id', $payload)) {
            $statusId = filter_var($payload['status_id'], FILTER_VALIDATE_INT);
            if ($statusId === false || $this->incidents->findStatusById((int) $statusId) === null) {
                $errors['status_id'] = 'Wybrany status nie istnieje.';
            } else {
                $normalized['status_id'] = (int) $statusId;
            }
        }

        if ($role === 'admin' && array_key_exists('admin_note', $payload)) {
            $normalized['admin_note'] = trim((string) $payload['admin_note']);
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'payload' => $normalized,
        ];
    }

    private function canEditIncident(array $incident, array $user): bool
    {
        if ((string) ($user['role'] ?? '') === 'admin') {
            return true;
        }

        return (int) ($incident['reporter_id'] ?? 0) === (int) ($user['id'] ?? 0);
    }

    private function canDeleteIncident(array $incident, array $user): bool
    {
        if ((string) ($user['role'] ?? '') === 'admin') {
            return true;
        }

        return (int) ($incident['reporter_id'] ?? 0) === (int) ($user['id'] ?? 0)
            && (string) ($incident['status_name'] ?? '') === 'new';
    }

    private function extractIncidentId(array $params): ?int
    {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        return $id === false ? null : (int) $id;
    }

    private function firstErrorMessage(array $response, string $fallback): string
    {
        if (!empty($response['error']) && is_string($response['error'])) {
            return $response['error'];
        }

        if (!empty($response['errors']) && is_array($response['errors'])) {
            $first = reset($response['errors']);
            if (is_string($first)) {
                return $first;
            }
        }

        return $fallback;
    }
}
