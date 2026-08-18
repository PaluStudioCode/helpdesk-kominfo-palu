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
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('reply_id')->nullable()->constrained('ticket_replies')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->enum('attachment_type', ['issue_proof', 'resolution_proof', 'reply_attachment']);
            $table->string('file_path', 255);
            $table->string('file_name', 255);
            $table->unsignedInteger('file_size');
            $table->timestamps();
            
            $table->index(['ticket_id', 'reply_id', 'attachment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
