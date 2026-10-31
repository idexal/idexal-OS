<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_receives_a_personal_workspace(): void
    {
        $response = $this->post('/register', [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();

        $this->assertSame(User::ROLE_USER, $user->role);
        $this->assertDatabaseHas('organizations', [
            'owner_id' => $user->getKey(),
            'type' => Organization::TYPE_PERSONAL,
        ]);
        $this->assertDatabaseHas('organization_user', [
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->getKey(),
            'type' => 'welcome',
        ]);
    }

    public function test_registration_is_rejected_when_disabled(): void
    {
        Setting::put('registration_enabled', false, false, 'general');

        $this->get('/register')->assertRedirect('/login');
        $this->post('/register', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
        ])->assertForbidden();
    }
}
