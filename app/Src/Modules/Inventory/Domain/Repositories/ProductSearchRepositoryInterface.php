<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Repositories;

interface ProductSearchRepositoryInterface
{
    public function searchByPrefix(string $term, int $limit): array;

    public function searchContaining(string $term, int $limit, array $excludeIds = []): array;
}
