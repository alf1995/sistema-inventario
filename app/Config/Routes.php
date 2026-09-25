<?php

use CodeIgniter\Router\RouteCollection;
use Config\RouteModules;

/** @var RouteCollection $routes */
/** @var RouteModules $routeModules */
$routeModules = config(RouteModules::class);

foreach ($routeModules->providers as $provider) {
    $provider::register($routes);
}
