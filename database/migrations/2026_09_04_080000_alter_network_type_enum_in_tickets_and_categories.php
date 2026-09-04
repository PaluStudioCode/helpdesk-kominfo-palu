<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('ticket_categories', 'network_type')) {
            DB::statement("ALTER TABLE ticket_categories MODIFY COLUMN network_type VARCHAR(50) NOT NULL");
            DB::statement("UPDATE ticket_categories SET network_type = 'Fiber optic' WHERE network_type = 'fiber_optic'");
            DB::statement("UPDATE ticket_categories SET network_type = 'Perangkat/Akses' WHERE network_type = 'lan'");
            DB::statement("UPDATE ticket_categories SET network_type = 'Layanan/jaringan' WHERE network_type = 'wifi'");
            DB::statement("ALTER TABLE ticket_categories MODIFY COLUMN network_type ENUM('Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan') NOT NULL");
        }

        if (Schema::hasColumn('tickets', 'network_type')) {
            DB::statement("ALTER TABLE tickets MODIFY COLUMN network_type VARCHAR(50) NULL");
            DB::statement("UPDATE tickets SET network_type = 'Fiber optic' WHERE network_type = 'fiber_optic'");
            DB::statement("UPDATE tickets SET network_type = 'Perangkat/Akses' WHERE network_type = 'lan'");
            DB::statement("UPDATE tickets SET network_type = 'Layanan/jaringan' WHERE network_type = 'wifi'");
            DB::statement("ALTER TABLE tickets MODIFY COLUMN network_type ENUM('Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan') NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ticket_categories MODIFY COLUMN network_type VARCHAR(50) NOT NULL");
        DB::statement("ALTER TABLE tickets MODIFY COLUMN network_type VARCHAR(50) NULL");

        DB::statement("UPDATE ticket_categories SET network_type = 'fiber_optic' WHERE network_type = 'Fiber optic'");
        DB::statement("UPDATE ticket_categories SET network_type = 'lan' WHERE network_type = 'Perangkat/Akses'");
        DB::statement("UPDATE ticket_categories SET network_type = 'wifi' WHERE network_type = 'Layanan/jaringan'");

        DB::statement("UPDATE tickets SET network_type = 'fiber_optic' WHERE network_type = 'Fiber optic'");
        DB::statement("UPDATE tickets SET network_type = 'lan' WHERE network_type = 'Perangkat/Akses'");
        DB::statement("UPDATE tickets SET network_type = 'wifi' WHERE network_type = 'Layanan/jaringan'");

        DB::statement("ALTER TABLE ticket_categories MODIFY COLUMN network_type ENUM('fiber_optic', 'lan', 'wifi') NOT NULL");
        DB::statement("ALTER TABLE tickets MODIFY COLUMN network_type ENUM('fiber_optic', 'lan', 'wifi') NULL");
    }
};
