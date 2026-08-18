<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use App\Http\Requests\StoreTicketReplyRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketActionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Sub-Phase 7.1: Penugasan & Klaim Tiket (Assignment)
     */
    public function assign(Request $request, Ticket $ticket)
    {
        $this->authorize('assign', $ticket);

        $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id']
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Lock ticket to prevent race conditions when multiple technicians try to assign concurrently
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if ($lockedTicket->status !== 'open') {
                DB::rollBack();
                return back()->with('error', 'Tiket sudah ditangani oleh teknisi lain.');
            }

            // Assign logic
            $assigneeId = $request->input('assigned_to', $user->id);

            $lockedTicket->update([
                'assigned_to' => $assigneeId,
                'status' => 'in_progress',
            ]);

            // Track Status History
            $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'open',
                'new_status' => 'in_progress',
                'comment' => 'Tiket diambil / ditugaskan ke Teknisi.',
                'created_at' => now(),
            ]);

            // Log Activity
            ActivityLogger::log('ticket.assigned', $lockedTicket, [
                'assigned_to' => $assigneeId,
                'ticket_number' => $lockedTicket->ticket_number,
            ], $user->id);

            DB::commit();

            // Trigger Notification
            $assigneeUser = User::find($assigneeId);
            NotificationDispatcher::ticketAssigned($lockedTicket, $assigneeUser);

            return back()->with('success', 'Tiket berhasil diklaim dan status diubah ke In Progress.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat memproses penugasan.');
        }
    }

    /**
     * Sub-Phase 7.2: Thread Diskusi & Catatan Internal
     */
    public function storeReply(StoreTicketReplyRequest $request, Ticket $ticket)
    {
        $validated = $request->validated();
        $user = $request->user();

        DB::beginTransaction();
        try {
            $reply = $ticket->replies()->create([
                'user_id' => $user->id,
                'message' => $validated['message'],
                'is_internal' => $validated['is_internal'],
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    
                    $ticket->attachments()->create([
                        'reply_id' => $reply->id,
                        'uploaded_by' => $user->id,
                        'attachment_type' => 'reply_attachment',
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            ActivityLogger::log('ticket.replied', $ticket, [
                'reply_id' => $reply->id,
                'is_internal' => $reply->is_internal,
            ], $user->id);

            DB::commit();
            return back()->with('success', 'Tanggapan berhasil dikirim.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat mengirim tanggapan.')->withInput();
        }
    }

    /**
     * Sub-Phase 7.3: Penyelesaian, Konfirmasi Selesai, Reopen, dan Cancel
     */
    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $validated = $request->validated();
        $user = $request->user();
        $newStatus = $validated['status'];
        $previousStatus = $ticket->status;

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            $updateData = ['status' => $newStatus];
            $comment = $validated['comment'] ?? "Status diubah menjadi {$newStatus}.";

            if ($newStatus === 'resolved') {
                $updateData['resolved_at'] = now();
                $updateData['resolution_note'] = $validated['resolution_note'];
                $comment = 'Teknisi menandai tiket telah diselesaikan.';
                
                // Handle Resolution Proof Uploads
                if ($request->hasFile('resolution_proofs')) {
                    foreach ($request->file('resolution_proofs') as $file) {
                        $path = $file->store('ticket-attachments', 'public');
                        $lockedTicket->attachments()->create([
                            'uploaded_by' => $user->id,
                            'attachment_type' => 'resolution_proof',
                            'file_path' => $path,
                            'file_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                        ]);
                    }
                }
            } 
            elseif ($newStatus === 'closed') {
                $updateData['closed_at'] = now();
                $comment = 'OPD mengonfirmasi bahwa kendala telah selesai dengan baik.';
            } 
            elseif ($newStatus === 'in_progress' && $previousStatus === 'resolved') { // REOPEN
                $updateData['resolved_at'] = null; // Clear resolved timestamp
                // NOTE: We intentionally DO NOT reset 'assigned_to'. It is kept.
                $comment = 'OPD membuka kembali tiket karena kendala belum tuntas.';
                if (isset($validated['comment'])) {
                    $comment .= " Alasan: " . $validated['comment'];
                }
            }
            elseif ($newStatus === 'cancelled') {
                $updateData['closed_at'] = now();
                $comment = 'Tiket dibatalkan. Alasan: ' . $validated['comment'];
            }

            $lockedTicket->update($updateData);

            // Track Status History
            $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'comment' => $comment,
                'created_at' => now(),
            ]);

            // Log Activity
            ActivityLogger::log('ticket.status_changed', $lockedTicket, [
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'comment' => $comment,
            ], $user->id);

            DB::commit();

            // Trigger Queued Notifications based on new status
            if ($newStatus === 'resolved') {
                NotificationDispatcher::ticketResolved($lockedTicket);
            } elseif ($newStatus === 'closed') {
                NotificationDispatcher::ticketClosed($lockedTicket);
            } elseif ($newStatus === 'in_progress' && $previousStatus === 'resolved') {
                NotificationDispatcher::ticketReopened($lockedTicket);
            } elseif ($newStatus === 'cancelled') {
                NotificationDispatcher::ticketCancelled($lockedTicket, $validated['comment']);
            }

            return back()->with('success', "Status tiket berhasil diubah menjadi {$newStatus}.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat mengubah status tiket.')->withInput();
        }
    }
}
