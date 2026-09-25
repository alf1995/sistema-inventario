<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Controllers\BaseController;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ProductModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SystemSettings;

final class InventoryController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        
        $config = config(SystemSettings::class);

        $search = trim((string) $this->request->getGet('q'));
        $model = new ProductModel();
        $query = $model->asArray()
            ->select([
                'products.id',
                'products.name',
                'products.units_per_package',
                'products.price',
                'products.current_stock',
                'products.status',
                'inventory_product_types.name AS product_type_name',
            ])
            ->join('inventory_product_types', 'inventory_product_types.id = products.product_type_id');

        if ($search !== '') {
            $query->groupStart()
                ->like('products.name', $search)
                ->orLike('inventory_product_types.name', $search)
                ->orLike('products.status', $search)
                ->groupEnd();
        }

        $products = $query
            ->orderBy('products.name', 'ASC')
            ->paginate($config->pageSize('inventory'), 'inventory');

        return $this->renderTwig('inventory/stock/index', [
            'title' => 'Inventario actual',
            'products' => $products,
            'pagination' => $products !== []
                ? $model->pager->only(['q'])->links('inventory', 'default_full')
                : '',
            'paginationMeta' => $this->paginationMeta($model->pager, 'inventory'),
            'search' => $search,
        ]);
    }

    public function history(int $productId): string|ResponseInterface
    {
        
        $config = config(SystemSettings::class);
        $product = (new ProductModel())->asArray()
            ->select('products.*, inventory_product_types.name AS product_type_name')
            ->join('inventory_product_types', 'inventory_product_types.id = products.product_type_id')
            ->find($productId);

        if ($product === null) {
            return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
                'title' => 'Producto no encontrado',
                'message' => 'El producto solicitado no existe.',
            ]));
        }

        $search = trim((string) $this->request->getGet('q'));
        $movementModel = new InventoryMovementModel();
        $query = $movementModel->asArray()
            ->select([
                'inventory_movements.*',
                'inventory_movement_types.name AS movement_type_name',
                'users.username',
            ])
            ->join('inventory_movement_types', 'inventory_movement_types.id = inventory_movements.movement_type_id')
            ->join('users', 'users.id = inventory_movements.user_id')
            ->where('inventory_movements.product_id', $productId);

        if ($search !== '') {
            $query->groupStart()
                ->like('inventory_movement_types.name', $search)
                ->orLike('users.username', $search)
                ->orLike('inventory_movements.note', $search);

            $normalized = mb_strtolower($search);
            if (str_contains('ingreso', $normalized)) {
                $query->orWhere('inventory_movements.direction', 'IN');
            }
            if (str_contains('salida', $normalized)) {
                $query->orWhere('inventory_movements.direction', 'OUT');
            }

            $query->groupEnd();
        }

        $movements = $query
            ->orderBy('inventory_movements.id', 'DESC')
            ->paginate($config->pageSize('kardex'), 'kardex');

        return $this->renderTwig('inventory/stock/history', [
            'title' => 'Histórico de ' . (string) $product['name'],
            'product' => $product,
            'movements' => $movements,
            'pagination' => $movements !== []
                ? $movementModel->pager->only(['q'])->links('kardex', 'default_full')
                : '',
            'paginationMeta' => $this->paginationMeta($movementModel->pager, 'kardex'),
            'search' => $search,
        ]);
    }
}
