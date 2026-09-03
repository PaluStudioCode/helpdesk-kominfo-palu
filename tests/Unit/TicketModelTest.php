<?php

namespace Tests\Unit;

use App\Models\Ticket;
use Tests\TestCase;

class TicketModelTest extends TestCase
{
    public function test_ticket_status_helper_methods(): void
    {
        $ticket = new Ticket(['status' => 'pending_admin']);
        $this->assertTrue($ticket->isPendingAdmin());
        $this->assertFalse($ticket->isInProgress());
        $this->assertFalse($ticket->isClosed());
        $this->assertFalse($ticket->isCancelled());

        $ticket->status = 'in_progress';
        $this->assertTrue($ticket->isInProgress());

        $ticket->status = 'pending_approval';
        $this->assertTrue($ticket->isPendingApproval());

        $ticket->status = 'closed';
        $this->assertTrue($ticket->isClosed());

        $ticket->status = 'cancelled';
        $this->assertTrue($ticket->isCancelled());
    }

    public function test_ticket_can_be_resubmitted_calculation(): void
    {
        $ticket = new Ticket([
            'status' => 'cancelled',
            'cancelled_at' => now()->subHours(10),
        ]);
        $this->assertTrue($ticket->canBeResubmitted());

        $ticket->cancelled_at = now()->subHours(73);
        $this->assertFalse($ticket->canBeResubmitted());

        $ticket->status = 'closed';
        $this->assertFalse($ticket->canBeResubmitted());
    }
}
