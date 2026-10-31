<?php

namespace Database\Seeders;

use App\Models\AiModel;
use App\Models\AiProvider;
use Illuminate\Database\Seeder;

class AiCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'OpenAI',
                'slug' => 'openai',
                'driver' => AiProvider::DRIVER_OPENAI,
                'base_url' => 'https://api.openai.com/v1',
                'priority' => 100,
                'notes' => 'Add your OpenAI API key and activate this provider to serve its models.',
                'models' => [
                    ['gpt-4.1', 'GPT-4.1', 1047576, 32768, 2.00, 8.00],
                    ['gpt-4.1-mini', 'GPT-4.1 mini', 1047576, 32768, 0.40, 1.60],
                    ['gpt-4o', 'GPT-4o', 128000, 16384, 2.50, 10.00],
                    ['gpt-4o-mini', 'GPT-4o mini', 128000, 16384, 0.15, 0.60],
                    ['o3-mini', 'o3-mini', 200000, 100000, 1.10, 4.40],
                ],
            ],
            [
                'name' => 'Anthropic',
                'slug' => 'anthropic',
                'driver' => AiProvider::DRIVER_ANTHROPIC,
                'base_url' => 'https://api.anthropic.com/v1',
                'priority' => 90,
                'notes' => 'Add your Anthropic API key and activate this provider to serve Claude models.',
                'models' => [
                    ['claude-sonnet-4-5', 'Claude Sonnet 4.5', 200000, 64000, 3.00, 15.00],
                    ['claude-haiku-4-5', 'Claude Haiku 4.5', 200000, 64000, 1.00, 5.00],
                    ['claude-opus-4-1', 'Claude Opus 4.1', 200000, 32000, 15.00, 75.00],
                    ['claude-3-5-haiku-latest', 'Claude 3.5 Haiku', 200000, 8192, 0.80, 4.00],
                ],
            ],
            [
                'name' => 'Google Gemini',
                'slug' => 'google',
                'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'priority' => 80,
                'notes' => 'Uses the Gemini OpenAI-compatible endpoint. Add a Gemini API key and activate.',
                'models' => [
                    ['gemini-2.5-pro', 'Gemini 2.5 Pro', 1048576, 65536, 1.25, 10.00],
                    ['gemini-2.5-flash', 'Gemini 2.5 Flash', 1048576, 65536, 0.30, 2.50],
                ],
            ],
            [
                'name' => 'OpenRouter',
                'slug' => 'openrouter',
                'driver' => AiProvider::DRIVER_OPENAI_COMPATIBLE,
                'base_url' => 'https://openrouter.ai/api/v1',
                'priority' => 70,
                'notes' => 'One key for hundreds of models. Add an OpenRouter key and activate.',
                'models' => [
                    ['openai/gpt-4.1-mini', 'OpenRouter · GPT-4.1 mini', 1047576, 32768, 0.40, 1.60],
                    ['anthropic/claude-sonnet-4.5', 'OpenRouter · Claude Sonnet 4.5', 200000, 64000, 3.00, 15.00],
                    ['google/gemini-2.5-flash', 'OpenRouter · Gemini 2.5 Flash', 1048576, 65536, 0.30, 2.50],
                ],
            ],
        ];

        foreach ($catalog as $entry) {
            $provider = AiProvider::query()->firstOrCreate(
                ['slug' => $entry['slug']],
                [
                    'name' => $entry['name'],
                    'driver' => $entry['driver'],
                    'base_url' => $entry['base_url'],
                    'is_active' => false,
                    'priority' => $entry['priority'],
                    'supports_streaming' => true,
                    'notes' => $entry['notes'],
                ],
            );

            foreach ($entry['models'] as $index => [$modelId, $displayName, $context, $output, $inputPrice, $outputPrice]) {
                AiModel::query()->firstOrCreate(
                    [
                        'ai_provider_id' => $provider->getKey(),
                        'model_id' => $modelId,
                    ],
                    [
                        'display_name' => $displayName,
                        'context_window' => $context,
                        'max_output_tokens' => $output,
                        'input_price' => $inputPrice,
                        'output_price' => $outputPrice,
                        'capabilities' => ['chat' => true, 'streaming' => true, 'tools' => true],
                        'is_active' => true,
                        'sort_order' => $index,
                    ],
                );
            }
        }
    }
}
