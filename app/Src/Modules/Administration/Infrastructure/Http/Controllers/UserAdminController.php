<?php

declare(strict_types=1);

namespace App\Modules\Administration\Infrastructure\Http\Controllers;

use App\Modules\Administration\Application\Services\UserAdministrationService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SystemSettings;

final class UserAdminController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        
        $service = service('userAdministrationService');

        return $this->renderTwig('admin/users/index', [
            'title' => 'Usuarios',
            'users' => $service->users(),
        ]);
    }

    public function create(): string
    {
        
        $service = service('userAdministrationService');

        return $this->renderTwig('admin/users/form', [
            'title' => 'Nuevo usuario',
            'mode'  => 'create',
            'user'  => null,
            'roles' => $service->roles(),
        ]);
    }

    public function store(): RedirectResponse
    {
        $password = (string) $this->request->getPost('password');
        $confirm  = (string) $this->request->getPost('password_confirm');
        $old      = $this->userFormInput();

        if ($password === '' || $password !== $confirm) {
            return redirect()->to('/admin/users/create')
                ->with('form_error', $password === '' ? 'La contraseña inicial es obligatoria.' : 'La confirmación de contraseña no coincide.')
                ->with('old_form', $old);
        }

        
        $service = service('userAdministrationService');
        $result  = $service->create(
            $this->actorUserId(),
            $old['username'],
            $old['email'],
            $password,
            $old['status'],
            $old['role_ids'],
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to('/admin/users/create')
                ->with('form_error', $result->message)
                ->with('old_form', $old);
        }

        return redirect()->to('/admin/users')->with('success', $result->message);
    }

    public function edit(int $userId): string|ResponseInterface
    {
        
        $service = service('userAdministrationService');
        $user    = $service->user($userId);

        if ($user === null) {
            return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
                'title'   => 'Usuario no encontrado',
                'message' => 'El usuario solicitado no existe.',
            ]));
        }

        return $this->renderTwig('admin/users/form', [
            'title' => 'Editar usuario',
            'mode'  => 'edit',
            'user'  => $user,
            'roles' => $service->roles(),
        ]);
    }

    public function update(int $userId): RedirectResponse
    {
        $old = $this->userFormInput();

        
        $service = service('userAdministrationService');
        $result  = $service->update(
            $this->actorUserId(),
            $userId,
            $old['username'],
            $old['email'],
            $old['status'],
            $old['role_ids'],
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to('/admin/users/' . $userId . '/edit')
                ->with('form_error', $result->message)
                ->with('old_form', $old);
        }

        return redirect()->to('/admin/users')->with('success', $result->message);
    }

    public function resetPasswordForm(int $userId): string|ResponseInterface
    {
        
        $service = service('userAdministrationService');
        $user    = $service->user($userId);

        if ($user === null) {
            return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
                'title'   => 'Usuario no encontrado',
                'message' => 'El usuario solicitado no existe.',
            ]));
        }

        return $this->renderTwig('admin/users/reset_password', ['title' => 'Restablecer contraseña', 'user' => $user]);
    }

    public function resetPassword(int $userId): RedirectResponse
    {
        $actorPassword = (string) $this->request->getPost('admin_current_password');
        $newPassword   = (string) $this->request->getPost('new_password');
        $confirmation  = (string) $this->request->getPost('new_password_confirm');

        if ($actorPassword === '' || $newPassword === '' || $newPassword !== $confirmation) {
            return redirect()->to('/admin/users/' . $userId . '/password')
                ->with('form_error', $newPassword !== $confirmation
                    ? 'La confirmación de la nueva contraseña no coincide.'
                    : 'Completa todos los campos de contraseña.');
        }

        
        $config = config(SystemSettings::class);
        if (mb_strlen($actorPassword) > $config->passwordVerificationMaxLength) {
            return redirect()->to('/admin/users/' . $userId . '/password')
                ->with('form_error', 'La contraseña administrativa supera la longitud máxima permitida.');
        }

        
        $service = service('userAdministrationService');
        $result  = $service->resetPassword(
            $this->actorUserId(),
            $userId,
            $actorPassword,
            $newPassword,
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to('/admin/users/' . $userId . '/password')->with('form_error', $result->message);
        }

        return redirect()->to('/admin/users')->with('success', $result->message);
    }

    public function revokeSessions(int $userId): RedirectResponse
    {
        
        $service = service('userAdministrationService');
        $result  = $service->revokeSessions(
            $this->actorUserId(),
            $userId,
            (string) service('session')->get('auth_session_token'),
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        return redirect()->to('/admin/users')
            ->with($result->success ? 'success' : 'form_error', $result->message);
    }

    public function delete(int $userId): RedirectResponse
    {
        
        $service = service('userAdministrationService');
        $result = $service->delete(
            $this->actorUserId(),
            $userId,
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        return redirect()->to('/admin/users')
            ->with($result->success ? 'success' : 'form_error', $result->message);
    }

    
    private function userFormInput(): array
    {
        return [
            'username' => trim((string) $this->request->getPost('username')),
            'email'    => trim((string) $this->request->getPost('email')),
            'status' => trim((string) $this->request->getPost('status')),
            'role_ids' => array_values(array_map('intval', (array) $this->request->getPost('role_ids'))),
        ];
    }

    private function actorUserId(): int
    {
        return (int) service('session')->get('auth_user_id');
    }
}
