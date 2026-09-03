<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel Publik Tiket: Pelapor OPD (instansi yang sama), Admin, dan Tim Teknisi Ditugaskan
Broadcast::channel('ticket.{ticketId}', function ($user, int $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return false;
    }

    if ($user->role === 'admin') {
        return true;
    }

    if ($user->role === 'technician') {
        return $ticket->assigned_to === $user->id 
            || $ticket->technicians()->where('user_id', $user->id)->exists();
    }

    if ($user->role === 'opd_user' && (int) $user->department_id === (int) $ticket->department_id) {
        return true;
    }

    return false;
});

// Channel Internal Tiket: KHUSUS Admin dan Tim Teknisi Ditugaskan (Isolasi Catatan Internal)
Broadcast::channel('ticket.{ticketId}.internal', function ($user, int $ticketId) {
    $ticket = Ticket::find($ticketId);

    if (!$ticket) {
        return false;
    }

    if ($user->role === 'admin') {
        return true;
    }

    if ($user->role === 'technician') {
        return $ticket->assigned_to === $user->id 
            || $ticket->technicians()->where('user_id', $user->id)->exists();
    }

    return false;
});
