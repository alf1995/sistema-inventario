<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Entities;

use CodeIgniter\Entity\Entity;

final class ReportExport extends Entity
{
    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'row_count' => 'integer',
    ];
}
