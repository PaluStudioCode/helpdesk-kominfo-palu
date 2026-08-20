<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Department;
use App\Models\TicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        TicketCategory::create([
            'name' => 'Kabel Fiber Optic Putus',
            'network_type' => 'fiber_optic',
            'sla_hours' => 24, // 24 hours SLA
            'status' => 'active',
        ]);

        TicketCategory::create([
            'name' => 'Koneksi LAN Gedung Bermasalah',
            'network_type' => 'lan',
            'sla_hours' => 12, // 12 hours SLA
            'status' => 'active',
        ]);

        TicketCategory::create([
            'name' => 'Access Point WiFi Tidak Memancarkan Sinyal',
            'network_type' => 'wifi',
            'sla_hours' => 8, // 8 hours SLA
            'status' => 'active',
        ]);

        TicketCategory::create([
            'name' => 'Internet Mati Total (Emergency)',
            'network_type' => 'fiber_optic',
            'sla_hours' => 6, // 6 hours SLA
            'status' => 'active',
        ]);

        User::create([
            'name' => 'Administrator Kominfo',
            'email' => 'admin@kominfo.go.id',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone_number' => '6280011112222',
            'status' => 'active',
        ]);

        $this->call([
            DepartmentSeeder::class,
        ]);     
               
        // Technician
        User::firstOrCreate(
            ['email' => 'teknisi@example.com'],
            [
                'name' => 'Ahmad Teknisi',
                'password' => Hash::make('password'),
                'role' => 'technician',
                'phone_number' => '6280033334444',
                'status' => 'active',
            ]
        );
    }
}
