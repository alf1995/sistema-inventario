<?php

declare(strict_types=1);

namespace App\Modules\Administration\Infrastructure\Http\Controllers;

use App\Modules\Administration\Application\Services\ModuleAdministrationService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class ModuleAdminController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        
        $service = service('moduleAdministrationService');

        return $this->renderTwig('admin/modules/index', [
            'title' => 'Módulos',
            'modules' => $service->modules(),
        ]);
    }

    public function create(): string
    {
        return $this->renderTwig('admin/modules/form', [
            'title' => 'Nuevo módulo',
            'mode' => 'create',
            'module' => null,
        ]);
    }

    public function store(): RedirectResponse
    {
        $old = $this->moduleFormInput();

        
        $service = service('moduleAdministrationService');
        $result = $service->create(
            $this->actorUserId(),
            $old['name'],
            $old['slug'],
            $old['description'],
            $old['status'],
            $old['allow_view'],
            $old['allow_create'],
            $old['allow_edit'],
            $old['allow_delete'],
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to('/admin/modules/create')
                ->with('form_error', $result->message)
                ->with('old_form', $old);
        }

        return redirect()->to('/admin/modules')->with('success', $result->message);
    }

    public function edit(int $moduleId): string|ResponseInterface
    {
        
        $service = service('moduleAdministrationService');
        $module = $service->module($moduleId);

        if ($module === null) {
            return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
                'title' => 'Módulo no encontrado',
                'message' => 'El módulo solicitado no existe.',
            ]));
        }

        return $this->renderTwig('admin/modules/form', [
            'title' => 'Editar módulo',
            'mode' => 'edit',
            'module' => $module,
        ]);
    }

    public function update(int $moduleId): RedirectResponse
    {
        $old = $this->moduleFormInput();

        
        $service = service('moduleAdministrationService');
        $result = $service->update(
            $this->actorUserId(),
            $moduleId,
            $old['name'],
            $old['slug'],
            $old['description'],
            $old['status'],
            $old['allow_view'],
            $old['allow_create'],
            $old['allow_edit'],
            $old['allow_delete'],
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to('/admin/modules/' . $moduleId . '/edit')
                ->with('form_error', $result->message)
                ->with('old_form', $old);
        }

        return redirect()->to('/admin/modules')->with('success', $result->message);
    }

    public function delete(int $moduleId): RedirectResponse
    {
        
        $service = service('moduleAdministrationService');
        $result = $service->delete(
            $this->actorUserId(),
            $moduleId,
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        return redirect()->to('/admin/modules')
            ->with($result->success ? 'success' : 'form_error', $result->message);
    }

    
    private function moduleFormInput(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => trim((string) $this->request->getPost('slug')),
            'description' => trim((string) $this->request->getPost('description')),
            'status' => trim((string) $this->request->getPost('status')),
            'allow_view' => $this->request->getPost('allow_view') === '1',
            'allow_create' => $this->request->getPost('allow_create') === '1',
            'allow_edit' => $this->request->getPost('allow_edit') === '1',
            'allow_delete' => $this->request->getPost('allow_delete') === '1',
        ];
    }

    private function actorUserId(): int
    {
        return (int) service('session')->get('auth_user_id');
    }
}
