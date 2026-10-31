<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public Models & providers page: it is generated from the providers the
 * platform actually runs, lists each one under the plans that can use it, and
 * never advertises an inactive provider or model.
 */
class PublicProvidersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_lists_active_providers_their_models_and_plans(): void
    {
        $free = Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'is_active' => true, 'is_public' => true]);
        $pro = Plan::factory()->create(['slug' => 'pro', 'name' => 'Pro', 'is_active' => true, 'is_public' => true]);

        $openai = AiProvider::factory()->create(['slug' => 'openai', 'name' => 'OpenAI', 'is_active' => true]);
        AiModel::factory()->create([
            'ai_provider_id' => $openai->getKey(),
            'model_id' => 'gpt-4o-mini',
            'display_name' => 'GPT-4o mini',
            'is_active' => true,
        ]);

        $proOnly = AiProvider::factory()->create(['slug' => 'anthropic', 'name' => 'Anthropic', 'is_active' => true]);
        $proOnly->plans()->attach($pro);
        AiModel::factory()->create([
            'ai_provider_id' => $proOnly->getKey(),
            'model_id' => 'claude-sonnet-4',
            'display_name' => 'Claude Sonnet 4',
            'is_active' => true,
        ]);

        $inactive = AiProvider::factory()->create(['slug' => 'retired', 'name' => 'Retired', 'is_active' => false]);
        AiModel::factory()->create([
            'ai_provider_id' => $inactive->getKey(),
            'model_id' => 'old-model',
            'display_name' => 'Old model',
            'is_active' => true,
        ]);

        $response = $this->get('/providers')->assertOk();

        $response->assertSeeText('OpenAI');
        $response->assertSeeText('GPT-4o mini');
        $response->assertSeeText('Anthropic');
        $response->assertSeeText('Claude Sonnet 4');
        $response->assertSeeText('Free');
        $response->assertSeeText('Pro');

        $this->assertStringNotContainsString('retired', (string) $response->getContent());
        $this->assertStringNotContainsString('old-model', (string) $response->getContent());
    }

    public function test_the_page_speaks_arabic_when_the_visitor_does(): void
    {
        $response = $this->get('/providers?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText('يجري إعداد المزوّدات');
    }

    public function test_each_model_shows_the_numbers_a_reader_compares(): void
    {
        $openai = AiProvider::factory()->create(['slug' => 'openai', 'name' => 'OpenAI', 'is_active' => true]);

        AiModel::factory()->create([
            'ai_provider_id' => $openai->getKey(),
            'model_id' => 'gpt-4.1',
            'display_name' => 'GPT-4.1',
            'context_window' => 1047576,
            'max_output_tokens' => 32768,
            'input_price' => 2,
            'output_price' => 8,
            'capabilities' => ['chat' => true, 'streaming' => true, 'tools' => true, 'vision' => true],
            'is_active' => true,
        ]);

        $response = $this->get('/providers')->assertOk();

        // 1,047,576 tokens reads as 1.05M, and the price is per million tokens.
        $response->assertSeeText('1.05M');
        $response->assertSeeText('32.8K');
        $response->assertSeeText('$2 / $8');
        $response->assertSeeText(__('ui.providers.cap_tools'));
        $response->assertSeeText(__('ui.providers.cap_vision'));
        $response->assertSeeText(__('ui.providers.col_context'));
    }

    public function test_the_page_is_in_the_sitemap(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('<loc>'.route('providers').'</loc>', (string) $response->getContent());
    }
}
