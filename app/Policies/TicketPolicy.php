<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): Response
    {
        if ($user->role === 'admin' || $user->role === 'technician') {
            return Response::allow();
        }

        return $user->department_id === $ticket->department_id
            ? Response::allow()
            : Response::denyAsNotFound('Anda tidak memiliki hak akses untuk melihat atau mengelola tiket ini.');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'opd_user']);
    }

    /**
     * Determine whether the user can assign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        if ($ticket->status !== 'open') {
            return false;
        }
        
        return in_array($user->role, ['admin', 'technician']);
    }

    /**
     * Determine whether the user can update progress.
     */
    public function updateProgress(User $user, Ticket $ticket): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'technician' && $ticket->assigned_to === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can resolve the ticket.
     */
    public function resolve(User $user, Ticket $ticket): bool
    {
        if ($ticket->status !== 'in_progress') {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'technician' && $ticket->assigned_to === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        if ($ticket->status !== 'resolved') {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'opd_user' && $ticket->department_id === $user->department_id) {
            return true;
        }

        return false;
    }
    
    /**
     * Determine whether the user can reopen the ticket.
     */
    public function reopen(User $user, Ticket $ticket): bool
    {
        if ($ticket->status !== 'resolved') {
            return false;
        }
        
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role === 'opd_user' && $ticket->department_id === $user->department_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the ticket.
     */
    public function cancel(User $user, Ticket $ticket): bool
    {
        if ($user->role === 'admin') {
            return in_array($ticket->status, ['open', 'in_progress']);
        }

        if ($user->role === 'opd_user' && $ticket->department_id === $user->department_id) {
            return $ticket->status === 'open';
        }

        return false;
    }

    /**
     * Determine whether the user can reply public.
     */
    public function replyPublic(User $user, Ticket $ticket): bool
    {
        if (in_array($ticket->status, ['closed', 'cancelled'])) {
            return false;
        }

        if (in_array($user->role, ['admin', 'technician'])) {
            return true;
        }

        if ($user->role === 'opd_user' && $ticket->department_id === $user->department_id) {
            return true;
        }

        return false;
    }
    
    /**
     * Determine whether the user can reply internal.
     */
    public function replyInternal(User $user, Ticket $ticket): bool
    {
        if (in_array($ticket->status, ['closed', 'cancelled'])) {
            return false;
        }

        return in_array($user->role, ['admin', 'technician']);
    }
}
