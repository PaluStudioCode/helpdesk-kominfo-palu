<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use App\Events\TicketReplyCreated;
use App\Events\TicketStatusUpdated;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use App\Http\Requests\StoreTicketReplyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketActionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Admin verifies and assigns ticket to team technicians (pending_admin -> in_progress).
     */
    public function verifyAndAssign(Request $request, Ticket $ticket)
    {
        $this->authorize('verifyAndAssign', $ticket);

        if ($request->has('network_type') && !$request->has('infrastructure_type')) {
            $request->merge(['infrastructure_type' => $request->input('network_type')]);
        }

        $validated = $request->validate([
            'infrastructure_type' => ['required', 'string', \Illuminate\Validation\Rule::in(['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'])],
            'network_type' => ['nullable', 'string'],
            'category_id' => ['required', 'exists:ticket_categories,id'],
            'priority' => ['required', 'in:low,medium,high,emergency'],
            'technician_ids' => ['required', 'array', 'min:1'],
            'technician_ids.*' => ['exists:users,id'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isPendingAdmin()) {
                DB::rollBack();
                return back()->with('error', 'Status tiket sudah berubah atau tidak valid untuk diverifikasi.');
            }

            $category = TicketCategory::findOrFail($validated['category_id']);
            $assignedAt = now();
            $dueAt = (clone $assignedAt)->addHours($category->sla_hours);
            $leadTechnicianId = $validated['technician_ids'][0];

            $lockedTicket->update([
                'infrastructure_type' => $validated['infrastructure_type'],
                'category_id' => $category->id,
                'priority' => $validated['priority'],
                'assigned_to' => $leadTechnicianId,
                'assigned_at' => $assignedAt,
                'due_at' => $dueAt,
                'status' => 'in_progress',
            ]);

            // Sync multi-technicians
            $lockedTicket->technicians()->sync($validated['technician_ids']);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'pending_admin',
                'new_status' => 'in_progress',
                'comment' => 'Laporan diverifikasi oleh Admin dan ditugaskan ke Tim Teknisi.',
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.verified_assigned', $lockedTicket, [
                'technician_ids' => $validated['technician_ids'],
                'priority' => $validated['priority'],
                'due_at' => $dueAt->toDateTimeString(),
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notifications
            NotificationDispatcher::ticketAssigned($lockedTicket);

            return back()->with('success', 'Laporan berhasil diverifikasi dan ditugaskan ke tim teknisi.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat memproses verifikasi: ' . $e->getMessage());
        }
    }

    /**
     * Admin rejects ticket with reason (pending_admin -> cancelled).
     */
    public function reject(Request $request, Ticket $ticket)
    {
        $this->authorize('reject', $ticket);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isPendingAdmin()) {
                DB::rollBack();
                return back()->with('error', 'Status tiket sudah berubah atau tidak valid untuk ditolak.');
            }

            $lockedTicket->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'pending_admin',
                'new_status' => 'cancelled',
                'comment' => 'Laporan ditolak oleh Admin. Alasan: ' . $validated['reason'],
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.rejected', $lockedTicket, [
                'reason' => $validated['reason'],
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notifications
            NotificationDispatcher::ticketRejected($lockedTicket, $validated['reason']);

            return back()->with('success', 'Laporan tiket telah ditolak.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat menolak tiket: ' . $e->getMessage());
        }
    }

    /**
     * OPD User cancels their pending ticket with a reason (pending_admin -> cancelled).
     */
    public function cancelByReporter(Request $request, Ticket $ticket)
    {
        $this->authorize('cancelByReporter', $ticket);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Alasan pembatalan laporan wajib diisi.',
            'reason.min' => 'Alasan pembatalan minimal 5 karakter.',
            'reason.max' => 'Alasan pembatalan maksimal 500 karakter.',
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isPendingAdmin()) {
                DB::rollBack();
                return back()->with('error', 'Status tiket sudah berubah atau tidak valid untuk dibatalkan.');
            }

            $lockedTicket->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'pending_admin',
                'new_status' => 'cancelled',
                'comment' => 'Dibatalkan oleh Pelapor: ' . $validated['reason'],
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.cancelled_by_reporter', $lockedTicket, [
                'reason' => $validated['reason'],
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notifications to Admins
            NotificationDispatcher::ticketCancelledByReporter($lockedTicket, $validated['reason']);

            return back()->with('success', 'Laporan berhasil dibatalkan.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat membatalkan laporan: ' . $e->getMessage());
        }
    }

    /**
     * OPD User fixes and resubmits rejected ticket within 72 hours (cancelled -> pending_admin).
     */
    public function resubmit(Request $request, Ticket $ticket)
    {
        $this->authorize('resubmit', $ticket);

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'location_details' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['required', 'string', 'min:10'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->canBeResubmitted()) {
                DB::rollBack();
                return back()->with('error', 'Masa pengajuan ulang tiket ini telah berakhir (melewati 72 jam).');
            }

            $lockedTicket->update([
                'title' => $validated['title'],
                'location_details' => $validated['location_details'],
                'description' => $validated['description'],
                'status' => 'pending_admin',
                'cancelled_at' => null,
            ]);

            // Handle Attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    $lockedTicket->attachments()->create([
                        'uploaded_by' => $user->id,
                        'attachment_type' => 'issue_proof',
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'cancelled',
                'new_status' => 'pending_admin',
                'comment' => 'OPD telah memperbaiki data laporan dan mengajukan kembali.',
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.resubmitted', $lockedTicket, [
                'ticket_number' => $lockedTicket->ticket_number,
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notification
            NotificationDispatcher::ticketResubmitted($lockedTicket);

            return back()->with('success', 'Laporan perbaikan berhasil diajukan kembali ke Admin.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat mengajukan ulang: ' . $e->getMessage());
        }
    }

    /**
     * Technician submits resolution & real category confirmation (in_progress -> pending_approval).
     */
    public function submitResolution(Request $request, Ticket $ticket)
    {
        $this->authorize('submitResolution', $ticket);

        if ($request->has('network_type') && !$request->has('infrastructure_type')) {
            $request->merge(['infrastructure_type' => $request->input('network_type')]);
        }

        $validated = $request->validate([
            'resolution_note' => ['required', 'string', 'min:10'],
            'infrastructure_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'])],
            'network_type' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:ticket_categories,id'],
            'resolution_proofs' => ['nullable', 'array', 'max:3'],
            'resolution_proofs.*' => ['file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isInProgress()) {
                DB::rollBack();
                return back()->with('error', 'Tiket tidak sedang dalam status pengerjaan (In Progress).');
            }

            $updateData = [
                'resolution_note' => $validated['resolution_note'],
                'status' => 'pending_approval',
                'resolved_at' => now(),
            ];

            // Dynamic SLA recalculation if technician updated real category
            if (!empty($validated['category_id']) && (int) $validated['category_id'] !== (int) $lockedTicket->category_id) {
                $newCategory = TicketCategory::findOrFail($validated['category_id']);
                $updateData['category_id'] = $newCategory->id;
                
                $infraType = $validated['infrastructure_type'] ?? $validated['network_type'] ?? null;
                if (!empty($infraType)) {
                    $updateData['infrastructure_type'] = $infraType;
                }

                // Recalculate SLA from assigned_at
                $startPoint = $lockedTicket->assigned_at ?? $lockedTicket->created_at;
                $updateData['due_at'] = (clone $startPoint)->addHours($newCategory->sla_hours);
            }

            $lockedTicket->update($updateData);

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

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'in_progress',
                'new_status' => 'pending_approval',
                'comment' => 'Teknisi menyelesaikan perbaikan di lokasi dan mengajukan review hasil kerja.',
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.resolution_submitted', $lockedTicket, [
                'category_id' => $lockedTicket->category_id,
                'due_at' => $lockedTicket->due_at?->toDateTimeString(),
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notification
            NotificationDispatcher::pendingApproval($lockedTicket);

            return back()->with('success', 'Laporan perbaikan berhasil dikirim ke Admin untuk ditinjau.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat mengirim laporan perbaikan: ' . $e->getMessage());
        }
    }

    /**
     * Admin approves resolution & closes ticket (pending_approval -> closed).
     */
    public function approveResolution(Request $request, Ticket $ticket)
    {
        $this->authorize('approveResolution', $ticket);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isPendingApproval()) {
                DB::rollBack();
                return back()->with('error', 'Tiket tidak dalam status menunggu review.');
            }

            $lockedTicket->update([
                'status' => 'closed',
                'resolved_at' => $lockedTicket->resolved_at ?? now(),
                'closed_at' => now(),
            ]);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'pending_approval',
                'new_status' => 'closed',
                'comment' => 'Admin memverifikasi mutu hasil perbaikan dan menutup tiket secara resmi.',
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.approved', $lockedTicket, [
                'closed_at' => now()->toDateTimeString(),
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notification
            NotificationDispatcher::ticketClosed($lockedTicket);

            return back()->with('success', 'Hasil perbaikan disetujui dan tiket resmi ditutup.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat menyetujui tiket: ' . $e->getMessage());
        }
    }

    /**
     * Admin requests revision / rework from technicians (pending_approval -> in_progress).
     */
    public function requestRevision(Request $request, Ticket $ticket)
    {
        $this->authorize('requestRevision', $ticket);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isPendingApproval()) {
                DB::rollBack();
                return back()->with('error', 'Tiket tidak dalam status menunggu review.');
            }

            // Status returns to in_progress (SLA due_at is NOT extended)
            $lockedTicket->update([
                'status' => 'in_progress',
                'resolved_at' => null,
            ]);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'pending_approval',
                'new_status' => 'in_progress',
                'comment' => 'Admin meminta perbaikan ulang/revisi. Instruksi: ' . $validated['comment'],
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.revision_requested', $lockedTicket, [
                'comment' => $validated['comment'],
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notification
            NotificationDispatcher::ticketRevision($lockedTicket, $validated['comment']);

            return back()->with('success', 'Instruksi revisi telah dikirimkan ke tim teknisi.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat meminta revisi: ' . $e->getMessage());
        }
    }

    /**
     * OPD User rates and provides feedback for closed ticket.
     */
    public function rate(Request $request, Ticket $ticket)
    {
        $this->authorize('rate', $ticket);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'feedback_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isClosed() || $lockedTicket->rating !== null) {
                DB::rollBack();
                return back()->with('error', 'Tiket belum selesai atau penilaian sudah pernah dikirim.');
            }

            $lockedTicket->update([
                'rating' => $validated['rating'],
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'rated_at' => now(),
            ]);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'closed',
                'new_status' => 'closed',
                'comment' => "OPD memberikan penilaian kepuasan layanan: {$validated['rating']} bintang.",
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.rated', $lockedTicket, [
                'rating' => $validated['rating'],
                'feedback_comment' => $validated['feedback_comment'] ?? null,
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            return back()->with('success', 'Terima kasih! Penilaian kepuasan Anda telah berhasil dikirim.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat menyimpan penilaian: ' . $e->getMessage());
        }
    }

    /**
     * Thread discussion & internal notes.
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

            // Mark this ticket as read for the sender
            \App\Models\TicketRead::updateOrCreate(
                ['ticket_id' => $ticket->id, 'user_id' => $user->id],
                [
                    'last_read_reply_id' => $reply->id,
                    'last_read_at' => now(),
                ]
            );

            DB::commit();

            broadcast(new TicketReplyCreated($reply, $ticket->id));

            return back();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat mengirim tanggapan.')->withInput();
        }
    }

    /**
     * Asynchronously mark discussion thread as read for current user.
     */
    public function markAsRead(Request $request, Ticket $ticket)
    {
        $this->authorize('view', $ticket);
        $user = $request->user();

        $latestReplyQuery = $ticket->replies();
        if ($user->role === 'opd_user') {
            $latestReplyQuery->where('is_internal', false);
        }
        $latestReplyId = $latestReplyQuery->max('id');

        \App\Models\TicketRead::updateOrCreate(
            ['ticket_id' => $ticket->id, 'user_id' => $user->id],
            [
                'last_read_reply_id' => $latestReplyId,
                'last_read_at' => now(),
            ]
        );

        return response()->json([
            'success' => true, 
            'last_read_reply_id' => $latestReplyId
        ]);
    }
}
