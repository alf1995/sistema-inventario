<?php

namespace App\Modules\Administration\Infrastructure\Routing;

use App\Modules\Administration\Infrastructure\Http\Controllers\ModuleAdminController;
use App\Modules\Administration\Infrastructure\Http\Controllers\RoleAdminController;
use App\Modules\Administration\Infrastructure\Http\Controllers\UserAdminController;
use CodeIgniter\Router\RouteCollection;

final class AdministrationRoutes
{
    public static function register(RouteCollection $routes): void
    {
        $routes->group('admin', ['filter' => 'auth'], static function (RouteCollection $routes): void {
            self::registerUserRoutes($routes);
            self::registerRoleRoutes($routes);
            self::registerModuleRoutes($routes);
        });
    }

    private static function registerUserRoutes(RouteCollection $routes): void
    {
        $routes->group('users', static function (RouteCollection $routes): void {
            $routes->get('', [UserAdminController::class, 'index'], ['filter' => 'permission:users,view']);
            $routes->get('create', [UserAdminController::class, 'create'], ['filter' => 'permission:users,create']);
            $routes->post('', [UserAdminController::class, 'store'], ['filter' => 'permission:users,create']);
            $routes->get('(:num)/edit', [UserAdminController::class, 'edit/$1'], ['filter' => 'permission:users,edit']);
            $routes->post('(:num)', [UserAdminController::class, 'update/$1'], ['filter' => 'permission:users,edit']);
            $routes->get('(:num)/password', [UserAdminController::class, 'resetPasswordForm/$1'], ['filter' => 'permission:users,edit']);
            $routes->post('(:num)/password', [UserAdminController::class, 'resetPassword/$1'], ['filter' => 'permission:users,edit']);
            $routes->post('(:num)/revoke-sessions', [UserAdminController::class, 'revokeSessions/$1'], ['filter' => 'permission:users,edit']);
            $routes->post('(:num)/delete', [UserAdminController::class, 'delete/$1'], ['filter' => 'permission:users,delete']);
        });
    }

    private static function registerRoleRoutes(RouteCollection $routes): void
    {
        $routes->group('roles', static function (RouteCollection $routes): void {
            $routes->get('', [RoleAdminController::class, 'index'], ['filter' => 'permission:roles,view']);
            $routes->get('create', [RoleAdminController::class, 'create'], ['filter' => 'permission:roles,create']);
            $routes->post('', [RoleAdminController::class, 'store'], ['filter' => 'permission:roles,create']);
            $routes->get('(:num)/edit', [RoleAdminController::class, 'edit/$1'], ['filter' => 'permission:roles,edit']);
            $routes->post('(:num)', [RoleAdminController::class, 'update/$1'], ['filter' => 'permission:roles,edit']);
            $routes->post('(:num)/delete', [RoleAdminController::class, 'delete/$1'], ['filter' => 'permission:roles,delete']);
        });
    }

    private static function registerModuleRoutes(RouteCollection $routes): void
    {
        $routes->group('modules', static function (RouteCollection $routes): void {
            $routes->get('', [ModuleAdminController::class, 'index'], ['filter' => 'permission:modules,view']);
            $routes->get('create', [ModuleAdminController::class, 'create'], ['filter' => 'permission:modules,create']);
            $routes->post('', [ModuleAdminController::class, 'store'], ['filter' => 'permission:modules,create']);
            $routes->get('(:num)/edit', [ModuleAdminController::class, 'edit/$1'], ['filter' => 'permission:modules,edit']);
            $routes->post('(:num)', [ModuleAdminController::class, 'update/$1'], ['filter' => 'permission:modules,edit']);
            $routes->post('(:num)/delete', [ModuleAdminController::class, 'delete/$1'], ['filter' => 'permission:modules,delete']);
        });
    }
}
