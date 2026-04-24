<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class AdminController extends AppController
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function index(array $params): void
    {
        $this->requireRole('admin');
        $this->render('admin/index.html');
    }

    public function users(array $params): void
    {
        $this->requireRole('admin');

        $this->jsonResponse([
            'users' => $this->users->findAll(),
            'roles' => $this->users->getRoles(),
            'counts_by_role' => $this->users->countByRole(),
        ]);
    }

    public function updateUser(array $params): void
    {
        $this->requireRole('admin');

        if (!$this->validateCsrf()) {
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        $userId = $this->extractUserId($params);
        if ($userId === null) {
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy identyfikator użytkownika.']], 400);
            return;
        }

        $result = $this->applyRoleUpdate($userId, $this->getBody());
        $status = (int) ($result['status'] ?? 200);
        unset($result['status']);

        $this->jsonResponse($result, $status);
    }

    public function updateUserFromPage(array $params): void
    {
        $this->requireRole('admin');

        if (!$this->validateCsrf()) {
            $this->flash('error', 'Nieprawidłowy token CSRF.');
            $this->redirect('/admin');
        }

        $userId = $this->extractUserId($params);
        if ($userId === null) {
            $this->flash('error', 'Nieprawidłowy identyfikator użytkownika.');
            $this->redirect('/admin');
        }

        $result = $this->applyRoleUpdate($userId, $this->getBody());
        if (isset($result['errors'])) {
            $firstError = reset($result['errors']);
            $this->flash('error', is_string($firstError) ? $firstError : 'Nie udało się zaktualizować roli.');
            $this->redirect('/admin');
        }

        $this->flash('success', 'Rola użytkownika została zaktualizowana.');
        $this->redirect('/admin');
    }

    private function applyRoleUpdate(int $userId, array $body): array
    {
        $role = trim((string) ($body['role'] ?? ''));
        if ($role === '' || !$this->users->roleExists($role)) {
            return ['status' => 422, 'errors' => ['role' => 'Wybierz poprawną rolę.']];
        }

        $currentUser = $this->getCurrentUser();
        if ((int) ($currentUser['id'] ?? 0) === $userId) {
            return ['status' => 422, 'errors' => ['role' => 'Nie można zmienić własnej roli podczas aktywnej sesji.']];
        }

        $targetUser = $this->users->findById($userId);
        if ($targetUser === null) {
            return ['status' => 404, 'errors' => ['general' => 'Użytkownik nie istnieje.']];
        }

        if ($targetUser['role_name'] === 'admin' && $role !== 'admin' && $this->users->countAdmins() <= 1) {
            return ['status' => 409, 'errors' => ['role' => 'System musi mieć co najmniej jednego administratora.']];
        }

        $updatedUser = $this->users->updateRole($userId, $role);
        if ($updatedUser === null) {
            return ['status' => 500, 'errors' => ['general' => 'Nie udało się zaktualizować roli.']];
        }

        return [
            'message' => 'Rola użytkownika została zaktualizowana.',
            'user' => $updatedUser,
        ];
    }

    private function extractUserId(array $params): ?int
    {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT);
        return $id === false ? null : (int) $id;
    }
}
