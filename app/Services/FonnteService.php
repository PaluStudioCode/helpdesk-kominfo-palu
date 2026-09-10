<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    /**
     * Send WhatsApp message via Fonnte Gateway API.
     */
    public function sendMessage(
        Ticket $ticket,
        User $recipient,
        string $rawPhone,
        string $eventType,
        string $message
    ): bool {
        $normalizedPhone = PhoneNormalizer::normalize($rawPhone);
        $token = config('services.fonnte.token');
        $endpoint = config('services.fonnte.url', 'https://api.fonnte.com/send');

        if (empty($normalizedPhone)) {
            Log::warning("Fonnte WA skipped: Invalid phone number '{$rawPhone}' for recipient ID {$recipient->id}");
            return false;
        }

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

            $payload = $response->json();

            if ($response->successful() && (!isset($payload['status']) || $payload['status'] !== false)) {
                return true;
            }

            Log::error("Fonnte WA delivery failure for ticket {$ticket->ticket_number} (event: {$eventType}): " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("Fonnte WA exception for ticket {$ticket->ticket_number} (event: {$eventType}): " . $e->getMessage());
            return false;
        }
    }
}
