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
}
