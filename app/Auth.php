<?php
declare(strict_types=1);

final class Auth
{
    public static function check(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['tenant_id']);
    }

    public static function user(): ?array
    {
        static $user = null;

        if (!self::check()) {
            return null;
        }

        if ($user !== null) {
            return $user;
        }

        $stmt = db()->prepare(
            'SELECT u.*, o.name AS organization_name
             FROM users u
             JOIN organizations o ON o.id = u.tenant_id
             WHERE u.id = ? AND u.tenant_id = ? AND u.is_active = 1'
        );
        $stmt->execute([(int) $_SESSION['user_id'], (int) $_SESSION['tenant_id']]);
        $user = $stmt->fetch() ?: null;

        if ($user === null) {
            self::logout();
        }

        return $user;
    }

    public static function attempt(string $email, string $password): bool
    {
        $email = mb_strtolower(trim($email));
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);

        $limit = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE (email = ? OR ip_address = ?) AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $limit->execute([$email, $ip]);

        if ((int) $limit->fetchColumn() >= 5) {
            return false;
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $record = db()->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)');
            $record->execute([$email, $ip]);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['tenant_id'] = (int) $user['tenant_id'];
        $_SESSION['last_activity'] = time();

        db()->prepare('DELETE FROM login_attempts WHERE email = ? OR ip_address = ?')->execute([$email, $ip]);
        db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
        audit('login', 'users', (int) $user['id'], 'Anmeldung erfolgreich');

        return true;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('?page=login');
        }

        $last = (int) ($_SESSION['last_activity'] ?? 0);
        if ($last > 0 && time() - $last > 7200) {
            self::logout();
            redirect('?page=login&expired=1');
        }

        $_SESSION['last_activity'] = time();
    }

    public static function canManage(): bool
    {
        $user = self::user();
        return $user && ((int) $user['is_superadmin'] === 1 || in_array($user['role'], ['admin', 'leader'], true));
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user && ((int) $user['is_superadmin'] === 1 || $user['role'] === 'admin');
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
