<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\Services;

use App\Modules\Inventory\Domain\Repositories\InventoryReportRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class InventoryReportService
{
    public function __construct(
        private readonly InventoryReportRepositoryInterface $repository,
        private readonly LoggerInterface $logger,
        private readonly string $reportDirectory,
        private readonly int $searchMaxLength,
        private readonly string $timezone,
    ) {
    }

    public function normalizeFilters(array $filters): array
    {
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $direction = strtoupper(trim((string) ($filters['direction'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));

        if (mb_strlen($search) > $this->searchMaxLength) {
            throw new RuntimeException(
                'El texto de búsqueda no puede superar los ' . $this->searchMaxLength . ' caracteres.'
            );
        }

        if ($dateFrom !== '' && ! $this->isDate($dateFrom)) {
            throw new RuntimeException('La fecha desde no tiene un formato válido.');
        }

        if ($dateTo !== '' && ! $this->isDate($dateTo)) {
            throw new RuntimeException('La fecha hasta no tiene un formato válido.');
        }

        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            throw new RuntimeException('La fecha desde no puede ser posterior a la fecha hasta.');
        }

        if (! in_array($direction, ['', 'IN', 'OUT'], true)) {
            throw new RuntimeException('El filtro de tipo de movimiento no es válido.');
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'direction' => $direction,
            'search' => $search,
        ];
    }

    public function movements(array $filters, ?int $limit = null): array
    {
        return $this->repository->movements($filters, $limit);
    }

    public function export(int $userId, array $filters): InventoryOperationResult
    {
        if ($userId <= 0) {
            return InventoryOperationResult::failure('No se pudo identificar al usuario que solicita el reporte.');
        }

        if (! class_exists(Spreadsheet::class) || ! class_exists(Xlsx::class)) {
            return InventoryOperationResult::failure(
                'PhpSpreadsheet no está instalado. Ejecuta composer install después de actualizar las dependencias del proyecto.',
            );
        }

        try {
            $rows = $this->movements($filters);
            $now = $this->now();

            if (! is_dir($this->reportDirectory)
                && ! mkdir($this->reportDirectory, 0775, true)
                && ! is_dir($this->reportDirectory)) {
                throw new RuntimeException('No se pudo crear el directorio de reportes.');
            }

            $baseName = 'Reporte_' . $now->format('Ymd_Hi');
            $fileName = $this->availableFileName($this->reportDirectory, $baseName);
            $absolutePath = $this->reportDirectory . DIRECTORY_SEPARATOR . $fileName;

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Movimientos');
            $sheet->fromArray([
                'Fecha y hora',
                'Producto',
                'Tipo de producto',
                'Operación',
                'Tipo de movimiento',
                'Cantidad',
                'Stock anterior',
                'Stock actual',
                'Usuario',
                'Observación',
            ], null, 'A1');
            $sheet->getStyle('A1:J1')->getFont()->setBold(true);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:J1');

            $rowNumber = 2;
            foreach ($rows as $row) {
                $sheet->fromArray([
                    (string) $row['movement_at'],
                    (string) $row['product_name'],
                    (string) $row['product_type_name'],
                    (string) $row['direction'] === 'IN' ? 'Ingreso' : 'Salida',
                    (string) $row['movement_type_name'],
                    (int) $row['quantity'],
                    (int) $row['stock_before'],
                    (int) $row['stock_after'],
                    (string) $row['username'],
                    (string) ($row['note'] ?? ''),
                ], null, 'A' . $rowNumber);
                $rowNumber++;
            }

            foreach (range('A', 'J') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($absolutePath);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            try {
                $reportId = $this->repository->createExport([
                    'user_id' => $userId,
                    'date_from' => $filters['date_from'] !== '' ? $filters['date_from'] : null,
                    'date_to' => $filters['date_to'] !== '' ? $filters['date_to'] : null,
                    'direction' => $filters['direction'] !== '' ? $filters['direction'] : null,
                    'file_name' => $fileName,
                    'file_path' => 'reports/' . $fileName,
                    'parameters_json' => json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'row_count' => count($rows),
                    'generated_at' => $now->format('Y-m-d H:i:s'),
                ]);
            } catch (Throwable $exception) {
                @unlink($absolutePath);
                throw $exception;
            }

            return InventoryOperationResult::success('Reporte generado correctamente.', $reportId);
        } catch (Throwable $exception) {
            $this->logger->error('Error al generar reporte de inventario: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return InventoryOperationResult::failure($exception->getMessage());
        }
    }

    private function isDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            'now',
            new DateTimeZone($this->timezone !== '' ? $this->timezone : 'UTC'),
        );
    }

    private function availableFileName(string $directory, string $baseName): string
    {
        $fileName = $baseName . '.xlsx';
        if (! is_file($directory . DIRECTORY_SEPARATOR . $fileName)) {
            return $fileName;
        }

        $suffix = 2;
        do {
            $fileName = $baseName . '_' . $suffix . '.xlsx';
            $suffix++;
        } while (is_file($directory . DIRECTORY_SEPARATOR . $fileName));

        return $fileName;
    }
}
