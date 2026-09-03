<?php

namespace Tests;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    /**
     * Helper to create an Admin user.
     */
    protected function createAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Admin Testing',
            'email' => 'admin_' . uniqid() . '@palukota.go.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone_number' => '081234567890',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create a Technician user.
     */
    protected function createTechnician(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Teknisi Testing',
            'email' => 'teknisi_' . uniqid() . '@palukota.go.id',
            'password' => Hash::make('password'),
            'role' => 'technician',
            'phone_number' => '081234567891',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create an OPD Department.
     */
    protected function createDepartment(array $attributes = []): Department
    {
        $code = 'D' . rand(1000, 9999) . rand(100, 999);
        return Department::create(array_merge([
            'code' => substr($code, 0, 10),
            'name' => 'Dinas Testing ' . uniqid(),
            'address' => 'Jl. Balai Kota No. 1 Palu',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create an OPD User.
     */
    protected function createOpdUser(?Department $department = null, array $attributes = []): User
    {
        $dept = $department ?? $this->createDepartment();

        return User::create(array_merge([
            'name' => 'Operator OPD Testing',
            'email' => 'opd_' . uniqid() . '@palukota.go.id',
            'password' => Hash::make('password'),
            'role' => 'opd_user',
            'department_id' => $dept->id,
            'phone_number' => '081234567892',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create a Ticket Category.
     */
    protected function createCategory(array $attributes = []): TicketCategory
    {
        return TicketCategory::create(array_merge([
            'name' => 'Kategori Test ' . uniqid(),
            'network_type' => 'lan',
            'sla_hours' => 4,
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Helper to create a Ticket.
     */
    protected function createTicket(array $attributes = []): Ticket
    {
        $department = $attributes['department_id'] ?? $this->createDepartment()->id;
        $reporter = $attributes['reporter_id'] ?? $this->createOpdUser(Department::find($department))->id;

        return Ticket::create(array_merge([
            'ticket_number' => 'TKT-' . date('Ymd') . '-' . rand(1000, 9999),
            'department_id' => $department,
            'reporter_id' => $reporter,
            'assigned_to' => null,
            'category_id' => null,
            'network_type' => null,
            'title' => 'Laporan Gangguan Testing',
            'location_details' => 'Ruang Server Lt 2',
            'description' => 'Deskripsi gangguan testing.',
            'priority' => null,
            'status' => 'pending_admin',
            'assigned_at' => null,
            'due_at' => null,
        ], $attributes));
    }
}
