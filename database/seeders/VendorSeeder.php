<?php

namespace Database\Seeders;

use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = [
            [
                'name' => 'PT Telkom Indonesia',
                'category' => 'Penyedia Jaringan / ISP',
                'phone' => '0451-421147',
                'address' => 'Jl. Sam Ratulangi No. 1, Kota Palu',
                'description' => 'Penyedia backbone jaringan internet FO dan link Astinet / Indihome OPD Pemkot Palu',
                'status' => 'active',
            ],
            [
                'name' => 'PT Indonesia Comnets Plus (Icon+)',
                'category' => 'Penyedia Jaringan / ISP',
                'phone' => '0451-456789',
                'address' => 'Jl. R.A. Kartini, Kota Palu',
                'description' => 'Penyedia jaringan fiber optic intra-pemerintah dan metro ethernet',
                'status' => 'active',
            ],
            [
                'name' => 'PT PLN (Persero) UP3 Palu',
                'category' => 'Kelistrikan & Daya',
                'phone' => '0451-421014',
                'address' => 'Jl. RA Kartini No. 28, Kota Palu',
                'description' => 'Penyedia suplai kelistrikan ruang server, gardu distribusi, dan tiang tumpu kabel FO',
                'status' => 'active',
            ],
        ];

        foreach ($vendors as $v) {
            Vendor::firstOrCreate(
                ['name' => $v['name']],
                $v
            );
        }
    }
}
