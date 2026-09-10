<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BeritaAcaraTest extends TestCase
{
    public function test_guest_cannot_access_berita_acara(): void
    {
        $ticket = $this->createTicket();
        $response = $this->get("/tickets/{$ticket->id}/rincian-teknis");
        $response->assertRedirect('/login');
    }

    public function test_opd_cannot_access_berita_acara(): void
    {
        $dept = $this->createDepartment();
        $opd = $this->createOpdUser($dept);
        $ticket = $this->createTicket(['department_id' => $dept->id]);

        $response = $this->actingAs($opd)->get("/tickets/{$ticket->id}/rincian-teknis");
        $response->assertStatus(403);
    }

    public function test_unassigned_technician_cannot_access_berita_acara(): void
    {
        $techA = $this->createTechnician();
        $techB = $this->createTechnician();

        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techA->id,
        ]);

        $response = $this->actingAs($techB)->get("/tickets/{$ticket->id}/rincian-teknis");
        $response->assertStatus(404);
    }

    public function test_assigned_technician_can_access_berita_acara(): void
    {
        $tech = $this->createTechnician();
        $category = $this->createCategory();

        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($tech)->get("/tickets/{$ticket->id}/rincian-teknis");
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/BeritaAcara')
            ->has('ticket')
            ->where('ticket.id', $ticket->id)
        );
    }

    public function test_admin_can_access_berita_acara_of_any_ticket(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket(['status' => 'pending_admin']);

        $response = $this->actingAs($admin)->get("/tickets/{$ticket->id}/rincian-teknis");
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/BeritaAcara')
            ->has('ticket')
            ->where('ticket.id', $ticket->id)
        );
    }

    public function test_berita_acara_displays_structured_resolution_data(): void
    {
        $admin = $this->createAdmin();
        $tech = $this->createTechnician();
        $category = $this->createCategory(['name' => 'Kabel FO Putus', 'infrastructure_type' => 'Fiber optic']);

        $ticket = $this->createTicket([
            'status' => 'pending_approval',
            'assigned_to' => $tech->id,
            'category_id' => $category->id,
        ]);

        $ticket->resolution()->updateOrCreate(
            ['ticket_id' => $ticket->id],
            [
                'category_id' => $category->id,
                'affected_device' => 'Kabel Drop Core FO',
                'actual_repair_location' => 'Tiang FO No 10',
                'inspection_result' => 'Redaman tinggi -30 dBm',
                'root_cause' => 'Bending kabel',
                'action_taken' => 'Penarikan ulang kabel',
                'materials_used' => 'Protection sleeve (1 pcs)',
                'test_result' => 'Normal -18 dBm',
                'test_parameters' => '-18 dBm',
                'resolution_note' => 'Perbaikan tuntas',
                'resolved_by' => $tech->id,
                'resolved_at' => now(),
            ]
        );

        $response = $this->actingAs($admin)->get("/tickets/{$ticket->id}/rincian-teknis");
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/BeritaAcara')
            ->where('ticket.resolution.affected_device', 'Kabel Drop Core FO')
            ->where('ticket.resolution.actual_repair_location', 'Tiang FO No 10')
            ->where('ticket.resolution.test_result', 'Normal -18 dBm')
        );
    }
}
