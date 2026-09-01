<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_opd_user_can_create_ticket_with_simple_symptoms(): void
    {
        Storage::fake('public');

        $user = User::where('email', 'operator@dinkes.palukota.go.id')->first();
        $file = UploadedFile::fake()->image('error.jpg');

        $response = $this->actingAs($user)->post('/tickets', [
            'title' => 'LAN Ruang Rapat Rusak',
            'location_details' => 'Ruang Rapat Utama Lantai 2',
            'description' => 'Sudah 2 hari LAN mati total tidak bisa dipakai.',
            'attachments' => [$file],
        ]);

        $response->assertRedirect('/tickets');
        $response->assertSessionHas('success');

        $ticket = Ticket::where('title', 'LAN Ruang Rapat Rusak')->first();
        
        $this->assertNotNull($ticket);
        $this->assertEquals($user->department_id, $ticket->department_id);
        $this->assertEquals('pending_admin', $ticket->status);
        $this->assertNull($ticket->category_id);
        $this->assertNull($ticket->network_type);
        $this->assertNull($ticket->priority);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);

        // Ensure attachment is saved
        $this->assertCount(1, $ticket->attachments);
        Storage::disk('public')->assertExists($ticket->attachments->first()->file_path);
    }

    public function test_admin_can_create_ticket_on_behalf_with_multi_technicians(): void
    {
        $admin = User::where('role', 'admin')->first();
        $disdik = Department::where('code', 'DISDIK')->first();
        $category = TicketCategory::where('network_type', 'fiber_optic')->first();
        $technician = User::where('role', 'technician')->first();

        $response = $this->actingAs($admin)->post('/tickets', [
            'department_id' => $disdik->id,
            'network_type' => 'fiber_optic',
            'category_id' => $category->id,
            'title' => 'FO Disdik Putus (Emergency)',
            'location_details' => 'Gedung Disdik',
            'description' => 'Dilaporkan via WA bahwa kabel putus tertimpa pohon.',
            'priority' => 'emergency',
            'technician_ids' => [$technician->id],
        ]);

        $response->assertRedirect('/tickets');
        $response->assertSessionHas('success');

        $ticket = Ticket::where('title', 'FO Disdik Putus (Emergency)')->first();
        
        $this->assertNotNull($ticket);
        $this->assertEquals($disdik->id, $ticket->department_id);
        $this->assertEquals($admin->id, $ticket->reporter_id);
        $this->assertEquals('emergency', $ticket->priority);
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNotNull($ticket->due_at);
    }

    public function test_ticket_number_generator_is_incremental(): void
    {
        $user = User::where('role', 'opd_user')->first();

        $payload = [
            'title' => 'Test Ticket',
            'location_details' => 'Test',
            'description' => 'Testing incremental generation',
        ];

        $this->actingAs($user)->post('/tickets', $payload);
        $this->actingAs($user)->post('/tickets', $payload);

        $tickets = Ticket::latest('id')->take(2)->get();
        
        $this->assertStringEndsWith('0002', $tickets[0]->ticket_number);
        $this->assertStringEndsWith('0001', $tickets[1]->ticket_number);
    }
}
