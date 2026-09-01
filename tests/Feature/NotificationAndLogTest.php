<?php

namespace Tests\Feature;

use App\Jobs\SendTicketNotificationJob;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WhatsappNotification;
use App\Models\ActivityLog;
use App\Services\FonnteService;
use App\Services\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationAndLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $technician;
    protected User $opdUser;
    protected Department $department;
    protected TicketCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'name' => 'Dinas Pendidikan',
            'code' => 'DISDIK',
            'address' => 'Jl. Pendidikan No. 1, Kota Palu',
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
            'phone_number' => '081122334455',
            'role' => 'admin',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->technician = User::create([
            'name' => 'Teknisi Jaringan 1',
            'email' => 'teknisi1@kominfo.palukota.go.id',
            'phone_number' => '085566778899',
            'role' => 'technician',
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);

        $this->opdUser = User::create([
            'name' => 'Pengelola Jaringan Disdik',
            'email' => 'disdik@palukota.go.id',
            'phone_number' => '081234567890',
            'role' => 'opd_user',
            'department_id' => $this->department->id,
            'status' => 'active',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_phone_normalizer_converts_indonesian_phone_numbers_correctly(): void
    {
        $this->assertEquals('6281234567890', PhoneNormalizer::normalize('081234567890'));
        $this->assertEquals('6281234567890', PhoneNormalizer::normalize('+6281234567890'));
        $this->assertEquals('6281234567890', PhoneNormalizer::normalize('6281234567890'));
        $this->assertEquals('6281234567890', PhoneNormalizer::normalize('0812-3456-7890'));
        $this->assertEquals('6281234567890', PhoneNormalizer::normalize('81234567890'));
        $this->assertNull(PhoneNormalizer::normalize(''));
        $this->assertNull(PhoneNormalizer::normalize(null));
    }

    public function test_ticket_creation_dispatches_notification_jobs_and_creates_activity_log(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->opdUser)->post(route('tickets.store'), [
            'title' => 'Kabel Utama Putus di Ruang Server',
            'location_details' => 'Gedung A Lantai 2 Ruang Server',
            'description' => 'Indikator FO di switch mati total sejak pagi ini.',
        ]);

        $response->assertRedirect(route('tickets.index'));

        // Check Notification Jobs queued for Admin & OPD
        Queue::assertPushed(SendTicketNotificationJob::class);

        // Check Activity Log
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->opdUser->id,
            'action' => 'ticket.created',
            'subject_type' => Ticket::class,
        ]);
    }

    public function test_ticket_assignment_dispatches_notification_and_activity_log(): void
    {
        Queue::fake();

        $ticket = Ticket::create([
            'ticket_number' => 'TKT-20260818-0001',
            'department_id' => $this->department->id,
            'reporter_id' => $this->opdUser->id,
            'title' => 'Kabel FO Putus',
            'location_details' => 'Ruang Server',
            'description' => 'Deskripsi gangguan kabel.',
            'status' => 'pending_admin',
        ]);

        $response = $this->actingAs($this->admin)->post(route('tickets.verify-assign', $ticket), [
            'network_type' => 'fiber_optic',
            'category_id' => $this->category->id,
            'priority' => 'high',
            'technician_ids' => [$this->technician->id],
        ]);
        $response->assertRedirect();

        Queue::assertPushed(SendTicketNotificationJob::class);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'action' => 'ticket.verified_assigned',
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
        ]);
    }

    public function test_fonnte_service_records_success_and_failure_without_throwing(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-20260818-0002',
            'department_id' => $this->department->id,
            'reporter_id' => $this->opdUser->id,
            'category_id' => $this->category->id,
            'network_type' => 'fiber_optic',
            'title' => 'WiFi Down',
            'location_details' => 'Ruang Rapat',
            'description' => 'Deskripsi masalah.',
            'priority' => 'medium',
            'status' => 'in_progress',
            'due_at' => now()->addHours(4),
        ]);

        // Fake successful Fonnte response
        Http::fakeSequence()
            ->push(['status' => true, 'target' => ['6281234567890']], 200)
            ->push(['status' => false, 'reason' => 'device disconnected'], 400);

        $fonnteService = new FonnteService();
        $notif = $fonnteService->sendMessage(
            ticket: $ticket,
            recipient: $this->opdUser,
            rawPhone: '081234567890',
            eventType: 'ticket_created',
            message: 'Halo tiket Anda terdaftar.'
        );

        $this->assertInstanceOf(WhatsappNotification::class, $notif);
        $this->assertEquals('success', $notif->status);
        $this->assertEquals('6281234567890', $notif->target_phone);
        $this->assertDatabaseHas('whatsapp_notifications', [
            'id' => $notif->id,
            'status' => 'success',
            'target_phone' => '6281234567890',
        ]);

        $failedNotif = $fonnteService->sendMessage(
            ticket: $ticket,
            recipient: $this->opdUser,
            rawPhone: '081234567890',
            eventType: 'status_in_progress',
            message: 'Pesan perbaikan'
        );

        $this->assertEquals('failed', $failedNotif->status);
        $this->assertDatabaseHas('whatsapp_notifications', [
            'id' => $failedNotif->id,
            'status' => 'failed',
        ]);
    }
}
