<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketService
{
    /**
     * Create a new ticket (either by OPD User or Admin On-Behalf).
     */
    public function createTicket(array $validated, User $user, array $attachments = []): Ticket
    {
        return DB::transaction(function () use ($validated, $user, $attachments) {
            $ticketNumber = $this->generateTicketNumber();

            if ($user->role === 'admin') {
                $category = TicketCategory::findOrFail($validated['category_id']);
                $dueAt = now()->addHours($category->sla_hours);
                $leadTechnicianId = $validated['technician_ids'][0];

                $ticket = Ticket::create([
                    'ticket_number' => $ticketNumber,
                    'department_id' => $validated['department_id'],
                    'reporter_id' => $user->id,
                    'assigned_to' => $leadTechnicianId,
                    'category_id' => $category->id,
                    'infrastructure_type' => $validated['infrastructure_type'] ?? $validated['network_type'] ?? null,
                    'title' => $validated['title'],
                    'location_details' => $validated['location_details'] ?? null,
                    'description' => $validated['description'],
                    'priority' => $validated['priority'],
                    'status' => 'in_progress',
                    'assigned_at' => now(),
                    'due_at' => $dueAt,
                ]);

                // Sync team technicians
                $ticket->technicians()->sync($validated['technician_ids']);

                // Status History
                $ticket->statusHistories()->create([
                    'changed_by' => $user->id,
                    'previous_status' => null,
                    'new_status' => 'in_progress',
                    'comment' => 'Tiket dibuat mewakili OPD oleh Admin dan langsung ditugaskan ke Tim Teknisi.',
                    'created_at' => now(),
                ]);

                $activityAction = 'ticket.created_on_behalf';
            } else {
                $ticket = Ticket::create([
                    'ticket_number' => $ticketNumber,
                    'department_id' => $user->department_id,
                    'reporter_id' => $user->id,
                    'assigned_to' => null,
                    'category_id' => null,
                    'infrastructure_type' => null,
                    'title' => $validated['title'],
                    'location_details' => $validated['location_details'] ?? null,
                    'description' => $validated['description'],
                    'priority' => null,
                    'status' => 'pending_admin',
                    'assigned_at' => null,
                    'due_at' => null,
                ]);

                // Status History
                $ticket->statusHistories()->create([
                    'changed_by' => $user->id,
                    'previous_status' => null,
                    'new_status' => 'pending_admin',
                    'comment' => 'Laporan gangguan didaftarkan oleh OPD (Menunggu Verifikasi Admin).',
                    'created_at' => now(),
                ]);

                $activityAction = 'ticket.created';
            }

            // Save Attachments
            if (!empty($attachments)) {
                foreach ($attachments as $file) {
                    $path = $file->store('ticket-attachments', 'public');
                    
                    $ticket->attachments()->create([
                        'uploaded_by' => $user->id,
                        'attachment_type' => 'issue_proof',
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            // Activity Log
            ActivityLogger::log($activityAction, $ticket, [
                'ticket_number' => $ticket->ticket_number,
                'title' => $ticket->title,
                'status' => $ticket->status,
            ], $user->id);

            // Dispatch Notifications
            if ($user->role === 'admin') {
                NotificationDispatcher::ticketAssigned($ticket);
            } else {
                NotificationDispatcher::ticketCreated($ticket);
            }

            return $ticket;
        });
    }

    /**
     * Generate unique sequential ticket number (TKT-YYYYMMDD-XXXX).
     */
    protected function generateTicketNumber(): string
    {
        $datePrefix = date('Ymd');
        
        $latestTicket = Ticket::where('ticket_number', 'like', "TKT-{$datePrefix}-%")
            ->lockForUpdate()
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($latestTicket) {
            $lastSequence = (int) substr($latestTicket->ticket_number, -4);
            $sequence = $lastSequence + 1;
        }

        return "TKT-{$datePrefix}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
