<?php
declare(strict_types=1);

namespace JFS;

use PDO;

final class AuthService
{
    private ?array $userCache = null;

    public function __construct(private readonly PDO $db)
    {
    }

    public function user(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId < 1) {
            return null;
        }
        if ($this->userCache !== null) {
            return $this->userCache;
        }

        $statement = $this->db->prepare('SELECT id, first_name, last_name, email, is_superadmin FROM users WHERE id = :id AND is_active = 1');
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!$user) {
            $this->logout();
            return null;
        }
        return $this->userCache = $user;
    }

    public function attempt(string $email, string $password): bool
    {
        $statement = $this->db->prepare('SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1');
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $user = $statement->fetch();
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        $this->userCache = null;

        return true;
    }

    public function tenants(): array
    {
        $user = $this->user();
        if (!$user) {
            return [];
        }

        $statement = $this->db->prepare(
            'SELECT t.id, t.name, t.slug, t.city, t.primary_color, t.logo_path, tu.role_key
             FROM tenant_users tu
             INNER JOIN tenants t ON t.id = tu.tenant_id
             WHERE tu.user_id = :user_id AND tu.is_active = 1 AND t.is_active = 1
             ORDER BY t.name'
        );
        $statement->execute(['user_id' => $user['id']]);
        return $statement->fetchAll();
    }

    public function selectTenant(int $tenantId): bool
    {
        foreach ($this->tenants() as $tenant) {
            if ((int) $tenant['id'] === $tenantId) {
                $_SESSION['tenant_id'] = $tenantId;
                return true;
            }
        }
        return false;
    }

    public function permission(int $tenantId, string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        if ((int) $user['is_superadmin'] === 1) {
            return true;
        }

        $statement = $this->db->prepare(
            'SELECT COUNT(*)
             FROM tenant_users tu
             INNER JOIN role_permissions rp ON rp.role_key = tu.role_key
             WHERE tu.tenant_id = :tenant_id AND tu.user_id = :user_id
               AND tu.is_active = 1 AND rp.permission_key = :permission'
        );
        $statement->execute([
            'tenant_id' => $tenantId,
            'user_id' => $user['id'],
            'permission' => $permission,
        ]);
        return (int) $statement->fetchColumn() > 0;
    }

    public function logout(): void
    {
        $_SESSION = [];
        $this->userCache = null;
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }
        session_destroy();
    }
}
