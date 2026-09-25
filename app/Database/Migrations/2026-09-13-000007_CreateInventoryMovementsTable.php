<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateInventoryMovementsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'product_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'movement_type_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'movement_at' => [
                'type' => 'DATETIME',
            ],
            'direction' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
            ],
            'quantity' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'stock_before' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'stock_after' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['product_id', 'movement_at'], false, false, 'idx_inventory_movements_product_date');
        $this->forge->addKey(['direction', 'movement_at'], false, false, 'idx_inventory_movements_direction_date');
        $this->forge->addKey('movement_type_id', false, false, 'idx_inventory_movements_type');
        $this->forge->addKey('user_id', false, false, 'idx_inventory_movements_user');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE', 'fk_inventory_movements_product');
        $this->forge->addForeignKey('movement_type_id', 'inventory_movement_types', 'id', 'RESTRICT', 'CASCADE', 'fk_inventory_movements_type');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'CASCADE', 'fk_inventory_movements_user');
        $this->forge->createTable('inventory_movements', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_movements', true);
    }
}
