<?php

require_once __DIR__ . '/AppController.php';
require_once __DIR__ . '/../repository/UserRepository.php';
require_once __DIR__ . '/../utils/Validator.php';

class AuthController extends AppController
{
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
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        $body = $this->getBody();

        $v = (new Validator())
            ->required('email',    $body['email']    ?? '')
            ->email('email',       $body['email']    ?? '')
            ->required('password', $body['password'] ?? '');

        if (!$v->passes()) {
            $this->jsonResponse(['errors' => $v->errors()], 422);
            return;
        }

        $user = $this->users->findByEmail($body['email']);

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy email lub hasło.']], 401);
            return;
        }

        session_regenerate_id(true);
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
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
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
            $this->jsonResponse(['errors' => ['general' => 'Nieprawidłowy token CSRF.']], 403);
            return;
        }

        $body = $this->getBody();

        $v = (new Validator())
            ->required('full_name', $body['full_name'] ?? '')
            ->maxLength('full_name', $body['full_name'] ?? '', 100)
            ->required('email',    $body['email']    ?? '')
            ->email('email',       $body['email']    ?? '')
            ->required('password', $body['password'] ?? '')
            ->minLength('password', $body['password'] ?? '', 8)
            ->matches('password_confirm', $body['password_confirm'] ?? '', $body['password'] ?? '');

        if (!$v->passes()) {
            $this->jsonResponse(['errors' => $v->errors()], 422);
            return;
        }

        if ($this->users->emailExists($body['email'])) {
            $this->jsonResponse(['errors' => ['email' => 'Ten adres email jest już zajęty.']], 409);
            return;
        }

        $hash = password_hash($body['password'], PASSWORD_BCRYPT);
        $this->users->create($body['email'], $hash, $body['full_name']);

        $this->jsonResponse([
            'redirect' => '/login',
            'message'  => 'Konto zostało utworzone. Możesz się zalogować.',
        ]);
    }
}
