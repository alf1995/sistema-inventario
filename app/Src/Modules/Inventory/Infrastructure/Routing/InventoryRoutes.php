<?php

namespace App\Modules\Inventory\Infrastructure\Routing;

use App\Modules\Inventory\Infrastructure\Http\Controllers\InventoryController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\MovementController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\MovementTypeController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\ProductController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\ProductTypeController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\ReportController;
use CodeIgniter\Router\RouteCollection;

final class InventoryRoutes
{
    public static function register(RouteCollection $routes): void
    {
        $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
            self::registerProductRoutes($routes);
            self::registerMovementRoutes($routes);
            self::registerInventoryRoutes($routes);
            self::registerReportRoutes($routes);
            self::registerProductTypeRoutes($routes);
            self::registerMovementTypeRoutes($routes);
        });
    }

    private static function registerProductRoutes(RouteCollection $routes): void
    {
        $routes->group('products', static function (RouteCollection $routes): void {
            $routes->get('', [ProductController::class, 'index'], ['filter' => 'permission:products,view']);
            $routes->get('create', [ProductController::class, 'create'], ['filter' => 'permission:products,create']);
            $routes->post('', [ProductController::class, 'store'], ['filter' => 'permission:products,create']);
            $routes->get('(:num)/edit', [ProductController::class, 'edit/$1'], ['filter' => 'permission:products,edit']);
            $routes->post('(:num)', [ProductController::class, 'update/$1'], ['filter' => 'permission:products,edit']);
            $routes->post('(:num)/delete', [ProductController::class, 'delete/$1'], ['filter' => 'permission:products,delete']);
        });
    }

    private static function registerMovementRoutes(RouteCollection $routes): void
    {
        $routes->group('movements', static function (RouteCollection $routes): void {
            $routes->get('', [MovementController::class, 'index'], ['filter' => 'permission:movements,view']);
            $routes->get('create', [MovementController::class, 'create'], ['filter' => 'permission:movements,create']);
            $routes->get('products/search', [MovementController::class, 'searchProducts'], ['filter' => 'permission:movements,create']);
            $routes->post('', [MovementController::class, 'store'], ['filter' => 'permission:movements,create']);
        });
    }

    private static function registerInventoryRoutes(RouteCollection $routes): void
    {
        $routes->group('inventory', static function (RouteCollection $routes): void {
            $routes->get('', [InventoryController::class, 'index'], ['filter' => 'permission:inventory,view']);
            $routes->get('(:num)/history', [InventoryController::class, 'history/$1'], ['filter' => 'permission:inventory,view']);
        });
    }

    private static function registerReportRoutes(RouteCollection $routes): void
    {
        $routes->group('reports', static function (RouteCollection $routes): void {
            $routes->get('', [ReportController::class, 'index'], ['filter' => 'permission:inventory_reports,view']);
            $routes->post('export', [ReportController::class, 'export'], ['filter' => 'permission:inventory_reports,create']);
            $routes->get('history', [ReportController::class, 'history'], ['filter' => 'permission:inventory_reports,view']);
            $routes->get('(:num)/download', [ReportController::class, 'download/$1'], ['filter' => 'permission:inventory_reports,view']);
        });
    }

    private static function registerProductTypeRoutes(RouteCollection $routes): void
    {
        $routes->group('settings/product-types', static function (RouteCollection $routes): void {
            $routes->get('', [ProductTypeController::class, 'index'], ['filter' => 'permission:inventory_settings,view']);
            $routes->get('create', [ProductTypeController::class, 'create'], ['filter' => 'permission:inventory_settings,create']);
            $routes->post('', [ProductTypeController::class, 'store'], ['filter' => 'permission:inventory_settings,create']);
            $routes->get('(:num)/edit', [ProductTypeController::class, 'edit/$1'], ['filter' => 'permission:inventory_settings,edit']);
            $routes->post('(:num)', [ProductTypeController::class, 'update/$1'], ['filter' => 'permission:inventory_settings,edit']);
            $routes->post('(:num)/delete', [ProductTypeController::class, 'delete/$1'], ['filter' => 'permission:inventory_settings,delete']);
        });
    }

    private static function registerMovementTypeRoutes(RouteCollection $routes): void
    {
        $routes->group('settings/movement-types', static function (RouteCollection $routes): void {
            $routes->get('', [MovementTypeController::class, 'index'], ['filter' => 'permission:inventory_settings,view']);
            $routes->get('create', [MovementTypeController::class, 'create'], ['filter' => 'permission:inventory_settings,create']);
            $routes->post('', [MovementTypeController::class, 'store'], ['filter' => 'permission:inventory_settings,create']);
            $routes->get('(:num)/edit', [MovementTypeController::class, 'edit/$1'], ['filter' => 'permission:inventory_settings,edit']);
            $routes->post('(:num)', [MovementTypeController::class, 'update/$1'], ['filter' => 'permission:inventory_settings,edit']);
            $routes->post('(:num)/delete', [MovementTypeController::class, 'delete/$1'], ['filter' => 'permission:inventory_settings,delete']);
        });
    }
}
