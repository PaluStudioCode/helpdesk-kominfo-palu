<?php

namespace App\Events;

use App\Models\TicketReply;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketReplyCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $reply;

    public function __construct(
        public TicketReply $ticketReply,
        public int $ticketId,
    ) {
        $ticketReply->load(['user:id,name,role', 'attachments']);

        $this->reply = [
            'id' => $ticketReply->id,
            'ticket_id' => $ticketReply->ticket_id,
            'user_id' => $ticketReply->user_id,
            'message' => $ticketReply->message,
            'is_internal' => $ticketReply->is_internal,
            'created_at' => $ticketReply->created_at->toISOString(),
            'user' => [
                'id' => $ticketReply->user->id,
                'name' => $ticketReply->user->name,
                'role' => $ticketReply->user->role,
            ],
            'attachments' => $ticketReply->attachments->map(fn ($att) => [
                'id' => $att->id,
                'file_path' => $att->file_path,
                'file_name' => $att->file_name,
            ])->toArray(),
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ticket.{$this->ticketId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reply.created';
    }

    public function broadcastWith(): array
    {
        return [
            'reply' => $this->reply,
        ];
    }
}
