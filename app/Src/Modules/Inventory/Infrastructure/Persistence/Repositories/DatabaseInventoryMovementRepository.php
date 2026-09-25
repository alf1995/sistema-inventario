<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Repositories;

use App\Modules\Inventory\Domain\Repositories\InventoryMovementRepositoryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryMovementModel;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

final class DatabaseInventoryMovementRepository implements InventoryMovementRepositoryInterface
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly InventoryMovementModel $movementModel,
    ) {
    }

    public function transaction(callable $operation): mixed
    {
        $this->db->transBegin();

        try {
            $result = $operation();

            if (! $this->db->transStatus()) {
                throw new RuntimeException('No se pudo completar la operación de inventario.');
            }

            $this->db->transCommit();

            return $result;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function findProductForUpdate(int $productId): ?array
    {
        return $this->db->query(
            'SELECT id, name, current_stock, status FROM products WHERE id = ? AND deleted_at IS NULL FOR UPDATE',
            [$productId],
        )->getRowArray();
    }

    public function findMovementType(int $movementTypeId): ?array
    {
        return $this->db->table('inventory_movement_types')
            ->select('id, name, allowed_direction, status')
            ->where('id', $movementTypeId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
    }

    public function createMovement(array $data): int
    {
        $movementId = $this->movementModel->insert($data, true);

        if ($movementId === false) {
            $errors = $this->movementModel->errors();
            throw new RuntimeException($errors !== [] ? implode(' ', $errors) : 'No se pudo registrar el movimiento.');
        }

        return (int) $movementId;
    }

    public function updateProductStock(int $productId, int $stock): bool
    {
        return $this->db->table('products')
            ->where('id', $productId)
            ->where('deleted_at', null)
            ->update([
                'current_stock' => $stock,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
