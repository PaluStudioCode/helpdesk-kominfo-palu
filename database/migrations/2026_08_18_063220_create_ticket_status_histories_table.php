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
        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('changed_by')->constrained('users')->onDelete('restrict');
            $table->enum('previous_status', ['open', 'in_progress', 'resolved', 'closed', 'cancelled'])->nullable();
            $table->enum('new_status', ['open', 'in_progress', 'resolved', 'closed', 'cancelled']);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_status_histories');
    }
};
