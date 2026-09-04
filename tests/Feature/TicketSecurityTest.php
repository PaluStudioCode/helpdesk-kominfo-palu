<?php

namespace Tests\Feature;

use Tests\TestCase;

class TicketSecurityTest extends TestCase
{
    public function test_opd_cannot_view_ticket_of_another_department(): void
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();

        $opdUserA = $this->createOpdUser($deptA);
        $ticketOfB = $this->createTicket(['department_id' => $deptB->id]);

        $response = $this->actingAs($opdUserA)->get("/tickets/{$ticketOfB->id}");
        $response->assertStatus(404);
    }

    public function test_technician_cannot_view_ticket_not_assigned_to_them(): void
    {
        $techA = $this->createTechnician();
        $techB = $this->createTechnician();

        $ticketOfA = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techA->id,
        ]);

        $response = $this->actingAs($techB)->get("/tickets/{$ticketOfA->id}");
        $response->assertStatus(404);
    }

    public function test_unassigned_technician_cannot_submit_resolution(): void
    {
        $techA = $this->createTechnician();
        $techB = $this->createTechnician();

        $ticketOfA = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techA->id,
        ]);

        $response = $this->actingAs($techB)->post("/tickets/{$ticketOfA->id}/submit-resolution", [
            'resolution_note' => 'Percobaan submit ilegal oleh teknisi lain.',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_cannot_submit_resolution(): void
    {
        $admin = $this->createAdmin();
        $tech = $this->createTechnician();

        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/submit-resolution", [
            'resolution_note' => 'Percobaan submit resolusi oleh admin.',
        ]);

        $response->assertStatus(403);
    }

    public function test_opd_cannot_submit_resolution(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $tech = $this->createTechnician();

        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/submit-resolution", [
            'resolution_note' => 'Percobaan submit resolusi oleh OPD.',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_ticket(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket(['status' => 'pending_admin']);

        $response = $this->actingAs($admin)->get("/tickets/{$ticket->id}");
        $response->assertStatus(200);
    }
}
