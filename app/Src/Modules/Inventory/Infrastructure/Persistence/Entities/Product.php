<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Entities;

use CodeIgniter\Entity\Entity;

final class Product extends Entity
{
    protected $casts = [
        'id' => 'integer',
        'product_type_id' => 'integer',
        'units_per_package' => 'integer',
        'price' => 'float',
        'current_stock' => 'integer',
    ];
}
