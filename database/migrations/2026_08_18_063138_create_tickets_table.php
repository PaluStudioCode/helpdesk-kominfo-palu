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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 50)->unique();
            $table->foreignId('department_id')->constrained()->onDelete('restrict');
            $table->foreignId('reporter_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('category_id')->constrained('ticket_categories')->onDelete('restrict');
            $table->enum('network_type', ['fiber_optic', 'lan', 'wifi']);
            $table->string('title', 200);
            $table->string('location_details', 255);
            $table->text('description');
            $table->enum('priority', ['low', 'medium', 'high', 'emergency'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'cancelled'])->default('open');
            $table->text('resolution_note')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['department_id', 'status', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['network_type', 'priority', 'status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
