<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Repositories;

interface InventoryDashboardRepositoryInterface
{
    public function summary(
        string $startOfDay,
        string $endOfDay,
        int $recentMovementsLimit,
        int $topProductsLimit,
    ): array;
}
