<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Http\Filters;

use App\Modules\Authentication\Application\Services\AuthenticationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SystemSettings;

final class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');
        $userId  = (int) $session->get('auth_user_id');
        $token   = (string) $session->get('auth_session_token');

        
        $config = config(SystemSettings::class);

        if ($userId <= 0 || $token === '') {
            return redirect()->to($config->loginPath)
                ->with('auth_warning', 'Debes iniciar sesión para continuar.');
        }

        $lastActivity = (int) $session->get('auth_last_activity');
        if ($lastActivity > 0 && (time() - $lastActivity) >= $config->inactivityTimeout) {
            
            $auth = service('authenticationService');
            $auth->logout($userId, $token);
            $this->clearAuthenticationSession();
            $session->setFlashdata('auth_warning', 'Tu sesión expiró por inactividad. Inicia sesión nuevamente.');

            return redirect()->to($config->loginPath);
        }

        
        $auth = service('authenticationService');
        $result = $auth->validateSession($userId, $token);

        if (! $result->valid || $result->user === null) {
            $this->clearAuthenticationSession();

            $message = $result->code === 'expired_session'
                ? 'Tu sesión expiró por inactividad. Inicia sesión nuevamente.'
                : 'La sesión ya no es válida. Inicia sesión nuevamente.';

            $session->setFlashdata('auth_warning', $message);

            return redirect()->to($config->loginPath);
        }

        
        $authorization = service('authorizationService');

        $session->set([
            'auth_username'      => $result->user->username,
            'auth_email'         => $result->user->email,
            'auth_roles'         => $result->user->roles,
            'auth_permissions'        => $authorization->permissionsForUser($userId),
            'auth_must_change_password' => $result->user->mustChangePassword,
            'auth_last_activity'      => time(),
        ]);

        if ($result->user->mustChangePassword) {
            $path = trim($request->getUri()->getPath(), '/');
            $passwordPath = $config->routePath($config->changePasswordPath);
            $logoutPath   = $config->routePath($config->logoutPath);

            $isPasswordRoute = $path === $passwordPath || str_ends_with($path, '/' . $passwordPath);
            $isLogoutRoute   = $path === $logoutPath || str_ends_with($path, '/' . $logoutPath);

            if (! $isPasswordRoute && ! $isLogoutRoute) {
                return redirect()->to($config->changePasswordPath)
                    ->with('form_error', 'Debes cambiar tu contraseña inicial antes de continuar.');
            }
        }

        return null;
    }

    private function clearAuthenticationSession(): void
    {
        $session = service('session');
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
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
