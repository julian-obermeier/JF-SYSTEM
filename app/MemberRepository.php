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

    public function update(int $id, array $input): void
    {
        $statement = $this->db->prepare(
            'UPDATE members SET first_name=:first_name,last_name=:last_name,birth_date=:birth_date,
             entry_date=:entry_date,member_type=:member_type,email=:email,phone=:phone,
             address_street=:address_street,postal_code=:postal_code,city=:city,
             emergency_name=:emergency_name,emergency_phone=:emergency_phone,notes_text=:notes_text
             WHERE id=:id AND tenant_id=:tenant_id'
        );
        $statement->execute([
            'first_name'=>trim((string)$input['first_name']),'last_name'=>trim((string)$input['last_name']),
            'birth_date'=>($input['birth_date']??'')?:null,'entry_date'=>($input['entry_date']??'')?:null,
            'member_type'=>in_array($input['member_type']??'', ['youth','staff'], true)?$input['member_type']:'youth',
            'email'=>trim((string)($input['email']??''))?:null,'phone'=>trim((string)($input['phone']??''))?:null,
            'address_street'=>trim((string)($input['address_street']??''))?:null,'postal_code'=>trim((string)($input['postal_code']??''))?:null,
            'city'=>trim((string)($input['city']??''))?:null,'emergency_name'=>trim((string)($input['emergency_name']??''))?:null,
            'emergency_phone'=>trim((string)($input['emergency_phone']??''))?:null,'notes_text'=>trim((string)($input['notes_text']??''))?:null,
            'id'=>$id,'tenant_id'=>$this->tenant->id(),
        ]);
    }

    public function saveGuardian(int $memberId, array $input): void
    {
        if (trim((string)($input['guardian_name']??'')) === '') return;
        $exists=$this->db->prepare('SELECT id FROM member_guardians WHERE member_id=:member_id AND tenant_id=:tenant_id ORDER BY is_primary DESC,id LIMIT 1');
        $exists->execute(['member_id'=>$memberId,'tenant_id'=>$this->tenant->id()]);
        $id=$exists->fetchColumn();
        if ($id) {
            $statement=$this->db->prepare('UPDATE member_guardians SET full_name=:name,relationship_name=:relationship,email=:email,phone=:phone WHERE id=:id AND tenant_id=:tenant_id');
            $statement->execute(['name'=>trim((string)$input['guardian_name']),'relationship'=>trim((string)($input['guardian_relationship']??''))?:null,'email'=>trim((string)($input['guardian_email']??''))?:null,'phone'=>trim((string)($input['guardian_phone']??''))?:null,'id'=>$id,'tenant_id'=>$this->tenant->id()]);
            return;
        }
        $statement=$this->db->prepare('INSERT INTO member_guardians(tenant_id,member_id,full_name,relationship_name,email,phone,is_primary) VALUES(:tenant_id,:member_id,:name,:relationship,:email,:phone,1)');
        $statement->execute(['tenant_id'=>$this->tenant->id(),'member_id'=>$memberId,'name'=>trim((string)$input['guardian_name']),'relationship'=>trim((string)($input['guardian_relationship']??''))?:null,'email'=>trim((string)($input['guardian_email']??''))?:null,'phone'=>trim((string)($input['guardian_phone']??''))?:null]);
    }

    public function saveConsent(int $memberId, array $input): void
    {
        $title=trim((string)($input['consent_title']??''));
        if($title==='') return;
        $statement=$this->db->prepare('INSERT INTO member_consents(tenant_id,member_id,consent_type,title,status_name,granted_at) VALUES(:tenant_id,:member_id,:type,:title,:status,:granted_at)');
        $status=in_array($input['consent_status']??'', ['open','granted','declined','expired','revoked'], true)?$input['consent_status']:'open';
        $statement->execute(['tenant_id'=>$this->tenant->id(),'member_id'=>$memberId,'type'=>'other','title'=>$title,'status'=>$status,'granted_at'=>$status==='granted'?date('Y-m-d'):null]);
    }
}
