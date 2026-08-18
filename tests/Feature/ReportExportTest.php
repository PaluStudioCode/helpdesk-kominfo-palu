<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $technician;
    protected User $opdUser;
    protected Department $department;
    protected TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => 'Badan Pendapatan Daerah',
            'code' => 'BAPENDA',
            'address' => 'Jl. Bapenda No. 1, Kota Palu',
            'pic_name' => 'Rahmat Hidayat',
            'pic_phone' => '081234567111',
            'status' => 'active',
        ]);

        $this->category = TicketCategory::create([
            'name' => 'Kabel FO Putus',
            'network_type' => 'fiber_optic',
            'sla_hours' => 24,
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Kominfo',
            'email' => 'admin@kominfo.palukota.go.id',
            'phone_number' => '081122334455',
            'role' => 'admin',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->technician = User::create([
            'name' => 'Teknisi Jaringan',
            'email' => 'teknisi@kominfo.palukota.go.id',
            'phone_number' => '082233445566',
            'role' => 'technician',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->opdUser = User::create([
            'name' => 'User Bapenda',
            'email' => 'bapenda@palukota.go.id',
            'phone_number' => '081234567111',
            'role' => 'opd_user',
            'department_id' => $this->department->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        // Create mock tickets
        Ticket::create([
            'ticket_number' => 'TKT-20260818-0001',
            'department_id' => $this->department->id,
            'reporter_id' => $this->opdUser->id,
            'assigned_to' => $this->technician->id,
            'category_id' => $this->category->id,
            'network_type' => 'fiber_optic',
            'title' => 'FO Bapenda Putus',
            'location_details' => 'Gedung Pelayanan',
            'description' => 'FO putus tertimpa pohon.',
            'priority' => 'high',
            'status' => 'in_progress',
            'due_at' => now()->addHours(24),
        ]);
    }

    public function test_admin_can_view_reports_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Index')
            ->has('tickets.data', 1)
            ->has('departments')
            ->has('technicians')
        );
    }

    public function test_non_admin_cannot_access_reports_page(): void
    {
        $response = $this->actingAs($this->opdUser)->get(route('admin.reports.index'));
        $response->assertStatus(403);

        $responseTech = $this->actingAs($this->technician)->get(route('admin.reports.index'));
        $responseTech->assertStatus(403);
    }

    public function test_admin_can_export_reports_to_pdf(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf'));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_export_reports_to_excel(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.excel'));

        $response->assertStatus(200);
        $this->assertTrue(
            str_contains($response->headers->get('content-disposition') ?? '', '.csv') ||
            str_contains($response->headers->get('content-type') ?? '', 'excel') ||
            str_contains($response->headers->get('content-type') ?? '', 'csv')
        );
    }
}
