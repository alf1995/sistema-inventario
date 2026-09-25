<?php

declare(strict_types=1);

namespace App\Modules\Administration\Infrastructure\Persistence;

use App\Modules\Administration\Domain\Repositories\ModuleAdministrationRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use Throwable;

final class DatabaseModuleAdministrationRepository implements ModuleAdministrationRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function allModules(): array
    {
        $modules = $this->db->table('modules')
            ->select('id, name, slug, description, allow_view, allow_create, allow_edit, allow_delete, is_system, is_active, created_at, updated_at')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $roleCounts = [];
        foreach ($this->db->table('role_module_permissions')
            ->select('module_id, COUNT(*) AS total', false)
            ->groupBy('module_id')
            ->get()
            ->getResultArray() as $row) {
            $roleCounts[(int) $row['module_id']] = (int) $row['total'];
        }

        foreach ($modules as &$module) {
            $module['role_count'] = $roleCounts[(int) $module['id']] ?? 0;
        }
        unset($module);

        return $modules;
    }

    public function findModule(int $moduleId): ?array
    {
        return $this->db->table('modules')
            ->select('id, name, slug, description, allow_view, allow_create, allow_edit, allow_delete, is_system, is_active, created_at, updated_at')
            ->where('id', $moduleId)
            ->limit(1)
            ->get()
            ->getRowArray();
    }

    public function slugExists(string $slug, ?int $exceptModuleId = null): bool
    {
        $builder = $this->db->table('modules')->where('slug', $slug);
        if ($exceptModuleId !== null) {
            $builder->where('id !=', $exceptModuleId);
        }

        return $builder->countAllResults() > 0;
    }

    public function nameExists(string $name, ?int $exceptModuleId = null): bool
    {
        $builder = $this->db->table('modules')->where('name', $name);
        if ($exceptModuleId !== null) {
            $builder->where('id !=', $exceptModuleId);
        }

        return $builder->countAllResults() > 0;
    }

    public function createModule(array $data): int
    {
        $this->db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('modules')->insert($data + ['created_at' => $now, 'updated_at' => $now]);
            $moduleId = (int) $this->db->insertID();
            $this->syncAdministratorPermissions($moduleId, $data, $now);
            $this->db->transCommit();

            return $moduleId;
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function updateModule(int $moduleId, array $data): void
    {
        $this->db->transBegin();

        try {
            $now = date('Y-m-d H:i:s');
            $this->db->table('modules')
                ->where('id', $moduleId)
                ->update($data + ['updated_at' => $now]);

            $this->removeUnsupportedRolePermissions($moduleId, $data, $now);
            $this->syncAdministratorPermissions($moduleId, $data, $now);
            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function deactivateModule(int $moduleId): void
    {
        $this->db->table('modules')
            ->where('id', $moduleId)
            ->update([
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    
    private function removeUnsupportedRolePermissions(int $moduleId, array $module, string $now): void
    {
        $updates = ['updated_at' => $now];
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            if ((int) ($module['allow_' . $action] ?? 0) !== 1) {
                $updates['can_' . $action] = 0;
            }
        }

        if (count($updates) > 1) {
            $this->db->table('role_module_permissions')
                ->where('module_id', $moduleId)
                ->update($updates);
        }
    }

    
    private function syncAdministratorPermissions(int $moduleId, array $module, string $now): void
    {
        $admin = $this->db->table('roles')->select('id')->where('slug', 'administrador')->limit(1)->get()->getRowArray();
        if ($admin === null) {
            return;
        }

        $roleId = (int) $admin['id'];
        $permissions = [
            'can_view' => (int) ($module['allow_view'] ?? 0),
            'can_create' => (int) ($module['allow_create'] ?? 0),
            'can_edit' => (int) ($module['allow_edit'] ?? 0),
            'can_delete' => (int) ($module['allow_delete'] ?? 0),
            'updated_at' => $now,
        ];

        $exists = $this->db->table('role_module_permissions')
            ->where('role_id', $roleId)
            ->where('module_id', $moduleId)
            ->countAllResults() > 0;

        if ($exists) {
            $this->db->table('role_module_permissions')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->update($permissions);
            return;
        }

        $this->db->table('role_module_permissions')->insert($permissions + [
            'role_id' => $roleId,
            'module_id' => $moduleId,
            'created_at' => $now,
        ]);
    }
}
