<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Controllers\BaseController;
use App\Modules\Inventory\Application\Services\InventoryReportService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\ReportExportModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SystemSettings;
use RuntimeException;

final class ReportController extends BaseController
{
    protected $helpers = ['form', 'url', 'authorization'];

    public function index(): string
    {
        
        $config = config(SystemSettings::class);

        
        $service = service('inventoryReportService');

        $rawFilters = [
            'date_from' => trim((string) $this->request->getGet('date_from')),
            'date_to' => trim((string) $this->request->getGet('date_to')),
            'direction' => trim((string) $this->request->getGet('direction')),
            'search' => trim((string) $this->request->getGet('search')),
        ];

        $error = null;
        $rows = [];
        try {
            $filters = $service->normalizeFilters($rawFilters);
            $rows = $service->movements($filters, $config->reportPreviewLimit);
        } catch (RuntimeException $exception) {
            $filters = ['date_from' => '', 'date_to' => '', 'direction' => '', 'search' => ''];
            $error = $exception->getMessage();
        }

        return $this->renderTwig('inventory/reports/index', [
            'title' => 'Reportes de inventario',
            'filters' => $filters,
            'rows' => $rows,
            'filterError' => $error,
            'reportPreviewLimit' => $config->reportPreviewLimit,
            'reportSearchMaxLength' => $config->reportSearchMaxLength,
        ]);
    }

    public function export(): RedirectResponse
    {
        
        $service = service('inventoryReportService');

        try {
            $filters = $service->normalizeFilters([
                'date_from' => trim((string) $this->request->getPost('date_from')),
                'date_to' => trim((string) $this->request->getPost('date_to')),
                'direction' => trim((string) $this->request->getPost('direction')),
                'search' => trim((string) $this->request->getPost('search')),
            ]);
        } catch (RuntimeException $exception) {
            return redirect()->to('/reports')->with('form_error', $exception->getMessage());
        }

        $result = $service->export((int) session()->get('auth_user_id'), $filters);
        if (! $result->success || $result->id === null) {
            return redirect()->to('/reports')->with('form_error', $result->message);
        }

        return redirect()->to('/reports/' . $result->id . '/download')->with('success', $result->message);
    }

    public function history(): string
    {
        
        $config = config(SystemSettings::class);

        $search = trim((string) $this->request->getGet('q'));
        $model = new ReportExportModel();
        $query = $model->asArray()
            ->select('inventory_report_exports.*, users.username')
            ->join('users', 'users.id = inventory_report_exports.user_id');

        if ($search !== '') {
            $query->groupStart()
                ->like('users.username', $search)
                ->orLike('inventory_report_exports.file_name', $search)
                ->orLike('inventory_report_exports.parameters_json', $search)
                ->groupEnd();
        }

        $reports = $query
            ->orderBy('inventory_report_exports.generated_at', 'DESC')
            ->paginate($config->pageSize('report_history'), 'report_history');

        return $this->renderTwig('inventory/reports/history', [
            'title' => 'Histórico de reportes',
            'reports' => $reports,
            'pagination' => $reports !== []
                ? $model->pager->only(['q'])->links('report_history', 'default_full')
                : '',
            'paginationMeta' => $this->paginationMeta($model->pager, 'report_history'),
            'search' => $search,
        ]);
    }

    public function download(int $reportId): ResponseInterface
    {
        $report = (new ReportExportModel())->find($reportId);
        if ($report === null) {
            return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
                'title' => 'Reporte no encontrado',
                'message' => 'El reporte solicitado no existe.',
            ]));
        }

        $fileName = basename((string) $report->file_name);
        $path = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . $fileName;

        if (! is_file($path)) {
            return $this->response->setStatusCode(404)->setBody($this->renderTwig('errors/access_denied', [
                'title' => 'Archivo no disponible',
                'message' => 'El registro del reporte existe, pero el archivo ya no está disponible en el servidor.',
            ]));
        }

        return $this->response->download($path, null)->setFileName($fileName);
    }
}
