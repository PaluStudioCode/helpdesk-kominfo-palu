<?php

namespace Tests\Feature;

use App\Jobs\SendTicketNotificationJob;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutoCloseTicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $opdUser;
    protected User $technician;
    protected Department $department;
    protected TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => 'Dinas Perhubungan',
            'code' => 'DISHUB',
            'address' => 'Jl. Dishub No. 1, Kota Palu',
            'status' => 'active',
        ]);

        $this->category = TicketCategory::create([
            'name' => 'Kabel LAN Lepas',
            'network_type' => 'lan',
            'sla_hours' => 12,
            'status' => 'active',
        ]);

        $this->opdUser = User::create([
            'name' => 'User Dishub',
            'email' => 'dishub@palukota.go.id',
            'phone_number' => '081234567899',
            'role' => 'opd_user',
            'department_id' => $this->department->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->technician = User::create([
            'name' => 'Teknisi 2',
            'email' => 'teknisi2@kominfo.palukota.go.id',
            'phone_number' => '082233445566',
            'role' => 'technician',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_tickets_auto_close_closes_tickets_resolved_more_than_72_hours_ago(): void
    {
        Queue::fake();

        // 1. Ticket resolved 73 hours ago -> SHOULD be closed
        $oldResolvedTicket = Ticket::create([
            'ticket_number' => 'TKT-20260815-0001',
            'department_id' => $this->department->id,
            'reporter_id' => $this->opdUser->id,
            'assigned_to' => $this->technician->id,
            'category_id' => $this->category->id,
            'network_type' => 'lan',
            'title' => 'Kabel LAN Lepas di Dishub',
            'location_details' => 'Ruang Administrasi',
            'description' => 'LAN lepas dari switch.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now()->subHours(73),
            'resolution_note' => 'Kabel sudah dicolokkan dan dites normal.',
            'due_at' => now()->subHours(80),
        ]);

        // 2. Ticket resolved 24 hours ago -> SHOULD NOT be closed yet
        $recentResolvedTicket = Ticket::create([
            'ticket_number' => 'TKT-20260817-0002',
            'department_id' => $this->department->id,
            'reporter_id' => $this->opdUser->id,
            'assigned_to' => $this->technician->id,
            'category_id' => $this->category->id,
            'network_type' => 'lan',
            'title' => 'Kabel LAN Ruang Kadis',
            'location_details' => 'Ruang Kadis',
            'description' => 'Koneksi lambat.',
            'priority' => 'low',
            'status' => 'resolved',
            'resolved_at' => now()->subHours(24),
            'resolution_note' => 'Switch direstart.',
            'due_at' => now()->subHours(30),
        ]);

        // 3. Ticket currently open -> SHOULD NOT be closed
        $openTicket = Ticket::create([
            'ticket_number' => 'TKT-20260818-0003',
            'department_id' => $this->department->id,
            'reporter_id' => $this->opdUser->id,
            'category_id' => $this->category->id,
            'network_type' => 'lan',
            'title' => 'Tiket Masih Terbuka',
            'location_details' => 'Lantai 1',
            'description' => 'Masalah baru.',
            'priority' => 'high',
            'status' => 'open',
            'due_at' => now()->addHours(10),
        ]);

        // Run artisan command
        $this->artisan('tickets:auto-close')
            ->expectsOutputToContain('Total tiket ditutup: 1')
            ->assertExitCode(0);

        // Verify database state
        $oldResolvedTicket->refresh();
        $this->assertEquals('closed', $oldResolvedTicket->status);
        $this->assertNotNull($oldResolvedTicket->closed_at);

        $recentResolvedTicket->refresh();
        $this->assertEquals('resolved', $recentResolvedTicket->status);
        $this->assertNull($recentResolvedTicket->closed_at);

        $openTicket->refresh();
        $this->assertEquals('open', $openTicket->status);

        // Verify status history created
        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $oldResolvedTicket->id,
            'previous_status' => 'resolved',
            'new_status' => 'closed',
        ]);

        // Verify Activity log created
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ticket.auto_closed',
            'subject_id' => $oldResolvedTicket->id,
        ]);

        // Verify Notification dispatched
        Queue::assertPushed(SendTicketNotificationJob::class);
    }
}
