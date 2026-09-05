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
            'infrastructure_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'])],
            'network_type' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:ticket_categories,id'],
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

            $category = !empty($validated['category_id']) ? TicketCategory::find($validated['category_id']) : null;
            $assignedAt = now();

            $slaHours = match ($validated['priority']) {
                'emergency' => 4,
                'high' => 8,
                'medium' => 24,
                'low' => 48,
                default => 24,
            };
            $dueAt = (clone $assignedAt)->addHours($slaHours);

            $leadTechnicianId = $validated['technician_ids'][0];

            $updateData = [
                'priority' => $validated['priority'],
                'assigned_to' => $leadTechnicianId,
                'assigned_at' => $assignedAt,
                'due_at' => $dueAt,
                'status' => 'in_progress',
            ];

            if (!empty($validated['infrastructure_type'])) {
                $updateData['infrastructure_type'] = $validated['infrastructure_type'];
            }
            if ($category) {
                $updateData['category_id'] = $category->id;
            }

            $lockedTicket->update($updateData);

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

        // If resolution_note is not explicitly provided, synthesize from notes or action_taken
        if (!$request->filled('resolution_note')) {
            $note = $request->input('notes') ?? $request->input('action_taken') ?? 'Tindakan perbaikan telah selesai dilaksanakan.';
            $request->merge(['resolution_note' => $note]);
        }

        $validated = $request->validate([
            'affected_device' => ['nullable', 'string', 'max:150'],
            'actual_repair_location' => ['nullable', 'string', 'max:255'],
            'infrastructure_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['Fiber optic', 'Perangkat/Akses', 'Power/poe', 'Converter', 'Layanan/jaringan'])],
            'network_type' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:ticket_categories,id'],
            'inspection_result' => ['nullable', 'string'],
            'root_cause' => ['nullable', 'string'],
            'action_taken' => ['nullable', 'string'],
            'materials_used' => ['nullable'],
            'test_result' => ['nullable', 'string'],
            'test_parameters' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'resolution_note' => ['nullable', 'string'],
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

            $finalNote = $validated['notes'] ?? $validated['resolution_note'] ?? $validated['action_taken'] ?? 'Tindakan perbaikan selesai.';
            $finalAction = $validated['action_taken'] ?? $validated['resolution_note'] ?? null;

            $materialsUsed = null;
            if ($request->has('materials_used')) {
                $rawMaterials = $request->input('materials_used');
                if (is_array($rawMaterials)) {
                    $items = [];
                    foreach ($rawMaterials as $item) {
                        if (is_array($item) && !empty($item['material']) && $item['material'] !== 'none' && !empty($item['quantity'])) {
                            $name = $item['material'] === 'Lainnya' ? ($item['custom_material'] ?? 'Lainnya') : $item['material'];
                            $unit = $item['unit'] ?? 'pcs';
                            $items[] = "{$name} ({$item['quantity']} {$unit})";
                        }
                    }
                    $materialsUsed = !empty($items) ? implode(', ', $items) : null;
                } elseif (is_string($rawMaterials)) {
                    $trimmed = trim($rawMaterials);
                    $materialsUsed = $trimmed !== '' ? $trimmed : null;
                }
            }

            $updateData = [
                'affected_device' => $validated['affected_device'] ?? null,
                'actual_repair_location' => $validated['actual_repair_location'] ?? null,
                'inspection_result' => $validated['inspection_result'] ?? null,
                'root_cause' => $validated['root_cause'] ?? null,
                'action_taken' => $finalAction,
                'materials_used' => $materialsUsed,
                'test_result' => $validated['test_result'] ?? null,
                'test_parameters' => $validated['test_parameters'] ?? null,
                'resolution_note' => $finalNote,
                'status' => 'pending_approval',
                'resolved_at' => now(),
            ];

            // Category & Infrastructure assignment by technician
            $infraType = $validated['infrastructure_type'] ?? $validated['network_type'] ?? null;
            if (!empty($infraType)) {
                $updateData['infrastructure_type'] = $infraType;
            }

            if (!empty($validated['category_id'])) {
                $updateData['category_id'] = $validated['category_id'];
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

            return redirect()->route('tickets.show', $lockedTicket->id)->with('success', 'Laporan penyelesaian dan berita acara perbaikan berhasil dikirim ke Admin untuk ditinjau.');

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

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

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

            $comment = !empty($validated['admin_note']) && trim($validated['admin_note']) !== ''
                ? trim($validated['admin_note'])
                : 'Admin memverifikasi mutu hasil perbaikan dan menutup tiket secara resmi.';

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'pending_approval',
                'new_status' => 'closed',
                'comment' => $comment,
                'created_at' => now(),
            ]);

            // Activity Log
            ActivityLogger::log('ticket.approved', $lockedTicket, [
                'closed_at' => now()->toDateTimeString(),
                'admin_note' => $validated['admin_note'] ?? null,
            ], $user->id);

            DB::commit();

            // Broadcast Realtime Status
            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            // Dispatch Notification
            NotificationDispatcher::ticketClosed($lockedTicket);

            return redirect()->route('tickets.show', $lockedTicket->id)->with('success', 'Hasil perbaikan disetujui dan tiket resmi ditutup.');

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

            return redirect()->route('tickets.show', $lockedTicket->id)->with('success', 'Instruksi revisi telah dikirimkan ke tim teknisi.');

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
     * Put ticket on hold (in_progress -> on_hold) with reason category & note.
     * Pauses the SLA countdown.
     */
    public function holdTicket(Request $request, Ticket $ticket)
    {
        $this->authorize('hold', $ticket);

        $validated = $request->validate([
            'hold_reason_category' => ['required', 'string', \Illuminate\Validation\Rule::in([
                'vendor_isp',
                'material_procurement',
                'access_permit',
                'weather_force_majeure',
                'need_escalation',
            ])],
            'hold_reason_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isInProgress()) {
                DB::rollBack();
                return back()->with('error', 'Tiket tidak sedang dalam status pengerjaan (In Progress).');
            }

            $categoryLabels = [
                'vendor_isp' => 'Ketergantungan Pihak Ketiga (Vendor ISP / Telkom / PLN)',
                'material_procurement' => 'Ketiadaan Material & Suku Cadang (Menunggu Pengadaan)',
                'access_permit' => 'Kendala Izin Akses Fisik / Kunci Lokasi',
                'weather_force_majeure' => 'Faktor Keamanan & Cuaca Ekstrem',
                'need_escalation' => 'Eskalasi ke Tim Ahli / Network Engineer',
            ];
            $catLabel = $categoryLabels[$validated['hold_reason_category']] ?? $validated['hold_reason_category'];

            $lockedTicket->update([
                'status' => 'on_hold',
                'hold_reason_category' => $validated['hold_reason_category'],
                'hold_reason_note' => $validated['hold_reason_note'],
                'hold_started_at' => now(),
            ]);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'in_progress',
                'new_status' => 'on_hold',
                'comment' => "Pengerjaan dijeda ({$catLabel}). Catatan: {$validated['hold_reason_note']}",
                'created_at' => now(),
            ]);

            ActivityLogger::log('ticket.held', $lockedTicket, [
                'category' => $validated['hold_reason_category'],
                'note' => $validated['hold_reason_note'],
            ], $user->id);

            DB::commit();

            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            NotificationDispatcher::ticketHeld($lockedTicket, $catLabel, $validated['hold_reason_note']);

            return back()->with('success', 'Status tiket berhasil diubah menjadi Tertunda (On-Hold). Timer SLA telah dijeda.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat menunda tiket: ' . $e->getMessage());
        }
    }

    /**
     * Resume a held ticket (on_hold -> in_progress).
     * Calculates hold duration and shifts SLA due_at forward.
     */
    public function resumeTicket(Request $request, Ticket $ticket)
    {
        $this->authorize('resume', $ticket);

        $user = $request->user();

        DB::beginTransaction();
        try {
            $lockedTicket = Ticket::where('id', $ticket->id)->lockForUpdate()->first();

            if (!$lockedTicket->isOnHold()) {
                DB::rollBack();
                return back()->with('error', 'Tiket tidak sedang dalam status tertunda (On-Hold).');
            }

            // Calculate hold duration in minutes
            $holdStartedAt = $lockedTicket->hold_started_at ? \Carbon\Carbon::parse($lockedTicket->hold_started_at) : now();
            $holdDurationMinutes = max(0, (int) $holdStartedAt->diffInMinutes(now()));

            $totalHold = ($lockedTicket->total_hold_duration_minutes ?? 0) + $holdDurationMinutes;

            $updateData = [
                'status' => 'in_progress',
                'hold_started_at' => null,
                'total_hold_duration_minutes' => $totalHold,
            ];

            // Shift due_at forward by hold duration if due_at exists
            if ($lockedTicket->due_at) {
                $updateData['due_at'] = \Carbon\Carbon::parse($lockedTicket->due_at)->addMinutes($holdDurationMinutes);
            }

            $lockedTicket->update($updateData);

            // Status History
            $history = $lockedTicket->statusHistories()->create([
                'changed_by' => $user->id,
                'previous_status' => 'on_hold',
                'new_status' => 'in_progress',
                'comment' => "Pekerjaan lapangan dilanjutkan kembali. Durasi jeda: {$holdDurationMinutes} menit (Target SLA diperpanjang).",
                'created_at' => now(),
            ]);

            ActivityLogger::log('ticket.resumed', $lockedTicket, [
                'hold_duration_minutes' => $holdDurationMinutes,
                'total_hold_duration_minutes' => $totalHold,
            ], $user->id);

            DB::commit();

            broadcast(new TicketStatusUpdated($lockedTicket, $history));

            NotificationDispatcher::ticketResumed($lockedTicket);

            return back()->with('success', "Pekerjaan tiket dilanjutkan kembali. Target SLA telah disesuaikan (+{$holdDurationMinutes} menit).");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat melanjutkan tiket: ' . $e->getMessage());
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
