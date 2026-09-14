<?php
declare(strict_types=1);

namespace JFS;

use PDO;
use Throwable;

final class Application
{
    private readonly PDO $db;
    private readonly View $view;
    private readonly AuthService $auth;
    private readonly TenantContext $tenant;

    public function __construct(private readonly array $config)
    {
        $this->startSession();
        $this->securityHeaders();
        $this->db = (new Database($config['database'] ?? []))->pdo();
        $this->view = new View(JFS_ROOT . '/resources/views');
        $this->auth = new AuthService($this->db);
        $this->tenant = new TenantContext();
    }

    public function run(): void
    {
        try {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $path = $this->path();

            if ($path === '/login') {
                $this->login($method);
                return;
            }
            if ($path === '/logout' && $method === 'POST') {
                $this->assertCsrf();
                $this->auth->logout();
                $this->redirect('/login');
            }

            $user = $this->auth->user();
            if (!$user) {
                $this->redirect('/login');
            }

            $this->loadTenant();
            if ($path === '/tenant/select' && $method === 'POST') {
                $this->selectTenant();
                return;
            }

            if ($path === '/' || $path === '/dashboard') {
                $this->dashboard($user);
                return;
            }
            if ($path === '/members') {
                $this->members($method, $user);
                return;
            }
            if (preg_match('#^/members/(\d+)$#', $path, $matches)) {
                $this->memberDetail((int) $matches[1]);
                return;
            }
            if ($path === '/events') {
                $this->events($method, $user);
                return;
            }

            http_response_code(404);
            echo $this->view->render('errors/404', $this->shared('Nicht gefunden'));
        } catch (Throwable $exception) {
            http_response_code(500);
            $message = !empty($this->config['app']['debug']) ? $exception->getMessage() : 'Ein unerwarteter Fehler ist aufgetreten.';
            echo $this->view->render('errors/500', $this->shared('Fehler') + ['message' => $message]);
        }
    }

    private function login(string $method): void
    {
        if ($this->auth->user()) {
            $this->redirect('/');
        }
        $error = null;
        if ($method === 'POST') {
            $this->assertCsrf();
            if ($this->auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                $tenants = $this->auth->tenants();
                if (count($tenants) === 1) {
                    $this->auth->selectTenant((int) $tenants[0]['id']);
                }
                $this->redirect('/');
            }
            $error = 'E-Mail-Adresse oder Passwort ist nicht korrekt.';
        }
        echo $this->view->render('auth/login', ['error' => $error, 'title' => 'Anmelden'], 'layouts/auth');
    }

    private function loadTenant(): void
    {
        $tenants = $this->auth->tenants();
        if ($tenants === []) {
            throw new \RuntimeException('Diesem Benutzer ist kein aktiver Mandant zugeordnet.');
        }

        $selected = (int) ($_SESSION['tenant_id'] ?? 0);
        foreach ($tenants as $tenant) {
            if ((int) $tenant['id'] === $selected) {
                $this->tenant->set($tenant);
                return;
            }
        }

        if (count($tenants) === 1) {
            $this->auth->selectTenant((int) $tenants[0]['id']);
            $this->tenant->set($tenants[0]);
            return;
        }

        echo $this->view->render('auth/select-tenant', ['tenants' => $tenants, 'title' => 'Organisation auswählen'], 'layouts/auth');
        exit;
    }

    private function selectTenant(): void
    {
        $this->assertCsrf();
        if (!$this->auth->selectTenant((int) ($_POST['tenant_id'] ?? 0))) {
            throw new \RuntimeException('Die Organisation konnte nicht ausgewählt werden.');
        }
        $this->redirect('/');
    }

    private function dashboard(array $user): void
    {
        $service = new DashboardService($this->db, $this->tenant);
        echo $this->view->render('dashboard/index', $this->shared('Übersicht', 'dashboard') + $service->data() + ['user' => $user]);
    }

    private function members(string $method, array $user): void
    {
        $repository = new MemberRepository($this->db, $this->tenant);
        $errors = [];
        if ($method === 'POST') {
            $this->assertCsrf();
            $this->assertPermission('members.manage');
            foreach (['first_name' => 'Vorname', 'last_name' => 'Nachname'] as $field => $label) {
                if (trim((string) ($_POST[$field] ?? '')) === '') {
                    $errors[$field] = $label . ' ist erforderlich.';
                }
            }
            if ($errors === []) {
                $id = $repository->create($_POST, (int) $user['id']);
                $_SESSION['flash'] = 'Mitglied wurde erfolgreich angelegt.';
                $this->redirect('/members?member=' . $id);
            }
        }

        $search = trim((string) ($_GET['search'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $members = $repository->all($search, $status);
        $selected = null;
        if ((int) ($_GET['member'] ?? 0) > 0) {
            $selected = $repository->find((int) $_GET['member']);
        }
        echo $this->view->render('members/index', $this->shared('Mitglieder', 'members') + compact('members', 'selected', 'search', 'status', 'errors'));
    }

    private function memberDetail(int $id): void
    {
        $member = (new MemberRepository($this->db, $this->tenant))->find($id);
        if (!$member) {
            http_response_code(404);
            echo '<p>Mitglied nicht gefunden.</p>';
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($member, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function events(string $method, array $user): void
    {
        $repository = new EventRepository($this->db, $this->tenant);
        $errors = [];
        if ($method === 'POST') {
            $this->assertCsrf();
            $this->assertPermission('events.manage');
            foreach (['title' => 'Titel', 'starts_at' => 'Beginn', 'ends_at' => 'Ende'] as $field => $label) {
                if (trim((string) ($_POST[$field] ?? '')) === '') {
                    $errors[$field] = $label . ' ist erforderlich.';
                }
            }
            if ($errors === []) {
                $repository->create($_POST, (int) $user['id']);
                $_SESSION['flash'] = 'Dienst wurde erfolgreich angelegt.';
                $this->redirect('/events');
            }
        }
        echo $this->view->render('events/index', $this->shared('Dienste & Übungen', 'events') + ['events' => $repository->upcoming(), 'errors' => $errors]);
    }

    private function shared(string $title, string $active = ''): array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return [
            'title' => $title,
            'active' => $active,
            'user' => $this->auth->user(),
            'tenant' => $this->tenant->active() ? $this->tenant->current() : null,
            'tenants' => $this->auth->user() ? $this->auth->tenants() : [],
            'flash' => $flash,
        ];
    }

    private function assertPermission(string $permission): void
    {
        if (!$this->auth->permission($this->tenant->id(), $permission)) {
            http_response_code(403);
            throw new \RuntimeException('Für diese Aktion fehlt die Berechtigung.');
        }
    }

    private function assertCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            throw new \RuntimeException('Die Sitzung ist abgelaufen. Bitte lade die Seite neu.');
        }
    }

    private function path(): string
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    private function startSession(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        session_name((string) ($this->config['security']['session_name'] ?? 'jfs_v2_session'));
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }

    private function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }
}
