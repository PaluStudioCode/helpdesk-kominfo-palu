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
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('affected_device', 150)->nullable()->after('infrastructure_type');
            $table->string('actual_repair_location', 255)->nullable()->after('affected_device');
            $table->text('inspection_result')->nullable()->after('resolution_note');
            $table->text('root_cause')->nullable()->after('inspection_result');
            $table->text('action_taken')->nullable()->after('root_cause');
            $table->text('materials_used')->nullable()->after('action_taken');
            $table->text('test_result')->nullable()->after('materials_used');
            $table->text('test_parameters')->nullable()->after('test_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'affected_device',
                'actual_repair_location',
                'inspection_result',
                'root_cause',
                'action_taken',
                'materials_used',
                'test_result',
                'test_parameters',
            ]);
        });
    }
};
