<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use App\Models\WhatsappNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    /**
     * Send WhatsApp message via Fonnte Gateway API and record in whatsapp_notifications table.
     */
    public function sendMessage(
        Ticket $ticket,
        User $recipient,
        string $rawPhone,
        string $eventType,
        string $message
    ): WhatsappNotification {
        $normalizedPhone = PhoneNormalizer::normalize($rawPhone);
        $token = config('services.fonnte.token');
        $endpoint = config('services.fonnte.url', 'https://api.fonnte.com/send');

        $status = 'failed';
        $responsePayload = null;

        if (empty($normalizedPhone)) {
            $responsePayload = ['error' => 'Invalid phone number after normalization', 'raw' => $rawPhone];
        } else {
            try {
                $response = Http::withOptions([
                    'verify' => app()->environment('production'),
                ])->withHeaders([
                    'Authorization' => $token,
                ])->asForm()->post($endpoint, [
                    'target' => $normalizedPhone,
                    'message' => $message,
                    'countryCode' => '62',
                ]);

                $responsePayload = $response->json() ?? ['body' => $response->body(), 'status' => $response->status()];

                if ($response->successful()) {
                    // Check fonnte specific JSON status if available
                    if (isset($responsePayload['status']) && $responsePayload['status'] === false) {
                        $status = 'failed';
                    } else {
                        $status = 'success';
                    }
                } else {
                    $status = 'failed';
                }
            } catch (\Throwable $e) {
                Log::error("Fonnte WA notification failed: " . $e->getMessage());
                $responsePayload = [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
                $status = 'failed';
            }
        }

        // Persist delivery log to whatsapp_notifications (MUST NOT fail/rollback the parent transaction)
        return WhatsappNotification::create([
            'ticket_id' => $ticket->id,
            'recipient_id' => $recipient->id,
            'target_phone' => $normalizedPhone ?? $rawPhone,
            'event_type' => $eventType,
            'message_content' => $message,
            'status' => $status,
            'response_payload' => $responsePayload,
            'created_at' => now(),
        ]);
    }
}
