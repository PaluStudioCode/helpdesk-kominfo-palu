<?php

namespace Tests\Feature;

use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    public function test_opd_can_access_dashboard(): void
    {
        $opdUser = $this->createOpdUser();

        $response = $this->actingAs($opdUser)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_technician_can_access_dashboard(): void
    {
        $tech = $this->createTechnician();

        $response = $this->actingAs($tech)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_admin_can_view_reports_page(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports');
        $response->assertStatus(200);
    }

    public function test_admin_can_export_reports_pdf(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports/export/pdf');
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_admin_can_export_reports_excel(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports/export/excel');
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_reports_and_exports(): void
    {
        // Guest Redirect
        $this->get('/admin/reports')->assertRedirect('/login');
        $this->get('/admin/reports/export/pdf')->assertRedirect('/login');
        $this->get('/admin/reports/export/excel')->assertRedirect('/login');

        $opd = $this->createOpdUser();
        $tech = $this->createTechnician();

        // OPD Forbidden
        $this->actingAs($opd)->get('/admin/reports')->assertStatus(403);
        $this->actingAs($opd)->get('/admin/reports/export/pdf')->assertStatus(403);
        $this->actingAs($opd)->get('/admin/reports/export/excel')->assertStatus(403);

        // Technician Forbidden
        $this->actingAs($tech)->get('/admin/reports')->assertStatus(403);
        $this->actingAs($tech)->get('/admin/reports/export/pdf')->assertStatus(403);
        $this->actingAs($tech)->get('/admin/reports/export/excel')->assertStatus(403);
    }

    public function test_admin_can_filter_reports(): void
    {
        $admin = $this->createAdmin();
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();

        $ticketA = $this->createTicket(['department_id' => $deptA->id, 'title' => 'Tiket Dept A']);
        $ticketB = $this->createTicket(['department_id' => $deptB->id, 'title' => 'Tiket Dept B']);

        // Filter by Department
        $response = $this->actingAs($admin)->get("/admin/reports?department_id={$deptA->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticketA->id)
        );

        // Filter by Status
        $responseStatus = $this->actingAs($admin)->get('/admin/reports?status=pending_admin');
        $responseStatus->assertStatus(200);
    }

    public function test_admin_can_export_pdf_and_excel_with_data(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment();
        $category = $this->createCategory(['infrastructure_type' => 'Fiber optic']);

        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'category_id' => $category->id,
            'status' => 'closed',
            'closed_at' => now(),
            'rating' => 5,
        ]);

        // Export PDF with data
        $responsePdf = $this->actingAs($admin)->get('/admin/reports/export/pdf');
        $responsePdf->assertStatus(200);
        $this->assertEquals('application/pdf', $responsePdf->headers->get('content-type'));

        // Export Excel with data
        $responseExcel = $this->actingAs($admin)->get('/admin/reports/export/excel');
        $responseExcel->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $responseExcel->headers->get('content-type'));
    }
}
