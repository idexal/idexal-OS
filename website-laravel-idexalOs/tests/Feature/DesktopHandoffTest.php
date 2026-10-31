<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_desktop_authorization_issues_a_single_use_grant(): void
    {
        [$user, $organization] = $this->workspace();

        $this->actingAs($user)
            ->withSession(['desktop_auth' => true, 'desktop_scheme' => 'idexalos'])
            ->post('/desktop/authorize', [
                'organization_id' => $organization->getKey(),
                'device_name' => 'Test Desktop',
            ])
            ->assertRedirect('/desktop/complete');

        $complete = $this->get('/desktop/complete')
            ->assertOk()
            ->assertSee('idexalos://den-auth?grant=', false);

        $this->assertMatchesRegularExpression('/grant=(dh_[A-Za-z0-9]+)/', $complete->getContent());
        preg_match('/grant=(dh_[A-Za-z0-9]+)/', $complete->getContent(), $matches);

        $exchange = $this->postJson('/v1/auth/desktop-handoff/exchange', [
            'grant' => $matches[1],
            'deviceName' => 'Test Desktop',
        ]);

        $exchange->assertOk()
            ->assertJsonPath('organization.id', $organization->getKey())
            ->assertJsonPath('connectEnabled', false)
            ->assertJsonStructure(['user' => ['id', 'email', 'name'], 'token', 'organization']);

        $token = $exchange->json('token');
        $this->assertIsString($token);

        $this->assertDatabaseHas('api_keys', [
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('desktop_sessions', [
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $this->withToken($token)->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->getKey());
        $this->withToken($token)->getJson('/v1/me/desktop-config')
            ->assertOk()
            ->assertJsonPath('connectEnabled', false)
            ->assertJsonPath('dashboardEnabled', false);
        $this->withToken($token)->getJson('/v1/features')
            ->assertOk()
            ->assertJsonPath('features.cloud', true);

        $this->postJson('/v1/auth/desktop-handoff/exchange', ['grant' => $matches[1]])
            ->assertStatus(410)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_expired_grants_are_rejected(): void
    {
        [$user, $organization] = $this->workspace();

        $this->actingAs($user)
            ->withSession(['desktop_auth' => true, 'desktop_scheme' => 'idexalos'])
            ->post('/desktop/authorize', [
                'organization_id' => $organization->getKey(),
            ])
            ->assertRedirect('/desktop/complete');

        $complete = $this->get('/desktop/complete')->assertOk();
        preg_match('/grant=(dh_[A-Za-z0-9]+)/', $complete->getContent(), $matches);

        $this->travel(11)->minutes();

        $this->postJson('/v1/auth/desktop-handoff/exchange', ['grant' => $matches[1]])
            ->assertStatus(410)
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_authenticated_desktop_requests_skip_the_login_screen(): void
    {
        [$user] = $this->workspace();

        $this->actingAs($user)
            ->get('/?mode=sign-in&desktopAuth=1&desktopScheme=idexalos')
            ->assertRedirect('/desktop/authorize');
    }

    public function test_the_desktop_flag_survives_the_login_redirect(): void
    {
        [$user] = $this->workspace();
        $user->forceFill(['password' => 'Password!123'])->save();

        $this->get('/?mode=sign-in&desktopAuth=1&desktopScheme=idexalos')
            ->assertRedirect('/login');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password!123',
        ])->assertRedirect('/desktop/authorize');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function workspace(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $user->getKey()]);

        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        return [$user, $organization];
    }
}
