<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Services\ActivityLogger;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoCloseResolvedTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:auto-close';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically close tickets that have been in resolved status for more than 72 hours (3x24h) without OPD dispute';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $thresholdTime = now()->subHours(72);

        $ticketsToClose = Ticket::where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $thresholdTime)
            ->get();

        $count = 0;

        foreach ($ticketsToClose as $ticket) {
            DB::beginTransaction();
            try {
                $lockedTicket = Ticket::where('id', $ticket->id)
                    ->where('status', 'resolved')
                    ->lockForUpdate()
                    ->first();

                if (!$lockedTicket) {
                    DB::rollBack();
                    continue;
                }

                $previousStatus = $lockedTicket->status;
                $lockedTicket->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

                // Record Status History
                // changed_by is set to reporter_id or assignee_id if available as fallback for foreign key constraint
                $changedById = $lockedTicket->reporter_id ?? $lockedTicket->assigned_to;
                $lockedTicket->statusHistories()->create([
                    'changed_by' => $changedById,
                    'previous_status' => $previousStatus,
                    'new_status' => 'closed',
                    'comment' => 'Tiket ditutup secara otomatis oleh sistem (melewati batas 3x24 jam sejak perbaikan selesai).',
                    'created_at' => now(),
                ]);

                // Log system activity (user_id is null for system event)
                ActivityLogger::log('ticket.auto_closed', $lockedTicket, [
                    'ticket_number' => $lockedTicket->ticket_number,
                    'resolved_at' => $lockedTicket->resolved_at?->toIso8601String(),
                    'closed_at' => now()->toIso8601String(),
                ], null);

                DB::commit();

                // Dispatch notification
                NotificationDispatcher::ticketClosed($lockedTicket);

                $count++;
                $this->info("Tiket #{$lockedTicket->ticket_number} berhasil ditutup otomatis.");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Gagal menutup otomatis tiket #{$ticket->ticket_number}: " . $e->getMessage());
            }
        }

        $this->info("Proses auto-close selesai. Total tiket ditutup: {$count}");

        return Command::SUCCESS;
    }
}
