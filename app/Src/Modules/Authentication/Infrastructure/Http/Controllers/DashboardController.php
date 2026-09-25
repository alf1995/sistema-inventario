<?php

declare(strict_types=1);

namespace App\Modules\Authentication\Infrastructure\Http\Controllers;

use App\Controllers\BaseController;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        $session = service('session');
        $permissions = (array) $session->get('auth_permissions');

        $canSeeInventory = $this->hasAnyPermission($permissions, [
            'products.view',
            'movements.view',
            'inventory.view',
            'inventory_reports.view',
        ]);

        $now = $this->now();

        return $this->renderTwig('dashboard/index', [
            'title' => 'Dashboard',
            'username' => (string) $session->get('auth_username'),
            'email' => (string) $session->get('auth_email'),
            'roles' => (array) $session->get('auth_roles'),
            'greeting' => $this->greeting((int) $now->format('G')),
            'today' => $now->format('d/m/Y'),
            'inventoryDashboard' => $canSeeInventory
                ? Services::inventoryDashboardService()->summary()
                : ['available' => false],
        ]);
    }

    
    private function hasAnyPermission(array $permissions, array $expected): bool
    {
        return array_intersect($permissions, $expected) !== [];
    }

    private function now(): DateTimeImmutable
    {
        $timezoneName = (string) config('App')->appTimezone;

        return new DateTimeImmutable(
            'now',
            new DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC'),
        );
    }

    private function greeting(int $hour): string
    {
        if ($hour < 12) {
            return 'Buenos días';
        }

        if ($hour < 19) {
            return 'Buenas tardes';
        }

        return 'Buenas noches';
    }
}
