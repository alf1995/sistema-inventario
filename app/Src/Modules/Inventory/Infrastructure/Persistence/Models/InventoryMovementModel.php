<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Inventory\Infrastructure\Persistence\Entities\InventoryMovement;
use CodeIgniter\Model;

final class InventoryMovementModel extends Model
{
    protected $table = 'inventory_movements';
    protected $primaryKey = 'id';
    protected $returnType = InventoryMovement::class;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = '';
    protected $allowedFields = [
        'product_id',
        'movement_type_id',
        'user_id',
        'movement_at',
        'direction',
        'quantity',
        'stock_before',
        'stock_after',
        'note',
    ];
    protected $validationRules = [
        'product_id' => 'required|is_natural_no_zero',
        'movement_type_id' => 'required|is_natural_no_zero',
        'user_id' => 'required|is_natural_no_zero',
        'movement_at' => 'required|valid_date[Y-m-d H:i:s]',
        'direction' => 'required|in_list[IN,OUT]',
        'quantity' => 'required|is_natural_no_zero',
        'stock_before' => 'required|is_natural',
        'stock_after' => 'required|is_natural',
        'note' => 'permit_empty|max_length[2000]',
    ];
}
