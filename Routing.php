<?php

class Routing
{
    private static array $routes = [];

    public static function get(string $pattern, string $controller, string $method): void
    {
        self::$routes['GET'][] = compact('pattern', 'controller', 'method');
    }

    public static function post(string $pattern, string $controller, string $method): void
    {
        self::$routes['POST'][] = compact('pattern', 'controller', 'method');
    }

    public static function patch(string $pattern, string $controller, string $method): void
    {
        self::$routes['PATCH'][] = compact('pattern', 'controller', 'method');
    }

    public static function delete(string $pattern, string $controller, string $method): void
    {
        self::$routes['DELETE'][] = compact('pattern', 'controller', 'method');
    }

    public static function run(string $path): void
    {
        self::registerRoutes();

        $httpMethod = self::resolveHttpMethod();
        $routes = self::$routes[$httpMethod] ?? [];

        foreach ($routes as $route) {
            $params = self::match($route['pattern'], $path);
            if ($params !== null) {
                self::dispatch($route['controller'], $route['method'], $params);
                return;
            }
        }

        http_response_code(404);
        include __DIR__ . '/public/views/404.html';
    }

    private static function registerRoutes(): void
    {
        // Page routes
        // BINGO A2: GET tylko renderuje widoki logowania i rejestracji.
        self::get('',                   'AuthController',      'loginPage');
        self::get('login',              'AuthController',      'loginPage');
        self::get('register',           'AuthController',      'registerPage');
        self::get('logout',             'AuthController',      'logout');
        self::post('logout',            'AuthController',      'logout');
        self::get('dashboard',          'DashboardController', 'index');
        self::get('incidents',          'IncidentController',  'listPage');
        self::get('incidents/report',   'IncidentController',  'reportPage');
        self::get('incidents/{id}',     'IncidentController',  'previewPage');
        self::get('admin',              'AdminController',     'index');
        self::get('admin/dashboard',    'AdminController',     'index');

        // Auth API
        // BINGO A2: dane logowania/rejestracji sa obslugiwane wylacznie przez POST.
        self::post('api/auth/login',    'AuthController', 'login');
        self::post('api/auth/logout',   'AuthController', 'logout');
        self::post('api/auth/register', 'AuthController', 'register');

        // Incidents API
        self::get('api/incidents',         'IncidentController', 'list');
        self::post('api/incidents',        'IncidentController', 'create');
        self::get('api/incidents/{id}',    'IncidentController', 'get');
        self::patch('api/incidents/{id}',  'IncidentController', 'update');
        self::delete('api/incidents/{id}', 'IncidentController', 'remove');

        // Dashboard & Categories API
        self::get('api/dashboard/stats', 'DashboardController', 'stats');
        self::get('api/categories',      'CategoryController',  'list');

        // Admin API
        self::get('api/admin/users',        'AdminController', 'users');
        self::patch('api/admin/users/{id}', 'AdminController', 'updateUser');
    }

    private static function match(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts    = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $i => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $params[trim($part, '{}')] = $pathParts[$i];
            } elseif ($part !== $pathParts[$i]) {
                return null;
            }
        }

        return $params;
    }

    private static function resolveHttpMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;

            if (is_string($override)) {
                $override = strtoupper(trim($override));
                if (in_array($override, ['PATCH', 'DELETE'], true)) {
                    return $override;
                }
            }
        }

        return $method;
    }

    private static function dispatch(string $controllerName, string $method, array $params): void
    {
        $file = __DIR__ . "/src/controllers/{$controllerName}.php";

        if (!file_exists($file)) {
            http_response_code(500);
            echo json_encode(['error' => "Controller not found: {$controllerName}"]);
            return;
        }

        require_once __DIR__ . '/src/controllers/AppController.php';
        require_once $file;

        if (!class_exists($controllerName)) {
            http_response_code(500);
            echo json_encode(['error' => "Controller class not found: {$controllerName}"]);
            return;
        }

        $controller = new $controllerName();
        if (!method_exists($controller, $method)) {
            http_response_code(500);
            echo json_encode(['error' => "Method not found: {$controllerName}::{$method}"]);
            return;
        }

        $controller->$method($params);
    }
}
