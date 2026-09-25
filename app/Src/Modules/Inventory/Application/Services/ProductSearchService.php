<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\Repositories\ProductSearchRepositoryInterface;

final class ProductSearchService
{
    public function __construct(private readonly ProductSearchRepositoryInterface $repository)
    {
    }

    public function search(
        string $term,
        int $limit,
        int $minChars,
        int $maxLength,
        bool $containsFallback = true,
    ): array {
        $term = preg_replace('/\s+/u', ' ', trim($term)) ?? '';
        $term = mb_substr($term, 0, max(1, $maxLength));
        $limit = max(1, min($limit, 100));
        $minChars = max(1, $minChars);

        if (mb_strlen($term) < $minChars) {
            return [
                'results' => [],
                'hasMore' => false,
                'query' => $term,
            ];
        }

        $probeLimit = $limit + 1;
        $products = $this->repository->searchByPrefix($term, $probeLimit);
        $hasMore = count($products) > $limit;

        if (! $hasMore && $containsFallback && count($products) < $probeLimit) {
            $remaining = $probeLimit - count($products);
            $ids = array_map(static fn (array $product): int => (int) $product['id'], $products);
            $products = array_merge(
                $products,
                $this->repository->searchContaining($term, $remaining, $ids),
            );
            $hasMore = count($products) > $limit;
        }

        $products = array_slice($products, 0, $limit);

        return [
            'results' => array_map(static fn (array $product): array => [
                'id' => (int) $product['id'],
                'name' => (string) $product['name'],
                'stock' => (int) $product['current_stock'],
                'units_per_package' => (int) $product['units_per_package'],
            ], $products),
            'hasMore' => $hasMore,
            'query' => $term,
        ];
    }
}
