<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class AuthSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $adminRoleId = $this->firstOrCreate('roles', 'slug', 'administrador', [
            'name'        => 'Administrador',
            'slug'        => 'administrador',
            'description' => 'Acceso administrativo al sistema.',
            'is_active'   => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $editorRoleId = $this->firstOrCreate('roles', 'slug', 'editor', [
            'name'        => 'Editor',
            'slug'        => 'editor',
            'description' => 'Puede consultar y editar información autorizada.',
            'is_active'   => 1,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $modules = [
            'users' => ['Usuarios', 'Administración de cuentas, roles, contraseñas y sesiones.', 1, 1, 1, 1, 1],
            'roles' => ['Roles', 'Administración de roles y su matriz de permisos.', 1, 1, 1, 1, 1],
            'modules' => ['Módulos', 'Catálogo de módulos y acciones disponibles para autorización.', 1, 1, 1, 1, 1],
            'inventory_settings' => ['Configuraciones de inventario', 'Catálogos de tipos de producto y tipos de movimiento.', 1, 1, 1, 1, 1],
            'products' => ['Productos', 'Administración del catálogo de productos.', 1, 1, 1, 1, 1],
            'movements' => ['Movimientos de inventario', 'Registro y consulta de entradas y salidas de stock.', 1, 1, 0, 0, 1],
            'inventory' => ['Inventario', 'Consulta de stock actual y kardex por producto.', 1, 0, 0, 0, 1],
            'inventory_reports' => ['Reportes de inventario', 'Consulta, exportación Excel e histórico de reportes.', 1, 1, 0, 0, 1],
        ];

        $moduleRows = [];
        foreach ($modules as $slug => [$name, $description, $view, $create, $edit, $delete, $system]) {
            $moduleId = $this->firstOrCreate('modules', 'slug', $slug, [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'allow_view' => $view,
                'allow_create' => $create,
                'allow_edit' => $edit,
                'allow_delete' => $delete,
                'is_system' => $system,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $moduleRows[$slug] = [
                'id' => $moduleId,
                'view' => $view,
                'create' => $create,
                'edit' => $edit,
                'delete' => $delete,
            ];
        }

        foreach ($moduleRows as $module) {
            $this->upsertModulePermissions($adminRoleId, $module['id'], [
                'can_view' => $module['view'],
                'can_create' => $module['create'],
                'can_edit' => $module['edit'],
                'can_delete' => $module['delete'],
            ], $now);
        }

        $inventory = $moduleRows['inventory'];
        $this->upsertModulePermissions($editorRoleId, $inventory['id'], [
            'can_view' => 1,
            'can_create' => 0,
            'can_edit' => 0,
            'can_delete' => 0,
        ], $now);

        $adminUsername = mb_strtolower(trim((string) env('auth.adminUsername', 'admin')));
        $adminEmail    = mb_strtolower(trim((string) env('auth.adminEmail', 'admin@inventario.local')));
        $configuredPassword = env('auth.adminPassword');

        if (ENVIRONMENT === 'production' && ($configuredPassword === null || trim((string) $configuredPassword) === '')) {
            throw new RuntimeException('Define auth.adminPassword en .env antes de ejecutar AuthSeeder en producción.');
        }

        $adminPassword = (string) ($configuredPassword ?? 'Admin123!2026');
        $user = $this->db->table('users')->where('username', $adminUsername)->get()->getRowArray();

        if ($user === null) {
            $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                throw new RuntimeException('No se pudo generar el hash del usuario administrador.');
            }

            $this->db->table('users')->insert([
                'username' => $adminUsername,
                'email' => $adminEmail,
                'password_hash' => $passwordHash,
                'status' => 'active',
                'must_change_password' => 1,
                'password_changed_at' => null,
                'last_activity' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $adminUserId = (int) $this->db->insertID();
        } else {
            $adminUserId = (int) $user['id'];
        }

        $this->attachIfMissing('user_roles', [
            'user_id' => $adminUserId,
            'role_id' => $adminRoleId,
        ], $now);
    }

    /** @param array<string, mixed> $data */
    private function firstOrCreate(string $table, string $key, string $value, array $data): int
    {
        $row = $this->db->table($table)->where($key, $value)->get()->getRowArray();
        if ($row !== null) {
            return (int) $row['id'];
        }

        $this->db->table($table)->insert($data);
        return (int) $this->db->insertID();
    }

    /** @param array<string, int> $keys */
    private function attachIfMissing(string $table, array $keys, string $now): void
    {
        $builder = $this->db->table($table);
        foreach ($keys as $column => $value) {
            $builder->where($column, $value);
        }

        if ($builder->countAllResults() > 0) {
            return;
        }

        $this->db->table($table)->insert($keys + ['created_at' => $now]);
    }

    /** @param array{can_view:int,can_create:int,can_edit:int,can_delete:int} $permissions */
    private function upsertModulePermissions(int $roleId, int $moduleId, array $permissions, string $now): void
    {
        $exists = $this->db->table('role_module_permissions')
            ->where('role_id', $roleId)
            ->where('module_id', $moduleId)
            ->countAllResults() > 0;

        if ($exists) {
            $this->db->table('role_module_permissions')
                ->where('role_id', $roleId)
                ->where('module_id', $moduleId)
                ->update($permissions + ['updated_at' => $now]);
            return;
        }

        $this->db->table('role_module_permissions')->insert($permissions + [
            'role_id' => $roleId,
            'module_id' => $moduleId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
