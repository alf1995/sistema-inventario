<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Http\Controllers;

use App\Modules\Authentication\Application\Services\AccountPasswordService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Config\SystemSettings;

final class AccountController extends BaseController
{
    protected $helpers = ['form', 'url'];

    public function password(): string
    {
        return $this->renderTwig('account/password', [
            'title' => 'Cambiar mi contraseña',
        ]);
    }

    public function changePassword(): RedirectResponse
    {
        
        $config = config(SystemSettings::class);

        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword     = (string) $this->request->getPost('new_password');
        $confirmation    = (string) $this->request->getPost('new_password_confirm');

        if ($currentPassword === '' || $newPassword === '' || $confirmation === '') {
            return redirect()->to($config->changePasswordPath)->with('form_error', 'Completa todos los campos de contraseña.');
        }

        if (mb_strlen($currentPassword) > $config->passwordVerificationMaxLength) {
            return redirect()->to($config->changePasswordPath)->with('form_error', 'La contraseña actual supera la longitud máxima permitida.');
        }

        if ($newPassword !== $confirmation) {
            return redirect()->to($config->changePasswordPath)->with('form_error', 'La confirmación de la nueva contraseña no coincide.');
        }

        $session = service('session');

        
        $service = service('accountPasswordService');
        $result  = $service->change(
            (int) $session->get('auth_user_id'),
            $currentPassword,
            $newPassword,
            (string) $session->get('auth_session_token'),
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success) {
            return redirect()->to($config->changePasswordPath)->with('form_error', $result->message);
        }

        $session->regenerate(true);
        $session->set([
            'auth_must_change_password' => false,
            'auth_last_activity' => time(),
        ]);

        return redirect()->to($config->changePasswordPath)->with('success', $result->message);
    }
}
