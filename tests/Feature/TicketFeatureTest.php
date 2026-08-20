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

    public function test_opd_user_can_create_ticket(): void
    {
        Storage::fake('public');

        $user = User::where('email', 'operator@dinkes.palukota.go.id')->first();
        $category = TicketCategory::where('network_type', 'lan')->first();

        // Create a fake JPG image
        $file = UploadedFile::fake()->image('error.jpg');

        $response = $this->actingAs($user)->post('/tickets', [
            'network_type' => 'lan',
            'category_id' => $category->id,
            'title' => 'LAN Ruang Rapat Rusak',
            'location_details' => 'Ruang Rapat Utama Lantai 2',
            'description' => 'Sudah 2 hari LAN mati total tidak bisa dipakai.',
            'priority' => 'high',
            'attachments' => [$file],
        ]);

        $response->assertRedirect('/tickets');
        $response->assertSessionHas('success');

        $ticket = Ticket::where('title', 'LAN Ruang Rapat Rusak')->first();
        
        $this->assertNotNull($ticket);
        $this->assertEquals($user->department_id, $ticket->department_id);
        $this->assertEquals('lan', $ticket->network_type);
        $this->assertEquals('open', $ticket->status);
        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);

        // Ensure attachment is saved
        $this->assertCount(1, $ticket->attachments);
        Storage::disk('public')->assertExists($ticket->attachments->first()->file_path);
    }

    public function test_admin_can_create_ticket_on_behalf(): void
    {
        $admin = User::where('role', 'admin')->first();
        $disdik = Department::where('code', 'DISDIK')->first();
        $category = TicketCategory::where('network_type', 'fiber_optic')->first();

        $response = $this->actingAs($admin)->post('/tickets', [
            'network_type' => 'fiber_optic',
            'category_id' => $category->id,
            'title' => 'FO Disdik Putus (Emergency)',
            'location_details' => 'Gedung Disdik',
            'description' => 'Dilaporkan via WA bahwa kabel putus tertimpa pohon.',
            'priority' => 'emergency',
            'department_id' => $disdik->id, // On behalf
        ]);

        $response->assertRedirect('/tickets');
        $response->assertSessionHas('success');

        $ticket = Ticket::where('title', 'FO Disdik Putus (Emergency)')->first();
        
        $this->assertNotNull($ticket);
        $this->assertEquals($disdik->id, $ticket->department_id); // Owned by DISDIK
        $this->assertEquals($admin->id, $ticket->reporter_id); // Reported by ADMIN
        $this->assertEquals('emergency', $ticket->priority);
    }

    public function test_ticket_number_generator_is_incremental(): void
    {
        $user = User::where('role', 'opd_user')->first();
        $category = TicketCategory::first();

        $payload = [
            'network_type' => $category->network_type,
            'category_id' => $category->id,
            'title' => 'Test Ticket',
            'location_details' => 'Test',
            'description' => 'Testing incremental generation',
            'priority' => 'low',
        ];

        $this->actingAs($user)->post('/tickets', $payload);
        $this->actingAs($user)->post('/tickets', $payload);

        $tickets = Ticket::latest('id')->take(2)->get();
        
        $this->assertStringEndsWith('0002', $tickets[0]->ticket_number);
        $this->assertStringEndsWith('0001', $tickets[1]->ticket_number);
    }

    public function test_category_must_match_network_type(): void
    {
        $user = User::where('role', 'opd_user')->first();
        $wifiCategory = TicketCategory::where('network_type', 'wifi')->first();

        // Deliberately mismatching network_type and category_id
        $response = $this->actingAs($user)->post('/tickets', [
            'network_type' => 'lan', // Sent as LAN
            'category_id' => $wifiCategory->id, // But using WIFI category
            'title' => 'Mismatch Test',
            'location_details' => 'Test',
            'description' => 'Test mismatch',
            'priority' => 'low',
        ]);

        $response->assertSessionHasErrors('category_id');
    }
}
