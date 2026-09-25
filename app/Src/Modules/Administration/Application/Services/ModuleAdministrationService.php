<?php

declare(strict_types=1);

namespace App\Modules\Administration\Application\Services;

use App\Shared\Application\Ports\SecurityAuditLoggerInterface;
use App\Modules\Administration\Domain\Repositories\ModuleAdministrationRepositoryInterface;
use App\Modules\Authentication\Domain\Repositories\AuthorizationRepositoryInterface;
use Throwable;

final class ModuleAdministrationService
{
    private const PROTECTED_ADMIN_MODULE = 'modules';

    public function __construct(
        private readonly ModuleAdministrationRepositoryInterface $modules,
        private readonly AuthorizationRepositoryInterface $authorization,
        private readonly SecurityAuditLoggerInterface $audit,
    ) {
    }

    
    public function modules(): array
    {
        return $this->modules->allModules();
    }

    
    public function module(int $moduleId): ?array
    {
        return $this->modules->findModule($moduleId);
    }

    public function create(
        int $actorUserId,
        string $name,
        string $slug,
        ?string $description,
        string $status,
        bool $allowView,
        bool $allowCreate,
        bool $allowEdit,
        bool $allowDelete,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'modules', 'create')) {
            return AdministrationResult::failure('No tienes permiso para crear módulos.', 'forbidden');
        }

        $name = trim($name);
        $slug = mb_strtolower(trim($slug));
        $description = $this->cleanDescription($description);

        $error = $this->validate($name, $slug, $status, $allowView, $allowCreate, $allowEdit, $allowDelete);
        if ($error !== null) {
            return AdministrationResult::failure($error);
        }

        if ($this->modules->slugExists($slug)) {
            return AdministrationResult::failure('El identificador del módulo ya existe.');
        }

        if ($this->modules->nameExists($name)) {
            return AdministrationResult::failure('El nombre del módulo ya existe.');
        }

        try {
            $moduleId = $this->modules->createModule([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'allow_view' => $allowView ? 1 : 0,
                'allow_create' => $allowCreate ? 1 : 0,
                'allow_edit' => $allowEdit ? 1 : 0,
                'allow_delete' => $allowDelete ? 1 : 0,
                'is_system' => 0,
                'is_active' => $status === 'active' ? 1 : 0,
            ]);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo crear el módulo.', 'database');
        }

        $this->audit->log($actorUserId, null, 'module.created', $ipAddress, $userAgent, [
            'module_id' => $moduleId,
            'slug' => $slug,
        ]);

        return AdministrationResult::success('Módulo creado correctamente.', $moduleId);
    }

    public function update(
        int $actorUserId,
        int $moduleId,
        string $name,
        string $slug,
        ?string $description,
        string $status,
        bool $allowView,
        bool $allowCreate,
        bool $allowEdit,
        bool $allowDelete,
        string $ipAddress,
        string $userAgent,
    ): AdministrationResult {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'modules', 'edit')) {
            return AdministrationResult::failure('No tienes permiso para editar módulos.', 'forbidden');
        }

        $current = $this->modules->findModule($moduleId);
        if ($current === null) {
            return AdministrationResult::failure('El módulo no existe.', 'not_found');
        }

        $name = trim($name);
        $slug = mb_strtolower(trim($slug));
        $description = $this->cleanDescription($description);

        if ((int) $current['is_system'] === 1) {
            $slug = (string) $current['slug'];
            $status = 'active';
        }

        if ((string) $current['slug'] === self::PROTECTED_ADMIN_MODULE) {
            $allowView = true;
            $allowEdit = true;
        }

        $error = $this->validate($name, $slug, $status, $allowView, $allowCreate, $allowEdit, $allowDelete);
        if ($error !== null) {
            return AdministrationResult::failure($error);
        }

        if ($this->modules->slugExists($slug, $moduleId)) {
            return AdministrationResult::failure('El identificador del módulo ya existe.');
        }

        if ($this->modules->nameExists($name, $moduleId)) {
            return AdministrationResult::failure('El nombre del módulo ya existe.');
        }

        try {
            $this->modules->updateModule($moduleId, [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'allow_view' => $allowView ? 1 : 0,
                'allow_create' => $allowCreate ? 1 : 0,
                'allow_edit' => $allowEdit ? 1 : 0,
                'allow_delete' => $allowDelete ? 1 : 0,
                'is_active' => $status === 'active' ? 1 : 0,
            ]);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo actualizar el módulo.', 'database');
        }

        $this->audit->log($actorUserId, null, 'module.updated', $ipAddress, $userAgent, [
            'module_id' => $moduleId,
            'slug' => $slug,
        ]);

        return AdministrationResult::success('Módulo actualizado correctamente.', $moduleId);
    }

    public function delete(int $actorUserId, int $moduleId, string $ipAddress, string $userAgent): AdministrationResult
    {
        if (! $this->authorization->userHasModulePermission($actorUserId, 'modules', 'delete')) {
            return AdministrationResult::failure('No tienes permiso para eliminar módulos.', 'forbidden');
        }

        $module = $this->modules->findModule($moduleId);
        if ($module === null) {
            return AdministrationResult::failure('El módulo no existe.', 'not_found');
        }

        if ((int) $module['is_system'] === 1) {
            return AdministrationResult::failure('Los módulos internos del sistema no pueden eliminarse.');
        }

        if ((int) $module['is_active'] !== 1) {
            return AdministrationResult::failure('El módulo ya se encuentra inactivo.');
        }

        try {
            $this->modules->deactivateModule($moduleId);
        } catch (Throwable) {
            return AdministrationResult::failure('No se pudo eliminar lógicamente el módulo.', 'database');
        }

        $this->audit->log($actorUserId, null, 'module.deleted_logically', $ipAddress, $userAgent, [
            'module_id' => $moduleId,
            'slug' => (string) $module['slug'],
        ]);

        return AdministrationResult::success('Módulo eliminado lógicamente.');
    }

    private function validate(
        string $name,
        string $slug,
        string $status,
        bool $allowView,
        bool $allowCreate,
        bool $allowEdit,
        bool $allowDelete,
    ): ?string {
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
            return 'El nombre del módulo debe tener entre 3 y 100 caracteres.';
        }

        if (! preg_match('/^[a-z0-9][a-z0-9._-]{1,99}$/', $slug)) {
            return 'El identificador del módulo debe usar minúsculas, números, punto, guion o guion bajo.';
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            return 'El estado seleccionado no es válido.';
        }

        if (! $allowView && ! $allowCreate && ! $allowEdit && ! $allowDelete) {
            return 'El módulo debe habilitar por lo menos un permiso.';
        }

        return null;
    }

    private function cleanDescription(?string $description): ?string
    {
        $description = trim((string) $description);
        return $description === '' ? null : mb_substr($description, 0, 1000);
    }
}
