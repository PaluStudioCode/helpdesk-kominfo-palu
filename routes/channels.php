<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel Publik Tiket: Pelapor OPD (instansi yang sama), Admin, dan Teknisi
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

// Channel Internal Tiket: KHUSUS Admin dan Teknisi (Isolasi Catatan Internal)
Broadcast::channel('ticket.{ticketId}.internal', function ($user, int $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return false;
    }

    return in_array($user->role, ['admin', 'technician']);
});
