<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutoCloseTicketTest extends TestCase
{
    public function test_auto_close_closes_tickets_pending_approval_older_than_72_hours_with_resolution(): void
    {
        Queue::fake();

        $tech = $this->createTechnician();
        $category = $this->createCategory();

        $ticket = $this->createTicket([
            'status' => 'pending_approval',
            'assigned_to' => $tech->id,
            'category_id' => $category->id,
        ]);

        $pastTime = now()->subHours(73);

        $resolution = $ticket->resolution;
        $resolution->timestamps = false;
        $resolution->created_at = $pastTime;
        $resolution->save();

        $this->artisan('tickets:auto-close')
            ->expectsOutputToContain('Total tiket ditutup: 1')
            ->assertSuccessful();

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status);

        $this->assertDatabaseHas('ticket_status_histories', [
            'ticket_id' => $ticket->id,
            'previous_status' => 'pending_approval',
            'new_status' => 'closed',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'ticket.auto_closed',
            'subject_type' => Ticket::class,
            'subject_id' => $ticket->id,
        ]);
    }

    public function test_auto_close_does_not_close_tickets_pending_approval_younger_than_72_hours(): void
    {
        Queue::fake();

        $tech = $this->createTechnician();
        $category = $this->createCategory();

        $ticket = $this->createTicket([
            'status' => 'pending_approval',
            'assigned_to' => $tech->id,
            'category_id' => $category->id,
        ]);

        $resolution = $ticket->resolution;
        $resolution->timestamps = false;
        $resolution->created_at = now()->subHours(24);
        $resolution->save();

        $this->artisan('tickets:auto-close')
            ->expectsOutputToContain('Total tiket ditutup: 0')
            ->assertSuccessful();

        $ticket->refresh();
        $this->assertEquals('pending_approval', $ticket->status);
    }

    public function test_auto_close_ignores_tickets_with_other_statuses_even_if_old(): void
    {
        Queue::fake();

        $tech = $this->createTechnician();

        $ticket = $this->createTicket([
            'status' => 'in_progress',
            'assigned_to' => $tech->id,
        ]);

        $ticket->timestamps = false;
        $ticket->updated_at = now()->subHours(100);
        $ticket->save();

        $this->artisan('tickets:auto-close')
            ->expectsOutputToContain('Total tiket ditutup: 0')
            ->assertSuccessful();

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
    }

    public function test_auto_close_fallback_to_updated_at_when_no_resolution_exists(): void
    {
        Queue::fake();

        $ticket = $this->createTicket([
            'status' => 'pending_approval',
        ]);

        $ticket->timestamps = false;
        $ticket->updated_at = now()->subHours(80);
        $ticket->save();

        $this->artisan('tickets:auto-close')
            ->expectsOutputToContain('Total tiket ditutup: 1')
            ->assertSuccessful();

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status);
    }
}
