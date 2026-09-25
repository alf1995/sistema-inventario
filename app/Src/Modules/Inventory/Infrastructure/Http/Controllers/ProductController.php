<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Controllers\BaseController;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ProductTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SystemSettings;

final class ProductController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        
        $config = config(SystemSettings::class);

        $search = trim((string) $this->request->getGet('q'));
        $model = new ProductModel();
        $query = $model->asArray()
            ->select('products.*, inventory_product_types.name AS product_type_name')
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
            ->paginate($config->pageSize('products'), 'products');

        return $this->renderTwig('inventory/products/index', [
            'title' => 'Productos',
            'products' => $products,
            'pagination' => $products !== []
                ? $model->pager->only(['q'])->links('products', 'default_full')
                : '',
            'paginationMeta' => $this->paginationMeta($model->pager, 'products'),
            'search' => $search,
        ]);
    }

    public function create(): string|ResponseInterface
    {
        $types = $this->activeProductTypes();
        if ($types === []) {
            return $this->response->setStatusCode(409)->setBody($this->renderTwig('errors/access_denied', [
                'title' => 'Configuración requerida',
                'message' => 'Debes crear al menos un tipo de producto activo antes de registrar productos.',
            ]));
        }

        return $this->renderTwig('inventory/products/form', [
            'title' => 'Nuevo producto',
            'mode' => 'create',
            'product' => null,
            'productTypes' => $types,
        ]);
    }

    public function store(): RedirectResponse
    {
        $model = new ProductModel();
        $data = $this->formInput();

        if (! $this->productTypeIsActive((int) $data['product_type_id'])) {
            return redirect()->to('/products/create')
                ->with('form_error', 'El tipo de producto seleccionado no está disponible.')
                ->with('old_form', $data);
        }

        if ($this->nameExists($model, $data['name'])) {
            return redirect()->to('/products/create')
                ->with('form_error', 'Ya existe un producto con ese nombre.')
                ->with('old_form', $data);
        }

        if ($model->insert($data) === false) {
            return redirect()->to('/products/create')
                ->with('validation_errors', $model->errors())
                ->with('old_form', $data);
        }

        return redirect()->to('/products')->with('success', 'Producto creado correctamente.');
    }

    public function edit(int $id): string|ResponseInterface
    {
        $model = new ProductModel();
        $product = $model->asArray()->find($id);
        if ($product === null) {
            return $this->notFound('El producto solicitado no existe.');
        }

        return $this->renderTwig('inventory/products/form', [
            'title' => 'Editar producto',
            'mode' => 'edit',
            'product' => $product,
            'productTypes' => (new ProductTypeModel())->asArray()->orderBy('name', 'ASC')->findAll(),
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $model = new ProductModel();
        if ($model->find($id) === null) {
            return redirect()->to('/products')->with('form_error', 'El producto solicitado no existe.');
        }

        $data = $this->formInput();
        if (! $this->productTypeExists((int) $data['product_type_id'])) {
            return redirect()->to('/products/' . $id . '/edit')
                ->with('form_error', 'El tipo de producto seleccionado no existe.')
                ->with('old_form', $data);
        }

        if ($this->nameExists($model, $data['name'], $id)) {
            return redirect()->to('/products/' . $id . '/edit')
                ->with('form_error', 'Ya existe un producto con ese nombre.')
                ->with('old_form', $data);
        }

        if (! $model->update($id, $data)) {
            return redirect()->to('/products/' . $id . '/edit')
                ->with('validation_errors', $model->errors())
                ->with('old_form', $data);
        }

        return redirect()->to('/products')->with('success', 'Producto actualizado correctamente.');
    }

    public function delete(int $id): RedirectResponse
    {
        $model = new ProductModel();
        $product = $model->asArray()->find($id);
        if ($product === null) {
            return redirect()->to('/products')->with('form_error', 'El producto solicitado no existe.');
        }

        if ((int) $product['current_stock'] !== 0) {
            return redirect()->to('/products')->with(
                'form_error',
                'No se puede eliminar un producto con stock disponible. Registra el ajuste correspondiente o márcalo como inactivo.',
            );
        }

        $model->delete($id);
        return redirect()->to('/products')->with('success', 'Producto eliminado lógicamente.');
    }

    
    private function formInput(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'product_type_id' => (int) $this->request->getPost('product_type_id'),
            'units_per_package' => (int) $this->request->getPost('units_per_package'),
            'price' => trim((string) $this->request->getPost('price')),
            'status' => trim((string) $this->request->getPost('status')),
        ];
    }

    
    private function activeProductTypes(): array
    {
        return (new ProductTypeModel())->asArray()
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function productTypeIsActive(int $id): bool
    {
        return (new ProductTypeModel())->where('id', $id)->where('status', 'active')->countAllResults() > 0;
    }

    private function productTypeExists(int $id): bool
    {
        return (new ProductTypeModel())->where('id', $id)->countAllResults() > 0;
    }

    private function nameExists(ProductModel $model, string $name, ?int $ignoreId = null): bool
    {
        $builder = $model->withDeleted()->where('name', $name);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }

        return $builder->countAllResults() > 0;
    }

    private function notFound(string $message): ResponseInterface
    {
        return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
            'title' => 'Producto no encontrado',
            'message' => $message,
        ]));
    }
}
