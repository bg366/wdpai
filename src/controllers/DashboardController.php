<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/IncidentRepository.php';
require_once __DIR__ . '/../repository/UserRepository.php';

class DashboardController extends AppController
{
    private IncidentRepository $incidents;
    private UserRepository $users;

    public function __construct()
    {
        $this->incidents = new IncidentRepository();
        $this->users = new UserRepository();
    }

    public function index(array $params): void
    {
        $this->requireLogin();
        $this->renderSpaPage('Panel — SafeCity', 'dashboard');
    }

    public function stats(array $params): void
    {
        $this->requireLogin();

        $user = $this->getCurrentUser();
        $role = (string) ($user['role'] ?? 'citizen');
        $userId = (int) ($user['id'] ?? 0);

        $this->jsonResponse([
            'stats' => $this->incidents->getDashboardStats($role, $userId),
            'recent_incidents' => $this->incidents->findRecentIncidents($role, $userId, 6),
            'category_breakdown' => $this->incidents->getCategoryBreakdown($role, $userId, 6),
            'recent_activity' => $role === 'admin' ? $this->incidents->getRecentActivity(8) : [],
            'users_by_role' => $role === 'admin' ? $this->users->countByRole() : [],
        ]);
    }
}
