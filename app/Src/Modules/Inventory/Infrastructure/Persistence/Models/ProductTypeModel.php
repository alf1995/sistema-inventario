<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Inventory\Infrastructure\Persistence\Entities\ProductType;
use CodeIgniter\Model;

final class ProductTypeModel extends Model
{
    protected $table = 'inventory_product_types';
    protected $primaryKey = 'id';
    protected $returnType = ProductType::class;
    protected $useSoftDeletes = true;
    protected $allowedFields = ['name', 'description', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'name' => 'required|max_length[100]',
        'description' => 'permit_empty|max_length[500]',
        'status' => 'required|in_list[active,inactive]',
    ];
    protected $validationMessages = [
        'name' => [
            'required' => 'El nombre del tipo de producto es obligatorio.',
            'max_length' => 'El nombre no puede superar los 100 caracteres.',
        ],
        'status' => [
            'required' => 'El estado es obligatorio.',
            'in_list' => 'El estado seleccionado no es válido.',
        ],
    ];
}
