<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_access_master_data_users_tab(): void
    {
        $admin = User::where('role', 'admin')->first();

        $response = $this->actingAs($admin)->get('/admin/master-data?tab=users');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/MasterData/Index')
            ->where('activeTab', 'users')
            ->has('users.data')
            ->has('allDepartments')
        );
    }

    public function test_admin_can_create_user_with_technician_role(): void
    {
        $admin = User::where('role', 'admin')->first();

        $userData = [
            'name' => 'Budi Teknisi Jaringan',
            'email' => 'budi.teknisi@palukota.go.id',
            'password' => 'password123',
            'phone_number' => '081234567890',
            'role' => 'technician',
            'status' => 'active',
            'department_id' => null,
        ];

        $response = $this->actingAs($admin)
            ->from('/admin/master-data?tab=users')
            ->post(route('admin.users.store'), $userData);

        $response->assertRedirect('/admin/master-data?tab=users');
        $response->assertSessionHas('success', 'Pengguna berhasil didaftarkan.');

        $this->assertDatabaseHas('users', [
            'name' => 'Budi Teknisi Jaringan',
            'email' => 'budi.teknisi@palukota.go.id',
            'phone_number' => '081234567890',
            'role' => 'technician',
            'status' => 'active',
            'department_id' => null,
        ]);

        $createdUser = User::where('email', 'budi.teknisi@palukota.go.id')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue(Hash::check('password123', $createdUser->password));
    }

    public function test_technician_cannot_be_assigned_department_on_create(): void
    {
        $admin = User::where('role', 'admin')->first();
        $department = Department::first();

        $userData = [
            'name' => 'Ahmad Teknisi Baru',
            'email' => 'ahmad.teknisibaru@palukota.go.id',
            'password' => 'password123',
            'phone_number' => '081298765432',
            'role' => 'technician',
            'status' => 'active',
            'department_id' => $department->id,
        ];

        $response = $this->actingAs($admin)
            ->from('/admin/master-data?tab=users')
            ->post(route('admin.users.store'), $userData);

        $response->assertRedirect('/admin/master-data?tab=users');

        // Pastikan department_id tetap null di database karena teknisi tidak terikat ke OPD tertentu
        $this->assertDatabaseHas('users', [
            'email' => 'ahmad.teknisibaru@palukota.go.id',
            'role' => 'technician',
            'department_id' => null,
        ]);
    }

    public function test_validation_fails_for_invalid_technician_data(): void
    {
        $admin = User::where('role', 'admin')->first();

        $response = $this->actingAs($admin)
            ->from('/admin/master-data?tab=users')
            ->post(route('admin.users.store'), [
                'name' => '',
                'email' => 'invalid-email',
                'password' => 'short',
                'phone_number' => '12345',
                'role' => 'invalid_role',
                'status' => 'unknown',
            ]);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'phone_number', 'role', 'status']);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $technician = User::where('role', 'technician')->first();

        $response = $this->actingAs($technician)
            ->post(route('admin.users.store'), [
                'name' => 'Unauthorized User',
                'email' => 'unauthorized@example.com',
                'password' => 'password123',
                'phone_number' => '081234567890',
                'role' => 'technician',
                'status' => 'active',
            ]);

        // Non-admin blocked by middleware / policy (403)
        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', [
            'email' => 'unauthorized@example.com',
        ]);
    }
}
