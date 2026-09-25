<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Inventory\Infrastructure\Persistence\Entities\Product;
use CodeIgniter\Model;

final class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $returnType = Product::class;
    protected $useSoftDeletes = true;
    protected $allowedFields = ['name', 'product_type_id', 'units_per_package', 'price', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'name' => 'required|max_length[150]',
        'product_type_id' => 'required|is_natural_no_zero',
        'units_per_package' => 'required|is_natural_no_zero',
        'price' => 'required|decimal|greater_than_equal_to[0]',
        'status' => 'required|in_list[active,inactive]',
    ];
    protected $validationMessages = [
        'name' => [
            'required' => 'El nombre del producto es obligatorio.',
        ],
        'product_type_id' => [
            'required' => 'Debes seleccionar un tipo de producto.',
            'is_natural_no_zero' => 'Debes seleccionar un tipo de producto válido.',
        ],
        'units_per_package' => [
            'required' => 'Las unidades por empaque son obligatorias.',
            'is_natural_no_zero' => 'Las unidades por empaque deben ser mayores a cero.',
        ],
        'price' => [
            'required' => 'El precio es obligatorio.',
            'decimal' => 'El precio debe ser un valor decimal válido.',
            'greater_than_equal_to' => 'El precio no puede ser negativo.',
        ],
        'status' => [
            'required' => 'El estado del producto es obligatorio.',
            'in_list' => 'El estado seleccionado no es válido.',
        ],
    ];
}
