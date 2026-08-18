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
        Schema::create('ticket_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('network_type', ['fiber_optic', 'lan', 'wifi']);
            $table->unsignedInteger('sla_hours');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['network_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_categories');
    }
};
