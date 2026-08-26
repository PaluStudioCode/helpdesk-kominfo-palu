<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Fiber Optic Categories
            [
                'name' => 'Internet Putus Total / Backbone Down (Emergency)',
                'network_type' => 'fiber_optic',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Kabel FO Utama / Backbone Putus Fisik',
                'network_type' => 'fiber_optic',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Kerusakan / Masalah SFP / Media Converter',
                'network_type' => 'fiber_optic',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Redaman Fiber Optic Tinggi (High Attenuation / Bending)',
                'network_type' => 'fiber_optic',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Core FO Rusak / Sambungan Joint Closure Bermasalah',
                'network_type' => 'fiber_optic',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Koneksi FO Dropcore Putus / Terjepit di Lokasi OPD',
                'network_type' => 'fiber_optic',
                'sla_hours' => 12,
                'status' => 'active',
            ],

            // LAN Categories
            [
                'name' => 'Krimpingan RJ45 Longgar / Konektor Rusak',
                'network_type' => 'lan',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Masalah IP Conflict / DHCP / Gateway Not Reachable',
                'network_type' => 'lan',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Kabel UTP / LAN Gedung Putus atau Terkelupas',
                'network_type' => 'lan',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Switch Distribusi Gedung Hang / Mati Listrik',
                'network_type' => 'lan',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Koneksi Port Switch / Patch Panel Bermasalah',
                'network_type' => 'lan',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Koneksi LAN Antar Ruang / Server Lokal Lambat',
                'network_type' => 'lan',
                'sla_hours' => 8,
                'status' => 'active',
            ],

            // Wi-Fi Categories
            [
                'name' => 'Wi-Fi Terhubung tetapi Tidak Ada Akses Internet',
                'network_type' => 'wifi',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Gagal Login / Otentikasi Captive Portal Wi-Fi',
                'network_type' => 'wifi',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Access Point Mati Total / Indikator Merah',
                'network_type' => 'wifi',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Overload Pengguna / Kapasitas AP Penuh',
                'network_type' => 'wifi',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Interferensi Sinyal Wi-Fi / Kanal Padat',
                'network_type' => 'wifi',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Sinyal Wi-Fi Lemah / Blind Spot Ruangan',
                'network_type' => 'wifi',
                'sla_hours' => 12,
                'status' => 'active',
            ],
        ];

        foreach ($categories as $category) {
            TicketCategory::updateOrCreate(
                [
                    'name' => $category['name'],
                    'network_type' => $category['network_type'],
                ],
                [
                    'sla_hours' => $category['sla_hours'],
                    'status' => $category['status'],
                ]
            );
        }
    }
}
