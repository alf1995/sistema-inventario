<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\Repositories\InventoryDashboardRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Throwable;

final class InventoryDashboardService
{
    public function __construct(
        private readonly InventoryDashboardRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        private readonly int $recentMovementsLimit,
        private readonly int $topProductsLimit,
        private readonly string $timezone,
    ) {
    }

    public function summary(): array
    {
        $empty = [
            'available' => false,
            'active_products' => 0,
            'total_stock' => 0,
            'zero_stock' => 0,
            'movements_today' => 0,
            'inbound_today' => 0,
            'outbound_today' => 0,
            'recent_movements' => [],
            'top_products' => [],
        ];

        try {
            [$startOfDay, $endOfDay] = $this->todayRange();
            $summary = $this->repository->summary(
                $startOfDay,
                $endOfDay,
                max(1, $this->recentMovementsLimit),
                max(1, $this->topProductsLimit),
            );

            return ['available' => true] + $summary;
        } catch (Throwable $exception) {
            $this->logger->error('No se pudo cargar el resumen del dashboard: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return $empty;
        }
    }

    private function todayRange(): array
    {
        $timezone = new DateTimeZone($this->timezone !== '' ? $this->timezone : 'UTC');
        $today = new DateTimeImmutable('today', $timezone);

        return [
            $today->format('Y-m-d 00:00:00'),
            $today->format('Y-m-d 23:59:59'),
        ];
    }
}
