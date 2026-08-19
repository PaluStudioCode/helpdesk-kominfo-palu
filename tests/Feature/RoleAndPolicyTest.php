<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@test.com',
            'password' => bcrypt('password'),
            'status' => 'inactive',
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@test.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_middleware_role_protection(): void
    {
        $opdUser = User::where('email', 'operator@dinkes.palukota.go.id')->first();
        
        $response = $this->actingAs($opdUser)->get('/profile');
        $response->assertStatus(200);

        // Nanti kita akan test rute spesifik di phase berikutnya. 
        // Untuk sekarang, kita pastikan function check policy berfungsi
    }

    public function test_ticket_policy_isolates_department(): void
    {
        $dinkes = Department::where('code', 'DINKES')->first();
        $disdik = Department::where('code', 'DISDIK')->first();
        
        $opdDinkes = User::where('email', 'operator@dinkes.palukota.go.id')->first();
        $opdDisdik = User::where('email', 'operator@disdik.palukota.go.id')->first();
        $admin = User::where('email', 'admin@kominfo.palukota.go.id')->first();

        $category = TicketCategory::first();

        $ticketDinkes = Ticket::create([
            'ticket_number' => 'TKT-001',
            'department_id' => $dinkes->id,
            'reporter_id' => $opdDinkes->id,
            'category_id' => $category->id,
            'network_type' => 'wifi',
            'title' => 'WiFi Rusak',
            'location_details' => 'Ruang 1',
            'description' => 'Mati Total',
        ]);

        // OPD Dinkes can view their own ticket
        $this->assertTrue($opdDinkes->can('view', $ticketDinkes));
        
        // OPD Disdik CANNOT view Dinkes ticket
        $this->assertFalse($opdDisdik->can('view', $ticketDinkes));

        // Admin can view any ticket
        $this->assertTrue($admin->can('view', $ticketDinkes));
    }

    public function test_department_policy(): void
    {
        $opdUser = User::where('role', 'opd_user')->first();
        $admin = User::where('role', 'admin')->first();
        $department = Department::first();

        $this->assertFalse($opdUser->can('update', $department));
        $this->assertTrue($admin->can('update', $department));
    }

    public function test_ticket_category_policy(): void
    {
        $opdUser = User::where('role', 'opd_user')->first();
        $admin = User::where('role', 'admin')->first();
        $category = TicketCategory::first();

        $this->assertFalse($opdUser->can('viewAny', TicketCategory::class));
        $this->assertTrue($admin->can('viewAny', TicketCategory::class));
        $this->assertFalse($opdUser->can('update', $category));
        $this->assertTrue($admin->can('update', $category));
    }
}
