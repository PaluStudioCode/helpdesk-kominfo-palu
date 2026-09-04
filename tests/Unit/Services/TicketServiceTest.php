<?php

namespace Tests\Unit\Services;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Services\TicketService;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    protected TicketService $ticketService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ticketService = new TicketService();
    }

    public function test_ticket_service_generates_sequential_ticket_numbers(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);

        $ticket1 = $this->ticketService->createTicket([
            'title' => 'Tiket Uji Urutan 1',
            'location_details' => 'Lokasi 1',
            'description' => 'Deskripsi 1',
        ], $opdUser);

        $ticket2 = $this->ticketService->createTicket([
            'title' => 'Tiket Uji Urutan 2',
            'location_details' => 'Lokasi 2',
            'description' => 'Deskripsi 2',
        ], $opdUser);

        $datePrefix = date('Ymd');
        $this->assertStringStartsWith("TKT-{$datePrefix}-", $ticket1->ticket_number);
        $this->assertStringStartsWith("TKT-{$datePrefix}-", $ticket2->ticket_number);

        $seq1 = (int) substr($ticket1->ticket_number, -4);
        $seq2 = (int) substr($ticket2->ticket_number, -4);
        $this->assertEquals($seq1 + 1, $seq2);
    }

    public function test_ticket_service_calculates_dynamic_sla_for_admin_on_behalf(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment();
        $category = $this->createCategory(['sla_hours' => 5, 'network_type' => 'Fiber optic']);
        $leadTech = $this->createTechnician();
        $teamTech = $this->createTechnician();

        $ticket = $this->ticketService->createTicket([
            'department_id' => $dept->id,
            'network_type' => 'Fiber optic',
            'category_id' => $category->id,
            'priority' => 'high',
            'technician_ids' => [$leadTech->id, $teamTech->id],
            'title' => 'Kabel FO Putus Utama',
            'location_details' => 'Tiang FO No. 12',
            'description' => 'Kabel FO terputus tertabrak truk.',
        ], $admin);

        $this->assertEquals('in_progress', $ticket->status);
        $this->assertEquals($leadTech->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->due_at);
        $this->assertNotNull($ticket->assigned_at);

        // Due time must be approximately 5 hours from assigned_at
        $diffHours = $ticket->assigned_at->diffInHours($ticket->due_at);
        $this->assertEquals(5, $diffHours);

        // Check team technicians sync
        $this->assertTrue($ticket->technicians->contains('id', $teamTech->id));
    }
}
