<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_cannot_create_ticket(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/tickets', [
            'title' => 'Admin mencoba buat tiket',
            'location_details' => 'Lokasi',
            'description' => 'Deskripsi kendala.',
        ]);

        $response->assertStatus(403);
    }

    public function test_technician_cannot_create_ticket(): void
    {
        $tech = $this->createTechnician();

        $response = $this->actingAs($tech)->post('/tickets', [
            'title' => 'Teknisi mencoba buat tiket',
            'location_details' => 'Lokasi',
            'description' => 'Deskripsi kendala.',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_verify_and_assign_ticket_without_technical_inputs(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket([
            'status' => 'pending_admin',
            'category_id' => null,
            'infrastructure_type' => null,
        ]);
        $leadTech = $this->createTechnician();

        // Admin only inputs priority and technician_ids (no infrastructure_type or category_id)
        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/verify-assign", [
            'priority' => 'emergency',
            'technician_ids' => [$leadTech->id],
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNull($ticket->category_id);
        $this->assertNull($ticket->infrastructure_type);
        $this->assertEquals($leadTech->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->due_at);
        // Emergency SLA = 4 hours from assigned_at
        $this->assertEquals(
            $ticket->assigned_at->copy()->addHours(4)->toDateTimeString(),
            $ticket->due_at->toDateTimeString()
        );
    }

    public function test_admin_can_verify_and_assign_ticket_with_category_if_provided(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket(['status' => 'pending_admin']);
        $category = $this->createCategory(['network_type' => 'Perangkat/Akses']);
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

    public function test_assigned_technician_can_view_resolve_page(): void
    {
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $response = $this->actingAs($tech)->get("/tickets/{$ticket->id}/resolve");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Resolve')
            ->has('ticket')
            ->has('categoriesMap')
            ->has('availableDevices')
            ->has('availableMaterials')
        );
    }

    public function test_authorized_user_can_view_berita_acara_page(): void
    {
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'pending_approval',
            'assigned_to' => $tech->id,
            'action_taken' => 'Splicing FO dan penggantian dropcore',
        ]);

        $response = $this->actingAs($tech)->get("/tickets/{$ticket->id}/berita-acara");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/BeritaAcara')
            ->has('ticket')
        );

        $admin = $this->createAdmin();
        $adminResponse = $this->actingAs($admin)->get("/tickets/{$ticket->id}/berita-acara");
        $adminResponse->assertOk();
        $adminResponse->assertInertia(fn ($page) => $page
            ->component('Tickets/BeritaAcara')
            ->has('ticket')
        );
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

    public function test_technician_can_set_real_infrastructure_and_category_on_resolution(): void
    {
        $tech = $this->createTechnician();
        $initialDueAt = now()->addHours(24);
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'assigned_at' => now(),
            'due_at' => $initialDueAt,
            'category_id' => null,
            'infrastructure_type' => null,
            'priority' => 'medium',
        ]);
        $category = $this->createCategory(['network_type' => 'Fiber optic']);

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", [
            'resolution_note' => 'Kabel FO putus di tiang nomor 12 telah disambung menggunakan fusion splicer.',
            'infrastructure_type' => 'Fiber optic',
            'category_id' => $category->id,
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertEquals('Fiber optic', $ticket->infrastructure_type);
        $this->assertEquals($category->id, $ticket->category_id);
        // Target SLA (due_at) must remain locked as initially set by Admin Priority (not overridden by category sla)
        $this->assertEquals($initialDueAt->toDateTimeString(), $ticket->due_at->toDateTimeString());
    }

    public function test_technician_can_submit_full_structured_resolution_details(): void
    {
        Storage::fake('public');

        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'assigned_at' => now(),
        ]);
        $category = $this->createCategory(['network_type' => 'Fiber optic']);

        $proofFile = UploadedFile::fake()->image('bukti_perbaikan.jpg');

        $payload = [
            'affected_device' => 'Kabel Fiber Optic (Drop Core / Feeder)',
            'actual_repair_location' => 'Tiang FO No. 14 Depan Kantor',
            'infrastructure_type' => 'Fiber optic',
            'category_id' => $category->id,
            'inspection_result' => 'Core nomor 2 redaman tinggi -28 dBm akibat bending di tiang 14.',
            'root_cause' => 'Kabel terjepit dahan pohon dan tertarik tiang.',
            'action_taken' => 'Pemotongan dahan, penarikan ulang span 30m, dan splicing core FO.',
            'materials_used' => 'Protection sleeve 2 pcs, pigtail SC 1 pcs',
            'test_result' => 'Redaman normal kembali -18.5 dBm, link up stabil.',
            'test_parameters' => '-18.5 dBm (OTDR & OPM)',
            'notes' => 'Pekerjaan selesai bersama tim lapangan dan disaksikan perwakilan OPD.',
            'resolution_proofs' => [$proofFile],
        ];

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", $payload);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertEquals('Kabel Fiber Optic (Drop Core / Feeder)', $ticket->affected_device);
        $this->assertEquals('Tiang FO No. 14 Depan Kantor', $ticket->actual_repair_location);
        $this->assertEquals('Fiber optic', $ticket->infrastructure_type);
        $this->assertEquals($category->id, $ticket->category_id);
        $this->assertEquals('Core nomor 2 redaman tinggi -28 dBm akibat bending di tiang 14.', $ticket->inspection_result);
        $this->assertEquals('Kabel terjepit dahan pohon dan tertarik tiang.', $ticket->root_cause);
        $this->assertEquals('Pemotongan dahan, penarikan ulang span 30m, dan splicing core FO.', $ticket->action_taken);
        $this->assertEquals('Protection sleeve 2 pcs, pigtail SC 1 pcs', $ticket->materials_used);
        $this->assertEquals('Redaman normal kembali -18.5 dBm, link up stabil.', $ticket->test_result);
        $this->assertEquals('-18.5 dBm (OTDR & OPM)', $ticket->test_parameters);
        $this->assertEquals('Pekerjaan selesai bersama tim lapangan dan disaksikan perwakilan OPD.', $ticket->resolution_note);

        $this->assertCount(1, $ticket->resolutionProofs);
        $this->assertEquals('resolution_proof', $ticket->resolutionProofs->first()->attachment_type);
    }

    public function test_technician_can_submit_materials_with_dropdown_array_format(): void
    {
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $payload = [
            'affected_device' => 'Switch Access',
            'infrastructure_type' => 'Perangkat/Akses',
            'action_taken' => 'Penggantian konektor RJ45 dan penarikan kabel LAN baru.',
            'materials_used' => [
                ['material' => 'Konektor RJ-45 Cat6', 'quantity' => 2, 'unit' => 'pcs'],
                ['material' => 'Kabel UTP / LAN Cat6', 'quantity' => 15, 'unit' => 'meter'],
                ['material' => 'Lainnya', 'custom_material' => 'Klem Kabel No 8', 'quantity' => 1, 'unit' => 'pack'],
            ],
            'test_result' => 'Speed gigabit 1 Gbps up full duplex.',
        ];

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", $payload);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertEquals('Konektor RJ-45 Cat6 (2 pcs), Kabel UTP / LAN Cat6 (15 meter), Klem Kabel No 8 (1 pack)', $ticket->materials_used);
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
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'new_status' => 'closed',
            'comment' => 'Admin memverifikasi mutu hasil perbaikan dan menutup tiket secara resmi.',
        ]);
    }

    public function test_admin_can_approve_with_custom_admin_note(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket([
            'status' => 'pending_approval',
            'resolution_note' => 'Solusi perbaikan selesai.',
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/approve-resolution", [
            'admin_note' => 'Pekerjaan telah diverifikasi di lokasi dan koneksi Bappeda sudah normal kembali.',
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('closed', $ticket->status);
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'new_status' => 'closed',
            'comment' => 'Pekerjaan telah diverifikasi di lokasi dan koneksi Bappeda sudah normal kembali.',
        ]);
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

    public function test_assigned_technician_can_hold_ticket(): void
    {
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'due_at' => now()->addHours(6),
        ]);

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/hold", [
            'hold_reason_category' => 'vendor_isp',
            'hold_reason_note' => 'Menunggu perbaikan link backbone oleh Telkom (No Tiket: INC9999).',
        ]);

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('on_hold', $ticket->status);
        $this->assertEquals('vendor_isp', $ticket->hold_reason_category);
        $this->assertEquals('Menunggu perbaikan link backbone oleh Telkom (No Tiket: INC9999).', $ticket->hold_reason_note);
        $this->assertNotNull($ticket->hold_started_at);

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'changed_by' => $tech->id,
            'previous_status' => 'in_progress',
            'new_status' => 'on_hold',
        ]);
    }

    public function test_assigned_technician_can_resume_held_ticket_and_sla_is_extended(): void
    {
        $tech = $this->createTechnician();
        $initialDueAt = now()->addHours(4);
        $holdStartedAt = now()->subMinutes(120); // Held 2 hours ago

        $ticket = $this->createTicket([
            'status' => 'on_hold',
            'assigned_to' => $tech->id,
            'hold_reason_category' => 'material_procurement',
            'hold_reason_note' => 'Menunggu pengadaan SFP modul 10G.',
            'hold_started_at' => $holdStartedAt,
            'total_hold_duration_minutes' => 0,
            'due_at' => $initialDueAt,
        ]);

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/resume");

        $response->assertSessionHasNoErrors();
        $ticket->refresh();

        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNull($ticket->hold_started_at);
        $this->assertGreaterThanOrEqual(119, $ticket->total_hold_duration_minutes);
        // due_at should be extended by approx 120 minutes (4 hours + 2 hours = 6 hours from initial)
        $this->assertTrue($ticket->due_at->greaterThan($initialDueAt));

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'changed_by' => $tech->id,
            'previous_status' => 'on_hold',
            'new_status' => 'in_progress',
        ]);
    }

    public function test_opd_user_cannot_hold_or_resume_ticket(): void
    {
        $dept = $this->createDepartment();
        $opdUser = $this->createOpdUser($dept);
        $ticket = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/hold", [
            'hold_reason_category' => 'access_permit',
            'hold_reason_note' => 'Mencoba hold sendiri oleh OPD.',
        ]);
        $response->assertStatus(403);

        $ticketOnHold = $this->createTicket([
            'department_id' => $dept->id,
            'reporter_id' => $opdUser->id,
            'status' => 'on_hold',
        ]);

        $responseResume = $this->actingAs($opdUser)->post("/tickets/{$ticketOnHold->id}/resume");
        $responseResume->assertStatus(403);
    }

    public function test_admin_cannot_hold_or_resume_ticket(): void
    {
        $admin = $this->createAdmin();
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/hold", [
            'hold_reason_category' => 'need_escalation',
            'hold_reason_note' => 'Admin mencoba menunda langsung.',
        ]);
        $response->assertStatus(403);

        $ticketOnHold = $this->createTicket([
            'status' => 'on_hold',
            'assigned_to' => $tech->id,
        ]);

        $responseResume = $this->actingAs($admin)->post("/tickets/{$ticketOnHold->id}/resume");
        $responseResume->assertStatus(403);
    }

    public function test_unassigned_technician_cannot_hold_or_resume_ticket(): void
    {
        $techA = $this->createTechnician();
        $techB = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techA->id,
        ]);

        $response = $this->actingAs($techB)->post("/tickets/{$ticket->id}/hold", [
            'hold_reason_category' => 'vendor_isp',
            'hold_reason_note' => 'Teknisi lain mencoba menunda tiket.',
        ]);
        $response->assertStatus(403);

        $ticketOnHold = $this->createTicket([
            'status' => 'on_hold',
            'assigned_to' => $techA->id,
        ]);

        $responseResume = $this->actingAs($techB)->post("/tickets/{$ticketOnHold->id}/resume");
        $responseResume->assertStatus(403);
    }
}
