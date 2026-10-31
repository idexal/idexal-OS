<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\ComboModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Editing a combo has to be an edit, not a rebuild: the public id, the members
 * and every setting the form did not touch must survive a save.
 */
class ComboEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AiModel $primary;

    private AiModel $backup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['locale' => 'en']);

        $alpha = AiProvider::factory()->create([
            'name' => 'Provider A',
            'slug' => 'provider-a',
            'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
            'base_url' => 'https://provider-a.test/v1',
            'api_key' => 'secret-a',
            'is_active' => true,
            'priority' => 1,
        ]);
        $beta = AiProvider::factory()->create([
            'name' => 'Provider B',
            'slug' => 'provider-b',
            'driver' => AiProvider::DRIVER_OPENAI,
            'base_url' => 'https://provider-b.test/v1',
            'api_key' => 'secret-b',
            'is_active' => true,
            // Deliberately ranked above provider A: an explicit attempt order
            // must be able to put A first anyway.
            'priority' => 20,
        ]);

        $this->primary = AiModel::factory()->create([
            'ai_provider_id' => $alpha->getKey(),
            'model_id' => 'coding-a',
            'display_name' => 'Coding A',
            'capabilities' => ['chat' => true, 'streaming' => true, 'tools' => true],
            'is_active' => true,
        ]);
        $this->backup = AiModel::factory()->create([
            'ai_provider_id' => $beta->getKey(),
            'model_id' => 'coding-b',
            'display_name' => 'Coding B',
            'capabilities' => ['chat' => true, 'streaming' => true],
            'is_active' => true,
        ]);
    }

    public function test_editing_a_combo_keeps_its_public_id_and_its_members(): void
    {
        $combo = $this->createCombo();

        $this->actingAs($this->admin)
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => 'idexal-coding-renamed',
                'description' => 'Same models, new name',
                'strategy' => 'round_robin',
                'member_ids' => [$this->primary->getKey(), $this->backup->getKey()],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/combos');

        $combo->refresh();

        // The slug every desktop subscribed to is untouched.
        $this->assertSame('idexal-coding', $combo->model_id);
        $this->assertSame('idexal-coding-renamed', $combo->display_name);
        $this->assertSame('Same models, new name', $combo->description);
        $this->assertTrue($combo->is_active);
        $this->assertSame(
            [$this->primary->getKey(), $this->backup->getKey()],
            collect($combo->hybrid_config['members'])->pluck('ai_model_id')->all(),
        );
    }

    public function test_an_edit_keeps_the_roles_weights_and_fallback_chain_it_does_not_touch(): void
    {
        $combo = $this->createCombo([
            'strategy' => 'weighted',
            'roles' => [
                $this->primary->getKey() => 'coding',
                $this->backup->getKey() => 'reasoning',
            ],
            'weights' => [
                $this->primary->getKey() => 7,
                $this->backup->getKey() => 2,
            ],
            'fallback_chain' => [$this->backup->getKey()],
        ]);

        // A save that only renames the combo sends none of those fields.
        $this->actingAs($this->admin)
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => 'idexal-coding',
                'strategy' => 'weighted',
                'member_ids' => [$this->primary->getKey(), $this->backup->getKey()],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/combos');

        $combo->refresh();
        $members = collect($combo->hybrid_config['members'])->keyBy('ai_model_id');

        $this->assertSame('coding', $members[$this->primary->getKey()]['role']);
        $this->assertSame('reasoning', $members[$this->backup->getKey()]['role']);
        $this->assertSame(7, $members[$this->primary->getKey()]['weight']);
        $this->assertSame(2, $members[$this->backup->getKey()]['weight']);
        $this->assertFalse($members[$this->primary->getKey()]['in_fallback_chain']);
        $this->assertTrue($members[$this->backup->getKey()]['in_fallback_chain']);
    }

    public function test_attempt_order_decides_the_failover_sequence(): void
    {
        $combo = $this->createCombo([
            'strategy' => 'failover',
            'orders' => [
                $this->primary->getKey() => 1,
                $this->backup->getKey() => 2,
            ],
        ]);

        // Provider B outranks provider A, yet member order wins.
        $this->assertSame($this->primary->getKey(), $this->firstAttempt($combo));

        $this->actingAs($this->admin)
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => 'idexal-coding',
                'strategy' => 'failover',
                'member_ids' => [$this->primary->getKey(), $this->backup->getKey()],
                'orders' => [
                    $this->primary->getKey() => 2,
                    $this->backup->getKey() => 1,
                ],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/combos');

        $this->assertSame($this->backup->getKey(), $this->firstAttempt($combo->refresh()));
    }

    public function test_a_combo_still_cannot_take_another_combo_as_a_member_when_edited(): void
    {
        $combo = $this->createCombo();
        $nested = $this->createCombo([
            'name' => 'idexal-nested',
            'slug' => 'idexal-nested',
            'member_ids' => [$this->backup->getKey()],
        ]);

        $this->actingAs($this->admin)
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => 'idexal-coding',
                'strategy' => 'round_robin',
                'member_ids' => [$nested->getKey()],
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('member_ids');

        $this->assertSame(
            __('ui.admin.combos.nested_member'),
            session('errors')->first('member_ids'),
        );

        $this->assertCount(2, $combo->refresh()->hybrid_config['members']);
    }

    public function test_a_rejected_edit_keeps_the_typed_values_and_reopens_the_panel(): void
    {
        $combo = $this->createCombo();

        $page = $this->actingAs($this->admin)
            ->from('/admin/combos')
            ->followingRedirects()
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => '',
                'description' => 'Typed but not saved',
                'strategy' => 'weighted',
                'member_ids' => [$this->primary->getKey()],
                'is_active' => '1',
            ])
            ->assertOk();

        // The panel is rendered open, the typed description is still there, and
        // the rejection is explained inside the panel rather than at the top.
        $page->assertSee('x-data="{ open: true }"', false);
        $page->assertSee('Typed but not saved');
        $page->assertSee('The name field is required.');
        $page->assertSee('The name field is required.', false);
    }

    public function test_a_rejected_edit_remembers_which_combo_was_open(): void
    {
        $combo = $this->createCombo();

        $this->actingAs($this->admin)
            ->from('/admin/combos')
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => '',
                'strategy' => 'round_robin',
                'member_ids' => [$this->primary->getKey()],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/combos')
            ->assertSessionHasErrors('name');

        $this->assertSame($combo->getKey(), session('combo_edit'));
    }

    public function test_a_successful_edit_is_audited_against_the_combo(): void
    {
        $combo = $this->createCombo();
        AuditLog::query()->delete();

        $this->actingAs($this->admin)
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => 'idexal-coding-v2',
                'strategy' => 'round_robin',
                'member_ids' => [$this->primary->getKey(), $this->backup->getKey()],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/combos');

        $entry = AuditLog::query()->where('action', 'admin.combo.updated')->sole();

        $this->assertSame($combo->getKey(), $entry->subject_id);
        $this->assertSame('idexal-coding-v2', $combo->refresh()->display_name);
    }

    public function test_the_editor_renders_in_arabic(): void
    {
        $arabic = User::factory()->admin()->create(['locale' => 'ar']);
        $this->createCombo();

        $this->actingAs($arabic)
            ->get('/admin/combos')
            ->assertOk()
            ->assertSee(__('ui.admin.combos.order'))
            ->assertSee(__('ui.admin.combos.order_hint'))
            ->assertSee(__('ui.admin.combos.save'));
    }

    public function test_a_combo_names_a_member_it_can_no_longer_offer(): void
    {
        $combo = $this->createCombo();

        // A member turned off is no longer a legal choice, yet the combo still
        // counts it. The editor must name it instead of showing a short list.
        $this->backup->forceFill(['is_active' => false])->save();

        $this->actingAs($this->admin)
            ->get('/admin/combos')
            ->assertOk()
            ->assertSee(__('ui.admin.combos.unavailable_count', ['count' => 1]))
            ->assertSee(__('ui.admin.combos.unavailable_hint'))
            ->assertSee('Coding B');

        // Saving the combo keeps only what could be offered.
        $this->actingAs($this->admin)
            ->patch('/admin/combos/'.$combo->getKey(), [
                'name' => 'idexal-coding',
                'strategy' => 'round_robin',
                'member_ids' => [$this->primary->getKey()],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/combos');

        $this->assertSame(
            [$this->primary->getKey()],
            collect($combo->refresh()->hybrid_config['members'])->pluck('ai_model_id')->all(),
        );
    }

    public function test_a_deleted_member_is_still_counted_and_named_by_its_id(): void
    {
        $this->createCombo();
        $removedId = $this->backup->getKey();
        $this->backup->delete();

        $this->actingAs($this->admin)
            ->get('/admin/combos')
            ->assertOk()
            ->assertSee(__('ui.admin.combos.unavailable_count', ['count' => 1]))
            ->assertSee($removedId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createCombo(array $payload = []): AiModel
    {
        $this->actingAs($this->admin)
            ->post('/admin/combos', $payload + [
                'name' => 'idexal-coding',
                'slug' => 'idexal-coding',
                'strategy' => 'round_robin',
                'member_ids' => [$this->primary->getKey(), $this->backup->getKey()],
                'is_active' => '1',
            ])
            ->assertRedirect('/admin/combos');

        return AiModel::query()
            ->where('model_id', $payload['slug'] ?? 'idexal-coding')
            ->firstOrFail();
    }

    private function firstAttempt(AiModel $combo): string
    {
        $organization = Organization::factory()->create(['owner_id' => $this->admin->getKey()]);

        /** @var Collection<int, AiModel> $ordered */
        $ordered = app(ComboModelService::class)->expand(
            $combo,
            $organization,
            ['messages' => [['role' => 'user', 'content' => 'hello']]],
            'chat/completions',
        );

        return $ordered->first()->getKey();
    }
}
