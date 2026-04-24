<?php

abstract class AppController
{
    private const VIEWS_PATH = __DIR__ . '/../../public/views/';

    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function ensureCsrfToken(): void
    {
        $this->startSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    protected function render(string $view): void
    {
        $this->ensureCsrfToken();

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
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    protected function requireLogin(): void
    {
        $this->startSession();
        if (empty($_SESSION['user_id'])) {
            if ($this->isApiRequest()) {
                $this->jsonResponse([
                    'error' => 'Authentication required.',
                    'redirect' => '/login',
                ], 401);
                exit;
            }

            $this->redirect('/login');
        }
    }

    protected function requireRole(string $role): void
    {
        $this->requireLogin();
        if ($_SESSION['user_role'] !== $role) {
            if ($this->isApiRequest()) {
                $this->jsonResponse(['error' => 'Forbidden.'], 403);
                exit;
            }

            $this->renderStatusPage(403, 'Brak dostępu', 'Ta część systemu jest dostępna wyłącznie dla uprawnionych użytkowników.');
            exit;
        }
    }

    protected function getCurrentUser(): ?array
    {
        $this->startSession();
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
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?? [];
        }

        if (!empty($_POST)) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }

    protected function validateCsrf(): bool
    {
        $this->startSession();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    protected function isApiRequest(): bool
    {
        $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
        return str_starts_with($path, 'api/');
    }

    protected function e(null|string|int|float $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    protected function csrfToken(): string
    {
        $this->ensureCsrfToken();
        return (string) ($_SESSION['csrf_token'] ?? '');
    }

    protected function flash(string $type, string $message): void
    {
        $this->startSession();
        $_SESSION['_flash'][$type] = $message;
    }

    protected function pullFlash(string $type): ?string
    {
        $this->startSession();
        $message = $_SESSION['_flash'][$type] ?? null;
        unset($_SESSION['_flash'][$type]);
        return $message;
    }

    protected function renderStatusPage(int $status, string $title, string $message): void
    {
        http_response_code($status);
        $content = sprintf(
            '<section class="card"><h1>%s</h1><p>%s</p><p><a class="btn btn-primary" href="/dashboard">Wróć do panelu</a></p></section>',
            $this->e($title),
            $this->e($message)
        );

        $this->renderApplicationPage($title, $content);
    }

    protected function renderApplicationPage(string $title, string $content): void
    {
        $this->ensureCsrfToken();

        $user = $this->getCurrentUser();
        $csrfToken = $this->e($_SESSION['csrf_token'] ?? '');
        $safeTitle = $this->e($title);
        $userName = $this->e($user['name'] ?? 'Gość');
        $userRole = $this->e($user['role'] ?? 'guest');
        $adminLink = ($user['role'] ?? null) === 'admin'
            ? '<a href="/admin">Administracja</a>'
            : '';

        echo <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{$csrfToken}">
    <title>{$safeTitle} - SafeCity</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f7fb;
            --surface: #ffffff;
            --surface-alt: #eef3f9;
            --border: #d7dfeb;
            --text: #14324a;
            --muted: #5f7286;
            --primary: #0f6cbd;
            --danger: #c0392b;
            --warning: #d97706;
            --success: #15803d;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #eef4fb 0%, var(--bg) 100%);
            color: var(--text);
        }
        a { color: var(--primary); text-decoration: none; }
        header {
            background: #0d2236;
            color: #fff;
            padding: 1rem 1.5rem;
        }
        .topbar {
            max-width: 1120px;
            margin: 0 auto;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        nav {
            display: flex;
            gap: 0.85rem;
            align-items: center;
            flex-wrap: wrap;
        }
        nav a { color: #d9e8f6; font-weight: 600; }
        main {
            max-width: 1120px;
            margin: 0 auto;
            padding: 1.5rem;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .page-header h1 { margin: 0; }
        .grid {
            display: grid;
            gap: 1rem;
        }
        .grid.stats { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .grid.two { grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 16px 40px rgba(13, 34, 54, 0.06);
        }
        .stat-number {
            display: block;
            font-size: 1.9rem;
            font-weight: 700;
            margin-top: 0.35rem;
        }
        .muted { color: var(--muted); }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.2rem 0.7rem;
            font-size: 0.85rem;
            font-weight: 700;
            background: var(--surface-alt);
            color: var(--text);
        }
        .badge.status-new { background: #dbeafe; color: #1d4ed8; }
        .badge.status-in_progress { background: #fef3c7; color: #92400e; }
        .badge.status-resolved { background: #dcfce7; color: #166534; }
        .badge.status-rejected { background: #fee2e2; color: #991b1b; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 0.75rem;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        th { color: var(--muted); font-size: 0.9rem; }
        .stack {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        form.inline,
        .actions form {
            display: inline-flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
            margin: 0;
        }
        label {
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 600;
        }
        input, select, textarea, button {
            font: inherit;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        button,
        .btn {
            border: 0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-weight: 700;
            cursor: pointer;
            background: var(--surface-alt);
            color: var(--text);
        }
        .btn-primary,
        .btn.btn-primary {
            background: var(--primary);
            color: #fff;
        }
        .btn-danger {
            background: var(--danger);
            color: #fff;
        }
        .btn-warning {
            background: var(--warning);
            color: #fff;
        }
        .notice {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #e0f2fe;
            color: #0c4a6e;
        }
        .error {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            background: #fee2e2;
            color: #991b1b;
        }
        .timeline {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .timeline li {
            border-left: 3px solid var(--border);
            padding-left: 1rem;
        }
        .toolbar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        .toolbar > * {
            min-width: 160px;
            flex: 1 1 180px;
        }
        @media (max-width: 640px) {
            main { padding: 1rem; }
            th:nth-child(4),
            td:nth-child(4),
            th:nth-child(5),
            td:nth-child(5) { display: none; }
        }
    </style>
</head>
<body>
    <header>
        <div class="topbar">
            <div>
                <strong>SafeCity</strong>
                <div class="muted" style="color:#b6c7d9;">Panel {$userRole}</div>
            </div>
            <nav>
                <a href="/dashboard">Dashboard</a>
                <a href="/incidents">Incydenty</a>
                <a href="/incidents/report">Zgłoś incydent</a>
                {$adminLink}
                <form class="inline" action="/logout" method="post">
                    <input type="hidden" name="_csrf" value="{$csrfToken}">
                    <button type="submit">Wyloguj</button>
                </form>
            </nav>
            <div>{$userName}</div>
        </div>
    </header>
    <main>
        {$content}
    </main>
</body>
</html>
HTML;
    }
}
