<?php

declare(strict_types=1);

namespace App\Modules\Administration\Application\Services;

use App\Shared\Application\Ports\SecurityAuditLoggerInterface;
use App\Modules\Administration\Domain\Repositories\RoleAdministrationRepositoryInterface;
use App\Modules\Authentication\Domain\Repositories\AuthorizationRepositoryInterface;
use Throwable;

final class RoleAdministrationService
{
    private const BUILTIN_ADMIN_SLUG = 'administrador';

    public function __construct(
        private readonly RoleAdministrationRepositoryInterface $roles,
        private readonly AuthorizationRepositoryInterface $authorization,
        private readonly SecurityAuditLoggerInterface $audit,
    ) {
    }

    
    public function roles(): array
    {
        return $this->roles->allRoles();
    }

    
    public function role(int $roleId): ?array
    {
        return $this->roles->findRole($roleId);
    }

    
    public function modules(): array
    {
        return $this->roles->allActiveModules();
    }

    
    public function create(
        int $actorUserId,
        string $name,
        string $slug,
        ?string $description,
        string $status,
        array $requestedPermissions,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'roles', 'create')) {
            return AdministrationResult::failure('No tienes permiso para crear roles.', 'forbidden');
        }

        $name = trim($name);
        $slug = mb_strtolower(trim($slug));
        $description = $this->cleanDescription($description);

        $error = $this->validateRoleData($name, $slug, $status);
        if ($error !== null) {
            return AdministrationResult::failure($error);
        }

        if ($this->roles->slugExists($slug)) {
            return AdministrationResult::failure('El identificador del rol ya existe.');
        }

        if ($this->roles->nameExists($name)) {
            return AdministrationResult::failure('El nombre del rol ya existe.');
        }

        $permissions = $this->normalizePermissionMatrix($requestedPermissions, false);

        try {
            $roleId = $this->roles->createRole([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_active' => $status === 'active' ? 1 : 0,
            ], $permissions);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo crear el rol.', 'database');
        }

        $this->audit->log($actorUserId, null, 'role.created', $ipAddress, $userAgent, [
            'role_id' => $roleId,
            'slug' => $slug,
        ]);

        return AdministrationResult::success('Rol creado correctamente.', $roleId);
    }

    
    public function update(
        int $actorUserId,
        int $roleId,
        string $name,
        string $slug,
        ?string $description,
        string $status,
        array $requestedPermissions,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'roles', 'edit')) {
            return AdministrationResult::failure('No tienes permiso para editar roles.', 'forbidden');
        }

        $current = $this->roles->findRole($roleId);
        if ($current === null) {
            return AdministrationResult::failure('El rol no existe.', 'not_found');
        }

        $name = trim($name);
        $slug = mb_strtolower(trim($slug));
        $description = $this->cleanDescription($description);
        $isBuiltinAdmin = (string) $current['slug'] === self::BUILTIN_ADMIN_SLUG;

        if ($isBuiltinAdmin) {
            $slug = self::BUILTIN_ADMIN_SLUG;
            $status = 'active';
        }

        $error = $this->validateRoleData($name, $slug, $status);
        if ($error !== null) {
            return AdministrationResult::failure($error);
        }

        if ($this->roles->slugExists($slug, $roleId)) {
            return AdministrationResult::failure('El identificador del rol ya existe.');
        }

        if ($this->roles->nameExists($name, $roleId)) {
            return AdministrationResult::failure('El nombre del rol ya existe.');
        }

        $permissions = $this->normalizePermissionMatrix($requestedPermissions, $isBuiltinAdmin);

        try {
            $this->roles->updateRole($roleId, [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_active' => $status === 'active' ? 1 : 0,
            ], $permissions);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo actualizar el rol.', 'database');
        }

        $this->audit->log($actorUserId, null, 'role.updated', $ipAddress, $userAgent, [
            'role_id' => $roleId,
            'slug' => $slug,
        ]);

        return AdministrationResult::success('Rol actualizado correctamente.', $roleId);
    }

    public function delete(int $actorUserId, int $roleId, string $ipAddress, string $userAgent): AdministrationResult
    {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'roles', 'delete')) {
            return AdministrationResult::failure('No tienes permiso para eliminar roles.', 'forbidden');
        }

        $role = $this->roles->findRole($roleId);
        if ($role === null) {
            return AdministrationResult::failure('El rol no existe.', 'not_found');
        }

        if ((string) $role['slug'] === self::BUILTIN_ADMIN_SLUG) {
            return AdministrationResult::failure('El rol Administrador es protegido y no puede eliminarse.');
        }

        if ((int) $role['is_active'] !== 1) {
            return AdministrationResult::failure('El rol ya se encuentra inactivo.');
        }

        if ($this->roles->assignedUserCount($roleId) > 0) {
            return AdministrationResult::failure('No se puede eliminar un rol mientras tenga usuarios asignados.');
        }

        try {
            $this->roles->deactivateRole($roleId);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo eliminar lógicamente el rol.', 'database');
        }

        $this->audit->log($actorUserId, null, 'role.deleted_logically', $ipAddress, $userAgent, [
            'role_id' => $roleId,
            'slug' => (string) $role['slug'],
        ]);

        return AdministrationResult::success('Rol eliminado lógicamente.');
    }

    private function validateRoleData(string $name, string $slug, string $status): ?string
    {
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            return 'El nombre del rol debe tener entre 3 y 100 caracteres.';
        }

        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $slug)) {
            return 'El identificador del rol debe usar minúsculas, números, punto, guion o guion bajo.';
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            return 'El estado seleccionado no es válido.';
        }

        return null;
    }

    
    private function normalizePermissionMatrix(array $requested, bool $grantAllAvailable): array
    {
        $result = [];

        foreach ($this->roles->allActiveModules() as $module) {
            $moduleId = (int) $module['id'];
            $posted = (array) ($requested[$moduleId] ?? $requested[(string) $moduleId] ?? []);

            $actions = [];
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $available = (int) $module['allow_' . $action] === 1;
                $selected = $grantAllAvailable || isset($posted[$action]);
                $actions[$action] = ($available && $selected) ? 1 : 0;
            }

            if (array_sum($actions) > 0) {
                $result[$moduleId] = $actions;
            }
        }

        return $result;
    }

    private function cleanDescription(?string $description): ?string
    {
        $description = trim((string) $description);
        return $description === '' ? null : mb_substr($description, 0, 1000);
    }
}
