<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('ticket.{ticketId}', function ($user, int $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return false;
    }

    if (in_array($user->role, ['admin', 'technician'])) {
        return true;
    }

    if ($user->role === 'opd_user' && (int) $user->department_id === (int) $ticket->department_id) {
        return true;
    }

    return false;
});
