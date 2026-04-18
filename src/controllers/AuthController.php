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
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('login.html');
    }

    public function registerPage(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        $this->render('register.html');
    }

    public function login(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

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
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION = [];
        session_destroy();

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->redirect('/login');
        } else {
            $this->jsonResponse(['redirect' => '/login']);
        }
    }

    public function register(array $params): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

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
