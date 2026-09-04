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
            // 1. Fiber optic Categories
            [
                'name' => 'Internet Putus Total / Backbone Down (Emergency)',
                'infrastructure_type' => 'Fiber optic',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Kabel FO Utama / Backbone Putus Fisik',
                'infrastructure_type' => 'Fiber optic',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Redaman Fiber Optic Tinggi (High Attenuation / Bending)',
                'infrastructure_type' => 'Fiber optic',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Core FO Rusak / Sambungan Joint Closure Bermasalah',
                'infrastructure_type' => 'Fiber optic',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Koneksi FO Dropcore Putus / Terjepit di Lokasi OPD',
                'infrastructure_type' => 'Fiber optic',
                'sla_hours' => 12,
                'status' => 'active',
            ],
            [
                'name' => 'Patchcord FO Patah / Konektor OTB Rusak',
                'infrastructure_type' => 'Fiber optic',
                'sla_hours' => 4,
                'status' => 'active',
            ],

            // 2. Perangkat/Akses Categories
            [
                'name' => 'Switch Distribusi Gedung Hang / Mati Total',
                'infrastructure_type' => 'Perangkat/Akses',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Koneksi Port Switch / Patch Panel Bermasalah',
                'infrastructure_type' => 'Perangkat/Akses',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Access Point Mati Total / Indikator Merah',
                'infrastructure_type' => 'Perangkat/Akses',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Sinyal Wi-Fi Lemah / Blind Spot Ruangan',
                'infrastructure_type' => 'Perangkat/Akses',
                'sla_hours' => 12,
                'status' => 'active',
            ],
            [
                'name' => 'Krimpingan RJ45 Longgar / Konektor Rusak',
                'infrastructure_type' => 'Perangkat/Akses',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Router Gateway OPD Hang / Perlu Reset Konfigurasi',
                'infrastructure_type' => 'Perangkat/Akses',
                'sla_hours' => 4,
                'status' => 'active',
            ],

            // 3. Power/poe Categories
            [
                'name' => 'Adaptor / PoE Injector Access Point Mati Total',
                'infrastructure_type' => 'Power/poe',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Gangguan Pasokan Listrik Rack Server / UPS Drop',
                'infrastructure_type' => 'Power/poe',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Switch PoE Overload / Port PoE Tidak Mengalirkan Daya',
                'infrastructure_type' => 'Power/poe',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Kabel Power / Steker Perangkat Jaringan Lepas atau Rusak',
                'infrastructure_type' => 'Power/poe',
                'sla_hours' => 2,
                'status' => 'active',
            ],

            // 4. Converter Categories
            [
                'name' => 'Media Converter FO-LAN Mati / Rusak',
                'infrastructure_type' => 'Converter',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Kerusakan / Masalah SFP Transceiver Optic Modul',
                'infrastructure_type' => 'Converter',
                'sla_hours' => 6,
                'status' => 'active',
            ],
            [
                'name' => 'Lampu Indikator Link / FX Media Converter Mati',
                'infrastructure_type' => 'Converter',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Adaptor Power Media Converter Rusak / Drop Tegangan',
                'infrastructure_type' => 'Converter',
                'sla_hours' => 4,
                'status' => 'active',
            ],

            // 5. Layanan/jaringan Categories
            [
                'name' => 'Masalah IP Conflict / DHCP / Gateway Not Reachable',
                'infrastructure_type' => 'Layanan/jaringan',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Wi-Fi Terhubung tetapi Tidak Ada Akses Internet',
                'infrastructure_type' => 'Layanan/jaringan',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Gagal Login / Otentikasi Captive Portal Wi-Fi',
                'infrastructure_type' => 'Layanan/jaringan',
                'sla_hours' => 4,
                'status' => 'active',
            ],
            [
                'name' => 'Bandwidth Lambat / Koneksi Antar Ruang atau Server Lokal Lambat',
                'infrastructure_type' => 'Layanan/jaringan',
                'sla_hours' => 8,
                'status' => 'active',
            ],
            [
                'name' => 'Gagal Akses DNS / Layanan Sistem Informasi Pemkot Terkendala',
                'infrastructure_type' => 'Layanan/jaringan',
                'sla_hours' => 4,
                'status' => 'active',
            ],
        ];

        foreach ($categories as $category) {
            TicketCategory::updateOrCreate(
                [
                    'name' => $category['name'],
                    'infrastructure_type' => $category['infrastructure_type'],
                ],
                [
                    'sla_hours' => $category['sla_hours'],
                    'status' => $category['status'],
                ]
            );
        }
    }
}
