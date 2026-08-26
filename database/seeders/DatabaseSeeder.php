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

        // Admin
        User::firstOrCreate(
            ['email' => 'admin@kominfo.go.id'],
            [
                'name' => 'Administrator Kominfo',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone_number' => '6280011112222',
                'status' => 'active',
            ]
        );

        $this->call([
            TicketCategorySeeder::class,
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
