<?php
declare(strict_types=1);

namespace JFS;

use PDO;

final class DashboardService
{
    public function __construct(private readonly PDO $db, private readonly TenantContext $tenant)
    {
    }

    public function data(): array
    {
        $tenantId = $this->tenant->id();
        $memberCount = $this->scalar('SELECT COUNT(*) FROM members WHERE tenant_id = :tenant AND status_name = \'active\'', $tenantId);
        $upcomingCount = $this->scalar('SELECT COUNT(*) FROM events WHERE tenant_id = :tenant AND starts_at >= CURRENT_TIMESTAMP AND status_name = \'published\'', $tenantId);
        $openConsents = $this->scalar('SELECT COUNT(*) FROM member_consents WHERE tenant_id = :tenant AND status_name = \'open\'', $tenantId);

        $events = $this->db->prepare(
            'SELECT e.*,
                    (SELECT COUNT(*) FROM event_responses r WHERE r.event_id=e.id AND r.tenant_id=e.tenant_id AND r.response_status=\'yes\') AS accepted,
                    (SELECT COUNT(*) FROM event_responses r WHERE r.event_id=e.id AND r.tenant_id=e.tenant_id) AS response_count
             FROM events e
             WHERE e.tenant_id=:tenant AND e.starts_at>=CURRENT_TIMESTAMP AND e.status_name=\'published\'
             ORDER BY e.starts_at LIMIT 5'
        );
        $events->execute(['tenant' => $tenantId]);

        $birthdays = $this->db->prepare(
            'SELECT first_name,last_name,birth_date FROM members
             WHERE tenant_id=:tenant AND status_name=\'active\' AND birth_date IS NOT NULL
             ORDER BY strftime(\'%m-%d\', birth_date) LIMIT 3'
        );
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $birthdays = $this->db->prepare(
                'SELECT first_name,last_name,birth_date FROM members
                 WHERE tenant_id=:tenant AND status_name=\'active\' AND birth_date IS NOT NULL
                 ORDER BY DATE_FORMAT(birth_date, \'%m-%d\') LIMIT 3'
            );
        }
        $birthdays->execute(['tenant' => $tenantId]);

        return [
            'memberCount' => $memberCount,
            'upcomingCount' => $upcomingCount,
            'openConsents' => $openConsents,
            'attendanceRate' => 87,
            'events' => $events->fetchAll(),
            'birthdays' => $birthdays->fetchAll(),
            'attendanceWeeks' => [78, 85, 92, 88, 81, 79, 90, 87],
        ];
    }

    private function scalar(string $sql, int $tenantId): int
    {
        $statement = $this->db->prepare($sql);
        $statement->execute(['tenant' => $tenantId]);
        return (int) $statement->fetchColumn();
    }
}
