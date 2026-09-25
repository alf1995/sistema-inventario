<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Repositories;

use App\Modules\Inventory\Domain\Repositories\InventoryDashboardRepositoryInterface;
use CodeIgniter\Database\BaseConnection;

final class DatabaseInventoryDashboardRepository implements InventoryDashboardRepositoryInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function summary(
        string $startOfDay,
        string $endOfDay,
        int $recentMovementsLimit,
        int $topProductsLimit,
    ): array {
        $activeProducts = $this->db->table('products')
            ->where('deleted_at', null)
            ->where('status', 'active')
            ->countAllResults();

        $stockRow = $this->db->table('products')
            ->selectSum('current_stock', 'total_stock')
            ->where('deleted_at', null)
            ->where('status', 'active')
            ->get()
            ->getRowArray();

        $zeroStock = $this->db->table('products')
            ->where('deleted_at', null)
            ->where('status', 'active')
            ->where('current_stock', 0)
            ->countAllResults();

        $movementsToday = $this->db->table('inventory_movements')
            ->where('movement_at >=', $startOfDay)
            ->where('movement_at <=', $endOfDay)
            ->countAllResults();

        $recentMovements = $this->db->table('inventory_movements im')
            ->select([
                'im.id',
                'im.movement_at',
                'im.direction',
                'im.quantity',
                'im.stock_after',
                'p.name AS product_name',
                'mt.name AS movement_type_name',
                'u.username',
            ])
            ->join('products p', 'p.id = im.product_id')
            ->join('inventory_movement_types mt', 'mt.id = im.movement_type_id')
            ->join('users u', 'u.id = im.user_id')
            ->orderBy('im.movement_at', 'DESC')
            ->orderBy('im.id', 'DESC')
            ->limit(max(1, $recentMovementsLimit))
            ->get()
            ->getResultArray();

        $topProducts = $this->db->table('products')
            ->select('id, name, current_stock')
            ->where('deleted_at', null)
            ->where('status', 'active')
            ->orderBy('current_stock', 'DESC')
            ->orderBy('name', 'ASC')
            ->limit(max(1, $topProductsLimit))
            ->get()
            ->getResultArray();

        return [
            'active_products' => (int) $activeProducts,
            'total_stock' => (int) ($stockRow['total_stock'] ?? 0),
            'zero_stock' => (int) $zeroStock,
            'movements_today' => (int) $movementsToday,
            'inbound_today' => $this->movementQuantityForDay('IN', $startOfDay, $endOfDay),
            'outbound_today' => $this->movementQuantityForDay('OUT', $startOfDay, $endOfDay),
            'recent_movements' => $recentMovements,
            'top_products' => $topProducts,
        ];
    }

    private function movementQuantityForDay(string $direction, string $start, string $end): int
    {
        $row = $this->db->table('inventory_movements')
            ->selectSum('quantity', 'total_quantity')
            ->where('direction', $direction)
            ->where('movement_at >=', $start)
            ->where('movement_at <=', $end)
            ->get()
            ->getRowArray();

        return (int) ($row['total_quantity'] ?? 0);
    }
}
