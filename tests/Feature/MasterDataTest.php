<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Material;
use App\Models\NetworkDevice;
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

    public function test_admin_can_create_ticket_category(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'Kabel FO Putus Jalur Utama',
            'network_type' => 'Fiber optic',
            'status' => 'active',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('ticket_categories', [
            'name' => 'Kabel FO Putus Jalur Utama',
            'infrastructure_type' => 'Fiber optic',
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
        $this->assertModelExists($dept);
    }

    public function test_admin_can_delete_department_without_tickets(): void
    {
        $admin = $this->createAdmin();
        $dept = $this->createDepartment(['name' => 'Dinas Arsip Testing']);

        $response = $this->actingAs($admin)->delete("/admin/departments/{$dept->id}");

        $response->assertSessionHas('success');
        $this->assertModelMissing($dept);
    }

    public function test_admin_cannot_delete_category_with_active_tickets(): void
    {
        $admin = $this->createAdmin();
        $category = $this->createCategory();
        $this->createTicket(['category_id' => $category->id, 'status' => 'pending_approval']);

        $response = $this->actingAs($admin)->delete("/admin/categories/{$category->id}");

        $response->assertSessionHas('error');
        $this->assertModelExists($category);
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

        // Test in standalone Category index (redirects to Master Data Hub)
        $responseCategory = $this->actingAs($admin)->get('/admin/categories?infrastructure_type=Power/poe');
        $responseCategory->assertRedirect(route('admin.master-data.index', [
            'tab' => 'categories',
            'infrastructure_type' => 'Power/poe',
        ]));
    }

    public function test_admin_can_manage_network_devices(): void
    {
        $admin = $this->createAdmin();

        // 1. Create
        $response = $this->actingAs($admin)->post('/admin/devices', [
            'name' => 'OLT GPON Huawei 16-Port',
            'code' => 'OLT-HW',
            'description' => 'Perangkat pemancar optik GPON utama Kominfo',
            'status' => 'active',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('network_devices', ['name' => 'OLT GPON Huawei 16-Port']);

        $device = NetworkDevice::where('name', 'OLT GPON Huawei 16-Port')->first();

        // 2. Update
        $updateResponse = $this->actingAs($admin)->put("/admin/devices/{$device->id}", [
            'name' => 'OLT GPON Huawei 16-Port V2',
            'code' => 'OLT-HW-V2',
            'description' => 'Updated description',
            'status' => 'inactive',
        ]);
        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('network_devices', [
            'id' => $device->id,
            'name' => 'OLT GPON Huawei 16-Port V2',
            'status' => 'inactive',
        ]);

        // 3. Delete
        $deleteResponse = $this->actingAs($admin)->delete("/admin/devices/{$device->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertModelMissing($device);
    }

    public function test_admin_can_manage_materials(): void
    {
        $admin = $this->createAdmin();

        // 1. Create
        $response = $this->actingAs($admin)->post('/admin/materials', [
            'name' => 'Kabel UTP Outdoor STP Cat6',
            'default_unit' => 'meter',
            'description' => 'Kabel STP pelindung cuaca',
            'status' => 'active',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('materials', ['name' => 'Kabel UTP Outdoor STP Cat6']);

        $material = Material::where('name', 'Kabel UTP Outdoor STP Cat6')->first();

        // 2. Update
        $updateResponse = $this->actingAs($admin)->put("/admin/materials/{$material->id}", [
            'name' => 'Kabel UTP Outdoor STP Cat6 Shielded',
            'default_unit' => 'meter',
            'description' => 'Updated description',
            'status' => 'active',
        ]);
        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'name' => 'Kabel UTP Outdoor STP Cat6 Shielded',
        ]);

        // 3. Delete
        $deleteResponse = $this->actingAs($admin)->delete("/admin/materials/{$material->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertModelMissing($material);
    }

    public function test_admin_can_manage_vendors(): void
    {
        $admin = $this->createAdmin();

        // 1. Create
        $response = $this->actingAs($admin)->post('/admin/vendors', [
            'name' => 'PT Telkom Indonesia Witel Palu',
            'category' => 'ISP / Penyedia Bandwidth',
            'phone' => '0451-421000',
            'address' => 'Jl. Sam Ratulangi No. 1 Palu',
            'description' => 'Mitra backbone internet ASTINet',
            'status' => 'active',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendors', ['name' => 'PT Telkom Indonesia Witel Palu']);

        $vendor = \App\Models\Vendor::where('name', 'PT Telkom Indonesia Witel Palu')->first();

        // 2. Update
        $updateResponse = $this->actingAs($admin)->put("/admin/vendors/{$vendor->id}", [
            'name' => 'PT Telkom Indonesia Tbk Palu',
            'category' => 'ISP / Penyedia Bandwidth',
            'phone' => '0451-421111',
            'address' => 'Jl. Sam Ratulangi No. 1 Palu',
            'description' => 'Mitra utama ASTINet & Indihome',
            'status' => 'active',
        ]);
        $updateResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendors', [
            'id' => $vendor->id,
            'name' => 'PT Telkom Indonesia Tbk Palu',
            'phone' => '0451-421111',
        ]);

        // 3. Delete
        $deleteResponse = $this->actingAs($admin)->delete("/admin/vendors/{$vendor->id}");
        $deleteResponse->assertSessionHasNoErrors();
        $this->assertModelMissing($vendor);
    }

    public function test_ticket_show_loads_available_devices_and_materials_from_database(): void
    {
        $admin = $this->createAdmin();
        $ticket = $this->createTicket();

        NetworkDevice::firstOrCreate(['name' => 'Core Switch Testing Node'], [
            'code' => 'CS-TEST',
            'status' => 'active',
        ]);

        Material::firstOrCreate(['name' => 'Patch Cord Test 10m'], [
            'default_unit' => 'pcs',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get("/tickets/{$ticket->id}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Tickets/Show')
            ->has('availableDevices')
            ->has('availableMaterials')
            ->where('availableDevices', fn ($devices) => collect($devices)->contains('Core Switch Testing Node'))
            ->where('availableMaterials', fn ($materials) => collect($materials)->pluck('name')->contains('Patch Cord Test 10m'))
        );
    }

    public function test_legacy_master_data_routes_redirect_to_unified_hub(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)->get('/admin/departments')
            ->assertRedirect(route('admin.master-data.index', ['tab' => 'departments']));

        $this->actingAs($admin)->get('/admin/users')
            ->assertRedirect(route('admin.master-data.index', ['tab' => 'users']));

        $this->actingAs($admin)->get('/admin/categories')
            ->assertRedirect(route('admin.master-data.index', ['tab' => 'categories']));
    }
}

