<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateInventoryReportExportsTable extends Migration
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
            'user_id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
            ],
            'date_from' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'date_to' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'direction' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'null' => true,
            ],
            'file_name' => [
                'type' => 'VARCHAR',
                'constraint' => 191,
            ],
            'file_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
            ],
            'parameters_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'row_count' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ],
            'generated_at' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'generated_at'], false, false, 'idx_inventory_report_exports_user_date');
        $this->forge->addKey('generated_at', false, false, 'idx_inventory_report_exports_date');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'RESTRICT', 'CASCADE', 'fk_inventory_report_exports_user');
        $this->forge->createTable('inventory_report_exports', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_report_exports', true);
    }
}
