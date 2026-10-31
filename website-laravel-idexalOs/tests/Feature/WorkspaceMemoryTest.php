<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkspaceMemory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_memory_round_trips_and_upserts_per_workspace(): void
    {
        [$user, $organization, $token] = $this->workspace();

        $this->withToken($token)
            ->getJson('/v1/workspace-memory?workspaceId=ws_test')
            ->assertOk()
            ->assertJsonPath('workspaceId', 'ws_test')
            ->assertJsonPath('content', null);

        $content = "# idexal Os workspace memory\n\n### entry\nA durable decision.";
        $this->withToken($token)
            ->putJson('/v1/workspace-memory', [
                'workspaceId' => 'ws_test',
                'content' => $content,
            ])
            ->assertOk()
            ->assertJsonPath('content', $content)
            ->assertJsonPath('checksum', hash('sha256', $content));

        $this->withToken($token)
            ->putJson('/v1/workspace-memory', [
                'workspaceId' => 'ws_test',
                'content' => $content.' updated',
            ])
            ->assertOk();

        $this->assertSame(1, WorkspaceMemory::query()->count());

        $response = $this->withToken($token)
            ->getJson('/v1/workspace-memory?workspaceId=ws_test')
            ->assertOk();

        $this->assertStringEndsWith(' updated', $response->json('content'));
        $this->assertMatchesRegularExpression('/Z$/', $response->json('updatedAt'));
    }

    public function test_another_workspace_cannot_read_memory(): void
    {
        [, , $token] = $this->workspace();
        [$otherUser, , $otherToken] = $this->workspace('other');

        $this->withToken($token)
            ->putJson('/v1/workspace-memory', [
                'workspaceId' => 'ws_private',
                'content' => 'Only this organization should see it.',
            ])
            ->assertOk();

        $this->withToken($otherToken)
            ->getJson('/v1/workspace-memory?workspaceId=ws_private')
            ->assertOk()
            ->assertJsonPath('content', null);
    }

    /**
     * @return array{0: User, 1: Organization, 2: string}
     */
    private function workspace(string $suffix = 'main'): array
    {
        $plan = Plan::factory()->create(['slug' => 'free-'.$suffix]);
        $user = User::factory()->create(['email' => 'memory-'.$suffix.'@idexal.test']);
        $organization = Organization::factory()->create([
            'owner_id' => $user->getKey(),
            'slug' => 'memory-org-'.$suffix,
        ]);
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'role' => Membership::ROLE_OWNER,
            'status' => Membership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        Subscription::factory()->create([
            'organization_id' => $organization->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_end' => now()->addMonth(),
        ]);
        [, $token] = ApiKey::issue($organization, $user, 'Desktop');

        return [$user, $organization, $token];
    }
}
