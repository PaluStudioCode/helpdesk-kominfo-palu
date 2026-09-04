<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    public function test_opd_user_can_create_ticket_with_initial_pending_admin(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);

        $response = $this->actingAs($opdUser)->post('/tickets', [
            'title' => 'Gangguan Akses Internet Ruang Rapat',
            'location_details' => 'Gedung A Lantai 2',
            'description' => 'Koneksi internet terputus sejak pagi hari.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/tickets');

        $this->assertDatabaseHas('tickets', [
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'pending_admin',
            'title' => 'Gangguan Akses Internet Ruang Rapat',
        ]);
    }

    public function test_admin_can_create_ticket_on_behalf_immediately_in_progress(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment();
        $category = $this->createCategory(['sla_hours' => 6, 'network_type' => 'Perangkat/Akses']);
        $leadTech = $this->createTechnician();
        $secondTech = $this->createTechnician();

        $response = $this->actingAs($admin)->post('/tickets', [
            'department_id' => $dept->id,
            'network_type' => 'Perangkat/Akses',
            'category_id' => $category->id,
            'priority' => 'high',
            'technician_ids' => [$leadTech->id, $secondTech->id],
            'title' => 'Perbaikan Switch On Behalf',
            'location_details' => 'Ruang Server Diskominfo',
            'description' => 'Admin membuat tiket mewakili OPD.',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tickets', [
            'department_id' => $dept->id,
            'infrastructure_type' => 'Perangkat/Akses',
            'assigned_to' => $leadTech->id,
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $ticket = Ticket::where('title', 'Perbaikan Switch On Behalf')->first();
        $this->assertNotNull($ticket->due_at);
        $this->assertTrue($ticket->technicians()->where('users.id', $secondTech->id)->exists());
    }

    public function test_admin_can_verify_and_assign_ticket(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket(['status' => 'pending_admin']);
        $category = $this->createCategory(['sla_hours' => 4, 'network_type' => 'Perangkat/Akses']);
        $leadTech = $this->createTechnician();

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/verify-assign", [
            'infrastructure_type' => 'Perangkat/Akses',
            'category_id' => $category->id,
            'priority' => 'medium',
            'technician_ids' => [$leadTech->id],
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('in_progress', $ticket->status);
        $this->assertEquals('Perangkat/Akses', $ticket->infrastructure_type);
        $this->assertEquals($leadTech->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->due_at);
    }

    public function test_admin_can_reject_ticket(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket(['status' => 'pending_admin']);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/reject", [
            'reason' => 'Bukan wewenang infrastruktur jaringan Diskominfo.',
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('cancelled', $ticket->status);
        $this->assertNotNull($ticket->cancelled_at);
    }

    public function test_opd_can_resubmit_rejected_ticket_within_72_hours(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'cancelled',
            'cancelled_at' => now()->subHours(10),
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/resubmit", [
            'title' => 'Pengajuan Ulang: Router Port Rusak',
            'location_details' => 'Gedung C Lantai 1',
            'description' => 'Penjelasan tambahan telah diperjelas sesuai arahan admin.',
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('pending_admin', $ticket->status);
        $this->assertEquals('Pengajuan Ulang: Router Port Rusak', $ticket->title);
    }

    public function test_opd_cannot_resubmit_ticket_after_72_hours(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'cancelled',
            'cancelled_at' => now()->subHours(75), // > 72 hours
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/resubmit", [
            'title' => 'Pengajuan Terlambat',
            'location_details' => 'Lokasi',
            'description' => 'Deskripsi',
        ]);

        $response->assertStatus(403);
        $ticket->refresh();
        $this->assertEquals('cancelled', $ticket->status);
    }

    public function test_assigned_technician_can_submit_resolution(): void
    {
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", [
            'resolution_note' => 'Kabel LAN RJ-45 telah di-crimping ulang dan link speed kembali gigabit 1 Gbps.',
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertEquals('Kabel LAN RJ-45 telah di-crimping ulang dan link speed kembali gigabit 1 Gbps.', $ticket->resolution_note);
    }

    public function test_admin_can_approve_resolution_and_close_ticket(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket([
            'status' => 'pending_approval',
            'resolution_note' => 'Solusi perbaikan selesai.',
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/approve-resolution");

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_admin_can_request_revision(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket([
            'status' => 'pending_approval',
            'resolution_note' => 'Pekerjaan awal selesai.',
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/request-revision", [
            'comment' => 'Tolong rapikan kembali susunan kabel di patch panel sebelum disetujui.',
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('in_progress', $ticket->status);
    }

    public function test_opd_can_rate_closed_ticket_once(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'closed',
            'closed_at' => now(),
            'rating' => null,
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/rate", [
            'rating' => 5,
            'feedback_comment' => 'Pelayanan teknisi sangat cepat dan ramah.',
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals(5, $ticket->rating);
        $this->assertEquals('Pelayanan teknisi sangat cepat dan ramah.', $ticket->feedback_comment);
        $this->assertNotNull($ticket->rated_at);

        // Attempt second rating should be denied
        $secondResponse = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/rate", [
            'rating' => 4,
        ]);
        $secondResponse->assertStatus(403);
    }

    public function test_opd_user_can_cancel_their_own_pending_ticket(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'pending_admin',
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/cancel-by-reporter", [
            'reason' => 'Kendala jaringan telah teratasi sendiri oleh OPD.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $ticket->refresh();

        $this->assertEquals('cancelled', $ticket->status);
        $this->assertNotNull($ticket->cancelled_at);

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'changed_by' => $opdUser->id,
            'previous_status' => 'pending_admin',
            'new_status' => 'cancelled',
            'comment' => 'Dibatalkan oleh Pelapor: Kendala jaringan telah teratasi sendiri oleh OPD.',
        ]);
    }

    public function test_opd_user_cannot_cancel_ticket_in_progress_or_closed(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);

        $ticketInProgress = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticketInProgress->id}/cancel-by-reporter", [
            'reason' => 'Mencoba membatalkan tiket yang sedang dikerjakan.',
        ]);
        $response->assertStatus(403);

        $ticketClosed = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'closed',
        ]);

        $response2 = $this->actingAs($opdUser)->post("/tickets/{$ticketClosed->id}/cancel-by-reporter", [
            'reason' => 'Mencoba membatalkan tiket yang sudah selesai.',
        ]);
        $response2->assertStatus(403);
    }

    public function test_opd_user_cannot_cancel_another_department_ticket(): void
    {
        $deptA = $this->createDepartment();
        $deptB = $this->createDepartment();

        $opdUserA = $this->createOpdUser($deptA);
        $ticketOfB = $this->createTicket([
            'department_id' => $deptB->id,
            'status' => 'pending_admin',
        ]);

        $response = $this->actingAs($opdUserA)->post("/tickets/{$ticketOfB->id}/cancel-by-reporter", [
            'reason' => 'Mencoba membatalkan tiket milik dinas lain.',
        ]);
        $response->assertStatus(403);
    }

    public function test_cancel_by_reporter_validates_reason_length(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'pending_admin',
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/cancel-by-reporter", [
            'reason' => 'abc', // < 5 characters
        ]);
        $response->assertSessionHasErrors(['reason']);
    }
}
