<?php
declare(strict_types=1);

namespace JFS;

use PDO;
use RuntimeException;

final class SaasRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function plans(): array
    {
        return $this->db->query('SELECT id, name, plan_key, member_limit, user_limit, storage_limit_mb FROM plans WHERE is_active=1 ORDER BY id')->fetchAll();
    }

    public function tenants(): array
    {
        return $this->db->query(
            'SELECT t.id, t.name, t.slug, t.city, t.is_active, s.plan_id, p.name AS plan_name,
                    s.status_name, s.billing_cycle, s.trial_ends_at, s.current_period_end,
                    (SELECT COUNT(*) FROM members m WHERE m.tenant_id=t.id AND m.status_name=\'active\') AS member_count,
                    (SELECT COUNT(*) FROM tenant_users tu WHERE tu.tenant_id=t.id AND tu.is_active=1) AS user_count
             FROM tenants t
             LEFT JOIN subscriptions s ON s.tenant_id=t.id
             LEFT JOIN plans p ON p.id=s.plan_id
             ORDER BY t.name'
        )->fetchAll();
    }

    public function saveSubscription(int $tenantId, int $planId, string $status, string $cycle, ?string $trialEnds, int $userId, string $ip): void
    {
        if ($tenantId < 1 || $planId < 1
            || !in_array($status, ['trial', 'active', 'past_due', 'paused', 'cancelled', 'expired'], true)
            || !in_array($cycle, ['monthly', 'yearly', 'manual'], true)
            || ($trialEnds !== null && (!$this->validDate($trialEnds)))) {
            throw new RuntimeException('Ungültige Abo-Angaben.');
        }
        if ($status === 'trial' && $trialEnds === null) {
            throw new RuntimeException('Für eine Testphase ist ein Enddatum erforderlich.');
        }
        $this->db->beginTransaction();
        try {
            $tenant = $this->db->prepare('SELECT id FROM tenants WHERE id=:id');
            $tenant->execute(['id' => $tenantId]);
            $plan = $this->db->prepare('SELECT id FROM plans WHERE id=:id AND is_active=1');
            $plan->execute(['id' => $planId]);
            if (!$tenant->fetchColumn() || !$plan->fetchColumn()) {
                throw new RuntimeException('Mandant oder Tarif wurde nicht gefunden.');
            }
            $existing = $this->db->prepare('SELECT id FROM subscriptions WHERE tenant_id=:tenant');
            $existing->execute(['tenant' => $tenantId]);
            if ($existing->fetchColumn()) {
                $statement = $this->db->prepare(
                    'UPDATE subscriptions SET plan_id=:plan, status_name=:status, billing_cycle=:cycle, trial_ends_at=:trial WHERE tenant_id=:tenant'
                );
            } else {
                $statement = $this->db->prepare(
                    'INSERT INTO subscriptions (plan_id,status_name,billing_cycle,trial_ends_at,tenant_id) VALUES (:plan,:status,:cycle,:trial,:tenant)'
                );
            }
            $statement->execute(['plan' => $planId, 'status' => $status, 'cycle' => $cycle, 'trial' => $trialEnds, 'tenant' => $tenantId]);
            $audit = $this->db->prepare(
                'INSERT INTO audit_logs (tenant_id,user_id,action_name,entity_type,entity_id,description_text,ip_address)
                 VALUES (:tenant,:user,\'subscription.updated\',\'subscription\',:entity,:description,:ip)'
            );
            $audit->execute([
                'tenant' => $tenantId, 'user' => $userId, 'entity' => $tenantId,
                'description' => "Tarif {$planId}, Status {$status}, Zyklus {$cycle}, Testende " . ($trialEnds ?? '–'),
                'ip' => substr($ip, 0, 45),
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function createTenant(array $input, int $operatorId, string $ip): int
    {
        $name = trim((string) ($input['organization'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        $first = trim((string) ($input['first_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $planId = (int) ($input['plan_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 180 || mb_strlen($city) > 120
            || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 120
            || $first === '' || $last === '' || mb_strlen($first) > 100 || mb_strlen($last) > 100
            || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $planId < 1) {
            throw new RuntimeException('Bitte Organisationsname, Kurzname, Tarif und Admin-Daten prüfen.');
        }
        $this->db->beginTransaction();
        try {
            $plan = $this->db->prepare('SELECT id FROM plans WHERE id=:id AND is_active=1');
            $plan->execute(['id' => $planId]);
            if (!$plan->fetchColumn()) {
                throw new RuntimeException('Der gewählte Tarif ist nicht verfügbar.');
            }
            $duplicate = $this->db->prepare('SELECT id FROM tenants WHERE slug=:slug');
            $duplicate->execute(['slug' => $slug]);
            if ($duplicate->fetchColumn()) {
                throw new RuntimeException('Dieser Kurzname ist bereits vergeben.');
            }
            $user = $this->db->prepare('SELECT id, is_active FROM users WHERE email=:email');
            $user->execute(['email' => $email]);
            $existing = $user->fetch();
            if ($existing && (int) $existing['is_active'] !== 1) {
                throw new RuntimeException('Das bestehende Admin-Konto ist deaktiviert.');
            }
            if (!$existing && strlen($password) < 12) {
                throw new RuntimeException('Für neue Admin-Konten ist ein Passwort mit mindestens 12 Zeichen erforderlich.');
            }
            $tenant = $this->db->prepare('INSERT INTO tenants(name,slug,city) VALUES (:name,:slug,:city)');
            $tenant->execute(['name' => $name, 'slug' => $slug, 'city' => $city === '' ? null : $city]);
            $tenantId = (int) $this->db->lastInsertId();
            if ($existing) {
                $adminId = (int) $existing['id'];
            } else {
                $newUser = $this->db->prepare(
                    'INSERT INTO users(first_name,last_name,email,password_hash) VALUES (:first,:last,:email,:hash)'
                );
                $newUser->execute([
                    'first' => $first, 'last' => $last, 'email' => $email,
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $adminId = (int) $this->db->lastInsertId();
            }
            $member = $this->db->prepare(
                'INSERT INTO tenant_users(tenant_id,user_id,role_key) VALUES (:tenant,:user,\'admin\')'
            );
            $member->execute(['tenant' => $tenantId, 'user' => $adminId]);
            $trialEnd = (new \DateTimeImmutable('today'))->modify('+30 days')->format('Y-m-d');
            $subscription = $this->db->prepare(
                'INSERT INTO subscriptions(tenant_id,plan_id,status_name,billing_cycle,trial_ends_at)
                 VALUES (:tenant,:plan,\'trial\',\'manual\',:end)'
            );
            $subscription->execute(['tenant' => $tenantId, 'plan' => $planId, 'end' => $trialEnd]);
            $audit = $this->db->prepare(
                'INSERT INTO audit_logs(tenant_id,user_id,action_name,entity_type,entity_id,description_text,ip_address)
                 VALUES (:tenant,:user,\'tenant.created\',\'tenant\',:entity,:description,:ip)'
            );
            $audit->execute([
                'tenant' => $tenantId, 'user' => $operatorId, 'entity' => $tenantId,
                'description' => "Mandant {$name} angelegt; Tarif {$planId}; Admin {$email}; Testende {$trialEnd}",
                'ip' => substr($ip, 0, 45),
            ]);
            $this->db->commit();
            return $tenantId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function validDate(string $date): bool
    {
        $value = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $value !== false && $value->format('Y-m-d') === $date;
    }
}
