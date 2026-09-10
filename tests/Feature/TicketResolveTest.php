<?php

namespace Tests\Feature;

use App\Jobs\SendTicketNotificationJob;
use App\Models\Material;
use App\Models\NetworkDevice;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketResolveTest extends TestCase
{
    public function test_guest_cannot_access_resolve_page(): void
    {
        $ticket = $this->createTicket(['status' => 'in_progress']);
        $response = $this->get("/tickets/{$ticket->id}/resolve");
        $response->assertRedirect('/login');
    }

    public function test_admin_cannot_access_resolve_page(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket(['status' => 'in_progress']);

        $response = $this->actingAs($admin)->get("/tickets/{$ticket->id}/resolve");
        $response->assertStatus(403);
    }

    public function test_opd_cannot_access_resolve_page(): void
    {
        $opd = $this->createOpdUser();
        $ticket = $this->createTicket(['status' => 'in_progress']);

        $response = $this->actingAs($opd)->get("/tickets/{$ticket->id}/resolve");
        $response->assertStatus(403);
    }

    public function test_unassigned_technician_cannot_access_resolve_page(): void
    {
        $techA = $this->createTechnician();
        $techB = $this->createTechnician();
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $techA->id,
        ]);

        $response = $this->actingAs($techB)->get("/tickets/{$ticket->id}/resolve");
        $response->assertStatus(403);
    }

    public function test_non_lead_team_technician_cannot_access_resolve_page(): void
    {
        $leadTech = $this->createTechnician();
        $teamTech = $this->createTechnician();

        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $leadTech->id,
        ]);
        $ticket->technicians()->attach($teamTech->id);

        $response = $this->actingAs($teamTech)->get("/tickets/{$ticket->id}/resolve");
        $response->assertStatus(403);
    }

    public function test_lead_technician_cannot_access_resolve_page_when_not_in_progress(): void
    {
        $tech = $this->createTechnician();

        // 1. pending_admin
        $ticketPending = $this->createTicket(['status' => 'pending_admin', 'assigned_to' => $tech->id]);
        $this->actingAs($tech)->get("/tickets/{$ticketPending->id}/resolve")->assertStatus(403);

        // 2. on_hold
        $ticketHold = $this->createTicket(['status' => 'on_hold', 'assigned_to' => $tech->id]);
        $this->actingAs($tech)->get("/tickets/{$ticketHold->id}/resolve")->assertStatus(403);

        // 3. pending_approval
        $ticketApproval = $this->createTicket(['status' => 'pending_approval', 'assigned_to' => $tech->id]);
        $this->actingAs($tech)->get("/tickets/{$ticketApproval->id}/resolve")->assertStatus(403);

        // 4. closed
        $ticketClosed = $this->createTicket(['status' => 'closed', 'assigned_to' => $tech->id]);
        $this->actingAs($tech)->get("/tickets/{$ticketClosed->id}/resolve")->assertStatus(403);
    }

    public function test_lead_technician_can_access_resolve_page_when_in_progress(): void
    {
        $tech = $this->createTechnician();
        $category = $this->createCategory(['infrastructure_type' => 'Fiber optic']);
        NetworkDevice::create(['name' => 'OTB FO 24 Core', 'type' => 'passive', 'status' => 'active']);
        Material::create(['name' => 'Fast Connector SC', 'category' => 'connector', 'default_unit' => 'pcs', 'status' => 'active']);

        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($tech)->get("/tickets/{$ticket->id}/resolve");
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Tickets/Resolve')
            ->has('ticket')
            ->has('categoriesMap')
            ->has('availableDevices')
            ->has('availableMaterials')
        );
    }

    public function test_lead_technician_can_submit_resolution_successfully(): void
    {
        Queue::fake();
        Storage::fake('public');

        $tech = $this->createTechnician();
        $category = $this->createCategory(['infrastructure_type' => 'Fiber optic']);
        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'category_id' => $category->id,
        ]);

        $proofPhoto = UploadedFile::fake()->image('bukti_splicing.jpg', 600, 400)->size(1200);

        $payload = [
            'affected_device' => 'Kabel Fiber Optic Drop Core',
            'actual_repair_location' => 'Tiang FO No. 12 Depan Kantor BKD',
            'infrastructure_type' => 'Fiber optic',
            'category_id' => $category->id,
            'inspection_result' => 'Redaman tinggi -32 dBm karena kabel terjepit.',
            'root_cause' => 'Bending pada tiang akibat tertimpa dahan pohon.',
            'action_taken' => 'Pemotongan dahan dan perapihan span kabel FO.',
            'materials_used' => 'Protection sleeve 1 pcs, Klem kabel 2 pcs',
            'test_result' => 'Redaman normal -19.2 dBm, link up stabil.',
            'test_parameters' => '-19.2 dBm',
            'notes' => 'Pekerjaan selesai disaksikan staf pelapor.',
            'resolution_proofs' => [$proofPhoto],
        ];

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/tickets/{$ticket->id}");

        $ticket->refresh();
        $this->assertEquals('pending_approval', $ticket->status);

        // Verify resolution row in ticket_resolutions table
        $this->assertDatabaseHas('ticket_resolutions', [
            'ticket_id' => $ticket->id,
            'category_id' => $category->id,
            'affected_device' => 'Kabel Fiber Optic Drop Core',
            'actual_repair_location' => 'Tiang FO No. 12 Depan Kantor BKD',
            'inspection_result' => 'Redaman tinggi -32 dBm karena kabel terjepit.',
            'root_cause' => 'Bending pada tiang akibat tertimpa dahan pohon.',
            'action_taken' => 'Pemotongan dahan dan perapihan span kabel FO.',
            'materials_used' => 'Protection sleeve 1 pcs, Klem kabel 2 pcs',
            'test_result' => 'Redaman normal -19.2 dBm, link up stabil.',
            'test_parameters' => '-19.2 dBm',
            'resolved_by' => $tech->id,
        ]);

        // Verify proof attachment in ticket_attachments table
        $this->assertDatabaseHas('ticket_attachments', [
            'ticket_id' => $ticket->id,
            'uploaded_by' => $tech->id,
            'attachment_type' => 'resolution_proof',
            'file_name' => 'bukti_splicing.jpg',
        ]);

        // Verify status history
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'changed_by' => $tech->id,
            'previous_status' => 'in_progress',
            'new_status' => 'pending_approval',
        ]);

        // Verify notification dispatched
        Queue::assertPushed(SendTicketNotificationJob::class);
    }

    public function test_lead_technician_submitting_resolution_updates_existing_resolution_on_revision(): void
    {
        Queue::fake();

        $tech = $this->createTechnician();
        $category = $this->createCategory();

        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
            'category_id' => $category->id,
        ]);

        // 1st resolution submit
        $payload1 = [
            'affected_device' => 'Router',
            'actual_repair_location' => 'Ruang Server',
            'action_taken' => 'Restart router',
            'notes' => 'Perbaikan awal',
        ];
        $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", $payload1);

        $this->assertEquals(1, $ticket->resolution()->count());
        $this->assertEquals('Restart router', $ticket->resolution->action_taken);

        // Admin requests revision (status becomes in_progress)
        $admin = $this->createAdmin();
        $this->actingAs($admin)->post("/tickets/{$ticket->id}/request-revision", [
            'comment' => 'Harap periksa juga port switch.',
        ]);
        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);

        // 2nd resolution submit (Revision by technician)
        $payload2 = [
            'affected_device' => 'Router & Switch',
            'actual_repair_location' => 'Ruang Server',
            'action_taken' => 'Restart router dan konfigurasi ulang VLAN switch.',
            'notes' => 'Perbaikan revisi telah selesai',
        ];
        $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", $payload2);

        $ticket->refresh();
        $this->assertEquals('pending_approval', $ticket->status);

        // Still exactly 1 resolution record (updated, not duplicate)
        $this->assertEquals(1, $ticket->resolution()->count());
        $this->assertEquals('Restart router dan konfigurasi ulang VLAN switch.', $ticket->resolution->action_taken);
    }
}
