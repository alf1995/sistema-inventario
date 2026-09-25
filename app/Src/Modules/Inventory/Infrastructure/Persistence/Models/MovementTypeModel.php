<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Inventory\Infrastructure\Persistence\Entities\MovementType;
use CodeIgniter\Model;

final class MovementTypeModel extends Model
{
    protected $table = 'inventory_movement_types';
    protected $primaryKey = 'id';
    protected $returnType = MovementType::class;
    protected $useSoftDeletes = true;
    protected $allowedFields = ['name', 'description', 'allowed_direction', 'status'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $validationRules = [
        'name' => 'required|max_length[100]',
        'description' => 'permit_empty|max_length[500]',
        'allowed_direction' => 'required|in_list[IN,OUT,BOTH]',
        'status' => 'required|in_list[active,inactive]',
    ];
    protected $validationMessages = [
        'name' => [
            'required' => 'El nombre del tipo de movimiento es obligatorio.',
        ],
        'allowed_direction' => [
            'required' => 'Debes seleccionar la operación permitida.',
            'in_list' => 'La dirección permitida debe ser Ingreso, Salida o Ambas.',
        ],
        'status' => [
            'required' => 'El estado es obligatorio.',
            'in_list' => 'El estado seleccionado no es válido.',
        ],
    ];
}
