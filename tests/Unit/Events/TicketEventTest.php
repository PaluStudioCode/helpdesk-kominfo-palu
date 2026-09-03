<?php

namespace Tests\Unit\Events;

use App\Events\TicketReplyCreated;
use App\Events\TicketStatusUpdated;
use App\Models\TicketReply;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class TicketEventTest extends TestCase
{
    public function test_public_reply_broadcasts_on_public_ticket_channel(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket(['status' => 'in_progress']);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => 'Pesan publik',
            'is_internal' => false,
        ]);

        $event = new TicketReplyCreated($reply, $ticket->id);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-ticket.{$ticket->id}", $channels[0]->name);
        $this->assertEquals('reply.created', $event->broadcastAs());
    }

    public function test_internal_note_broadcasts_on_internal_ticket_channel(): void
    {
        $tech = $this->createTechnician();
        $ticket = $this->createTicket(['status' => 'in_progress']);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $tech->id,
            'message' => 'Catatan teknis rahasia',
            'is_internal' => true,
        ]);

        $event = new TicketReplyCreated($reply, $ticket->id);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-ticket.{$ticket->id}.internal", $channels[0]->name);
        $this->assertEquals('reply.created', $event->broadcastAs());
    }

    public function test_status_updated_broadcasts_on_ticket_channel(): void
    {
        $ticket = $this->createTicket(['status' => 'in_progress']);

        $event = new TicketStatusUpdated($ticket);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("private-ticket.{$ticket->id}", $channels[0]->name);
        $this->assertEquals('status.updated', $event->broadcastAs());
    }
}
