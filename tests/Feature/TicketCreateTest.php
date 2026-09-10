<?php

namespace Tests\Feature;

use App\Jobs\SendTicketNotificationJob;
use App\Models\ActivityLog;
use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketCreateTest extends TestCase
{
    public function test_guest_cannot_access_create_ticket_page(): void
    {
        $response = $this->get('/tickets/create');
        $response->assertRedirect('/login');
    }

    public function test_guest_cannot_submit_ticket(): void
    {
        $response = $this->post('/tickets', [
            'title' => 'Laporan dari Guest',
            'location_details' => 'Ruang 101',
            'description' => 'Deskripsi kendala jaringan lengkap.',
        ]);
        $response->assertRedirect('/login');
    }

    public function test_admin_cannot_access_create_ticket_page(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->get('/tickets/create');
        $response->assertStatus(403);
    }

    public function test_admin_cannot_submit_ticket(): void
    {
        $admin = $this->createAdmin();
        $response = $this->actingAs($admin)->post('/tickets', [
            'title' => 'Laporan dari Admin',
            'location_details' => 'Ruang Admin',
            'description' => 'Deskripsi kendala jaringan lengkap lebih dari 10 karakter.',
        ]);
        $response->assertStatus(403);
    }

    public function test_technician_cannot_access_create_ticket_page(): void
    {
        $technician = $this->createTechnician();
        $response = $this->actingAs($technician)->get('/tickets/create');
        $response->assertStatus(403);
    }

    public function test_technician_cannot_submit_ticket(): void
    {
        $technician = $this->createTechnician();
        $response = $this->actingAs($technician)->post('/tickets', [
            'title' => 'Laporan dari Teknisi',
            'location_details' => 'Ruang Server',
            'description' => 'Deskripsi kendala jaringan lengkap lebih dari 10 karakter.',
        ]);
        $response->assertStatus(403);
    }

    public function test_opd_user_can_access_create_ticket_page(): void
    {
        $opd = $this->createOpdUser();
        $response = $this->actingAs($opd)->get('/tickets/create');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Create')
        );
    }

    public function test_opd_user_can_submit_ticket_successfully(): void
    {
        Queue::fake();

        $dept = $this->createDepartment(['name' => 'Dinas Pendidikan']);
        $opd = $this->createOpdUser($dept);

        $response = $this->actingAs($opd)->post('/tickets', [
            'title' => 'Internet Mati Total di Ruang Rapat',
            'location_details' => 'Gedung Utama Lantai 2',
            'description' => 'Koneksi internet mati total sejak pukul 08.00 pagi, tidak bisa browsing.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/tickets');

        $this->assertDatabaseHas('tickets', [
            'department_id' => $dept->id,
            'reporter_id' => $opd->id,
            'title' => 'Internet Mati Total di Ruang Rapat',
            'location_details' => 'Gedung Utama Lantai 2',
            'status' => 'pending_admin',
            'priority' => null,
            'assigned_to' => null,
        ]);

        $ticket = Ticket::where('title', 'Internet Mati Total di Ruang Rapat')->first();
        $this->assertNotNull($ticket);
        $this->assertStringStartsWith('TKT-' . date('Ymd') . '-', $ticket->ticket_number);

        // Verify status history
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'changed_by' => $opd->id,
            'previous_status' => null,
            'new_status' => 'pending_admin',
        ]);

        // Verify activity log
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $opd->id,
            'action' => 'ticket.created',
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
        ]);

        // Verify notification job dispatched
        Queue::assertPushed(SendTicketNotificationJob::class);
    }

    public function test_opd_user_can_submit_ticket_with_image_attachments(): void
    {
        Queue::fake();
        Storage::fake('public');

        $opd = $this->createOpdUser();
        $file1 = UploadedFile::fake()->image('bukti_router.jpg', 300, 300)->size(500);
        $file2 = UploadedFile::fake()->image('error_screen.png', 400, 400)->size(800);

        $response = $this->actingAs($opd)->post('/tickets', [
            'title' => 'Kabel Jaringan Lepas di Meja Kerja',
            'location_details' => 'Ruang Pelayanan Publik',
            'description' => 'Kabel LAN konektor terlepas dan lampu indikator switch mati.',
            'attachments' => [$file1, $file2],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/tickets');

        $ticket = Ticket::where('title', 'Kabel Jaringan Lepas di Meja Kerja')->first();
        $this->assertNotNull($ticket);

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $opd->id,
            'attachment_type' => 'issue_proof',
            'file_name' => 'bukti_router.jpg',
        ]);

        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $opd->id,
            'attachment_type' => 'issue_proof',
            'file_name' => 'error_screen.png',
        ]);

        $this->assertEquals(2, $ticket->attachments()->count());
    }

    public function test_validation_errors_for_invalid_input(): void
    {
        $opd = $this->createOpdUser();

        // 1. Missing required fields
        $response = $this->actingAs($opd)->post('/tickets', []);
        $response->assertSessionHasErrors(['title', 'location_details', 'description']);

        // 2. Title too short (< 5 chars)
        $responseTitle = $this->actingAs($opd)->post('/tickets', [
            'title' => 'Abc',
            'location_details' => 'Ruang 1',
            'description' => 'Deskripsi cukup panjang lebih dari sepuluh karakter.',
        ]);
        $responseTitle->assertSessionHasErrors('title');

        // 3. Description too short (< 10 chars)
        $responseDesc = $this->actingAs($opd)->post('/tickets', [
            'title' => 'Judul Valid Lebih 5 Karakter',
            'location_details' => 'Ruang 1',
            'description' => 'Pendek',
        ]);
        $responseDesc->assertSessionHasErrors('description');
    }
}
