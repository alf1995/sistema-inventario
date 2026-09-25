<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $productTypes = [
            ['name' => 'Caja', 'description' => 'Presentación en caja.'],
            ['name' => 'Bolsa', 'description' => 'Presentación en bolsa.'],
            ['name' => 'Blíster', 'description' => 'Presentación en blíster.'],
            ['name' => 'Paquete', 'description' => 'Presentación en paquete.'],
            ['name' => 'Unidad suelta', 'description' => 'Producto administrado por unidad.'],
        ];

        foreach ($productTypes as $row) {
            $this->firstOrCreate('inventory_product_types', 'name', $row['name'], $row + [
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $movementTypes = [
            ['name' => 'Movimiento interno', 'description' => 'Movimiento interno de inventario.', 'allowed_direction' => 'BOTH'],
            ['name' => 'Ajuste por merma', 'description' => 'Salida por merma o pérdida.', 'allowed_direction' => 'OUT'],
            ['name' => 'Compra a proveedor', 'description' => 'Ingreso por compra a proveedor.', 'allowed_direction' => 'IN'],
            ['name' => 'Venta a cliente', 'description' => 'Salida por venta a cliente.', 'allowed_direction' => 'OUT'],
        ];

        foreach ($movementTypes as $row) {
            $this->firstOrCreate('inventory_movement_types', 'name', $row['name'], $row + [
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function firstOrCreate(string $table, string $key, string $value, array $data): void
    {
        if ($this->db->table($table)->where($key, $value)->countAllResults() > 0) {
            return;
        }

        $this->db->table($table)->insert($data);
    }
}
