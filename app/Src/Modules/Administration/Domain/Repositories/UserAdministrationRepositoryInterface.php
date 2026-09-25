<?php

declare(strict_types=1);

namespace App\Modules\Administration\Domain\Repositories;

interface UserAdministrationRepositoryInterface
{
    
    public function allUsers(): array;

    
    public function findUser(int $userId): ?array;

    
    public function activeRoles(): array;

    
    public function roleIdsForUser(int $userId): array;

    public function usernameExists(string $username, ?int $exceptUserId = null): bool;

    public function emailExists(string $email, ?int $exceptUserId = null): bool;

    
    public function roleIdsAreValid(array $roleIds): bool;

    
    public function createUser(array $data, array $roleIds): int;

    
    public function updateUser(int $userId, array $data, array $roleIds): void;

    public function userHasRoleSlug(int $userId, string $roleSlug): bool;

    public function countActiveUsersWithRoleSlugExcluding(string $roleSlug, int $excludedUserId): int;

    public function deactivateUser(int $userId): void;
}
