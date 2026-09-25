<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Entities;

use CodeIgniter\Entity\Entity;

final class InventoryMovement extends Entity
{
    protected $casts = [
        'id' => 'integer',
        'product_id' => 'integer',
        'movement_type_id' => 'integer',
        'user_id' => 'integer',
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
    ];
}
