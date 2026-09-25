<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Repositories;

interface InventoryMovementRepositoryInterface
{
    public function transaction(callable $operation): mixed;

    public function findProductForUpdate(int $productId): ?array;

    public function findMovementType(int $movementTypeId): ?array;

    public function createMovement(array $data): int;

    public function updateProductStock(int $productId, int $stock): bool;
}
