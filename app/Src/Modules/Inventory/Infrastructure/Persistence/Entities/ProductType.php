<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Entities;

use CodeIgniter\Entity\Entity;

final class ProductType extends Entity
{
    protected $casts = [
        'id' => 'integer',
    ];
}
