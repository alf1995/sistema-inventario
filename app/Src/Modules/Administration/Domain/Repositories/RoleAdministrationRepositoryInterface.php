<?php

declare(strict_types=1);

namespace App\Modules\Administration\Domain\Repositories;

interface RoleAdministrationRepositoryInterface
{
    
    public function allRoles(): array;

    
    public function findRole(int $roleId): ?array;

    
    public function allActiveModules(): array;

    
    public function modulePermissionsForRole(int $roleId): array;

    public function slugExists(string $slug, ?int $exceptRoleId = null): bool;

    public function nameExists(string $name, ?int $exceptRoleId = null): bool;

    
    public function createRole(array $data, array $permissions): int;

    
    public function updateRole(int $roleId, array $data, array $permissions): void;

    public function assignedUserCount(int $roleId): int;

    public function deactivateRole(int $roleId): void;
}
