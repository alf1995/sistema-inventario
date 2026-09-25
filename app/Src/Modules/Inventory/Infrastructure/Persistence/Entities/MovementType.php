<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Entities;

use CodeIgniter\Entity\Entity;

final class MovementType extends Entity
{
    protected $casts = [
        'id' => 'integer',
    ];
}
