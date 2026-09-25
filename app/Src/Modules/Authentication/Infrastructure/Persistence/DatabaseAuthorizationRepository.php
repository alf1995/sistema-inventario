<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Persistence;

use App\Modules\Authentication\Domain\Repositories\AuthorizationRepositoryInterface;
use CodeIgniter\Database\BaseConnection;

final class DatabaseAuthorizationRepository implements AuthorizationRepositoryInterface
{
    private const ACTION_COLUMNS = [
        'view' => ['permission' => 'rmp.can_view', 'available' => 'm.allow_view'],
        'create' => ['permission' => 'rmp.can_create', 'available' => 'm.allow_create'],
        'edit' => ['permission' => 'rmp.can_edit', 'available' => 'm.allow_edit'],
        'delete' => ['permission' => 'rmp.can_delete', 'available' => 'm.allow_delete'],
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function userHasModulePermission(int $userId, string $moduleSlug, string $action): bool
    {
        $columns = self::ACTION_COLUMNS[$action] ?? null;
        if ($columns === null) {
            return false;
        }

        return $this->basePermissionQuery($userId)
            ->where('m.slug', $moduleSlug)
            ->where($columns['available'], 1)
            ->where($columns['permission'], 1)
            ->countAllResults() > 0;
    }

    public function permissionsForUser(int $userId): array
    {
        $rows = $this->basePermissionQuery($userId)
            ->select('m.slug, m.allow_view, m.allow_create, m.allow_edit, m.allow_delete, rmp.can_view, rmp.can_create, rmp.can_edit, rmp.can_delete')
            ->orderBy('m.slug', 'ASC')
            ->get()
            ->getResultArray();

        $permissions = [];
        foreach ($rows as $row) {
            $slug = (string) $row['slug'];
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                if ((int) $row['allow_' . $action] === 1 && (int) $row['can_' . $action] === 1) {
                    $permissions[$slug . '.' . $action] = true;
                }
            }
        }

        $result = array_keys($permissions);
        sort($result);

        return array_values($result);
    }

    private function basePermissionQuery(int $userId): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->join('role_module_permissions rmp', 'rmp.role_id = r.id')
            ->join('modules m', 'm.id = rmp.module_id')
            ->where('ur.user_id', $userId)
            ->where('r.is_active', 1)
            ->where('m.is_active', 1);
    }
}
