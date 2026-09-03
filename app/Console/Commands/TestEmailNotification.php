<?php

namespace App\Console\Commands;

use App\Mail\TicketNotificationMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email? : Target email address (e.g. user@example.com)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test HTML email notification via configured SMTP Mailer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('=== Uji Coba Pengiriman Notifikasi Email (SMTP Brevo) ===');

        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $from = config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->line("Mailer Driver : {$mailer}");
        $this->line("SMTP Host     : {$host}:{$port}");
        $this->line("From Address  : {$from} ({$fromName})");

        $targetEmail = $this->argument('email');

        if (empty($targetEmail)) {
            $userWithEmail = User::whereNotNull('email')->first();
            if ($userWithEmail) {
                $targetEmail = $userWithEmail->email;
                $this->info("Menggunakan email dari user: {$userWithEmail->name} ({$targetEmail})");
            } else {
                $targetEmail = $this->ask('Masukkan email tujuan (contoh: user@gmail.com)');
            }
        }

        if (empty($targetEmail)) {
            $this->error('Alamat email tujuan wajib diisi.');
            return 1;
        }

        $recipient = User::where('email', $targetEmail)->first() ?? User::first() ?? User::factory()->create(['email' => $targetEmail]);
        $ticket = Ticket::first() ?? Ticket::factory()->create();

        $this->line("Mengirim email ke {$targetEmail}...");

        try {
            Mail::to($targetEmail)->send(
                new TicketNotificationMail(
                    ticket: $ticket,
                    recipient: $recipient,
                    eventType: 'test_mail',
                    emailSubject: 'Uji Coba Sistem Notifikasi Email Helpdesk',
                    headline: 'Integrasi SMTP Mailer Helpdesk Diskominfo Kota Palu Berhasil.',
                    customMessage: 'Ini adalah email uji coba resmi untuk memverifikasi fungsionalitas pengiriman email notifikasi tiket secara real-time.'
                )
            );

            $this->newLine();
            $this->info('✅ Email HTML BERHASIL dikirim!');
            $this->line("Tujuan : {$targetEmail}");
            $this->line("Subjek : [Helpdesk Kominfo Palu] Uji Coba Sistem Notifikasi Email Helpdesk - {$ticket->ticket_number}");
            $this->line("Waktu  : " . now()->translatedFormat('d F Y H:i:s') . " WITA");
            return 0;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('❌ Email GAGAL dikirim.');
            $this->line('Error: ' . $e->getMessage());
            $this->line('File: ' . $e->getFile() . ':' . $e->getLine());
            return 1;
        }
    }
}
