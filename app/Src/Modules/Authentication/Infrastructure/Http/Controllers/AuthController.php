<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Http\Controllers;

use App\Modules\Authentication\Application\Services\AuthenticationService;
use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;
use Config\SystemSettings;

final class AuthController extends BaseController
{
    protected $helpers = ['form', 'url'];

    public function showLogin(): string|RedirectResponse
    {
        if (service('session')->has('auth_user_id')) {
            
            $config = config(SystemSettings::class);

            return redirect()->to($config->dashboardPath);
        }

        return $this->renderTwig('auth/login', [
            'title' => 'Iniciar sesión',
        ]);
    }

    public function login(): RedirectResponse
    {
        
        $config = config(SystemSettings::class);

        $login    = trim((string) $this->request->getPost('login'));
        $password = (string) $this->request->getPost('password');

        $validation = service('validation');
        $validation->setRules([
            'login' => [
                'label' => 'Usuario o email',
                'rules' => 'required|max_length[' . $config->loginIdentifierMaxLength() . ']',
            ],
            'password' => [
                'label' => 'Contraseña',
                'rules' => 'required|max_length[' . $config->passwordVerificationMaxLength . ']',
            ],
        ]);

        if (! $validation->run(['login' => $login, 'password' => $password])) {
            return redirect()->to($config->loginPath)
                ->with('validation_errors', $validation->getErrors())
                ->with('old_login', $login);
        }

        
        $auth = service('authenticationService');
        $result = $auth->authenticate(
            $login,
            $password,
            $this->request->getIPAddress(),
            $this->request->getUserAgent()->getAgentString(),
        );

        if (! $result->success || $result->user === null || $result->sessionToken === null) {
            return redirect()->to($config->loginPath)
                ->with('auth_error', $result->message)
                ->with('old_login', $login);
        }

        $session = service('session');
        $session->regenerate(true);
        $session->set([
            'auth_user_id'       => $result->user->id,
            'auth_session_token' => $result->sessionToken,
            'auth_username'      => $result->user->username,
            'auth_email'         => $result->user->email,
            'auth_roles'         => $result->user->roles,
            'auth_permissions'        => [],
            'auth_must_change_password' => $result->user->mustChangePassword,
            'auth_last_activity'      => time(),
        ]);

        return redirect()->to($config->dashboardPath)
            ->with('auth_success', 'Bienvenido, ' . $result->user->username . '.');
    }

    public function logout(): RedirectResponse
    {
        $session = service('session');
        $userId  = (int) $session->get('auth_user_id');
        $token   = (string) $session->get('auth_session_token');

        
        $auth = service('authenticationService');
        $auth->logout($userId, $token);

        $session->remove([
            'auth_user_id',
            'auth_session_token',
            'auth_username',
            'auth_email',
            'auth_roles',
            'auth_permissions',
            'auth_must_change_password',
            'auth_last_activity',
        ]);
        $session->regenerate(true);
        $session->setFlashdata('auth_success', 'Sesión cerrada correctamente.');

        
        $config = config(SystemSettings::class);

        return redirect()->to($config->loginPath);
    }
}
