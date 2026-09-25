<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Controllers\BaseController;
use App\Modules\Inventory\Application\Services\InventoryMovementService;
use App\Modules\Inventory\Application\Services\ProductSearchService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\InventoryMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementTypeModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ProductModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SystemSettings;
use DateTimeImmutable;
use DateTimeZone;

final class MovementController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        
        $config = config(SystemSettings::class);

        $search = trim((string) $this->request->getGet('q'));
        $model = new InventoryMovementModel();
        $query = $model->asArray()
            ->select([
                'inventory_movements.*',
                'products.name AS product_name',
                'inventory_movement_types.name AS movement_type_name',
                'users.username',
            ])
            ->join('products', 'products.id = inventory_movements.product_id')
            ->join('inventory_movement_types', 'inventory_movement_types.id = inventory_movements.movement_type_id')
            ->join('users', 'users.id = inventory_movements.user_id');

        if ($search !== '') {
            $query->groupStart()
                ->like('products.name', $search)
                ->orLike('inventory_movement_types.name', $search)
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
            ->orderBy('inventory_movements.movement_at', 'DESC')
            ->orderBy('inventory_movements.id', 'DESC')
            ->paginate($config->pageSize('movements'), 'movements');

        return $this->renderTwig('inventory/movements/index', [
            'title' => 'Movimientos de inventario',
            'movements' => $movements,
            'pagination' => $movements !== []
                ? $model->pager->only(['q'])->links('movements', 'default_full')
                : '',
            'paginationMeta' => $this->paginationMeta($model->pager, 'movements'),
            'search' => $search,
        ]);
    }

    public function create(): string|ResponseInterface
    {
        
        $config = config(SystemSettings::class);
        $productModel = new ProductModel();
        $hasProducts = $productModel->where('status', 'active')->countAllResults() > 0;
        $movementTypes = (new MovementTypeModel())->asArray()
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->findAll();

        if (! $hasProducts || $movementTypes === []) {
            return $this->response->setStatusCode(409)->setBody($this->renderTwig('errors/access_denied', [
                'title' => 'Configuración requerida',
                'message' => ! $hasProducts
                    ? 'Debes registrar al menos un producto activo antes de crear movimientos.'
                    : 'Debes registrar al menos un tipo de movimiento activo antes de crear movimientos.',
            ]));
        }

        $old = (array) session()->getFlashdata('old_form');
        $selectedProduct = null;
        $selectedProductId = (int) ($old['product_id'] ?? 0);
        if ($selectedProductId > 0) {
            $selectedProduct = (new ProductModel())->asArray()
                ->select('id, name, current_stock')
                ->where('status', 'active')
                ->find($selectedProductId);
        }

        return $this->renderTwig('inventory/movements/form', [
            'title' => 'Registrar movimiento',
            'movementTypes' => $movementTypes,
            'defaultMovementAt' => $this->now()->format('Y-m-d\TH:i'),
            'oldForm' => $old,
            'selectedProduct' => $selectedProduct,
            'productSearchLimit' => $config->productSearchLimit,
            'productSearchMinChars' => $config->productSearchMinChars,
            'productSearchMaxLength' => $config->productSearchMaxLength,
            'productSearchDebounceMs' => $config->productSearchDebounceMs,
            'productSearchCacheTtlMs' => $config->productSearchCacheTtlMs,
        ]);
    }

    public function searchProducts(): ResponseInterface
    {
        
        $config = config(SystemSettings::class);

        
        $service = service('productSearchService');
        $result = $service->search(
            (string) $this->request->getGet('q'),
            $config->productSearchLimit,
            $config->productSearchMinChars,
            $config->productSearchMaxLength,
            $config->productSearchContainsFallback,
        );

        return $this->response->setJSON([
            'results' => $result['results'],
            'meta' => [
                'query' => $result['query'],
                'limit' => $config->productSearchLimit,
                'min_chars' => $config->productSearchMinChars,
                'has_more' => $result['hasMore'],
            ],
        ]);
    }

    public function store(): RedirectResponse
    {
        $old = [
            'product_id' => (int) $this->request->getPost('product_id'),
            'movement_type_id' => (int) $this->request->getPost('movement_type_id'),
            'movement_at' => trim((string) $this->request->getPost('movement_at')),
            'direction' => strtoupper(trim((string) $this->request->getPost('direction'))),
            'quantity' => (int) $this->request->getPost('quantity'),
            'note' => trim((string) $this->request->getPost('note')),
        ];

        if ($old['product_id'] <= 0) {
            return redirect()->to('/movements/create')
                ->with('form_error', 'Selecciona un producto válido mediante el buscador.')
                ->with('old_form', $old);
        }

        $movementAt = $this->normalizeDateTime($old['movement_at']);
        if ($movementAt === null) {
            return redirect()->to('/movements/create')
                ->with('form_error', 'La fecha y hora del movimiento no es válida.')
                ->with('old_form', $old);
        }

        
        $service = service('inventoryMovementService');
        $result = $service->register(
            $old['product_id'],
            $old['movement_type_id'],
            (int) session()->get('auth_user_id'),
            $movementAt,
            $old['direction'],
            $old['quantity'],
            $old['note'],
        );

        if (! $result->success) {
            return redirect()->to('/movements/create')
                ->with('form_error', $result->message)
                ->with('old_form', $old);
        }

        return redirect()->to('/movements')->with('success', $result->message);
    }

    private function normalizeDateTime(string $value): ?string
    {
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($parsed instanceof DateTimeImmutable && $parsed->format($format) === $value) {
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function now(): DateTimeImmutable
    {
        $timezone = (string) config('App')->appTimezone;
        return new DateTimeImmutable('now', new DateTimeZone($timezone !== '' ? $timezone : 'UTC'));
    }
}
