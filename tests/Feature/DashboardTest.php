<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_dashboard_metrics(): void
    {
        $admin = User::where('role', 'admin')->first();
        
        $response = $this->actingAs($admin)->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stats.total_tickets')
            ->has('stats.pending_admin')
            ->has('stats.pending_approval')
            ->has('stats.in_progress')
            ->has('stats.closed_tickets')
            ->has('stats.rejected_tickets')
            ->has('monthlyReports')
            ->has('availableYears')
            ->has('selectedYear')
            ->has('statusDistribution')
            ->has('networkTypeDistribution')
            ->has('priorityDistribution')
            ->has('ticketTrend')
        );
    }

    public function test_opd_user_dashboard_isolates_data(): void
    {
        Ticket::query()->forceDelete();

        $dinkes = Department::where('code', 'DINKES')->first();
        $disdik = Department::where('code', 'DISDIK')->first();
        
        $opdDinkes = User::where('email', 'operator@dinkes.palukota.go.id')->first();
        $category = TicketCategory::first();

        // Create 2 tickets for Dinkes (1 in process, 1 closed), 1 for Disdik
        Ticket::create([
            'ticket_number' => 'TKT-001',
            'department_id' => $dinkes->id,
            'reporter_id' => $opdDinkes->id,
            'category_id' => $category->id,
            'network_type' => 'wifi',
            'title' => 'WiFi Rusak',
            'location_details' => 'Ruang 1',
            'description' => 'Mati total.',
            'status' => 'in_progress'
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-002',
            'department_id' => $dinkes->id,
            'reporter_id' => $opdDinkes->id,
            'category_id' => $category->id,
            'network_type' => 'lan',
            'title' => 'LAN Rusak',
            'location_details' => 'Ruang 2',
            'description' => 'Mati total.',
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-003',
            'department_id' => $disdik->id, // OTHER DEPT
            'reporter_id' => User::where('email', 'operator@disdik.palukota.go.id')->first()->id,
            'category_id' => $category->id,
            'network_type' => 'lan',
            'title' => 'LAN Disdik Rusak',
            'location_details' => 'Ruang Disdik',
            'description' => 'Mati total.',
            'status' => 'pending_admin'
        ]);

        $response = $this->actingAs($opdDinkes)->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.in_process', 1) // Only TKT-001 is in_process for Dinkes
            ->where('stats.closed_tickets', 1) // Only TKT-002 is closed for Dinkes
            ->where('stats.total_reports', 2) // Dinkes has 2 total tickets
        );
    }
}
