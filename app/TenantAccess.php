<?php
declare(strict_types=1);

final class TenantAccess
{
    private static array $cache = [];

    public static function tableExists(string $table): bool
    {
        $key = 'table:' . $table;
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        try {
            $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
            $stmt->execute([$table]);
            return self::$cache[$key] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) { return false; }
    }

    public static function subscription(?int $tenantId = null): array
    {
        $tenantId ??= tenant_id();
        $key = 'subscription:' . $tenantId;
        if (isset(self::$cache[$key])) return self::$cache[$key];
        if (!self::tableExists('saas_subscriptions')) return [];
        $stmt = db()->prepare('SELECT s.*,p.name AS plan_name,p.plan_key,p.member_limit,p.user_limit,p.storage_limit_mb FROM saas_subscriptions s JOIN saas_plans p ON p.id=s.plan_id WHERE s.tenant_id=? LIMIT 1');
        $stmt->execute([$tenantId]);
        return self::$cache[$key] = ($stmt->fetch() ?: []);
    }

    public static function settings(?int $tenantId = null): array
    {
        $tenantId ??= tenant_id();
        $defaults = ['display_name'=>null,'logo_path'=>null,'primary_color'=>'#d71920','secondary_color'=>'#102b4e','dashboard_title'=>null,'readonly_override'=>0];
        if (!self::tableExists('saas_tenant_settings')) return $defaults;
        try {
            $stmt=db()->prepare('SELECT * FROM saas_tenant_settings WHERE tenant_id=?'); $stmt->execute([$tenantId]);
            return array_merge($defaults, $stmt->fetch() ?: []);
        } catch (Throwable) { return $defaults; }
    }

    public static function readOnly(): bool
    {
        if (support_mode()) return true;
        $settings = self::settings();
        if ((int)($settings['readonly_override'] ?? 0) === 1) return true;
        $subscription = self::subscription();
        if (!$subscription) return false;
        if (in_array($subscription['status_name'], ['paused','cancelled','expired'], true)) return true;
        if ($subscription['status_name'] === 'trial' && !empty($subscription['trial_ends_at']) && $subscription['trial_ends_at'] < date('Y-m-d')) return true;
        if (!empty($subscription['current_period_end']) && $subscription['current_period_end'] < date('Y-m-d') && $subscription['status_name'] !== 'active') return true;
        return false;
    }

    public static function usage(string $kind): int
    {
        $queries = [
            'members' => 'SELECT COUNT(*) FROM members WHERE tenant_id=?',
            'users' => 'SELECT COUNT(*) FROM users WHERE tenant_id=? AND is_active=1',
            'storage_mb' => 'SELECT CEIL(COALESCE(SUM(file_size),0)/1048576) FROM member_documents WHERE tenant_id=?',
        ];
        if (!isset($queries[$kind])) return 0;
        $stmt=db()->prepare($queries[$kind]); $stmt->execute([tenant_id()]); return (int)$stmt->fetchColumn();
    }

    public static function assertCanCreate(string $kind, int $additional = 1): void
    {
        $subscription=self::subscription();
        $column=['members'=>'member_limit','users'=>'user_limit','storage_mb'=>'storage_limit_mb'][$kind] ?? null;
        if (!$column || !$subscription || $subscription[$column] === null) return;
        if (self::usage($kind) + $additional > (int)$subscription[$column]) {
            throw new RuntimeException('Das ' . ($subscription['plan_name'] ?? 'aktuelle') . '-Tariflimit für ' . ['members'=>'Mitglieder','users'=>'Benutzer','storage_mb'=>'Speicher'][$kind] . ' ist erreicht.');
        }
    }

    public static function moduleEnabled(string $module): bool
    {
        if (!self::tableExists('tenant_modules')) return true;
        $stmt=db()->prepare('SELECT is_enabled FROM tenant_modules WHERE tenant_id=? AND module_key=?');
        $stmt->execute([tenant_id(),$module]); $value=$stmt->fetchColumn();
        return $value === false ? true : (bool)$value;
    }

    public static function can(string $permission): bool
    {
        $user=Auth::user();
        if (!$user) return false;
        if ((int)$user['is_superadmin'] === 1 || $user['role'] === 'admin') return true;
        if (!self::tableExists('tenant_user_roles')) return in_array($user['role'], ['leader','staff'], true);
        $assigned=db()->prepare('SELECT role_id FROM tenant_user_roles WHERE tenant_id=? AND user_id=?');
        $assigned->execute([tenant_id(),(int)$user['id']]);
        if (!$assigned->fetchColumn()) return in_array($user['role'], ['leader','staff'], true);
        $stmt=db()->prepare('SELECT COUNT(*) FROM tenant_user_roles ur JOIN tenant_role_permissions rp ON rp.role_id=ur.role_id WHERE ur.tenant_id=? AND ur.user_id=? AND rp.permission_key=?');
        $stmt->execute([tenant_id(),(int)$user['id'],$permission]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
