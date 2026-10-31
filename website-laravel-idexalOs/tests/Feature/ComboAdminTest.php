<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Organization;
use App\Models\User;
use App\Services\ComboModelService;
use App\Services\GatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComboAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_build_a_combo_from_models_on_different_providers(): void
    {
        $admin = User::factory()->admin()->create();
        $providerA = AiProvider::factory()->create([
            'name' => 'Provider A',
            'slug' => 'provider-a',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://provider-a.test/v1',
            'api_key' => 'secret-a',
            'is_active' => true,
        ]);
        $providerB = AiProvider::factory()->create([
            'name' => 'Provider B',
            'slug' => 'provider-b',
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://provider-b.test/v1',
            'api_key' => 'secret-b',
            'is_active' => true,
        ]);
        $modelA = AiModel::factory()->create([
            'ai_provider_id' => $providerA->getKey(),
            'model_id' => 'coding-a',
            'display_name' => 'Coding A',
            'capabilities' => ['chat' => true, 'streaming' => true, 'tools' => true],
            'is_active' => true,
        ]);
        $modelB = AiModel::factory()->create([
            'ai_provider_id' => $providerB->getKey(),
            'model_id' => 'coding-b',
            'display_name' => 'Coding B',
            'capabilities' => ['chat' => true, 'streaming' => true, 'vision' => true],
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get('/admin/combos')->assertOk();

        $this->actingAs($admin)
            ->post('/admin/combos', [
                'name' => 'idexal-coding',
                'slug' => 'idexal-coding',
                'strategy' => 'role',
                'member_ids' => [$modelA->getKey(), $modelB->getKey()],
                'roles' => [
                    $modelA->getKey() => 'coding',
                    $modelB->getKey() => 'vision',
                ],
            ])
            ->assertRedirect();

        $combo = AiModel::query()->where('model_id', 'idexal-coding')->firstOrFail();
        $this->assertTrue($combo->is_active);
        $this->assertCount(2, $combo->hybrid_config['members']);
        $this->assertTrue($combo->supports('tools'));
        $this->assertTrue($combo->supports('vision'));
        $this->assertSame('role', $combo->hybrid_config['strategy']);
    }

    public function test_the_combos_page_lists_selectable_members_and_existing_combos(): void
    {
        $admin = User::factory()->admin()->create();
        [$modelA, $modelB] = $this->pair();

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'idexal-coding',
            'slug' => 'idexal-coding',
            'strategy' => 'round_robin',
            'member_ids' => [$modelA->getKey(), $modelB->getKey()],
        ])->assertRedirect();

        $response = $this->actingAs($admin)->get('/admin/combos')->assertOk();

        // The member picker offers the non-combo models it can be built from.
        $response->assertSee('coding-a');
        $response->assertSee('coding-b');

        // The existing combo is listed.
        $response->assertSee('idexal-coding');
    }

    public function test_combo_sync_and_suggestions_are_reachable(): void
    {
        $admin = User::factory()->admin()->create();
        $this->pair();

        $this->actingAs($admin)->post('/admin/combos/sync')->assertRedirect();
        $this->actingAs($admin)->get('/admin/combos/suggest')->assertOk();
    }

    public function test_manual_combo_takes_its_members_out_of_the_desktop_catalog(): void
    {
        $admin = User::factory()->admin()->create();
        [$modelA, $modelB] = $this->pair();
        $organization = Organization::factory()->create(['owner_id' => $admin->getKey()]);

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'idexal-coding',
            'slug' => 'idexal-coding',
            'strategy' => 'role',
            'member_ids' => [$modelA->getKey(), $modelB->getKey()],
            'roles' => [$modelA->getKey() => 'coding', $modelB->getKey() => 'vision'],
        ])->assertRedirect();

        $combo = AiModel::query()->where('model_id', 'idexal-coding')->firstOrFail();

        $catalog = app(GatewayService::class)->desktopGatewayModels($organization);
        $this->assertTrue($catalog->contains(fn (AiModel $model): bool => $model->is($combo)));
        $this->assertFalse($catalog->contains(fn (AiModel $model): bool => in_array(
            $model->getKey(),
            [$modelA->getKey(), $modelB->getKey()],
            true,
        )));

        $this->actingAs($admin)->delete('/admin/combos/'.$combo->getKey())->assertRedirect();

        $catalog = app(GatewayService::class)->desktopGatewayModels($organization);
        $this->assertFalse($catalog->contains(fn (AiModel $model): bool => $model->is($combo)));
        $this->assertTrue($catalog->contains(fn (AiModel $model): bool => in_array(
            $model->getKey(),
            [$modelA->getKey(), $modelB->getKey()],
            true,
        )));
    }

    public function test_a_combo_cannot_be_built_from_another_combo(): void
    {
        $admin = User::factory()->admin()->create();
        [$modelA, $modelB] = $this->pair();

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'idexal-coding',
            'slug' => 'idexal-coding',
            'strategy' => 'round_robin',
            'member_ids' => [$modelA->getKey(), $modelB->getKey()],
        ])->assertRedirect();

        $combo = AiModel::query()->where('model_id', 'idexal-coding')->firstOrFail();

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'nested',
            'slug' => 'nested',
            'strategy' => 'round_robin',
            'member_ids' => [$combo->getKey()],
        ])->assertSessionHasErrors('member_ids');

        $this->assertSame(
            __('ui.admin.combos.nested_member'),
            session('errors')->first('member_ids'),
        );

        $this->assertDatabaseMissing('ai_models', ['model_id' => 'nested']);
    }

    public function test_a_combo_cannot_take_the_id_of_a_live_provider_model(): void
    {
        $admin = User::factory()->admin()->create();
        [$modelA] = $this->pair();

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'Shadow',
            'slug' => $modelA->model_id,
            'strategy' => 'round_robin',
            'member_ids' => [$modelA->getKey()],
        ])->assertSessionHasErrors('slug');

        $this->assertSame(
            __('ui.admin.combos.slug_taken', ['slug' => $modelA->model_id]),
            session('errors')->first('slug'),
        );

        $this->assertDatabaseMissing('ai_models', ['model_id' => $modelA->model_id, 'display_name' => 'Shadow']);
    }

    public function test_an_admin_can_edit_a_combo_in_place(): void
    {
        $admin = User::factory()->admin()->create();
        [$modelA, $modelB] = $this->pair();

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'idexal-coding',
            'slug' => 'idexal-coding',
            'strategy' => 'round_robin',
            'member_ids' => [$modelA->getKey(), $modelB->getKey()],
        ])->assertRedirect();

        $combo = AiModel::query()->where('model_id', 'idexal-coding')->firstOrFail();

        $this->actingAs($admin)->patch('/admin/combos/'.$combo->getKey(), [
            'name' => 'idexal-coding-v2',
            'description' => 'Updated blend',
            'strategy' => 'weighted',
            'member_ids' => [$modelB->getKey()],
            'weights' => [$modelB->getKey() => 5],
        ])->assertRedirect();

        $combo->refresh();
        $this->assertSame('idexal-coding-v2', $combo->display_name);
        $this->assertSame('weighted', $combo->hybrid_config['strategy']);
        $this->assertCount(1, $combo->hybrid_config['members']);
        $this->assertSame(5, $combo->hybrid_config['members'][0]['weight']);
        // The edit form left is_active unticked, so the combo is parked.
        $this->assertFalse($combo->is_active);
    }

    public function test_weighted_strategy_rotates_members_by_their_weight(): void
    {
        $admin = User::factory()->admin()->create();
        [$modelA, $modelB] = $this->pair();
        $organization = Organization::factory()->create(['owner_id' => $admin->getKey()]);

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'idexal-weighted',
            'slug' => 'idexal-weighted',
            'strategy' => 'weighted',
            'member_ids' => [$modelA->getKey(), $modelB->getKey()],
            'weights' => [$modelA->getKey() => 3, $modelB->getKey() => 1],
        ])->assertRedirect();

        $combo = AiModel::query()->where('model_id', 'idexal-weighted')->firstOrFail();
        $service = app(ComboModelService::class);

        $firsts = [];
        foreach (range(1, 4) as $ignored) {
            $ordered = $service->expand(
                $combo,
                $organization,
                ['messages' => [['role' => 'user', 'content' => 'hello']]],
                'chat/completions',
            );
            $firsts[] = $ordered->first()->getKey();
        }

        // Three of every four slots belong to the heavier member.
        $this->assertSame(3, count(array_filter($firsts, fn (string $id): bool => $id === $modelA->getKey())));
        $this->assertContains($modelB->getKey(), $firsts);
    }

    public function test_a_zero_weight_member_stays_as_fallback_only(): void
    {
        $admin = User::factory()->admin()->create();
        [$modelA, $modelB] = $this->pair();
        $organization = Organization::factory()->create(['owner_id' => $admin->getKey()]);

        $this->actingAs($admin)->post('/admin/combos', [
            'name' => 'idexal-primary',
            'slug' => 'idexal-primary',
            'strategy' => 'weighted',
            'member_ids' => [$modelA->getKey(), $modelB->getKey()],
            'weights' => [$modelA->getKey() => 1, $modelB->getKey() => 0],
        ])->assertRedirect();

        $combo = AiModel::query()->where('model_id', 'idexal-primary')->firstOrFail();

        $ordered = app(ComboModelService::class)->expand(
            $combo,
            $organization,
            ['messages' => [['role' => 'user', 'content' => 'hello']]],
            'chat/completions',
        );

        $this->assertSame($modelA->getKey(), $ordered->first()->getKey());
        $this->assertTrue($ordered->contains(fn (AiModel $model): bool => $model->is($modelB)));
    }

    /**
     * @return array{0: AiModel, 1: AiModel}
     */
    private function pair(): array
    {
        $providerA = AiProvider::factory()->create([
            'name' => 'Provider A',
            'slug' => 'provider-a',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://provider-a.test/v1',
            'api_key' => 'secret-a',
            'is_active' => true,
        ]);
        $providerB = AiProvider::factory()->create([
            'name' => 'Provider B',
            'slug' => 'provider-b',
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://provider-b.test/v1',
            'api_key' => 'secret-b',
            'is_active' => true,
        ]);

        return [
            AiModel::factory()->create([
                'ai_provider_id' => $providerA->getKey(),
                'model_id' => 'coding-a',
                'display_name' => 'Coding A',
                'capabilities' => ['chat' => true, 'streaming' => true, 'tools' => true],
                'is_active' => true,
            ]),
            AiModel::factory()->create([
                'ai_provider_id' => $providerB->getKey(),
                'model_id' => 'coding-b',
                'display_name' => 'Coding B',
                'capabilities' => ['chat' => true, 'streaming' => true, 'vision' => true],
                'is_active' => true,
            ]),
        ];
    }
}
