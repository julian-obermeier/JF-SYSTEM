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
}
