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
               
        // 10 Technicians
        $technicians = [
            ['name' => 'Ahmad Teknisi', 'email' => 'teknisi@example.com', 'phone' => '6280033330001'],
            ['name' => 'Budi Teknisi', 'email' => 'teknisi2@example.com', 'phone' => '6280033330002'],
            ['name' => 'Candra Teknisi', 'email' => 'teknisi3@example.com', 'phone' => '6280033330003'],
            ['name' => 'Dedi Teknisi', 'email' => 'teknisi4@example.com', 'phone' => '6280033330004'],
            ['name' => 'Eko Teknisi', 'email' => 'teknisi5@example.com', 'phone' => '6280033330005'],
            ['name' => 'Fajar Teknisi', 'email' => 'teknisi6@example.com', 'phone' => '6280033330006'],
            ['name' => 'Gilang Teknisi', 'email' => 'teknisi7@example.com', 'phone' => '6280033330007'],
            ['name' => 'Hendra Teknisi', 'email' => 'teknisi8@example.com', 'phone' => '6280033330008'],
            ['name' => 'Irwan Teknisi', 'email' => 'teknisi9@example.com', 'phone' => '6280033330009'],
            ['name' => 'Joko Teknisi', 'email' => 'teknisi10@example.com', 'phone' => '6280033330010'],
        ];

        $defaultPassword = Hash::make('password');

        foreach ($technicians as $tech) {
            User::firstOrCreate(
                ['email' => $tech['email']],
                [
                    'name' => $tech['name'],
                    'password' => $defaultPassword,
                    'role' => 'technician',
                    'phone_number' => $tech['phone'],
                    'status' => 'active',
                ]
            );
        }

        if (!app()->runningUnitTests()) {
            $this->call([
                TicketSeeder::class,
            ]);
        }
    }
}
