<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Alter status enum on tickets table to include 'on_hold'
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('pending_admin', 'in_progress', 'on_hold', 'pending_approval', 'closed', 'cancelled') NOT NULL DEFAULT 'pending_admin'");
            DB::statement("ALTER TABLE ticket_status_histories MODIFY COLUMN previous_status ENUM('pending_admin', 'in_progress', 'on_hold', 'pending_approval', 'closed', 'cancelled') NULL");
            DB::statement("ALTER TABLE ticket_status_histories MODIFY COLUMN new_status ENUM('pending_admin', 'in_progress', 'on_hold', 'pending_approval', 'closed', 'cancelled') NOT NULL");
        }

        // 2. Add columns for On-Hold details and SLA clock pausing
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('hold_reason_category', 50)->nullable()->after('status');
            $table->text('hold_reason_note')->nullable()->after('hold_reason_category');
            $table->timestamp('hold_started_at')->nullable()->after('hold_reason_note');
            $table->unsignedInteger('total_hold_duration_minutes')->default(0)->after('hold_started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'hold_reason_category',
                'hold_reason_note',
                'hold_started_at',
                'total_hold_duration_minutes',
            ]);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE tickets SET status = 'in_progress' WHERE status = 'on_hold'");
            DB::statement("ALTER TABLE tickets MODIFY COLUMN status ENUM('pending_admin', 'in_progress', 'pending_approval', 'closed', 'cancelled') NOT NULL DEFAULT 'pending_admin'");
            DB::statement("UPDATE ticket_status_histories SET previous_status = 'in_progress' WHERE previous_status = 'on_hold'");
            DB::statement("UPDATE ticket_status_histories SET new_status = 'in_progress' WHERE new_status = 'on_hold'");
            DB::statement("ALTER TABLE ticket_status_histories MODIFY COLUMN previous_status ENUM('pending_admin', 'in_progress', 'pending_approval', 'closed', 'cancelled') NULL");
            DB::statement("ALTER TABLE ticket_status_histories MODIFY COLUMN new_status ENUM('pending_admin', 'in_progress', 'pending_approval', 'closed', 'cancelled') NOT NULL");
        }
    }
};
