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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->onDelete('set null');
            $table->string('phone_number', 30)->after('password')->nullable();
            $table->enum('role', ['admin', 'technician', 'opd_user'])->after('phone_number')->default('opd_user');
            $table->enum('status', ['active', 'inactive'])->after('role')->default('active');
            $table->softDeletes();
            
            // Re-define unique email logic handling soft deletes
            $table->dropUnique(['email']);
            $table->unique(['email', 'deleted_at'], 'users_email_deleted_at_unique');
            
            $table->index(['role', 'status', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['role', 'status', 'department_id']);
            $table->dropUnique('users_email_deleted_at_unique');
            
            $table->dropColumn(['department_id', 'phone_number', 'role', 'status', 'deleted_at']);
            
            $table->unique('email');
        });
    }
};
