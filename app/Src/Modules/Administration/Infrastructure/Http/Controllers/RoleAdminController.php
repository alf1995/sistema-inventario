<?php

declare(strict_types=1);

namespace App\Modules\Administration\Infrastructure\Http\Controllers;

use App\Modules\Administration\Application\Services\RoleAdministrationService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

final class RoleAdminController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        
        $service = service('roleAdministrationService');

        return $this->renderTwig('admin/roles/index', [
            'title' => 'Roles',
            'roles' => $service->roles(),
        ]);
    }

    public function create(): string
    {
        
        $service = service('roleAdministrationService');

        return $this->renderTwig('admin/roles/form', [
            'title' => 'Nuevo rol',
            'mode' => 'create',
            'role' => null,
            'modules' => $service->modules(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $old = $this->roleFormInput();

        
        $service = service('roleAdministrationService');
        $result = $service->create(
            $this->actorUserId(),
            $old['name'],
            $old['slug'],
            $old['description'],
            $old['status'],
            $old['permissions'],
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to('/admin/roles/create')
                ->with('form_error', $result->message)
                ->with('old_form', $old);
        }

        return redirect()->to('/admin/roles')->with('success', $result->message);
    }

    public function edit(int $roleId): string|ResponseInterface
    {
        
        $service = service('roleAdministrationService');
        $role = $service->role($roleId);

        if ($role === null) {
            return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
                'title' => 'Rol no encontrado',
                'message' => 'El rol solicitado no existe.',
            ]));
        }

        return $this->renderTwig('admin/roles/form', [
            'title' => 'Editar rol',
            'mode' => 'edit',
            'role' => $role,
            'modules' => $service->modules(),
        ]);
    }

    public function update(int $roleId): RedirectResponse
    {
        $old = $this->roleFormInput();

        
        $service = service('roleAdministrationService');
        $result = $service->update(
            $this->actorUserId(),
            $roleId,
            $old['name'],
            $old['slug'],
            $old['description'],
            $old['status'],
            $old['permissions'],
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to('/admin/roles/' . $roleId . '/edit')
                ->with('form_error', $result->message)
                ->with('old_form', $old);
        }

        return redirect()->to('/admin/roles')->with('success', $result->message);
    }

    public function delete(int $roleId): RedirectResponse
    {
        
        $service = service('roleAdministrationService');
        $result = $service->delete(
            $this->actorUserId(),
            $roleId,
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        return redirect()->to('/admin/roles')
            ->with($result->success ? 'success' : 'form_error', $result->message);
    }

    
    private function roleFormInput(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'slug' => trim((string) $this->request->getPost('slug')),
            'description' => trim((string) $this->request->getPost('description')),
            'status' => trim((string) $this->request->getPost('status')),
            'permissions' => (array) $this->request->getPost('permissions'),
        ];
    }

    private function actorUserId(): int
    {
        return (int) service('session')->get('auth_user_id');
    }
}
