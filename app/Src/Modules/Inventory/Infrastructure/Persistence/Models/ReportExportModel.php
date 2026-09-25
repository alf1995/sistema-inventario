<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence\Models;

use App\Modules\Inventory\Infrastructure\Persistence\Entities\ReportExport;
use CodeIgniter\Model;

final class ReportExportModel extends Model
{
    protected $table = 'inventory_report_exports';
    protected $primaryKey = 'id';
    protected $returnType = ReportExport::class;
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = '';
    protected $allowedFields = [
        'user_id',
        'date_from',
        'date_to',
        'direction',
        'file_name',
        'file_path',
        'parameters_json',
        'row_count',
        'generated_at',
    ];
    protected $validationRules = [
        'user_id' => 'required|is_natural_no_zero',
        'date_from' => 'permit_empty|valid_date[Y-m-d]',
        'date_to' => 'permit_empty|valid_date[Y-m-d]',
        'direction' => 'permit_empty|in_list[IN,OUT]',
        'file_name' => 'required|max_length[191]',
        'file_path' => 'required|max_length[500]',
        'row_count' => 'required|is_natural',
        'generated_at' => 'required|valid_date[Y-m-d H:i:s]',
    ];
}
