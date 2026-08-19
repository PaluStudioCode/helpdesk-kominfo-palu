<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function createMockTicket()
    {
        $dinkes = Department::where('code', 'DINKES')->first();
        $opdDinkes = User::where('role', 'opd_user')->where('department_id', $dinkes->id)->first();
        $category = TicketCategory::first();

        return Ticket::create([
            'ticket_number' => 'TKT-20260818-0001',
            'department_id' => $dinkes->id,
            'reporter_id' => $opdDinkes->id,
            'category_id' => $category->id,
            'network_type' => $category->network_type,
            'title' => 'Test Lifecycle',
            'location_details' => 'Room',
            'description' => 'Desc',
            'priority' => 'medium',
            'status' => 'open',
            'due_at' => now()->addHours(10),
        ]);
    }

    public function test_technician_can_assign_to_me_and_resolve()
    {
        $ticket = $this->createMockTicket();
        $technician = User::where('role', 'technician')->first();

        // 1. Assign to me
        $response = $this->actingAs($technician)->post("/tickets/{$ticket->id}/assign");
        $response->assertSessionHas('success');
        
        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertEquals($technician->id, $ticket->assigned_to);

        // 2. Resolve
        $response = $this->actingAs($technician)->post("/tickets/{$ticket->id}/status", [
            'status' => 'resolved',
            'resolution_note' => 'Perbaikan sudah selesai, kabel sudah disambung.',
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertEquals('Perbaikan sudah selesai, kabel sudah disambung.', $ticket->resolution_note);
    }

    public function test_opd_can_close_resolved_ticket()
    {
        $ticket = $this->createMockTicket();
        $technician = User::where('role', 'technician')->first();
        $opdUser = User::where('role', 'opd_user')->where('department_id', $ticket->department_id)->first();

        $ticket->update(['status' => 'resolved', 'assigned_to' => $technician->id, 'resolved_at' => now(), 'resolution_note' => 'Done']);

        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/status", [
            'status' => 'closed',
        ]);
        
        $response->assertSessionHas('success');
        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_reopen_ticket_retains_assignee()
    {
        $ticket = $this->createMockTicket();
        $technician = User::where('role', 'technician')->first();
        $opdUser = User::where('role', 'opd_user')->where('department_id', $ticket->department_id)->first();

        // Put in resolved state
        $ticket->update([
            'status' => 'resolved', 
            'assigned_to' => $technician->id, 
            'resolved_at' => now(), 
            'resolution_note' => 'Done'
        ]);

        // Reopen it
        $response = $this->actingAs($opdUser)->post("/tickets/{$ticket->id}/status", [
            'status' => 'in_progress', // Reopen sends in_progress
            'comment' => 'Masih mati kak',
        ]);
        
        $response->assertSessionHas('success');
        
        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        // Important Check: Retains assignment
        $this->assertEquals($technician->id, $ticket->assigned_to);
        // Resolved at is reset
        $this->assertNull($ticket->resolved_at);
    }

    public function test_internal_reply_isolation()
    {
        $ticket = $this->createMockTicket();
        $technician = User::where('role', 'technician')->first();
        $opdUser = User::where('role', 'opd_user')->where('department_id', $ticket->department_id)->first();

        $this->actingAs($technician)->post("/tickets/{$ticket->id}/replies", [
            'message' => 'Catatan ini hanya untuk tim internal.',
            'is_internal' => true,
        ]);

        // Tech sees the internal note
        $responseTech = $this->actingAs($technician)->get("/tickets/{$ticket->id}");
        $responseTech->assertSee('Catatan ini hanya untuk tim internal.');

        // OPD does NOT see the internal note
        $responseOpd = $this->actingAs($opdUser)->get("/tickets/{$ticket->id}");
        $responseOpd->assertDontSee('Catatan ini hanya untuk tim internal.');
    }
}
