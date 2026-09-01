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

        return (int) $user->department_id === (int) $ticket->department_id
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
     * Determine whether the user can verify & assign the ticket.
     * Admin only on pending_admin status.
     */
    public function verifyAndAssign(User $user, Ticket $ticket): Response
    {
        return ($user->role === 'admin' && $ticket->isPendingAdmin())
            ? Response::allow()
            : Response::deny('Hanya Administrator yang dapat memverifikasi dan mendisposisikan tiket ini.');
    }

    /**
     * Determine whether the user can reject the ticket.
     * Admin only on pending_admin status.
     */
    public function reject(User $user, Ticket $ticket): Response
    {
        return ($user->role === 'admin' && $ticket->isPendingAdmin())
            ? Response::allow()
            : Response::deny('Hanya Administrator yang dapat menolak tiket ini.');
    }

    /**
     * Determine whether the OPD user can resubmit a rejected ticket within 72 hours.
     */
    public function resubmit(User $user, Ticket $ticket): Response
    {
        if ($user->role !== 'opd_user' || (int) $user->department_id !== (int) $ticket->department_id) {
            return Response::deny('Anda tidak memiliki wewenang untuk mengajukan ulang tiket instansi ini.');
        }

        if (!$ticket->canBeResubmitted()) {
            return Response::deny('Masa pengajuan ulang tiket ini telah berakhir (melewati batas 72 jam sejak penolakan).');
        }

        return Response::allow();
    }

    /**
     * Determine whether the user can submit resolution for the ticket.
     * Admin or any assigned technician in the team on in_progress status.
     */
    public function submitResolution(User $user, Ticket $ticket): Response
    {
        if (!$ticket->isInProgress()) {
            return Response::deny('Tiket tidak sedang dalam status pengerjaan (In Progress).');
        }

        if ($user->role === 'admin') {
            return Response::allow();
        }

        if ($user->role === 'technician') {
            $isAssigned = $ticket->assigned_to === $user->id 
                || $ticket->technicians()->where('user_id', $user->id)->exists();

            return $isAssigned 
                ? Response::allow() 
                : Response::deny('Anda bukan anggota tim teknisi penanggung jawab tiket ini.');
        }

        return Response::deny('Akses ditolak.');
    }

    /**
     * Determine whether the user can approve resolution & close the ticket.
     * Admin only on pending_approval status.
     */
    public function approveResolution(User $user, Ticket $ticket): Response
    {
        return ($user->role === 'admin' && $ticket->isPendingApproval())
            ? Response::allow()
            : Response::deny('Hanya Administrator yang dapat menyetujui hasil kerja dan menutup tiket ini.');
    }

    /**
     * Determine whether the user can request revision / rework.
     * Admin only on pending_approval status.
     */
    public function requestRevision(User $user, Ticket $ticket): Response
    {
        return ($user->role === 'admin' && $ticket->isPendingApproval())
            ? Response::allow()
            : Response::deny('Hanya Administrator yang dapat meminta revisi pengerjaan tiket ini.');
    }

    /**
     * Determine whether the OPD user can submit rating & review.
     * OPD Reporter of the department on closed ticket (unrated).
     */
    public function rate(User $user, Ticket $ticket): Response
    {
        if ($user->role !== 'opd_user' || (int) $user->department_id !== (int) $ticket->department_id) {
            return Response::deny('Hanya pihak pelapor OPD yang berhak memberikan penilaian kepuasan layanan.');
        }

        return ($ticket->isClosed() && $ticket->rating === null)
            ? Response::allow()
            : Response::deny('Tiket belum ditutup atau sudah dinilai sebelumnya.');
    }

    /**
     * Determine whether the user can reply in public discussion.
     */
    public function replyPublic(User $user, Ticket $ticket): bool
    {
        if ($ticket->isClosed() || $ticket->isCancelled()) {
            return false;
        }

        if (in_array($user->role, ['admin', 'technician'])) {
            return true;
        }

        if ($user->role === 'opd_user' && (int) $ticket->department_id === (int) $user->department_id) {
            return true;
        }

        return false;
    }
    
    /**
     * Determine whether the user can post internal notes.
     */
    public function replyInternal(User $user, Ticket $ticket): bool
    {
        if ($ticket->isClosed() || $ticket->isCancelled()) {
            return false;
        }

        return in_array($user->role, ['admin', 'technician']);
    }
}
