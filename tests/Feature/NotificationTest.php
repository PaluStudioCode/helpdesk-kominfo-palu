<?php

namespace Tests\Feature;

use App\Jobs\SendTicketNotificationJob;
use App\Mail\TicketNotificationMail;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Models\WhatsappNotification;
use App\Services\FonnteService;
use App\Services\NotificationDispatcher;
use App\Services\PhoneNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_phone_normalizer_converts_indonesian_numbers(): void
    {
        $this->assertEquals('628123456789', PhoneNormalizer::normalize('08123456789'));
        $this->assertEquals('628123456789', PhoneNormalizer::normalize('+628123456789'));
        $this->assertEquals('628123456789', PhoneNormalizer::normalize('628123456789'));
        $this->assertEquals('628123456789', PhoneNormalizer::normalize('0812-3456-789'));
        $this->assertEquals('628123456789', PhoneNormalizer::normalize(' 0812 3456 789 '));
    }

    public function test_ticket_creation_dispatches_notification_jobs(): void
    {
        Queue::fake();

        $admin = $this->createAdmin(['phone_number' => '081111111111']);
        $opd = $this->createOpdUser(null, ['phone_number' => '082222222222']);
        $category = $this->createCategory();

        $response = $this->actingAs($opd)->post('/tickets', [
            'category_id' => $category->id,
            'title' => 'Koneksi Gangguan',
            'description' => 'Deskripsi kendala jaringan lengkap lebih dari 20 karakter.',
            'location_details' => 'Gedung A Ruang 101',
            'reporter_name' => $opd->name,
            'reporter_phone' => '082222222222',
        ]);

        $response->assertSessionHasNoErrors();

        // Should dispatch job for reporter + admin
        Queue::assertPushed(SendTicketNotificationJob::class, function ($job) {
            return $job->eventType === 'ticket_created' || $job->eventType === 'ticket_created_admin';
        });
    }

    public function test_ticket_assign_dispatches_notification_jobs(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $opd = $this->createOpdUser(null, ['phone_number' => '082222222222']);
        $tech = $this->createTechnician(['phone_number' => '083333333333']);
        $category = $this->createCategory();
        $ticket = $this->createTicket([
            'reporter_id' => $opd->id,
            'department_id' => $opd->department_id,
            'category_id' => $category->id,
            'status' => 'pending_admin',
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/verify-assign", [
            'network_type' => 'fiber_optic',
            'category_id' => $category->id,
            'priority' => 'high',
            'technician_ids' => [$tech->id],
        ]);

        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendTicketNotificationJob::class, function ($job) {
            return $job->eventType === 'ticket_assigned' || $job->eventType === 'status_in_progress';
        });
    }

    public function test_ticket_rejection_dispatches_notification_job(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $opd = $this->createOpdUser(null, ['phone_number' => '082222222222']);
        $ticket = $this->createTicket([
            'reporter_id' => $opd->id,
            'department_id' => $opd->department_id,
            'status' => 'pending_admin',
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/reject", [
            'reason' => 'Bukan wewenang jaringan Kominfo Palu.',
        ]);

        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendTicketNotificationJob::class, function ($job) {
            return $job->eventType === 'ticket_rejected';
        });
    }

    public function test_ticket_close_dispatches_notification_job(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $opd = $this->createOpdUser(null, ['phone_number' => '082222222222']);
        $tech = $this->createTechnician();
        $ticket = $this->createTicket([
            'reporter_id' => $opd->id,
            'department_id' => $opd->department_id,
            'assigned_to' => $tech->id,
            'status' => 'pending_approval',
            'resolution_note' => 'Perbaikan FO selesai.',
        ]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/approve-resolution");

        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendTicketNotificationJob::class, function ($job) {
            return $job->eventType === 'status_closed';
        });
    }

    public function test_ticket_resubmit_dispatches_notification_job(): void
    {
        Queue::fake();

        $admin = $this->createAdmin(['phone_number' => '081111111111']);
        $opd = $this->createOpdUser(null, ['phone_number' => '082222222222']);
        $ticket = $this->createTicket([
            'reporter_id' => $opd->id,
            'department_id' => $opd->department_id,
            'status' => 'cancelled',
            'cancelled_at' => now()->subHours(5),
        ]);

        $response = $this->actingAs($opd)->post("/tickets/{$ticket->id}/resubmit", [
            'title' => $ticket->title,
            'description' => 'Deskripsi perbaikan lebih dari 20 karakter yang telah disempurnakan.',
            'location_details' => $ticket->location_details,
        ]);

        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendTicketNotificationJob::class, function ($job) {
            return $job->eventType === 'ticket_resubmitted';
        });
    }

    public function test_ticket_submit_resolution_dispatches_notification_job(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $opd = $this->createOpdUser();
        $tech = $this->createTechnician();
        $category = $this->createCategory();
        $ticket = $this->createTicket([
            'reporter_id' => $opd->id,
            'department_id' => $opd->department_id,
            'assigned_to' => $tech->id,
            'status' => 'in_progress',
        ]);
        $ticket->technicians()->sync([$tech->id]);

        $response = $this->actingAs($tech)->post("/tickets/{$ticket->id}/submit-resolution", [
            'actual_category_id' => $category->id,
            'resolution_note' => 'Perbaikan core FO selesai dilakukan.',
        ]);

        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendTicketNotificationJob::class, function ($job) {
            return $job->eventType === 'pending_approval';
        });
    }

    public function test_ticket_request_revision_dispatches_notification_job(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $opd = $this->createOpdUser();
        $tech = $this->createTechnician(['phone_number' => '083333333333']);
        $ticket = $this->createTicket([
            'reporter_id' => $opd->id,
            'department_id' => $opd->department_id,
            'assigned_to' => $tech->id,
            'status' => 'pending_approval',
        ]);
        $ticket->technicians()->sync([$tech->id]);

        $response = $this->actingAs($admin)->post("/tickets/{$ticket->id}/request-revision", [
            'comment' => 'Harap rapikan kabel patch cord di rack server.',
        ]);

        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendTicketNotificationJob::class, function ($job) {
            return $job->eventType === 'ticket_revision';
        });
    }

    public function test_fonnte_service_handles_api_failure_gracefully(): void
    {
        Http::fake([
            'https://api.fonnte.com/*' => Http::response(['status' => false, 'reason' => 'Device disconnected'], 500),
        ]);

        $user = $this->createOpdUser(null, ['phone_number' => '08123456789']);
        $ticket = $this->createTicket(['reporter_id' => $user->id, 'department_id' => $user->department_id]);

        $fonnteService = new FonnteService();
        $log = $fonnteService->sendMessage(
            ticket: $ticket,
            recipient: $user,
            rawPhone: '08123456789',
            eventType: 'ticket_created',
            message: 'Pesan test'
        );

        $this->assertEquals('failed', $log->status);
        $this->assertDatabaseHas('whatsapp_notifications', [
            'id' => $log->id,
            'status' => 'failed',
        ]);
    }

    public function test_send_ticket_notification_job_executes_wa_and_email(): void
    {
        Mail::fake();
        Http::fake([
            'https://api.fonnte.com/*' => Http::response(['status' => true, 'target' => ['628123456789']], 200),
        ]);

        $user = $this->createOpdUser(null, ['email' => 'user_test@palukota.go.id', 'phone_number' => '08123456789']);
        $ticket = $this->createTicket(['reporter_id' => $user->id, 'department_id' => $user->department_id]);

        $job = new SendTicketNotificationJob(
            ticket: $ticket,
            recipient: $user,
            eventType: 'ticket_created',
            targetPhone: '08123456789',
            waMessage: 'Pesan Uji WhatsApp',
            emailSubject: 'Subjek Uji Email',
            emailHeadline: 'Headline Uji',
            emailCustomMessage: 'Pesan Custom Uji'
        );

        $job->handle(new FonnteService());

        // Assert Email sent
        Mail::assertSent(TicketNotificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo('user_test@palukota.go.id');
        });

        // Assert WhatsApp logged in database
        $this->assertDatabaseHas('whatsapp_notifications', [
            'ticket_id' => $ticket->id,
            'recipient_id' => $user->id,
            'event_type' => 'ticket_created',
            'target_phone' => '628123456789',
            'status' => 'success',
        ]);
    }

    public function test_ticket_notification_mail_renders_html_properly(): void
    {
        $user = $this->createOpdUser();
        $ticket = $this->createTicket(['reporter_id' => $user->id, 'department_id' => $user->department_id]);

        $mailable = new TicketNotificationMail(
            ticket: $ticket,
            recipient: $user,
            eventType: 'ticket_created',
            emailSubject: 'Testing Subject',
            headline: 'Testing Headline',
            customMessage: 'Testing Message'
        );

        $rendered = $mailable->render();

        $this->assertStringContainsString($ticket->ticket_number, $rendered);
        $this->assertStringContainsString('Testing Headline', $rendered);
        $this->assertStringContainsString('Layanan Helpdesk Jaringan', $rendered);
    }
}
