<?php

namespace Tests\Feature;

use App\Services\AnthropicCompatibility;
use Tests\TestCase;

/**
 * The OpenAI <-> Anthropic mapping. These are pure functions, so the whole
 * translation is verifiable without a live upstream: the shape of the request
 * Anthropic receives, the OpenAI body the desktop receives back, and the
 * streamed deltas in between.
 */
class AnthropicCompatibilityTest extends TestCase
{
    public function test_a_plain_chat_request_becomes_an_anthropic_messages_request(): void
    {
        $payload = AnthropicCompatibility::toMessagesRequest([
            'model' => 'gwm_x',
            'messages' => [
                ['role' => 'system', 'content' => 'Be brief.'],
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi'],
                ['role' => 'user', 'content' => 'Again'],
            ],
            'temperature' => 0.3,
            'stop' => ['END'],
            'max_tokens' => 512,
        ], 'claude-sonnet-4', false, 4096);

        $this->assertSame('claude-sonnet-4', $payload['model']);
        $this->assertSame(512, $payload['max_tokens']);
        $this->assertSame('Be brief.', $payload['system']);
        $this->assertSame(0.3, $payload['temperature']);
        $this->assertSame(['END'], $payload['stop_sequences']);
        $this->assertFalse($payload['stream']);
        $this->assertCount(3, $payload['messages'], 'The system turn is lifted out of the conversation.');
        $this->assertSame('user', $payload['messages'][0]['role']);
        $this->assertSame([['type' => 'text', 'text' => 'Hello']], $payload['messages'][0]['content']);
    }

    public function test_max_tokens_is_always_sent_because_anthropic_requires_it(): void
    {
        $fromModel = AnthropicCompatibility::toMessagesRequest(
            ['messages' => [['role' => 'user', 'content' => 'Hi']]],
            'claude-sonnet-4',
            false,
            900,
        );
        $this->assertSame(900, $fromModel['max_tokens']);

        $fallback = AnthropicCompatibility::toMessagesRequest(
            ['messages' => [['role' => 'user', 'content' => 'Hi']]],
            'claude-sonnet-4',
            false,
            0,
        );
        $this->assertSame(AnthropicCompatibility::DEFAULT_MAX_TOKENS, $fallback['max_tokens']);
    }

