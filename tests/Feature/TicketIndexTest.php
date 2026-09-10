<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketIndexTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/tickets');
        $response->assertRedirect('/login');
    }

    public function test_admin_can_view_all_tickets(): void
    {
        $admin = $this->createAdmin();
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();

        $ticket1 = $this->createTicket(['department_id' => $deptA->id, 'title' => 'Gangguan Dept A']);
        $ticket2 = $this->createTicket(['department_id' => $deptB->id, 'title' => 'Gangguan Dept B']);

        $response = $this->actingAs($admin)->get('/tickets');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->where('tickets.data', fn ($data) => collect($data)->pluck('id')->contains($ticket1->id) && collect($data)->pluck('id')->contains($ticket2->id))
        );
    }

    public function test_technician_only_sees_assigned_and_team_tickets(): void
    {
        $techA = $this->createTechnician();
        $techB = $this->createTechnician();

        // Ticket 1: directly assigned to Tech A
        $ticket1 = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techA->id,
            'title' => 'Tugas Utama Tech A',
        ]);

        // Ticket 2: assigned to Tech B, but Tech A is in additional technicians team
        $ticket2 = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techB->id,
            'title' => 'Tugas Tim Tech A & B',
        ]);
        $ticket2->technicians()->attach($techA->id);

        // Ticket 3: only assigned to Tech B
        $ticket3 = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techB->id,
            'title' => 'Tugas Khusus Tech B',
        ]);

        $response = $this->actingAs($techA)->get('/tickets');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 2)
            ->where('tickets.data', fn ($data) => collect($data)->pluck('id')->contains($ticket1->id) && collect($data)->pluck('id')->contains($ticket2->id))
        );
    }

    public function test_opd_user_only_sees_tickets_from_their_department(): void
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();

        $opdUserA = $this->createOpdUser($deptA);

        $ticketA = $this->createTicket(['department_id' => $deptA->id, 'title' => 'Gangguan Kantor A']);
        $ticketB = $this->createTicket(['department_id' => $deptB->id, 'title' => 'Gangguan Kantor B']);

        $response = $this->actingAs($opdUserA)->get('/tickets');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticketA->id)
        );
    }

    public function test_search_filter_by_ticket_number_or_title(): void
    {
        $admin = $this->createAdmin();

        $ticket1 = $this->createTicket(['title' => 'Kabel FO Putus di Merdeka']);
        $ticket2 = $this->createTicket(['title' => 'Aplikasi e-Surat Lambat']);

        // Search by keyword "Merdeka"
        $response = $this->actingAs($admin)->get('/tickets?search=Merdeka');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticket1->id)
        );

        // Search by ticket number
        $responseNum = $this->actingAs($admin)->get('/tickets?search=' . $ticket2->ticket_number);
        $responseNum->assertStatus(200);
        $responseNum->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticket2->id)
        );
    }

    public function test_status_and_priority_filter(): void
    {
        $admin = $this->createAdmin();

        $ticketOpen = $this->createTicket(['status' => 'pending_admin', 'priority' => 'high']);
        $ticketProgress = $this->createTicket(['status' => 'in_progress', 'priority' => 'emergency']);

        // Filter status
        $responseStatus = $this->actingAs($admin)->get('/tickets?status=in_progress');
        $responseStatus->assertStatus(200);
        $responseStatus->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticketProgress->id)
        );

        // Filter priority
        $responsePriority = $this->actingAs($admin)->get('/tickets?priority=high');
        $responsePriority->assertStatus(200);
        $responsePriority->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticketOpen->id)
        );
    }

    public function test_infrastructure_type_filter(): void
    {
        $admin = $this->createAdmin();

        $catPower = $this->createCategory(['infrastructure_type' => 'Power/poe']);
        $catAP = $this->createCategory(['infrastructure_type' => 'Perangkat/Akses']);

        $ticketPower = $this->createTicket(['category_id' => $catPower->id]);
        $ticketAP = $this->createTicket(['category_id' => $catAP->id]);

        $response = $this->actingAs($admin)->get('/tickets?infrastructure_type=' . urlencode('Power/poe'));
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->has('tickets.data', 1)
            ->where('tickets.data.0.id', $ticketPower->id)
        );
    }

    public function test_sorting_safely(): void
    {
        $admin = $this->createAdmin();

        $searchKey = 'SortTest_' . uniqid();
        $ticket1 = $this->createTicket(['title' => $searchKey . ' A', 'created_at' => now()->subHours(2)]);
        $ticket2 = $this->createTicket(['title' => $searchKey . ' B', 'created_at' => now()]);

        // Ascending sort by created_at
        $response = $this->actingAs($admin)->get("/tickets?search={$searchKey}&sort=created_at&direction=asc");
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Index')
            ->where('tickets.data.0.id', $ticket1->id)
            ->where('tickets.data.1.id', $ticket2->id)
        );

        // Invalid sort column should fall back safely without error
        $responseInvalid = $this->actingAs($admin)->get('/tickets?sort=invalid_column&direction=desc');
        $responseInvalid->assertStatus(200);
    }
}
