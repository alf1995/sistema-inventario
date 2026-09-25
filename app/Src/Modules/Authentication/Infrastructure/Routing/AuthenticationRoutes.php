<?php

namespace App\Modules\Authentication\Infrastructure\Routing;

use App\Modules\Authentication\Infrastructure\Http\Controllers\AccountController;
use App\Modules\Authentication\Infrastructure\Http\Controllers\AuthController;
use App\Modules\Authentication\Infrastructure\Http\Controllers\DashboardController;
use CodeIgniter\Router\RouteCollection;
use Config\SystemSettings;

final class AuthenticationRoutes
{
    public static function register(RouteCollection $routes): void
    {
        
        $settings = config(SystemSettings::class);

        $loginRoute = $settings->routePath($settings->loginPath);
        $dashboardRoute = $settings->routePath($settings->dashboardPath);
        $logoutRoute = $settings->routePath($settings->logoutPath);
        $changePasswordRoute = $settings->routePath($settings->changePasswordPath);

        
        $routes->get('/', [AuthController::class, 'showLogin']);
        $routes->get($loginRoute, [AuthController::class, 'showLogin'], ['as' => 'login']);
        $routes->post($loginRoute, [AuthController::class, 'login']);

        
        $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) use (
            $dashboardRoute,
            $logoutRoute,
            $changePasswordRoute
        ): void {
            $routes->get($dashboardRoute, [DashboardController::class, 'index'], ['as' => 'dashboard']);
            $routes->post($logoutRoute, [AuthController::class, 'logout'], ['as' => 'logout']);

            $routes->get($changePasswordRoute, [AccountController::class, 'password']);
            $routes->post($changePasswordRoute, [AccountController::class, 'changePassword']);
        });
    }
}
