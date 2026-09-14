<?php
declare(strict_types=1);

namespace JFS;

use PDO;

final class MemberRepository
{
    public function __construct(private readonly PDO $db, private readonly TenantContext $tenant)
    {
    }

    public function all(string $search = '', string $status = ''): array
    {
        $where = ['m.tenant_id = :tenant_id'];
        $parameters = ['tenant_id' => $this->tenant->id()];
        if ($search !== '') {
            $where[] = '(m.first_name LIKE :search OR m.last_name LIKE :search OR m.email LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if (in_array($status, ['active', 'paused', 'left'], true)) {
            $where[] = 'm.status_name = :status';
            $parameters['status'] = $status;
        }

        $sql = 'SELECT m.*,
                       (SELECT COUNT(*) FROM member_consents c WHERE c.tenant_id = m.tenant_id AND c.member_id = m.id AND c.status_name = \'granted\') AS consents_granted,
                       (SELECT COUNT(*) FROM member_consents c WHERE c.tenant_id = m.tenant_id AND c.member_id = m.id) AS consents_total
                FROM members m WHERE ' . implode(' AND ', $where) . '
                ORDER BY m.last_name, m.first_name';
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM members WHERE id = :id AND tenant_id = :tenant_id LIMIT 1'
        );
        $statement->execute(['id' => $id, 'tenant_id' => $this->tenant->id()]);
        $member = $statement->fetch();
        if (!$member) {
            return null;
        }

        $guardian = $this->db->prepare(
            'SELECT * FROM member_guardians WHERE member_id = :member_id AND tenant_id = :tenant_id ORDER BY is_primary DESC, id LIMIT 1'
        );
        $guardian->execute(['member_id' => $id, 'tenant_id' => $this->tenant->id()]);
        $member['guardian'] = $guardian->fetch() ?: null;

        $consents = $this->db->prepare(
            'SELECT * FROM member_consents WHERE member_id = :member_id AND tenant_id = :tenant_id ORDER BY title'
        );
        $consents->execute(['member_id' => $id, 'tenant_id' => $this->tenant->id()]);
        $member['consents'] = $consents->fetchAll();
        return $member;
    }

    public function create(array $input, int $userId): int
    {
        $statement = $this->db->prepare(
            'INSERT INTO members
             (tenant_id, first_name, last_name, birth_date, entry_date, member_type, email, phone, status_name, created_by)
             VALUES (:tenant_id, :first_name, :last_name, :birth_date, :entry_date, :member_type, :email, :phone, \'active\', :created_by)'
        );
        $statement->execute([
            'tenant_id' => $this->tenant->id(),
            'first_name' => trim((string) $input['first_name']),
            'last_name' => trim((string) $input['last_name']),
            'birth_date' => $input['birth_date'] !== '' ? $input['birth_date'] : null,
            'entry_date' => $input['entry_date'] !== '' ? $input['entry_date'] : null,
            'member_type' => in_array($input['member_type'] ?? '', ['youth', 'staff'], true) ? $input['member_type'] : 'youth',
            'email' => trim((string) ($input['email'] ?? '')) ?: null,
            'phone' => trim((string) ($input['phone'] ?? '')) ?: null,
            'created_by' => $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }
}
