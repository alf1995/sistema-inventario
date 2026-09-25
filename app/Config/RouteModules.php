<?php

namespace Config;

use App\Modules\Administration\Infrastructure\Routing\AdministrationRoutes;
use App\Modules\Authentication\Infrastructure\Routing\AuthenticationRoutes;
use App\Modules\Inventory\Infrastructure\Routing\InventoryRoutes;
use CodeIgniter\Config\BaseConfig;

/**
 * Registro central de módulos que publican rutas HTTP.
 *
 * Routes.php solo recorre esta lista. Para incorporar un módulo nuevo,
 * crea su clase de rutas y agrégala aquí en el orden deseado.
 */
class RouteModules extends BaseConfig
{
    /**
     * @var array<class-string>
     */
    public array $providers = [
        AuthenticationRoutes::class,
        InventoryRoutes::class,
        AdministrationRoutes::class,
    ];
}
