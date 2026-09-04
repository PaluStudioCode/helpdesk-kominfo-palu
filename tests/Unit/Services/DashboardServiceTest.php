<?php

namespace Tests\Unit\Services;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    protected DashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardService = new DashboardService();
    }

    public function test_dashboard_service_aggregates_opd_data(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);

        $this->createTicket(['department_id' => $dept->id, 'status' => 'pending_admin']);
        $this->createTicket(['department_id' => $dept->id, 'status' => 'in_progress']);
        $this->createTicket(['department_id' => $dept->id, 'status' => 'closed', 'rating' => null]);

        $request = Request::create('/dashboard', 'GET');
        $data = $this->dashboardService->getDashboardData($request, $opdUser);

        $this->assertArrayHasKey('stats', $data);
        $this->assertEquals(2, $data['stats']['in_process']);
        $this->assertEquals(1, $data['stats']['closed_tickets']);
        $this->assertEquals(1, $data['stats']['pending_rating']);
        $this->assertEquals(3, $data['stats']['total_reports']);
    }

    public function test_dashboard_service_aggregates_technician_metrics_and_tasks(): void
    {
        $tech = $this->createTechnician();

        $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'priority' => 'emergency',
            'due_at' => now()->addHours(2),
        ]);

        $this->createTicket([
            'status' => 'closed',
            'assigned_to' => $tech->id,
            'rating' => 5,
            'feedback_comment' => 'Sangat memuaskan.',
            'rated_at' => now(),
            'closed_at' => now(),
        ]);

        $request = Request::create('/dashboard', 'GET');
        $data = $this->dashboardService->getDashboardData($request, $tech);

        $this->assertArrayHasKey('stats', $data);
        $this->assertEquals(1, $data['stats']['in_progress']);
        $this->assertEquals(1, $data['stats']['closed_tickets']);
        $this->assertEquals(5.0, $data['stats']['avg_rating']);
        $this->assertEquals(1, $data['stats']['rating_count']);

        $this->assertNotEmpty($data['activeTasks']);
        $this->assertEquals('emergency', $data['activeTasks'][0]['priority']);

        $this->assertNotEmpty($data['recentFeedbacks']);
        $this->assertEquals('Sangat memuaskan.', $data['recentFeedbacks'][0]['feedback_comment']);
    }

    public function test_dashboard_service_aggregates_admin_kpis_and_distributions(): void
    {
        $admin = $this->createAdmin();

        $this->createTicket(['status' => 'pending_admin', 'network_type' => 'Fiber optic', 'priority' => 'high']);
        $this->createTicket(['status' => 'in_progress', 'network_type' => 'Perangkat/Akses', 'priority' => 'medium']);
        $this->createTicket(['status' => 'closed', 'network_type' => 'Converter', 'priority' => 'low']);

        $request = Request::create('/dashboard', 'GET', ['filter_type' => 'year_month', 'year' => date('Y'), 'month' => 'all']);
        $data = $this->dashboardService->getDashboardData($request, $admin);

        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('statusDistribution', $data);
        $this->assertArrayHasKey('infrastructureDistribution', $data);
        $this->assertArrayHasKey('networkTypeDistribution', $data);
        $this->assertArrayHasKey('priorityDistribution', $data);
        $this->assertArrayHasKey('monthlyReports', $data);
        $this->assertArrayHasKey('ticketTrend', $data);
    }
}
