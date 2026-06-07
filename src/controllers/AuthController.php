<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../utils/Validator.php';

class AuthController extends AppController
{
    // BINGO A4: prosta blokada czasowa po kilku nieudanych probach logowania.
    private const MAX_FAILED_LOGIN_ATTEMPTS = 3;
    private const LOGIN_LOCK_SECONDS = 120;

    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function loginPage(array $params): void
    {
        $this->startSession();
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('login.html');
    }

    public function registerPage(array $params): void
    {
        $this->startSession();
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('register.html');
    }

    public function login(array $params): void
    {
        $this->startSession();

        if (!$this->validateCsrf()) {
            // BINGO B2: logowanie wymaga poprawnego tokenu CSRF.
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        if ($this->isLoginLocked()) {
            // BINGO A4: po przekroczeniu limitu zwracamy 429 i blokujemy kolejne proby.
            $this->jsonResponse([
                'errors' => ['general' => 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za chwilę.'],
            ], 429);
            return;
        }

        $body = $this->getBody();

        // BINGO C1/D2: email ma walidowany format i limit dlugosci po stronie serwera.
        $v = (new Validator())
            ->required('email',    $body['email']    ?? '')
            ->email('email',       $body['email']    ?? '')
            ->maxLength('email',   $body['email']    ?? '', 255)
            ->required('password', $body['password'] ?? '')
            ->maxLength('password', $body['password'] ?? '', 72);

        if (!$v->passes()) {
            $this->jsonResponse(['errors' => $v->errors()], 422);
            return;
        }

        $user = $this->users->findByEmail($body['email']);

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            // BINGO B1: nie zdradzamy, czy email istnieje - komunikat jest zawsze ogolny.
            $this->recordFailedLogin((string) ($body['email'] ?? ''));
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy email lub hasło.']], 401);
            return;
        }

        $this->clearFailedLoginAttempts();
        // BINGO B3: po poprawnym logowaniu regenerujemy ID sesji.
        session_regenerate_id(true);
        // BINGO B5: do sesji i odpowiedzi nie przekazujemy hasla ani password_hash.
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name']  = $user['full_name'];
        $_SESSION['user_role']  = $user['role_name'];

        $this->jsonResponse(['redirect' => '/dashboard']);
    }

    public function logout(array $params): void
    {
        $this->startSession();

        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !$this->validateCsrf()) {
            if ($this->isApiRequest()) {
                $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
                return;
            }

            $this->renderStatusPage(403, 'Nieprawidłowy token CSRF', 'Nie udało się wylogować z powodu błędnego tokenu bezpieczeństwa.');
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        // BINGO D5: poprawne wylogowanie czysci dane i niszczy sesje.
        session_destroy();

        if ($this->isApiRequest()) {
            $this->jsonResponse(['redirect' => '/login']);
            return;
        }

        $this->redirect('/login');
    }

    public function register(array $params): void
    {
        $this->startSession();

        if (!$this->validateCsrf()) {
            // BINGO C2: rejestracja wymaga poprawnego tokenu CSRF.
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        $body = $this->getBody();

        // BINGO B4/D2: haslo ma minimalna dlugosc, a pola formularza maja limity.
        $v = (new Validator())
            ->required('full_name', $body['full_name'] ?? '')
            ->maxLength('full_name', $body['full_name'] ?? '', 100)
            ->required('email',    $body['email']    ?? '')
            ->email('email',       $body['email']    ?? '')
            ->maxLength('email',   $body['email']    ?? '', 255)
            ->required('password', $body['password'] ?? '')
            ->minLength('password', $body['password'] ?? '', 8)
            ->maxLength('password', $body['password'] ?? '', 72)
            ->maxLength('password_confirm', $body['password_confirm'] ?? '', 72)
            ->matches('password_confirm', $body['password_confirm'] ?? '', $body['password'] ?? '');

        if (!$v->passes()) {
            $this->jsonResponse(['errors' => $v->errors()], 422);
            return;
        }

        if ($this->users->emailExists($body['email'])) {
            $this->jsonResponse(['errors' => ['email' => 'Ten adres email jest już zajęty.']], 409);
            return;
        }

        // BINGO E2: haslo zapisujemy w bazie tylko jako hash bcrypt.
        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        $this->users->create($body['email'], $hash, $body['full_name']);

        $this->jsonResponse([
            'redirect' => '/login',
            'message'  => 'Konto zostało utworzone. Możesz się zalogować.',
        ]);
    }

    private function isLoginLocked(): bool
    {
        return (int) ($_SESSION['login_lock_until'] ?? 0) > time();
    }

    private function recordFailedLogin(string $email): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // BINGO A3/E5: log audytowy nie zawiera hasla, tylko email i IP.
        error_log(sprintf(
            'Failed login for %s from IP %s',
            $this->safeAuditValue($email),
            $this->safeAuditValue($ip)
        ));

        $attempts = (int) ($_SESSION['failed_login_attempts'] ?? 0) + 1;
        $_SESSION['failed_login_attempts'] = $attempts;

        if ($attempts >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
            $_SESSION['failed_login_attempts'] = 0;
            $_SESSION['login_lock_until'] = time() + self::LOGIN_LOCK_SECONDS;
        }
    }

    private function clearFailedLoginAttempts(): void
    {
        unset($_SESSION['failed_login_attempts'], $_SESSION['login_lock_until']);
    }

    private function safeAuditValue(string $value): string
    {
        return str_replace(["\r", "\n"], '', substr($value, 0, 255));
    }
}
