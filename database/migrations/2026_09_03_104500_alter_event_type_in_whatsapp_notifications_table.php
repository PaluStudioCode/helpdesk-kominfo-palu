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
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            $table->string('event_type', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            $table->enum('event_type', [
                'ticket_created',
                'ticket_assigned',
                'status_in_progress',
                'status_resolved',
                'status_closed',
                'ticket_reopened',
                'ticket_cancelled'
            ])->change();
        });
    }
};
