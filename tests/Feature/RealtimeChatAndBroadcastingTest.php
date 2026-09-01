<?php

namespace Tests\Feature;

use App\Events\TicketReplyCreated;
use App\Events\TicketStatusUpdated;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeChatAndBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $technician;
    protected User $opdUserDinkes;
    protected User $opdUserDisdik;
    protected Department $dinkes;
    protected Department $disdik;
    protected TicketCategory $category;
    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dinkes = Department::create([
            'name' => 'Dinas Kesehatan',
            'code' => 'DINKES',
            'address' => 'Jl. Kesehatan No. 1',
            'status' => 'active',
        ]);

        $this->disdik = Department::create([
            'name' => 'Dinas Pendidikan',
            'code' => 'DISDIK',
            'address' => 'Jl. Pendidikan No. 1',
            'status' => 'active',
        ]);

        $this->category = TicketCategory::create([
            'name' => 'Kabel FO Putus',
            'network_type' => 'fiber_optic',
            'sla_hours' => 4,
            'status' => 'active',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Kominfo',
            'email' => 'admin@kominfo.palukota.go.id',
            'role' => 'admin',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->technician = User::create([
            'name' => 'Teknisi 1',
            'email' => 'teknisi1@kominfo.palukota.go.id',
            'role' => 'technician',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->opdUserDinkes = User::create([
            'name' => 'Operator Dinkes',
            'email' => 'dinkes@palukota.go.id',
            'role' => 'opd_user',
            'department_id' => $this->dinkes->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->opdUserDisdik = User::create([
            'name' => 'Operator Disdik',
            'email' => 'disdik@palukota.go.id',
            'role' => 'opd_user',
            'department_id' => $this->disdik->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->ticket = Ticket::create([
            'ticket_number' => 'TKT-20260901-0001',
            'department_id' => $this->dinkes->id,
            'reporter_id' => $this->opdUserDinkes->id,
            'title' => 'FO Down',
            'location_details' => 'Ruang Server',
            'description' => 'FO tidak konek.',
            'status' => 'pending_admin',
        ]);
    }

    public function test_public_reply_broadcasts_to_public_ticket_channel(): void
    {
        Event::fake([TicketReplyCreated::class]);

        $reply = TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->opdUserDinkes->id,
            'message' => 'Mohon bantuan perbaikan jaringan FO.',
            'is_internal' => false,
        ]);

        $event = new TicketReplyCreated($reply, $this->ticket->id);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals("private-ticket.{$this->ticket->id}", (string) $channels[0]);
    }

    public function test_internal_note_broadcasts_to_isolated_internal_channel(): void
    {
        $reply = TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->admin->id,
            'message' => 'Catatan rahasia teknisi: switch port 4 perlu diganti.',
            'is_internal' => true,
        ]);

        $event = new TicketReplyCreated($reply, $this->ticket->id);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals("private-ticket.{$this->ticket->id}.internal", (string) $channels[0]);
    }

    public function test_storing_reply_triggers_broadcast_event(): void
    {
        Event::fake([TicketReplyCreated::class]);

        $response = $this->actingAs($this->opdUserDinkes)->post(route('tickets.replies.store', $this->ticket->id), [
            'message' => 'Kabel FO kami masih belum menyala.',
            'is_internal' => false,
        ]);

        $response->assertRedirect();

        Event::assertDispatched(TicketReplyCreated::class, function ($event) {
            return $event->ticketId === $this->ticket->id
                && $event->reply['message'] === 'Kabel FO kami masih belum menyala.'
                && $event->reply['is_internal'] === false;
        });
    }

    public function test_verifying_ticket_triggers_realtime_status_updated_event(): void
    {
        Event::fake([TicketStatusUpdated::class]);

        $response = $this->actingAs($this->admin)->post(route('tickets.verify-assign', $this->ticket->id), [
            'network_type' => 'fiber_optic',
            'category_id' => $this->category->id,
            'priority' => 'high',
            'technician_ids' => [$this->technician->id],
        ]);

        $response->assertRedirect();

        Event::assertDispatched(TicketStatusUpdated::class, function ($event) {
            return $event->ticketData['id'] === $this->ticket->id
                && $event->ticketData['status'] === 'in_progress'
                && $event->newHistoryData['new_status'] === 'in_progress';
        });
    }

    public function test_unread_replies_count_is_calculated_and_persisted_across_page_views(): void
    {
        // 1. Initially 0 unread replies
        $response = $this->actingAs($this->opdUserDinkes)->get(route('tickets.show', $this->ticket->id));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('initialUnreadCount', 0));

        // 2. Admin sends a message
        TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->admin->id,
            'message' => 'Halo Dinkes, tim teknisi sedang menuju ke lokasi.',
            'is_internal' => false,
        ]);

        // 3. When OPD refreshes/opens the ticket detail page, initialUnreadCount should be 1
        $response = $this->actingAs($this->opdUserDinkes)->get(route('tickets.show', $this->ticket->id));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('initialUnreadCount', 1));

        // 4. OPD marks ticket as read
        $markReadResponse = $this->actingAs($this->opdUserDinkes)->post(route('tickets.mark-read', $this->ticket->id));
        $markReadResponse->assertOk();
        $markReadResponse->assertJson(['success' => true]);

        // 5. When OPD refreshes page again, initialUnreadCount should be 0
        $response = $this->actingAs($this->opdUserDinkes)->get(route('tickets.show', $this->ticket->id));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('initialUnreadCount', 0));
    }

    public function test_opd_does_not_count_internal_notes_as_unread(): void
    {
        // Admin creates an internal note
        TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->admin->id,
            'message' => 'Catatan internal rahasia teknisi.',
            'is_internal' => true,
        ]);

        // OPD should still see 0 unread replies
        $response = $this->actingAs($this->opdUserDinkes)->get(route('tickets.show', $this->ticket->id));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('initialUnreadCount', 0));

        // Technician should see 1 unread reply
        $responseTech = $this->actingAs($this->technician)->get(route('tickets.show', $this->ticket->id));
        $responseTech->assertOk();
        $responseTech->assertInertia(fn ($page) => $page->where('initialUnreadCount', 1));
    }
}
