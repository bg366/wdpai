<?php

abstract class AppController
{
    private const VIEWS_PATH = __DIR__ . '/../../public/views/';

    protected function render(string $view): void
    {
        $path = self::VIEWS_PATH . $view;
        if (!file_exists($path)) {
            http_response_code(404);
            include self::VIEWS_PATH . '404.html';
            return;
        }
        include $path;
    }

    protected function jsonResponse(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function requireLogin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            $this->redirect('/login');
        }
    }

    protected function requireRole(string $role): void
    {
        $this->requireLogin();
        if ($_SESSION['user_role'] !== $role) {
            http_response_code(403);
            $this->render('403.html');
            exit;
        }
    }

    protected function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'    => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name'  => $_SESSION['user_name'],
            'role'  => $_SESSION['user_role'],
        ];
    }

    protected function getBody(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }
}
