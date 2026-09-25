<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Repositories;

use App\Modules\Inventory\Domain\Repositories\InventoryReportRepositoryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ReportExportModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

final class DatabaseInventoryReportRepository implements InventoryReportRepositoryInterface
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly ReportExportModel $reportModel,
    ) {
    }

    public function movements(array $filters, ?int $limit = null): array
    {
        $builder = $this->movementQuery($filters)
            ->orderBy('im.movement_at', 'DESC')
            ->orderBy('im.id', 'DESC');

        if ($limit !== null && $limit > 0) {
            $builder->limit($limit);
        }

        return $builder->get()->getResultArray();
    }

    public function createExport(array $data): int
    {
        $reportId = $this->reportModel->insert($data, true);

        if ($reportId === false) {
            $errors = $this->reportModel->errors();
            throw new RuntimeException($errors !== [] ? implode(' ', $errors) : 'No se pudo guardar el histórico del reporte.');
        }

        return (int) $reportId;
    }

    private function movementQuery(array $filters): BaseBuilder
    {
        $builder = $this->db->table('inventory_movements im')
            ->select([
                'im.id',
                'im.movement_at',
                'im.direction',
                'im.quantity',
                'im.stock_before',
                'im.stock_after',
                'im.note',
                'p.name AS product_name',
                'pt.name AS product_type_name',
                'mt.name AS movement_type_name',
                'u.username',
            ])
            ->join('products p', 'p.id = im.product_id')
            ->join('inventory_product_types pt', 'pt.id = p.product_type_id')
            ->join('inventory_movement_types mt', 'mt.id = im.movement_type_id')
            ->join('users u', 'u.id = im.user_id');

        if ($filters['date_from'] !== '') {
            $builder->where('im.movement_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if ($filters['date_to'] !== '') {
            $builder->where('im.movement_at <=', $filters['date_to'] . ' 23:59:59');
        }

        if ($filters['direction'] !== '') {
            $builder->where('im.direction', $filters['direction']);
        }

        if ($filters['search'] !== '') {
            $builder->groupStart()
                ->like('p.name', $filters['search'])
                ->orLike('pt.name', $filters['search'])
                ->orLike('mt.name', $filters['search'])
                ->orLike('u.username', $filters['search'])
                ->orLike('im.note', $filters['search'])
                ->groupEnd();
        }

        return $builder;
    }
}
