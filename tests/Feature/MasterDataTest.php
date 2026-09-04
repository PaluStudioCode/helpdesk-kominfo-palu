<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\User;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    public function test_non_admin_cannot_access_master_data(): void
    {
        $opdUser = $this->createOpdUser();
        $technician = $this->createTechnician();

        $this->actingAs($opdUser)->get('/admin/master-data')->assertStatus(403);
        $this->actingAs($technician)->get('/admin/master-data')->assertStatus(403);
    }

    public function test_admin_can_access_master_data(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->get('/admin/master-data');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_department(): void
    {
        $admin = $this->createAdmin();
        $code = 'D' . rand(100, 999);

        $response = $this->actingAs($admin)->post('/admin/departments', [
            'code' => $code,
            'name' => 'Dinas Pendidikan & Kebudayaan',
            'address' => 'Jl. Balai Kota No. 2 Palu',
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('departments', ['code' => $code]);
    }

    public function test_admin_can_create_ticket_category_with_sla(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Kabel FO Putus Jalur Utama',
            'network_type' => 'Fiber optic',
            'sla_hours' => 3,
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_categories', [
            'name' => 'Kabel FO Putus Jalur Utama',
            'infrastructure_type' => 'Fiber optic',
            'sla_hours' => 3,
        ]);
    }

    public function test_admin_can_create_user_and_assign_to_department(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment();
        $email = 'operator_' . uniqid() . '@palukota.go.id';

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Operator Disdik Baru',
            'email' => $email,
            'password' => 'password123',
            'role' => 'opd_user',
            'department_id' => $dept->id,
            'phone_number' => '081234567899',
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'department_id' => $dept->id,
            'role' => 'opd_user',
        ]);
    }

    public function test_admin_can_update_user_phone_number(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createTechnician(['phone_number' => '081234567890']);

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => '089876543210',
            'role' => $user->role,
            'status' => $user->status,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('089876543210', $user->fresh()->phone_number);
    }

    public function test_admin_cannot_delete_department_with_active_tickets(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment();
        $this->createTicket(['department_id' => $dept->id, 'status' => 'in_progress']);

        $response = $this->actingAs($admin)->delete("/admin/departments/{$dept->id}");

        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted($dept);
    }

    public function test_admin_can_delete_department_with_closed_tickets_and_preserve_history(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment(['name' => 'Dinas Arsip Testing']);
        $ticket = $this->createTicket(['department_id' => $dept->id, 'status' => 'closed']);

        $response = $this->actingAs($admin)->delete("/admin/departments/{$dept->id}");

        $response->assertSessionHas('success');
        $this->assertSoftDeleted($dept);

        // Historical ticket should still be able to resolve department name
        $ticket->refresh();
        $this->assertNotNull($ticket->department);
        $this->assertEquals('Dinas Arsip Testing', $ticket->department->name);
    }

    public function test_admin_cannot_delete_category_with_active_tickets(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory();
        $this->createTicket(['category_id' => $category->id, 'status' => 'pending_approval']);

        $response = $this->actingAs($admin)->delete("/admin/categories/{$category->id}");

        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted($category);
    }

    public function test_admin_can_filter_categories_by_infrastructure_type(): void
    {
        $admin = $this->createAdmin();
        $catFo = $this->createCategory([
            'name' => 'Kategori Fiber Optic Unik',
            'infrastructure_type' => 'Fiber optic',
        ]);
        $catPower = $this->createCategory([
            'name' => 'Kategori Power Poe Unik',
            'infrastructure_type' => 'Power/poe',
        ]);

        // Test in Master Data Hub categories tab
        $response = $this->actingAs($admin)->get('/admin/master-data?tab=categories&infrastructure_type=Fiber optic');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/MasterData/Index')
            ->where('categories.data', function ($cats) {
                $names = collect($cats)->pluck('name');
                return $names->contains('Kategori Fiber Optic Unik')
                    && !$names->contains('Kategori Power Poe Unik');
            })
        );

        // Test in standalone Category index
        $responseCategory = $this->actingAs($admin)->get('/admin/categories?infrastructure_type=Power/poe');
        $responseCategory->assertStatus(200);
        $responseCategory->assertInertia(fn ($page) => $page
            ->component('Admin/Categories/Index')
            ->where('categories.data', function ($cats) {
                $names = collect($cats)->pluck('name');
                return $names->contains('Kategori Power Poe Unik')
                    && !$names->contains('Kategori Fiber Optic Unik');
            })
        );
    }
}

