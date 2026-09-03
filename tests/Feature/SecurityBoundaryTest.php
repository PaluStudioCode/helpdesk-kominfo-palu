<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityBoundaryTest extends TestCase
{
    public function test_system_rejects_non_image_attachments(): void
    {
        $opdUser = $this->createOpdUser();

        // Create fake PHP script file
        $maliciousFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $response = $this->actingAs($opdUser)->post('/tickets', [
            'title' => 'Laporan dengan Berkas PHP',
            'location_details' => 'Lokasi',
            'description' => 'Mencoba unggah file tidak sah.',
            'attachments' => [$maliciousFile],
        ]);

        $response->assertSessionHasErrors('attachments.0');
    }

    public function test_system_rejects_oversized_attachments_greater_than_5mb(): void
    {
        $opdUser = $this->createOpdUser();

        // 6 MB image file (exceeds 5120 KB limit)
        $oversizedImage = UploadedFile::fake()->image('huge_photo.jpg')->size(6000);

        $response = $this->actingAs($opdUser)->post('/tickets', [
            'title' => 'Laporan Gambar Besar',
            'location_details' => 'Lokasi',
            'description' => 'Mencoba unggah foto melebihi 5 MB.',
            'attachments' => [$oversizedImage],
        ]);

        $response->assertSessionHasErrors('attachments.0');
    }

    public function test_system_rejects_more_than_three_attachments(): void
    {
        $opdUser = $this->createOpdUser();

        // 4 valid image files (exceeds max 3 files limit)
        $files = [
            UploadedFile::fake()->image('photo1.jpg', 100, 100)->size(500),
            UploadedFile::fake()->image('photo2.jpg', 100, 100)->size(500),
            UploadedFile::fake()->image('photo3.jpg', 100, 100)->size(500),
            UploadedFile::fake()->image('photo4.jpg', 100, 100)->size(500),
        ];

        $response = $this->actingAs($opdUser)->post('/tickets', [
            'title' => 'Laporan 4 Gambar',
            'location_details' => 'Lokasi',
            'description' => 'Mencoba unggah 4 foto.',
            'attachments' => $files,
        ]);

        $response->assertSessionHasErrors('attachments');
    }

    public function test_system_accepts_valid_image_attachments(): void
    {
        $opdUser = $this->createOpdUser();

        $validFiles = [
            UploadedFile::fake()->image('bukti1.jpg', 200, 200)->size(1000),
            UploadedFile::fake()->image('bukti2.png', 200, 200)->size(1500),
        ];

        $response = $this->actingAs($opdUser)->post('/tickets', [
            'title' => 'Laporan Bukti Sah',
            'location_details' => 'Lokasi Sah',
            'description' => 'Unggah 2 foto bukti yang sah.',
            'attachments' => $validFiles,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/tickets');
    }
}
