<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $opdUser;
    protected User $technician1;
    protected User $technician2;
    protected Department $dinkes;
    protected TicketCategory $lanCategory;
    protected TicketCategory $foCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->first();
        $this->dinkes = Department::where('code', 'DINKES')->first();
        $this->opdUser = User::where('role', 'opd_user')->where('department_id', $this->dinkes->id)->first();
        $technicians = User::where('role', 'technician')->take(2)->get();
        $this->technician1 = $technicians[0];
        $this->technician2 = $technicians[1];

        $this->lanCategory = TicketCategory::where('network_type', 'lan')->first();
        $this->foCategory = TicketCategory::where('network_type', 'fiber_optic')->first();
    }

    /**
     * Skenario Lengkap Siklus Hidup Tiket Baru:
     * OPD Buat -> Admin Verifikasi & Multi-Assign -> Teknisi Selesaikan & Koreksi Kategori ->
     * Admin Minta Revisi -> Teknisi Kirim Ulang -> Admin Setujui (Closed) -> OPD Rating
     */
    public function test_complete_ticket_lifecycle_flow(): void
    {
        Storage::fake('public');
        $proof = UploadedFile::fake()->image('bukti_awal.jpg');

        // 1. OPD mendaftarkan keluhan gejala awam
        $response = $this->actingAs($this->opdUser)->post('/tickets', [
            'title' => 'Internet Ruang Poli Gigi Padam',
            'location_details' => 'Gedung B Lantai 2',
            'description' => 'Komputer administrasi tidak bisa akses SIMPUS sejak pagi.',
            'attachments' => [$proof],
        ]);
        $response->assertRedirect('/tickets');
        $response->assertSessionHas('success');

        $ticket = Ticket::where('title', 'Internet Ruang Poli Gigi Padam')->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('pending_admin', $ticket->status);
        $this->assertNull($ticket->category_id);
        $this->assertNull($ticket->network_type);
        $this->assertNull($ticket->priority);
        $this->assertNull($ticket->assigned_at);
        $this->assertNull($ticket->due_at); // SLA belum aktif

        // 2. Admin memverifikasi, mengestimasi kategori LAN, menentukan prioritas, & menugaskan 2 teknisi
        $response = $this->actingAs($this->admin)->post("/tickets/{$ticket->id}/verify-assign", [
            'network_type' => 'lan',
            'category_id' => $this->lanCategory->id,
            'priority' => 'high',
            'technician_ids' => [$this->technician1->id, $this->technician2->id],
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertEquals('lan', $ticket->network_type);
        $this->assertEquals($this->lanCategory->id, $ticket->category_id);
        $this->assertEquals('high', $ticket->priority);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNotNull($ticket->due_at); // SLA aktif
        $this->assertCount(2, $ticket->technicians);

        // 3. Teknisi 1 turun ke lapangan, menemukan kabel FO putus (koreksi kategori ke FO), dan lapor selesai
        $resProof = UploadedFile::fake()->image('bukti_selesai.jpg');
        $response = $this->actingAs($this->technician1)->post("/tickets/{$ticket->id}/submit-resolution", [
            'resolution_note' => 'Ditemukan kabel FO dropcore putus. Dilakukan splicing ulang.',
            'network_type' => 'fiber_optic',
            'category_id' => $this->foCategory->id,
            'resolution_proofs' => [$resProof],
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('pending_approval', $ticket->status);
        $this->assertEquals('fiber_optic', $ticket->network_type);
        $this->assertEquals($this->foCategory->id, $ticket->category_id);
        $this->assertNotNull($ticket->resolved_at);

        // 4. Admin melakukan QC mutu dan meminta revisi (perapian kabel)
        $response = $this->actingAs($this->admin)->post("/tickets/{$ticket->id}/request-revision", [
            'comment' => 'Tolong rapikan kabel patch cord di rack switch dan foto ulang.',
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertNull($ticket->resolved_at);

        // 5. Teknisi 2 merapikan kabel dan mengajukan kembali
        $response = $this->actingAs($this->technician2)->post("/tickets/{$ticket->id}/submit-resolution", [
            'resolution_note' => 'Kabel sudah dirapikan dengan spiral dan label port dipasang.',
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('pending_approval', $ticket->status);

        // 6. Admin menyetujui hasil pengerjaan (Quality Gate Closed)
        $response = $this->actingAs($this->admin)->post("/tickets/{$ticket->id}/approve-resolution");
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);

        // 7. OPD memberikan rating 5 bintang dan testimoni kepuasan
        $response = $this->actingAs($this->opdUser)->post("/tickets/{$ticket->id}/rate", [
            'rating' => 5,
            'feedback_comment' => 'Pelayanan cepat dan kabel sangat rapi. Terima kasih Diskominfo Palu!',
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals(5, $ticket->rating);
        $this->assertEquals('Pelayanan cepat dan kabel sangat rapi. Terima kasih Diskominfo Palu!', $ticket->feedback_comment);
        $this->assertNotNull($ticket->rated_at);
    }

    /**
     * Skenario Penolakan Tiket & Pengajuan Ulang (Re-Submit) Dalam 72 Jam
     */
    public function test_ticket_rejection_and_successful_resubmit_within_72_hours(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-20260901-0001',
            'department_id' => $this->dinkes->id,
            'reporter_id' => $this->opdUser->id,
            'title' => 'WiFi Lambat',
            'location_details' => 'Puskesmas',
            'description' => 'WiFi tidak ada internet.',
            'status' => 'pending_admin',
        ]);

        // Admin menolak tiket
        $response = $this->actingAs($this->admin)->post("/tickets/{$ticket->id}/reject", [
            'reason' => 'Mohon sebutkan nama ruangan spesifik dan nomor inventaris router.',
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('cancelled', $ticket->status);
        $this->assertNotNull($ticket->cancelled_at);

        // OPD memperbaiki laporan dalam masa tenggang 72 jam
        $response = $this->actingAs($this->opdUser)->post("/tickets/{$ticket->id}/resubmit", [
            'title' => 'WiFi Lambat di Ruang Farmasi',
            'location_details' => 'Ruang Farmasi Lantai 1, AP D-Link No. 04',
            'description' => 'WiFi menyala tetapi indikator internet merah sejak jam 8 pagi.',
        ]);
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertEquals('pending_admin', $ticket->status);
        $this->assertNull($ticket->cancelled_at);
        $this->assertEquals('WiFi Lambat di Ruang Farmasi', $ticket->title);
    }

    /**
     * Skenario Gagal: OPD mencoba memperbaiki laporan setelah melewati 72 jam
     */
    public function test_opd_cannot_resubmit_ticket_after_72_hours(): void
    {
        $ticket = Ticket::create([
            'ticket_number' => 'TKT-20260901-0002',
            'department_id' => $this->dinkes->id,
            'reporter_id' => $this->opdUser->id,
            'title' => 'Komputer Mati',
            'location_details' => 'Ruang Tata Usaha',
            'description' => 'Bukan jaringan kominfo.',
            'status' => 'cancelled',
            'cancelled_at' => now()->subHours(75), // Melewati batas 72 jam
        ]);

        $response = $this->actingAs($this->opdUser)->post("/tickets/{$ticket->id}/resubmit", [
            'title' => 'Perbaikan Laporan Telat',
            'location_details' => 'Ruang TU',
            'description' => 'Perbaikan data kendala.',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Skenario Admin Membuat Tiket On-Behalf (Langsung In Progress & Multi-Teknisi)
     */
    public function test_admin_can_create_ticket_on_behalf_with_direct_in_progress(): void
    {
        $response = $this->actingAs($this->admin)->post('/tickets', [
            'department_id' => $this->dinkes->id,
            'network_type' => 'lan',
            'category_id' => $this->lanCategory->id,
            'title' => 'Penanganan Darurat Switch Core Dinkes',
            'location_details' => 'Server Room Dinkes',
            'description' => 'Laporan langsung dari Kepala Dinas via telepon darurat.',
            'priority' => 'emergency',
            'technician_ids' => [$this->technician1->id, $this->technician2->id],
        ]);

        $response->assertRedirect('/tickets');
        $response->assertSessionHas('success');

        $ticket = Ticket::where('title', 'Penanganan Darurat Switch Core Dinkes')->first();
        $this->assertNotNull($ticket);
        $this->assertEquals('in_progress', $ticket->status);
        $this->assertEquals($this->admin->id, $ticket->reporter_id);
        $this->assertEquals($this->dinkes->id, $ticket->department_id);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNotNull($ticket->due_at);
        $this->assertCount(2, $ticket->technicians);
    }
}
