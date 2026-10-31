<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public pricing page: plan cards, the full comparison matrix, and the
 * models each plan can actually reach. Every number comes from the plan row
 * checkout charges from, so the page and the billing engine cannot disagree.
 */
class PublicPricingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_comparison_matrix_renders_every_plan(): void
    {
        Plan::factory()->create(['slug' => 'starter', 'name' => 'Starter', 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'is_active' => true, 'is_public' => true, 'sort_order' => 2]);

        $response = $this->get('/pricing')->assertOk();

        $response->assertSeeText(__('ui.pricing.compare_title'));
        $response->assertSeeText(__('ui.pricing.compare_capability'));
        $response->assertSeeText(__('ui.pricing.group_limits'));
        $response->assertSeeText(__('ui.pricing.row_price'));
        $response->assertSeeText('Starter');
        $response->assertSeeText('Pro');
    }

    public function test_limit_labels_are_words_not_placeholders(): void
    {
        Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'is_active' => true, 'is_public' => true]);

        $content = (string) $this->get('/pricing')->assertOk()->getContent();

        // A label carrying an unreplaced :value token is a broken page.
        $this->assertStringNotContainsString(':value', $content);
        $this->assertStringContainsString(__('ui.pricing.limit_requests'), $content);
    }

    public function test_each_plan_lists_the_providers_it_can_reach(): void
    {
        $free = Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'is_active' => true, 'is_public' => true, 'sort_order' => 2]);

        $openai = AiProvider::factory()->create(['slug' => 'openai', 'name' => 'OpenAI', 'is_active' => true]);
        AiModel::factory()->create(['ai_provider_id' => $openai->getKey(), 'model_id' => 'gpt-4.1', 'display_name' => 'GPT-4.1', 'is_active' => true]);

        $proOnly = AiProvider::factory()->create(['slug' => 'anthropic', 'name' => 'Anthropic', 'is_active' => true]);
        $proOnly->plans()->attach($pro);
        AiModel::factory()->create(['ai_provider_id' => $proOnly->getKey(), 'model_id' => 'claude-sonnet-4', 'display_name' => 'Claude Sonnet 4', 'is_active' => true]);

        $response = $this->get('/pricing')->assertOk();

        $response->assertSeeText(__('ui.pricing.models_title'));
        $response->assertSeeText('OpenAI');
        $response->assertSeeText('Anthropic');
        $response->assertSeeText(trans_choice('ui.pricing.models_count', 1, ['count' => 1]));
        $response->assertSeeText(trans_choice('ui.pricing.models_count', 2, ['count' => 2]));
    }

    public function test_the_page_speaks_arabic_when_the_visitor_does(): void
    {
        $response = $this->get('/pricing?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText(__('ui.pricing.compare_title', [], 'ar'));
    }

    public function test_the_page_is_in_the_sitemap(): void
    {
        $this->assertStringContainsString(
            '<loc>'.route('pricing').'</loc>',
            (string) $this->get('/sitemap.xml')->assertOk()->getContent(),
        );
    }
}