    public function test_openai_tools_become_anthropic_tools_with_an_input_schema(): void
    {
        $payload = AnthropicCompatibility::toMessagesRequest([
            'messages' => [['role' => 'user', 'content' => 'Weather?']],
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'get_weather',
                    'description' => 'Look up the weather',
                    'parameters' => ['type' => 'object', 'properties' => ['city' => ['type' => 'string']]],
                ],
            ]],
            'tool_choice' => 'required',
        ], 'claude-sonnet-4', false, 1024);

        $this->assertCount(1, $payload['tools']);
        $this->assertSame('get_weather', $payload['tools'][0]['name']);
        $this->assertSame('Look up the weather', $payload['tools'][0]['description']);
        $this->assertSame(['type' => 'object', 'properties' => ['city' => ['type' => 'string']]], $payload['tools'][0]['input_schema']);
        $this->assertSame(['type' => 'any'], $payload['tool_choice']);
    }

    public function test_tool_choice_none_withholds_the_tools_entirely(): void
    {
        $payload = AnthropicCompatibility::toMessagesRequest([
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'tools' => [['type' => 'function', 'function' => ['name' => 'f', 'parameters' => ['type' => 'object']]]],
            'tool_choice' => 'none',
        ], 'claude-sonnet-4', false, 1024);

        $this->assertArrayNotHasKey('tools', $payload);
        $this->assertArrayNotHasKey('tool_choice', $payload);
    }

    public function test_a_tool_result_turn_becomes_a_user_turn_with_a_tool_result_block(): void
    {
        $payload = AnthropicCompatibility::toMessagesRequest([
            'messages' => [
                ['role' => 'user', 'content' => 'Weather?'],
                [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Riyadh"}'],
                    ]],
                ],
                ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => '31C'],
            ],
        ], 'claude-sonnet-4', false, 1024);

        $assistant = $payload['messages'][1];
        $this->assertSame('assistant', $assistant['role']);
        $this->assertSame('tool_use', $assistant['content'][0]['type']);
        $this->assertSame('get_weather', $assistant['content'][0]['name']);
        $this->assertSame(['city' => 'Riyadh'], $assistant['content'][0]['input']);

        $tool = $payload['messages'][2];
        $this->assertSame('user', $tool['role']);
        $this->assertSame('tool_result', $tool['content'][0]['type']);
        $this->assertSame('call_1', $tool['content'][0]['tool_use_id']);
    }

    public function test_a_base64_image_becomes_an_anthropic_image_source(): void
    {
        $payload = AnthropicCompatibility::toMessagesRequest([
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'What is this?'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,AAAA']],
                ],
            ]],
        ], 'claude-sonnet-4', false, 1024);

        $blocks = $payload['messages'][0]['content'];
        $this->assertSame('text', $blocks[0]['type']);
        $this->assertSame('image', $blocks[1]['type']);
        $this->assertSame('base64', $blocks[1]['source']['type']);
        $this->assertSame('image/png', $blocks[1]['source']['media_type']);
        $this->assertSame('AAAA', $blocks[1]['source']['data']);
    }

    public function test_an_anthropic_message_becomes_an_openai_chat_completion(): void
    {
        $body = AnthropicCompatibility::toChatCompletion([
            'id' => 'msg_1',
            'type' => 'message',
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'Hello there']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 12, 'output_tokens' => 5],
        ], 'gwm_model');

        $this->assertSame('chat.completion', $body['object']);
        $this->assertSame('gwm_model', $body['model']);
        $this->assertSame('Hello there', $body['choices'][0]['message']['content']);
        $this->assertSame('stop', $body['choices'][0]['finish_reason']);
        $this->assertSame(12, $body['usage']['prompt_tokens']);
        $this->assertSame(5, $body['usage']['completion_tokens']);
        $this->assertSame(17, $body['usage']['total_tokens']);
    }

    public function test_a_tool_use_block_becomes_openai_tool_calls(): void
    {
        $body = AnthropicCompatibility::toChatCompletion([
            'content' => [
                ['type' => 'text', 'text' => 'Checking'],
                ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather', 'input' => ['city' => 'Riyadh']],
            ],
            'stop_reason' => 'tool_use',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 2],
        ], 'gwm_model');

        $message = $body['choices'][0]['message'];
        $this->assertSame('Checking', $message['content']);
        $this->assertSame('tool_calls', $body['choices'][0]['finish_reason']);
        $this->assertCount(1, $message['tool_calls']);
        $this->assertSame('toolu_1', $message['tool_calls'][0]['id']);
        $this->assertSame('get_weather', $message['tool_calls'][0]['function']['name']);
        $this->assertSame('{"city":"Riyadh"}', $message['tool_calls'][0]['function']['arguments']);
    }

    public function test_the_stream_deltas_map_text_tools_and_the_finish_reason(): void
    {
        $prompt = 0;
        $completion = 0;

        $start = AnthropicCompatibility::streamDelta(['type' => 'message_start', 'message' => ['usage' => ['input_tokens' => 9]]], 'gwm_model', $prompt, $completion);
        $this->assertSame('assistant', $start['choices'][0]['delta']['role']);
        $this->assertSame(9, $prompt);

        $text = AnthropicCompatibility::streamDelta(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hi']], 'gwm_model', $prompt, $completion);
        $this->assertSame('Hi', $text['choices'][0]['delta']['content']);

        $toolStart = AnthropicCompatibility::streamDelta([
            'type' => 'content_block_start',
            'index' => 1,
            'content_block' => ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'get_weather'],
        ], 'gwm_model', $prompt, $completion);
        $this->assertSame('toolu_1', $toolStart['choices'][0]['delta']['tool_calls'][0]['id']);
        $this->assertSame('get_weather', $toolStart['choices'][0]['delta']['tool_calls'][0]['function']['name']);

        $toolDelta = AnthropicCompatibility::streamDelta([
            'type' => 'content_block_delta',
            'index' => 1,
            'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"city":'],
        ], 'gwm_model', $prompt, $completion);
        $this->assertSame('{"city":', $toolDelta['choices'][0]['delta']['tool_calls'][0]['function']['arguments']);

        $end = AnthropicCompatibility::streamDelta(['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn'], 'usage' => ['output_tokens' => 4]], 'gwm_model', $prompt, $completion);
        $this->assertSame('stop', $end['choices'][0]['finish_reason']);
        $this->assertSame(4, $completion);
    }

    public function test_events_the_client_does_not_need_are_dropped(): void
    {
        $prompt = 0;
        $completion = 0;

        foreach (['ping', 'content_block_stop', 'message_stop', 'unknown_event'] as $type) {
            $this->assertNull(
                AnthropicCompatibility::streamDelta(['type' => $type], 'gwm_model', $prompt, $completion),
                "The {$type} event carries nothing the OpenAI client needs.",
            );
        }
    }
}
