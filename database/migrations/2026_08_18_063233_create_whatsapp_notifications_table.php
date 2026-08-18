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
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('recipient_id')->constrained('users')->onDelete('restrict');
            $table->string('target_phone', 30);
            $table->enum('event_type', ['ticket_created', 'ticket_assigned', 'status_in_progress', 'status_resolved', 'status_closed', 'ticket_reopened', 'ticket_cancelled']);
            $table->text('message_content');
            $table->enum('status', ['success', 'failed']);
            $table->json('response_payload')->nullable();
            $table->timestamp('created_at')->nullable();
            
            $table->index(['ticket_id', 'event_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
