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
            ->has('stats.total_active')
            ->has('stats.total_departments')
            ->has('stats.fiber_optic')
            ->has('recentTickets')
        );
    }

    public function test_opd_user_dashboard_isolates_data(): void
    {
        $dinkes = Department::where('code', 'DINKES')->first();
        $disdik = Department::where('code', 'DISDIK')->first();
        
        $opdDinkes = User::where('email', 'operator@dinkes.palukota.go.id')->first();
        $category = TicketCategory::first();

        // Create 2 tickets for Dinkes, 1 for Disdik
        Ticket::create([
            'ticket_number' => 'TKT-001',
            'department_id' => $dinkes->id,
            'reporter_id' => $opdDinkes->id,
            'category_id' => $category->id,
            'network_type' => 'wifi',
            'title' => 'WiFi Rusak',
            'location_details' => 'Ruang 1',
            'description' => 'Mati',
            'status' => 'open'
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-002',
            'department_id' => $dinkes->id,
            'reporter_id' => $opdDinkes->id,
            'category_id' => $category->id,
            'network_type' => 'lan',
            'title' => 'LAN Rusak',
            'location_details' => 'Ruang 2',
            'description' => 'Mati',
            'status' => 'resolved'
        ]);

        Ticket::create([
            'ticket_number' => 'TKT-003',
            'department_id' => $disdik->id, // OTHER DEPT
            'reporter_id' => User::where('email', 'operator@disdik.palukota.go.id')->first()->id,
            'category_id' => $category->id,
            'network_type' => 'lan',
            'title' => 'LAN Disdik Rusak',
            'location_details' => 'Ruang Disdik',
            'description' => 'Mati',
            'status' => 'open'
        ]);

        $response = $this->actingAs($opdDinkes)->get('/dashboard');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.active_tickets', 1) // Only TKT-001 is active for Dinkes
            ->where('stats.resolved_tickets', 1) // Only TKT-002 is resolved for Dinkes
            ->where('stats.total_tickets', 2) // Dinkes has 2 total tickets
            ->has('recentTickets', 2) // Should only see 2 tickets, not the 3rd one from Disdik
        );
    }
}
