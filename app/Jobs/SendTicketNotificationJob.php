<?php

namespace App\Jobs;

use App\Mail\TicketNotificationMail;
use App\Models\Ticket;
use App\Models\User;
use App\Services\FonnteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTicketNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ticket $ticket,
        public User $recipient,
        public string $eventType,
        public string $targetPhone,
        public string $waMessage,
        public string $emailSubject,
        public string $emailHeadline,
        public string $emailCustomMessage
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FonnteService $fonnteService): void
    {
        // 1. Send WhatsApp Notification via Fonnte
        try {
            if (!empty($this->targetPhone)) {
                $fonnteService->sendMessage(
                    ticket: $this->ticket,
                    recipient: $this->recipient,
                    rawPhone: $this->targetPhone,
                    eventType: $this->eventType,
                    message: $this->waMessage
                );
            }
        } catch (\Throwable $e) {
            Log::error("Failed in SendTicketNotificationJob WA: " . $e->getMessage());
        }

        // 2. Send Email via Mailer (Brevo SMTP fallback/primary)
        try {
            if (!empty($this->recipient->email)) {
                Mail::to($this->recipient->email)->send(
                    new TicketNotificationMail(
                        ticket: $this->ticket,
                        recipient: $this->recipient,
                        eventType: $this->eventType,
                        emailSubject: $this->emailSubject,
                        headline: $this->emailHeadline,
                        customMessage: $this->emailCustomMessage
                    )
                );
            }
        } catch (\Throwable $e) {
            Log::error("Failed in SendTicketNotificationJob Mail: " . $e->getMessage());
        }
    }
}
