<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Http\Filters;

use App\Modules\Authentication\Application\Services\AuthorizationService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Config\SystemSettings;

final class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');
        $userId  = (int) $session->get('auth_user_id');
        $arguments = array_values(array_filter(array_map('strval', (array) $arguments)));

        if ($userId <= 0) {
            
            $config = config(SystemSettings::class);

            return redirect()->to($config->loginPath)
                ->with('auth_warning', 'Debes iniciar sesión para continuar.');
        }

        if (count($arguments) !== 2) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(Services::twig()->render('errors/access_denied', [
                    'message' => 'La ruta no tiene módulo y acción configurados correctamente.',
                ]));
        }

        [$module, $action] = $arguments;

        
        $authorization = service('authorizationService');
        if (! $authorization->can($userId, $module, $action)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(Services::twig()->render('errors/access_denied', [
                    'message' => 'No tienes permisos suficientes para ejecutar esta acción.',
                ]));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
