<?php

declare(strict_types=1);

namespace App\Modules\Administration\Infrastructure\Persistence;

use App\Modules\Administration\Domain\Repositories\RoleAdministrationRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class DatabaseRoleAdministrationRepository implements RoleAdministrationRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function allRoles(): array
    {
        $roles = $this->db->table('roles')
            ->select('id, name, slug, description, is_active, created_at, updated_at')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $userCounts = [];
        foreach ($this->db->table('user_roles')
            ->select('role_id, COUNT(*) AS total', false)
            ->groupBy('role_id')
            ->get()
            ->getResultArray() as $row) {
            $userCounts[(int) $row['role_id']] = (int) $row['total'];
        }

        $permissionCounts = [];
        $moduleCounts = [];
        foreach ($this->db->table('role_module_permissions rmp')
            ->select('rmp.role_id, rmp.module_id, rmp.can_view, rmp.can_create, rmp.can_edit, rmp.can_delete')
            ->join('modules m', 'm.id = rmp.module_id')
            ->where('m.is_active', 1)
            ->get()
            ->getResultArray() as $row) {
            $roleId = (int) $row['role_id'];
            $count = (int) $row['can_view'] + (int) $row['can_create'] + (int) $row['can_edit'] + (int) $row['can_delete'];
            if ($count > 0) {
                $permissionCounts[$roleId] = ($permissionCounts[$roleId] ?? 0) + $count;
                $moduleCounts[$roleId] = ($moduleCounts[$roleId] ?? 0) + 1;
            }
        }

        foreach ($roles as &$role) {
            $roleId = (int) $role['id'];
            $role['user_count'] = $userCounts[$roleId] ?? 0;
            $role['permission_count'] = $permissionCounts[$roleId] ?? 0;
            $role['module_count'] = $moduleCounts[$roleId] ?? 0;
        }
        unset($role);

        return $roles;
    }

    public function findRole(int $roleId): ?array
    {
        $row = $this->db->table('roles')
            ->select('id, name, slug, description, is_active, created_at, updated_at')
            ->where('id', $roleId)
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        $row['module_permissions'] = $this->modulePermissionsForRole($roleId);
        $row['user_count'] = $this->assignedUserCount($roleId);

        return $row;
    }

    public function allActiveModules(): array
    {
        return $this->db->table('modules')
            ->select('id, name, slug, description, allow_view, allow_create, allow_edit, allow_delete, is_system')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function modulePermissionsForRole(int $roleId): array
    {
        $rows = $this->db->table('role_module_permissions')
            ->select('module_id, can_view, can_create, can_edit, can_delete')
            ->where('role_id', $roleId)
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['module_id']] = [
                'view' => (int) $row['can_view'],
                'create' => (int) $row['can_create'],
                'edit' => (int) $row['can_edit'],
                'delete' => (int) $row['can_delete'],
            ];
        }

        return $result;
    }

    public function slugExists(string $slug, ?int $exceptRoleId = null): bool
    {
        $builder = $this->db->table('roles')->where('slug', $slug);
        if ($exceptRoleId !== null) {
            $builder->where('id !=', $exceptRoleId);
        }

        return $builder->countAllResults() > 0;
    }

    public function nameExists(string $name, ?int $exceptRoleId = null): bool
    {
        $builder = $this->db->table('roles')->where('name', $name);
        if ($exceptRoleId !== null) {
            $builder->where('id !=', $exceptRoleId);
        }

        return $builder->countAllResults() > 0;
    }

    public function createRole(array $data, array $permissions): int
    {
        $this->db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('roles')->insert($data + ['created_at' => $now, 'updated_at' => $now]);
            $roleId = (int) $this->db->insertID();
            $this->syncPermissions($roleId, $permissions, $now);
            $this->db->transCommit();

            return $roleId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function updateRole(int $roleId, array $data, array $permissions): void
    {
        $this->db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('roles')
                ->where('id', $roleId)
                ->update($data + ['updated_at' => $now]);
            $this->syncPermissions($roleId, $permissions, $now);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function assignedUserCount(int $roleId): int
    {
        return $this->db->table('user_roles')->where('role_id', $roleId)->countAllResults();
    }

    public function deactivateRole(int $roleId): void
    {
        $this->db->table('roles')
            ->where('id', $roleId)
            ->update([
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    
    private function syncPermissions(int $roleId, array $permissions, string $now): void
    {
        $this->db->table('role_module_permissions')->where('role_id', $roleId)->delete();

        foreach ($permissions as $moduleId => $actions) {
            if ($moduleId <= 0 || array_sum($actions) === 0) {
                continue;
            }

            $this->db->table('role_module_permissions')->insert([
                'role_id' => $roleId,
                'module_id' => $moduleId,
                'can_view' => $actions['view'],
                'can_create' => $actions['create'],
                'can_edit' => $actions['edit'],
                'can_delete' => $actions['delete'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
