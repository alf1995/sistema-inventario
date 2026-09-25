<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Repositories;

use App\Modules\Inventory\Domain\Repositories\ProductSearchRepositoryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ProductModel;

final class DatabaseProductSearchRepository implements ProductSearchRepositoryInterface
{
    public function searchByPrefix(string $term, int $limit): array
    {
        return $this->baseQuery()
            ->like('name', $term, 'after')
            ->orderBy('name', 'ASC')
            ->findAll($limit);
    }

    public function searchContaining(string $term, int $limit, array $excludeIds = []): array
    {
        $query = $this->baseQuery()->like('name', $term, 'both');

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('name', 'ASC')->findAll($limit);
    }

    private function baseQuery(): ProductModel
    {
        return (new ProductModel())->asArray()
            ->select('id, name, current_stock, units_per_package')
            ->where('status', 'active');
    }
}
