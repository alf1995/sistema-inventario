<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Controllers\BaseController;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ProductTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class ProductTypeController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $model = new ProductTypeModel();
        $query = $model->asArray();

        if ($search !== '') {
            $query->groupStart()
                ->like('name', $search)
                ->orLike('description', $search)
                ->orLike('status', $search)
                ->groupEnd();
        }

        return $this->renderTwig('inventory/config/product_types/index', [
            'title' => 'Tipos de producto',
            'items' => $query->orderBy('name', 'ASC')->findAll(),
            'search' => $search,
        ]);
    }

    public function create(): string
    {
        return $this->renderTwig('inventory/config/product_types/form', [
            'title' => 'Nuevo tipo de producto',
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $model = new ProductTypeModel();
        $data = $this->formInput();

        if ($this->nameExists($model, $data['name'])) {
            return redirect()->to('/settings/product-types/create')
                ->with('form_error', 'Ya existe un tipo de producto con ese nombre.')
                ->with('old_form', $data);
        }

        if ($model->insert($data) === false) {
            return redirect()->to('/settings/product-types/create')
                ->with('validation_errors', $model->errors())
                ->with('old_form', $data);
        }

        return redirect()->to('/settings/product-types')->with('success', 'Tipo de producto creado correctamente.');
    }

    public function edit(int $id): string|ResponseInterface
    {
        $model = new ProductTypeModel();
        $item = $model->asArray()->find($id);

        if ($item === null) {
            return $this->notFound('El tipo de producto solicitado no existe.');
        }

        return $this->renderTwig('inventory/config/product_types/form', [
            'title' => 'Editar tipo de producto',
            'mode' => 'edit',
            'item' => $item,
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $model = new ProductTypeModel();
        if ($model->find($id) === null) {
            return redirect()->to('/settings/product-types')->with('form_error', 'El tipo de producto solicitado no existe.');
        }

        $data = $this->formInput();
        if ($this->nameExists($model, $data['name'], $id)) {
            return redirect()->to('/settings/product-types/' . $id . '/edit')
                ->with('form_error', 'Ya existe un tipo de producto con ese nombre.')
                ->with('old_form', $data);
        }

        if (! $model->update($id, $data)) {
            return redirect()->to('/settings/product-types/' . $id . '/edit')
                ->with('validation_errors', $model->errors())
                ->with('old_form', $data);
        }

        return redirect()->to('/settings/product-types')->with('success', 'Tipo de producto actualizado correctamente.');
    }

    public function delete(int $id): RedirectResponse
    {
        $model = new ProductTypeModel();
        if ($model->find($id) === null) {
            return redirect()->to('/settings/product-types')->with('form_error', 'El tipo de producto solicitado no existe.');
        }

        $productsUsingType = db_connect()->table('products')
            ->where('product_type_id', $id)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($productsUsingType > 0) {
            return redirect()->to('/settings/product-types')->with(
                'form_error',
                'No se puede eliminar el tipo de producto porque tiene productos asociados. Puedes marcarlo como inactivo.',
            );
        }

        $model->delete($id);
        return redirect()->to('/settings/product-types')->with('success', 'Tipo de producto eliminado lógicamente.');
    }

    
    private function formInput(): array
    {
        $description = trim((string) $this->request->getPost('description'));

        return [
            'name' => trim((string) $this->request->getPost('name')),
            'description' => $description !== '' ? $description : null,
            'status' => trim((string) $this->request->getPost('status')),
        ];
    }

    private function nameExists(ProductTypeModel $model, string $name, ?int $ignoreId = null): bool
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
            'title' => 'Registro no encontrado',
            'message' => $message,
        ]));
    }
}
