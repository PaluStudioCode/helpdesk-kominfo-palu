<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use App\Services\FonnteService;
use App\Services\PhoneNormalizer;
use Illuminate\Console\Command;

class TestWhatsappNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:test {phone? : Target WhatsApp number (e.g. 08123456789)} {--message= : Custom test message}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test WhatsApp notification message via Fonnte Gateway';

    /**
     * Execute the console command.
     */
    public function handle(FonnteService $fonnteService): int
    {
        $this->info('=== Uji Coba Pengiriman Notifikasi WhatsApp (Fonnte API) ===');

        $token = config('services.fonnte.token');
        if (empty($token)) {
            $this->error('FONNTE_TOKEN belum diset di file .env atau config/services.php!');
            return 1;
        }

        $this->line('Token Fonnte terdeteksi: ' . substr($token, 0, 6) . '...' . substr($token, -4));

        $phone = $this->argument('phone');

        if (empty($phone)) {
            // Find an existing admin or user with a phone number
            $userWithPhone = User::whereNotNull('phone_number')->where('phone_number', '!=', '')->first();
            if ($userWithPhone) {
                $phone = $userWithPhone->phone_number;
                $this->info("Menggunakan nomor dari user {$userWithPhone->name} ({$userWithPhone->email}): {$phone}");
            } else {
                $phone = $this->ask('Masukkan nomor WhatsApp tujuan (contoh: 08123456789)');
            }
        }

        if (empty($phone)) {
            $this->error('Nomor WhatsApp tujuan wajib diisi.');
            return 1;
        }

        $normalized = PhoneNormalizer::normalize($phone);
        $this->line("Normalisasi nomor: {$phone} -> {$normalized}");

        // Get or create dummy recipient user & ticket for logging
        $recipient = User::where('phone_number', $phone)->first() ?? User::first() ?? User::factory()->create();
        $ticket = Ticket::first() ?? Ticket::factory()->create();

        $customMsg = $this->option('message');
        $message = $customMsg ?: "*[HELPDESK DISKOMINFO KOTA PALU]*\n"
            . "*Pemberitahuan Uji Coba Gateway WhatsApp*\n\n"
            . "Yth. {$recipient->name},\n"
            . "Ini adalah pesan resmi uji coba integrasi Gateway WhatsApp Fonnte Helpdesk Kominfo Kota Palu.\n\n"
            . "Status Gateway: Terhubung dan Aktif\n"
            . "Waktu Pengiriman: " . now()->translatedFormat('d F Y H:i:s') . " WITA\n\n"
            . "Pesan ini dikirimkan secara otomatis oleh sistem.\n\n"
            . "Dinas Komunikasi, Informatika, Persandian dan Statistik Kota Palu";

        $this->line('Mengirim pesan ke Fonnte API...');

        $log = $fonnteService->sendMessage(
            ticket: $ticket,
            recipient: $recipient,
            rawPhone: $phone,
            eventType: 'test_whatsapp',
            message: $message
        );

        $this->newLine();
        if ($log->status === 'success') {
            $this->info('✅ Pesan WhatsApp BERHASIL dikirim!');
            $this->line('ID Log Database: ' . $log->id);
            $this->line('Target: ' . $log->target_phone);
            $this->line('Response Payload: ' . json_encode($log->response_payload, JSON_PRETTY_PRINT));
            return 0;
        } else {
            $this->error('❌ Pesan WhatsApp GAGAL dikirim.');
            $this->line('ID Log Database: ' . $log->id);
            $this->line('Target: ' . $log->target_phone);
            $this->line('Detail Respon Error: ' . json_encode($log->response_payload, JSON_PRETTY_PRINT));
            return 1;
        }
    }
}
