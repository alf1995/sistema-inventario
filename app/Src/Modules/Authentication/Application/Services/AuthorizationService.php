<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Application\Services;

use App\Modules\Authentication\Domain\Repositories\AuthorizationRepositoryInterface;

final class AuthorizationService
{
    private const ACTIONS = ['view', 'create', 'edit', 'delete'];

    public function __construct(private readonly AuthorizationRepositoryInterface $authorization)
    {
    }

    public function can(int $userId, string $module, string $action): bool
    {
        $module = mb_strtolower(trim($module));
        $action = mb_strtolower(trim($action));

        if ($userId <= 0 || $module === '' || ! in_array($action, self::ACTIONS, true)) {
            return false;
        }

        return $this->authorization->userHasModulePermission($userId, $module, $action);
    }

    
    public function permissionsForUser(int $userId): array
    {
        return $userId > 0 ? $this->authorization->permissionsForUser($userId) : [];
    }
}
