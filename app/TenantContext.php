<?php
declare(strict_types=1);

namespace JFS;

use LogicException;

final class TenantContext
{
    private ?int $tenantId = null;
    private ?array $tenant = null;

    public function set(array $tenant): void
    {
        if (!isset($tenant['id'])) {
            throw new LogicException('Ungültiger Mandantenkontext.');
        }
        $this->tenantId = (int) $tenant['id'];
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenantId = null;
        $this->tenant = null;
    }

    public function id(): int
    {
        if ($this->tenantId === null) {
            throw new LogicException('Es wurde kein Mandant ausgewählt.');
        }
        return $this->tenantId;
    }

    public function current(): array
    {
        $this->id();
        return $this->tenant ?? [];
    }

    public function active(): bool
    {
        return $this->tenantId !== null;
    }
}
