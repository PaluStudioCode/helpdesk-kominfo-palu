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
    protected $description = 'Automatically approve and close tickets that have been in pending_approval status for more than 72 hours (3x24h)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $thresholdTime = now()->subHours(72);

        $ticketsToClose = Ticket::where('status', 'pending_approval')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $thresholdTime)
            ->get();

        $count = 0;

        foreach ($ticketsToClose as $ticket) {
            DB::beginTransaction();
            try {
                $lockedTicket = Ticket::where('id', $ticket->id)
                    ->where('status', 'pending_approval')
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
                $changedById = $lockedTicket->reporter_id ?? $lockedTicket->assigned_to;
                $lockedTicket->statusHistories()->create([
                    'changed_by' => $changedById,
                    'previous_status' => $previousStatus,
                    'new_status' => 'closed',
                    'comment' => 'Tiket ditutup secara otomatis oleh sistem (melewati batas 3x24 jam sejak perbaikan teknisi selesai tanpa kendala).',
                    'created_at' => now(),
                ]);

                // Log system activity
                ActivityLogger::log('ticket.auto_closed', $lockedTicket, [
                    'ticket_number' => $lockedTicket->ticket_number,
                    'resolved_at' => $lockedTicket->resolved_at?->toIso8601String(),
                    'closed_at' => now()->toIso8601String(),
                ], null);

                DB::commit();

                // Dispatch notification
                NotificationDispatcher::ticketClosed($lockedTicket);

                $count++;
                $this->info("Tiket #{$lockedTicket->ticket_number} berhasil disetujui & ditutup otomatis.");

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Gagal menutup otomatis tiket #{$ticket->ticket_number}: " . $e->getMessage());
            }
        }

        $this->info("Proses auto-close selesai. Total tiket ditutup: {$count}");

        return Command::SUCCESS;
    }
}
