<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_user_can_view_login_page(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $admin = $this->createAdmin(['password' => bcrypt('password123')]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect('/dashboard');
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $admin = $this->createAdmin(['password' => bcrypt('password123')]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_inactive_user_is_blocked_from_login(): void
    {
        $inactiveUser = $this->createOpdUser(null, [
            'password' => bcrypt('password123'),
            'status' => 'inactive',
        ]);

        $response = $this->post('/login', [
            'email' => $inactiveUser->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_guest_is_redirected_to_login_when_accessing_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_profile_with_phone_number(): void
    {
        $admin = $this->createAdmin(['phone_number' => '081234567890']);

        $response = $this->actingAs($admin)->get('/profile');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Profile/Edit')
            ->where('auth.user.phone_number', '081234567890')
        );
    }

    public function test_user_can_update_profile_information(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->patch('/profile', [
            'name' => 'Nama Baru Admin',
            'email' => 'new_email_' . uniqid() . '@palukota.go.id',
            'phone_number' => '089876543210',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('Nama Baru Admin', $admin->fresh()->name);
        $this->assertEquals('089876543210', $admin->fresh()->phone_number);
    }

    public function test_user_can_logout(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
