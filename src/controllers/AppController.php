<?php

abstract class AppController
{
    private const VIEWS_PATH = __DIR__ . '/../../public/views/';

    protected function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // BINGO C3/E3: ciasteczko sesyjne ma HttpOnly i SameSite=Lax.
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $this->isHttpsRequest(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    protected function ensureCsrfToken(): void
    {
        $this->startSession();

        if (empty($_SESSION['csrf_token'])) {
            // BINGO B2/C2: jeden token CSRF trafia do formularzy logowania i rejestracji.
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

    protected function renderSpaPage(string $title, string $page): void
    {
        $this->ensureCsrfToken();

        $user = $this->getCurrentUser() ?? [
            'name' => 'Użytkownik',
            'email' => '',
            'role' => 'citizen',
        ];

        // BINGO D4: dane wypisywane do HTML/JS sa escapowane przed renderowaniem.
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $csrfToken = htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $bootstrap = json_encode(
            [
                'page' => $page,
                'user' => [
                    'name' => $user['name'] ?? 'Użytkownik',
                    'email' => $user['email'] ?? '',
                    'role' => $user['role'] ?? 'citizen',
                ],
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        ) ?: '{}';

        echo <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{$csrfToken}">
  <title>{$safeTitle}</title>
  <link rel="stylesheet" href="/public/css/main.css">
</head>
<body class="app-page">
  <div id="app-root"></div>
  <script id="app-bootstrap" type="application/json">{$bootstrap}</script>
  <script src="/public/js/app.js"></script>
</body>
</html>
HTML;
    }

    protected function jsonResponse(mixed $data, int $status = 200): void
    {
        // BINGO A5: kontrolery zwracaja jawne kody HTTP dla odpowiedzi i bledow.
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
                    'error' => 'Wymagane jest zalogowanie.',
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
                $this->jsonResponse(['error' => 'Brak dostępu.'], 403);
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

    protected function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    protected function renderStatusPage(int $status, string $title, string $message): void
    {
        http_response_code($status);
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $isAuthenticated = $this->getCurrentUser() !== null;
        $backUrl = $isAuthenticated ? '/dashboard' : '/login';
        $backLabel = $isAuthenticated ? 'Wróć do panelu' : 'Wróć do logowania';

        echo <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeTitle} — SafeCity</title>
  <link rel="stylesheet" href="/public/css/main.css">
</head>
<body class="app-page app-page--status">
  <main class="status-screen">
    <section class="surface-card surface-card--padded status-card">
      <p class="page-eyebrow">SafeCity</p>
      <h1>{$safeTitle}</h1>
      <p class="status-card__copy">{$safeMessage}</p>
      <a class="ui-btn ui-btn--primary" href="{$backUrl}">{$backLabel}</a>
    </section>
  </main>
</body>
</html>
HTML;
    }
}
