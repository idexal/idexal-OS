<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationSeatLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepting_an_invitation_cannot_exceed_the_plan_seat_limit(): void
    {
        $plan = Plan::factory()->create([
            'slug' => 'free',
            'is_active' => true,
            'is_public' => true,
            'limits' => [
                'requests_per_month' => 1000,
                'tokens_per_month' => 250000,
                'seats' => 1,
                'api_keys' => 2,
                'proxy_profiles' => 1,
                'proxy_bandwidth_gb' => 5,
            ],
        ]);

        $owner = User::factory()->create();
        $organization = Organization::factory()->create(['owner_id' => $owner->getKey()]);
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $owner->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $invitee = User::factory()->create(['email' => 'invitee@example.test']);
        $token = 'seat-limit-token';
        $invitation = Invitation::query()->create([
            'organization_id' => $organization->getKey(),
            'email' => $invitee->email,
            'role' => Membership::ROLE_MEMBER,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $owner->getKey(),
            'expires_at' => now()->addDay(),
        ]);

        $blocked = $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $token]));

        $blocked
            ->assertRedirect(route('dashboard.index'))
            ->assertSessionHasErrors('team');

        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $organization->getKey(),
            'user_id' => $invitee->getKey(),
        ]);
        $this->assertNull($invitation->fresh()->accepted_at);

        $plan->forceFill([
            'limits' => [...$plan->limits, 'seats' => 2],
        ])->save();

        $this->actingAs($invitee)
            ->post(route('invitations.accept', ['token' => $token]))
            ->assertRedirect(route('dashboard.index'));

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $organization->getKey(),
            'user_id' => $invitee->getKey(),
            'status' => Membership::STATUS_ACTIVE,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }
}
