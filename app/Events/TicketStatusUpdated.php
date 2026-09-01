<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $ticketData;
    public ?array $newHistoryData = null;

    public function __construct(
        public Ticket $ticket,
        public ?TicketStatusHistory $newHistory = null
    ) {
        $ticket->load(['category', 'technicians:id,name,phone_number']);

        $this->ticketData = [
            'id' => $ticket->id,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'network_type' => $ticket->network_type,
            'category_id' => $ticket->category_id,
            'category' => $ticket->category ? [
                'id' => $ticket->category->id,
                'name' => $ticket->category->name,
                'network_type' => $ticket->category->network_type,
            ] : null,
            'assigned_to' => $ticket->assigned_to,
            'assigned_at' => $ticket->assigned_at?->toISOString(),
            'due_at' => $ticket->due_at?->toISOString(),
            'resolved_at' => $ticket->resolved_at?->toISOString(),
            'closed_at' => $ticket->closed_at?->toISOString(),
            'cancelled_at' => $ticket->cancelled_at?->toISOString(),
            'rating' => $ticket->rating,
            'feedback_comment' => $ticket->feedback_comment,
            'rated_at' => $ticket->rated_at?->toISOString(),
            'technicians' => $ticket->technicians->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'phone_number' => $t->phone_number,
            ])->values()->toArray(),
        ];

        if ($newHistory) {
            $newHistory->load('changer:id,name,role');
            $this->newHistoryData = [
                'id' => $newHistory->id,
                'previous_status' => $newHistory->previous_status,
                'new_status' => $newHistory->new_status,
                'comment' => $newHistory->comment,
                'created_at' => $newHistory->created_at->toISOString(),
                'changer' => $newHistory->changer ? [
                    'id' => $newHistory->changer->id,
                    'name' => $newHistory->changer->name,
                    'role' => $newHistory->changer->role,
                ] : null,
            ];
        }
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ticket.{$this->ticket->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket' => $this->ticketData,
            'new_history' => $this->newHistoryData,
        ];
    }
}
