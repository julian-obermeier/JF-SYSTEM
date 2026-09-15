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

    private function validDate(string $date): bool
    {
        $value = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $value !== false && $value->format('Y-m-d') === $date;
    }
}
