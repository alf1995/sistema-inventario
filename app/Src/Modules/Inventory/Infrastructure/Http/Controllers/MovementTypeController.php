<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Controllers\BaseController;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementTypeModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class MovementTypeController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        $search = trim((string) $this->request->getGet('q'));
        $model = new MovementTypeModel();
        $query = $model->asArray();

        if ($search !== '') {
            $query->groupStart()
                ->like('name', $search)
                ->orLike('description', $search)
                ->orLike('allowed_direction', $search)
                ->orLike('status', $search)
                ->groupEnd();
        }

        return $this->renderTwig('inventory/config/movement_types/index', [
            'title' => 'Tipos de movimiento',
            'items' => $query->orderBy('name', 'ASC')->findAll(),
            'search' => $search,
        ]);
    }

    public function create(): string
    {
        return $this->renderTwig('inventory/config/movement_types/form', [
            'title' => 'Nuevo tipo de movimiento',
            'mode' => 'create',
            'item' => null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $model = new MovementTypeModel();
        $data = $this->formInput();

        if ($this->nameExists($model, $data['name'])) {
            return redirect()->to('/settings/movement-types/create')
                ->with('form_error', 'Ya existe un tipo de movimiento con ese nombre.')
                ->with('old_form', $data);
        }

        if ($model->insert($data) === false) {
            return redirect()->to('/settings/movement-types/create')
                ->with('validation_errors', $model->errors())
                ->with('old_form', $data);
        }

        return redirect()->to('/settings/movement-types')->with('success', 'Tipo de movimiento creado correctamente.');
    }

    public function edit(int $id): string|ResponseInterface
    {
        $model = new MovementTypeModel();
        $item = $model->asArray()->find($id);

        if ($item === null) {
            return $this->notFound('El tipo de movimiento solicitado no existe.');
        }

        return $this->renderTwig('inventory/config/movement_types/form', [
            'title' => 'Editar tipo de movimiento',
            'mode' => 'edit',
            'item' => $item,
        ]);
    }

    public function update(int $id): RedirectResponse
    {
        $model = new MovementTypeModel();
        if ($model->find($id) === null) {
            return redirect()->to('/settings/movement-types')->with('form_error', 'El tipo de movimiento solicitado no existe.');
        }

        $data = $this->formInput();
        if ($this->nameExists($model, $data['name'], $id)) {
            return redirect()->to('/settings/movement-types/' . $id . '/edit')
                ->with('form_error', 'Ya existe un tipo de movimiento con ese nombre.')
                ->with('old_form', $data);
        }

        if (! $model->update($id, $data)) {
            return redirect()->to('/settings/movement-types/' . $id . '/edit')
                ->with('validation_errors', $model->errors())
                ->with('old_form', $data);
        }

        return redirect()->to('/settings/movement-types')->with('success', 'Tipo de movimiento actualizado correctamente.');
    }

    public function delete(int $id): RedirectResponse
    {
        $model = new MovementTypeModel();
        if ($model->find($id) === null) {
            return redirect()->to('/settings/movement-types')->with('form_error', 'El tipo de movimiento solicitado no existe.');
        }

        $model->delete($id);
        return redirect()->to('/settings/movement-types')->with('success', 'Tipo de movimiento eliminado lógicamente.');
    }

    
    private function formInput(): array
    {
        $description = trim((string) $this->request->getPost('description'));

        return [
            'name' => trim((string) $this->request->getPost('name')),
            'description' => $description !== '' ? $description : null,
            'allowed_direction' => strtoupper(trim((string) $this->request->getPost('allowed_direction'))),
            'status' => trim((string) $this->request->getPost('status')),
        ];
    }

    private function nameExists(MovementTypeModel $model, string $name, ?int $ignoreId = null): bool
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
