<?php

namespace Tests\Fakes;

use App\Emr\StaffAccount;
use App\Role;

/**
 * A staff member of the pretend EMR in InMemoryEmrAdapter.
 */
final class FakeStaffAccount implements StaffAccount
{
    public function __construct(
        private string $id,
        private string $name,
        private Role $role,
    ) {}

    public function staffId(): string
    {
        return $this->id;
    }

    public function staffName(): string
    {
        return $this->name;
    }

    public function staffRole(): Role
    {
        return $this->role;
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isActiveStaff(): bool
    {
        return true;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): string
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
