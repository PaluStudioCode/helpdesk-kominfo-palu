<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('ticket_categories', 'network_type') && !Schema::hasColumn('ticket_categories', 'infrastructure_type')) {
            Schema::table('ticket_categories', function (Blueprint $table) {
                $table->renameColumn('network_type', 'infrastructure_type');
            });
        }

        if (Schema::hasColumn('tickets', 'network_type') && !Schema::hasColumn('tickets', 'infrastructure_type')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->renameColumn('network_type', 'infrastructure_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('ticket_categories', 'infrastructure_type') && !Schema::hasColumn('ticket_categories', 'network_type')) {
            Schema::table('ticket_categories', function (Blueprint $table) {
                $table->renameColumn('infrastructure_type', 'network_type');
            });
        }

        if (Schema::hasColumn('tickets', 'infrastructure_type') && !Schema::hasColumn('tickets', 'network_type')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->renameColumn('infrastructure_type', 'network_type');
            });
        }
    }
};
