<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\NetworkDevice;
use Illuminate\Database\Seeder;

class NetworkDeviceAndMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $devices = [
            ['name' => 'Router / Gateway', 'code' => 'RTR', 'description' => 'Router utama atau gateway akses internet OPD'],
            ['name' => 'Switch Core / Distribution', 'code' => 'SW-CORE', 'description' => 'Switch pusat distribusi backbone data'],
            ['name' => 'Switch Access', 'code' => 'SW-ACC', 'description' => 'Switch pembagi ke workstation / ruangan'],
            ['name' => 'Access Point (AP) / WiFi Indoor', 'code' => 'AP-IN', 'description' => 'Perangkat pemancar WiFi dalam ruangan'],
            ['name' => 'Access Point (AP) / WiFi Outdoor', 'code' => 'AP-OUT', 'description' => 'Perangkat pemancar WiFi luar ruangan'],
            ['name' => 'Optical Termination Box (OTB) / Joint Closure', 'code' => 'OTB', 'description' => 'Kotak terminasi sambungan fiber optic'],
            ['name' => 'Optical Distribution Point (ODP) / Splitter', 'code' => 'ODP', 'description' => 'Titik pembagi kabel distribusi FO'],
            ['name' => 'Media Converter / SFP Transceiver', 'code' => 'MC-SFP', 'description' => 'Konverter media FO ke Ethernet / Transceiver'],
            ['name' => 'Kabel Fiber Optic (Drop Core / Feeder)', 'code' => 'FO-CBL', 'description' => 'Bentangan kabel fiber optic outdoor/indoor'],
            ['name' => 'Kabel UTP / Patch Cord / LAN RJ45', 'code' => 'UTP-CBL', 'description' => 'Kabel jaringan tembaga LAN / Patch cord'],
            ['name' => 'Power Supply / PoE Injector / UPS', 'code' => 'PWR-UPS', 'description' => 'Pencatu daya perangkat jaringan dan backup listrik'],
            ['name' => 'Server / OLT / Rack Server', 'code' => 'SRV-OLT', 'description' => 'Server fisik atau OLT pemancar FO'],
            ['name' => 'Keystone Jack RJ-45 / Wallplate', 'code' => 'WALL-RJ', 'description' => 'Wallplate atau socket port LAN di dinding/meja'],
            ['name' => 'Patch Panel 24-Port / 48-Port', 'code' => 'P-PANEL', 'description' => 'Panel terminasi kabel jaringan UTP di rak server'],
            ['name' => 'Modem GPON / ONT', 'code' => 'ONT-GPON', 'description' => 'Optical Network Terminal / Modem FO pelanggan'],
        ];

        foreach ($devices as $d) {
            NetworkDevice::firstOrCreate(
                ['name' => $d['name']],
                [
                    'code' => $d['code'],
                    'description' => $d['description'],
                    'status' => 'active',
                ]
            );
        }

        $materials = [
            ['name' => 'Konektor RJ-45 Cat6', 'default_unit' => 'pcs', 'description' => 'Konektor modular UTP Cat6'],
            ['name' => 'Patch Cord UTP Cat6', 'default_unit' => 'pcs', 'description' => 'Kabel LAN pabrikan siap pakai 1m - 5m'],
            ['name' => 'Kabel UTP / LAN Cat6', 'default_unit' => 'meter', 'description' => 'Kabel tarikan LAN Cat6 roll'],
            ['name' => 'Kabel Drop Core Fiber Optic (1-Core / 2-Core)', 'default_unit' => 'meter', 'description' => 'Kabel FO drop core outdoor'],
            ['name' => 'Patch Cord Fiber Optic (SC-SC / LC-SC)', 'default_unit' => 'pcs', 'description' => 'Kabel jumper FO'],
            ['name' => 'Pigtail Fiber Optic SC/UPC', 'default_unit' => 'pcs', 'description' => 'Kabel pigtail untuk splicing OTB'],
            ['name' => 'Fast Connector SC/UPC', 'default_unit' => 'pcs', 'description' => 'Konektor manual FO tanpa splicing'],
            ['name' => 'Fast Connector SC/APC', 'default_unit' => 'pcs', 'description' => 'Konektor manual FO warna hijau'],
            ['name' => 'Protection Sleeve FO (Splicing)', 'default_unit' => 'pcs', 'description' => 'Pelindung sambungan core kaca'],
            ['name' => 'Optical Termination Box (OTB)', 'default_unit' => 'unit', 'description' => 'Box OTB 4/8/12/24 core'],
            ['name' => 'Optical Distribution Point (ODP)', 'default_unit' => 'unit', 'description' => 'Box ODP tiang'],
            ['name' => 'Adaptor Fiber Optic SC/UPC', 'default_unit' => 'pcs', 'description' => 'Barel coupler sambungan SC'],
            ['name' => 'SFP Transceiver Module (1.25G / 10G)', 'default_unit' => 'unit', 'description' => 'Modul optik switch/router'],
            ['name' => 'Media Converter FO to LAN', 'default_unit' => 'unit', 'description' => 'Sepasang konverter optik ke RJ45'],
            ['name' => 'PoE Injector (24V / 48V)', 'default_unit' => 'unit', 'description' => 'Adaptor PoE untuk AP/CCTV'],
            ['name' => 'Power Supply / Adaptor (12V / 24V)', 'default_unit' => 'unit', 'description' => 'Adaptor daya perangkat'],
            ['name' => 'Access Point (AP)', 'default_unit' => 'unit', 'description' => 'Perangkat unit AP pengganti'],
            ['name' => 'Switch Hub (8-Port / 16-Port / 24-Port)', 'default_unit' => 'unit', 'description' => 'Switch unit pengganti'],
            ['name' => 'Router Board / Mikrotik', 'default_unit' => 'unit', 'description' => 'Router board unit'],
            ['name' => 'Modular Jack / Keystone RJ-45', 'default_unit' => 'pcs', 'description' => 'Soket modular keystone Cat6 wallplate'],
            ['name' => 'Faceplate / Wallplate 1-Port / 2-Port', 'default_unit' => 'pcs', 'description' => 'Plat penutup outlet LAN di dinding'],
            ['name' => 'Modem ONT GPON', 'default_unit' => 'unit', 'description' => 'Unit modem ONT GPON pengganti'],
            ['name' => 'Barrel Connector RJ-45', 'default_unit' => 'pcs', 'description' => 'Coupler penyambung kabel LAN RJ-45'],
            ['name' => 'Stop Kontak / Steker Listrik', 'default_unit' => 'pcs', 'description' => 'Komponen colokan listrik'],
            ['name' => 'Kabel Ties / Velcro', 'default_unit' => 'pack', 'description' => 'Pengikat kabel instalasi'],
            ['name' => 'Pipa Conduit / Cable Protector Duct', 'default_unit' => 'batang', 'description' => 'Pelindung jalur kabel'],
            ['name' => 'Isolasi Listrik / Heat Shrink', 'default_unit' => 'roll', 'description' => 'Isolator sambungan kabel'],
            ['name' => 'Fisher & Sekrup Rak / Dinding', 'default_unit' => 'pack', 'description' => 'Baut cagenut dan fisher pemasangan fisik'],
        ];

        foreach ($materials as $m) {
            Material::firstOrCreate(
                ['name' => $m['name']],
                [
                    'default_unit' => $m['default_unit'],
                    'description' => $m['description'],
                    'status' => 'active',
                ]
            );
        }
    }
}
