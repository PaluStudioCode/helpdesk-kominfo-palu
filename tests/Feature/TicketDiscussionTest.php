<?php

namespace Tests\Feature;

use Tests\TestCase;

class TicketDiscussionTest extends TestCase
{
    public function test_opd_can_post_public_reply_on_their_ticket(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/replies", [
            'message' => 'Terima kasih, tim kami menunggu di lobi.',
            'is_internal' => false,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $opdUser->id,
            'message' => 'Terima kasih, tim kami menunggu di lobi.',
            'is_internal' => 0,
        ]);
    }

    public function test_assigned_technician_can_post_internal_note(): void
    {
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/replies", [
            'message' => 'Catatan teknis: Port 3 switch terbakar.',
            'is_internal' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $tech->id,
            'message' => 'Catatan teknis: Port 3 switch terbakar.',
            'is_internal' => 1,
        ]);
    }

    public function test_opd_cannot_post_internal_note(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/replies", [
            'message' => 'Mencoba kirim internal note ilegal.',
            'is_internal' => true,
        ]);

        $response->assertStatus(403);
    }

    public function test_unassigned_technician_cannot_post_reply(): void
    {
        $techA = $this->createTechnician();
        $techB = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techA->id,
        ]);

        $response = $this->actingAs($techB)->post("/tickets/{$ticket->id}/replies", [
            'message' => 'Komentar tidak sah oleh teknisi luar tim.',
            'is_internal' => false,
        ]);

        $response->assertStatus(403);
    }

    public function test_cannot_post_reply_on_closed_ticket(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/replies", [
            'message' => 'Komentar pada tiket yang sudah selesai.',
            'is_internal' => false,
        ]);

        $response->assertStatus(403);
    }
}
