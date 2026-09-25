<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Domain\Repositories;

interface AuthorizationRepositoryInterface
{
    public function userHasModulePermission(int $userId, string $moduleSlug, string $action): bool;

    
    public function permissionsForUser(int $userId): array;
}
