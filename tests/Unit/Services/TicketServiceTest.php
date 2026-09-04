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

    public function test_ticket_service_creates_pending_admin_ticket_for_opd_user(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);

        $ticket = $this->ticketService->createTicket([
            'title' => 'Kabel Jaringan Putus',
            'location_details' => 'Ruang Rapat Lantai 2',
            'description' => 'Kabel LAN terlepas dan konektor pecah.',
        ], $opdUser);

        $this->assertEquals('pending_admin', $ticket->status);
        $this->assertEquals($dept->id, $ticket->department_id);
        $this->assertEquals($opdUser->id, $ticket->reporter_id);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->due_at);
        $this->assertNull($ticket->category_id);
        $this->assertNull($ticket->infrastructure_type);
        $this->assertNotNull($ticket->ticket_number);
    }
}
