<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Repositories;

interface InventoryReportRepositoryInterface
{
    public function movements(array $filters, ?int $limit = null): array;

    public function createExport(array $data): int;
}
