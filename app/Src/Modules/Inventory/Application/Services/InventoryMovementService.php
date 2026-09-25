<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\Repositories\InventoryMovementRepositoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class InventoryMovementService
{
    public function __construct(
        private readonly InventoryMovementRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function register(
        int $productId,
        int $movementTypeId,
        int $userId,
        string $movementAt,
        string $direction,
        int $quantity,
        ?string $note,
    ): InventoryOperationResult {
        $direction = strtoupper(trim($direction));
        $note = trim((string) $note);

        if ($productId <= 0 || $movementTypeId <= 0 || $userId <= 0) {
            return InventoryOperationResult::failure('Los datos del movimiento no son válidos.');
        }

        if (! in_array($direction, ['IN', 'OUT'], true)) {
            return InventoryOperationResult::failure('La operación debe ser un ingreso o una salida.');
        }

        if ($quantity <= 0) {
            return InventoryOperationResult::failure('La cantidad debe ser mayor a cero.');
        }

        try {
            $result = $this->repository->transaction(function () use (
                $productId,
                $movementTypeId,
                $userId,
                $movementAt,
                $direction,
                $quantity,
                $note,
            ): array {
                $product = $this->repository->findProductForUpdate($productId);

                if ($product === null) {
                    throw new RuntimeException('El producto seleccionado no existe.');
                }

                if ((string) $product['status'] !== 'active') {
                    throw new RuntimeException('No se pueden registrar movimientos para un producto inactivo.');
                }

                $movementType = $this->repository->findMovementType($movementTypeId);

                if ($movementType === null || (string) $movementType['status'] !== 'active') {
                    throw new RuntimeException('El tipo de movimiento seleccionado no está disponible.');
                }

                $allowedDirection = (string) $movementType['allowed_direction'];
                if ($allowedDirection !== 'BOTH' && $allowedDirection !== $direction) {
                    throw new RuntimeException('El tipo de movimiento seleccionado no corresponde a la operación elegida.');
                }

                $stockBefore = (int) $product['current_stock'];
                if ($direction === 'OUT' && $quantity > $stockBefore) {
                    throw new RuntimeException(
                        sprintf('Stock insuficiente. Disponible: %d unidades; salida solicitada: %d.', $stockBefore, $quantity),
                    );
                }

                $stockAfter = $direction === 'IN'
                    ? $stockBefore + $quantity
                    : $stockBefore - $quantity;

                $movementId = $this->repository->createMovement([
                    'product_id' => $productId,
                    'movement_type_id' => $movementTypeId,
                    'user_id' => $userId,
                    'movement_at' => $movementAt,
                    'direction' => $direction,
                    'quantity' => $quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'note' => $note !== '' ? $note : null,
                ]);

                if (! $this->repository->updateProductStock($productId, $stockAfter)) {
                    throw new RuntimeException('No se pudo actualizar el stock del producto.');
                }

                return [
                    'movement_id' => $movementId,
                    'product_name' => (string) $product['name'],
                    'stock_after' => $stockAfter,
                ];
            });

            return InventoryOperationResult::success(
                sprintf(
                    'Movimiento registrado. Stock actual de %s: %d unidades.',
                    $result['product_name'],
                    $result['stock_after'],
                ),
                $result['movement_id'],
            );
        } catch (Throwable $exception) {
            $this->logger->error('Error al registrar movimiento de inventario: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return InventoryOperationResult::failure($exception->getMessage());
        }
    }
}
