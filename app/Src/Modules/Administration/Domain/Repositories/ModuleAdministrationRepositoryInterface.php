<?php

declare(strict_types=1);

namespace App\Modules\Administration\Domain\Repositories;

interface ModuleAdministrationRepositoryInterface
{
    
    public function allModules(): array;

    
    public function findModule(int $moduleId): ?array;

    public function slugExists(string $slug, ?int $exceptModuleId = null): bool;

    public function nameExists(string $name, ?int $exceptModuleId = null): bool;

    
    public function createModule(array $data): int;

    
    public function updateModule(int $moduleId, array $data): void;

    public function deactivateModule(int $moduleId): void;
}
