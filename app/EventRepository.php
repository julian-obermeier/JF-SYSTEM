<?php
declare(strict_types=1);

namespace JFS;

use PDO;

final class EventRepository
{
    public function __construct(private readonly PDO $db, private readonly TenantContext $tenant)
    {
    }

    public function upcoming(): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM events WHERE tenant_id=:tenant ORDER BY starts_at DESC LIMIT 50'
        );
        $statement->execute(['tenant' => $this->tenant->id()]);
        return $statement->fetchAll();
    }

    public function create(array $input, int $userId): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO events
             (tenant_id,title,event_type,starts_at,ends_at,location_name,description_text,status_name,created_by)
             VALUES (:tenant,:title,:event_type,:starts_at,:ends_at,:location,:description,\'published\',:created_by)'
        );
        $statement->execute([
            'tenant' => $this->tenant->id(),
            'title' => trim((string) $input['title']),
            'event_type' => in_array($input['event_type'] ?? '', ['practice', 'meeting', 'trip', 'competition', 'other'], true) ? $input['event_type'] : 'practice',
            'starts_at' => str_replace('T', ' ', (string) $input['starts_at']),
            'ends_at' => str_replace('T', ' ', (string) $input['ends_at']),
            'location' => trim((string) ($input['location_name'] ?? '')) ?: null,
            'description' => trim((string) ($input['description_text'] ?? '')) ?: null,
            'created_by' => $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function attendanceEvents(): array
    {
        $statement = $this->db->prepare(
            'SELECT id, title, starts_at, event_type FROM events WHERE tenant_id=:tenant AND status_name <> \'cancelled\' ORDER BY starts_at DESC LIMIT 30'
        );
        $statement->execute(['tenant' => $this->tenant->id()]);
        return $statement->fetchAll();
    }

    public function attendanceMatrix(int $eventId): array
    {
        $statement = $this->db->prepare(
            'SELECT m.id, m.first_name, m.last_name, m.member_type,
                    COALESCE(er.response_status, \'open\') AS response_status,
                    er.responded_at
             FROM members m
             LEFT JOIN event_responses er ON er.member_id=m.id AND er.event_id=:event AND er.tenant_id=:tenant
             WHERE m.tenant_id=:tenant AND m.status_name=\'active\'
             ORDER BY m.last_name, m.first_name'
        );
        $statement->execute(['tenant' => $this->tenant->id(), 'event' => $eventId]);
        return $statement->fetchAll();
    }

    public function saveAttendance(int $eventId, int $memberId, string $status): void
    {
        $allowed = ['yes', 'no', 'maybe', 'open'];
        if (!in_array($status, $allowed, true)) {
            $status = 'open';
        }
        $lookup = $this->db->prepare('SELECT id FROM event_responses WHERE tenant_id=:tenant AND event_id=:event AND member_id=:member');
        $lookup->execute(['tenant' => $this->tenant->id(), 'event' => $eventId, 'member' => $memberId]);
        $id = $lookup->fetchColumn();
        if ($id) {
            $statement = $this->db->prepare('UPDATE event_responses SET response_status=:status, responded_at=CURRENT_TIMESTAMP WHERE id=:id AND tenant_id=:tenant');
            $statement->execute(['status' => $status, 'id' => $id, 'tenant' => $this->tenant->id()]);
            return;
        }
        $statement = $this->db->prepare('INSERT INTO event_responses (tenant_id,event_id,member_id,response_status,responded_at) VALUES (:tenant,:event,:member,:status,CURRENT_TIMESTAMP)');
        $statement->execute(['tenant' => $this->tenant->id(), 'event' => $eventId, 'member' => $memberId, 'status' => $status]);
    }
}
