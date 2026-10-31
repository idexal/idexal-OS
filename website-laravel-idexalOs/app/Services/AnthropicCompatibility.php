<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Translates between the OpenAI chat shape the desktop speaks and Anthropic's
 * Messages API.
 *
 * The desktop transport is OpenAI-compatible (`/chat/completions`), and until
 * now that meant an Anthropic-driver provider could never serve a desktop: its
 * models were filtered out of the catalog. This class removes that limit by
 * converting in both directions, so an operator's Claude models reach the
 * desktop like any other.
 *
 * Everything here is a pure function over arrays and strings, so the mapping is
 * testable without a live upstream. It is used only when the
 * `gateway_anthropic_desktop` setting is on and only for Anthropic-driver
 * providers; every other provider keeps the path it has today.
 */
final class AnthropicCompatibility
{
    /** Anthropic requires `max_tokens`; this is the floor when nothing says otherwise. */
    public const DEFAULT_MAX_TOKENS = 4096;

    /**
     * An OpenAI chat-completions body as an Anthropic Messages body.
     *
     * @param  array<string, mixed>  $input  the OpenAI-shaped request body
     * @return array<string, mixed>
     */
    public static function toMessagesRequest(array $input, string $upstreamModel, bool $stream, int $maxTokens): array
    {
        [$system, $messages] = self::splitSystemAndMessages($input['messages'] ?? []);

        $payload = [
            'model' => $upstreamModel,
            'max_tokens' => self::resolveMaxTokens($input, $maxTokens),
            'messages' => $messages,
            'stream' => $stream,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        foreach (['temperature', 'top_p'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $payload[$key] = $input[$key];
            }
        }

        if (isset($input['stop'])) {
            $stop = is_array($input['stop']) ? $input['stop'] : [$input['stop']];
            $stop = array_values(array_filter($stop, fn (mixed $value): bool => is_string($value) && $value !== ''));

            if ($stop !== []) {
                $payload['stop_sequences'] = $stop;
            }
        }

        [$tools, $toolChoice] = self::toolsFor($input);

        if ($tools !== []) {
            $payload['tools'] = $tools;

            if ($toolChoice !== null) {
                $payload['tool_choice'] = $toolChoice;
            }
        }

        return $payload;
    }

    /**
     * An Anthropic Messages body as an OpenAI chat completion.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function toChatCompletion(array $body, string $model): array
    {
        $text = '';
        $toolCalls = [];

        foreach ((array) ($body['content'] ?? []) as $index => $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');

                continue;
            }

            if (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = [
                    'index' => count($toolCalls),
                    'id' => (string) ($block['id'] ?? 'tool_'.($index + 1)),
                    'type' => 'function',
                    'function' => [
                        'name' => (string) ($block['name'] ?? ''),
                        'arguments' => self::encodeArguments($block['input'] ?? []),
                    ],
                ];
            }
        }

        $message = ['role' => 'assistant', 'content' => $text];

        if ($toolCalls !== []) {
            $message['tool_calls'] = $toolCalls;
        }

        $prompt = (int) data_get($body, 'usage.input_tokens', 0);
        $completion = (int) data_get($body, 'usage.output_tokens', 0);

        return [
            'id' => (string) ($body['id'] ?? 'chatcmpl-'.Str::uuid()),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $model,
            'choices' => [[
                'index' => 0,
                'message' => $message,
                'finish_reason' => self::finishReason((string) ($body['stop_reason'] ?? 'end_turn')),
            ]],
            'usage' => [
                'prompt_tokens' => $prompt,
                'completion_tokens' => $completion,
                'total_tokens' => $prompt + $completion,
            ],
        ];
    }

    /**
     * A stateful Anthropic-SSE to OpenAI-SSE translator.
     *
     * Anthropic streams typed events (`message_start`, `content_block_delta`,
     * `message_delta`, `message_stop`); OpenAI streams one `choices[].delta`
     * per line and ends with `[DONE]`. `push()` takes one already-parsed SSE
     * event and returns the OpenAI data payloads the client should receive, so
     * the caller decides how to frame and flush them.
     *
     * @return array<string, mixed>|null the OpenAI delta to emit, or null when
     *                                   the event carries nothing the client needs
     */
    public static function streamDelta(array $event, string $model, int &$promptTokens, int &$completionTokens): ?array
    {
        $type = (string) ($event['type'] ?? '');

        if ($type === 'message_start') {
            $promptTokens = (int) data_get($event, 'message.usage.input_tokens', $promptTokens);

            return self::delta(['role' => 'assistant', 'content' => ''], $model);
        }

        if ($type === 'content_block_delta') {
            $delta = (array) ($event['delta'] ?? []);

            if (($delta['type'] ?? null) === 'text_delta') {
                return self::delta(['content' => (string) ($delta['text'] ?? '')], $model);
            }

            if (($delta['type'] ?? null) === 'input_json_delta') {
                return self::delta([
                    'tool_calls' => [[
                        'index' => (int) ($event['index'] ?? 0),
                        'function' => ['arguments' => (string) ($delta['partial_json'] ?? '')],
                    ]],
                ], $model);
            }

            return null;
        }

        if ($type === 'content_block_start') {
            $block = (array) ($event['content_block'] ?? []);

            if (($block['type'] ?? null) === 'tool_use') {
                return self::delta([
                    'tool_calls' => [[
                        'index' => (int) ($event['index'] ?? 0),
                        'id' => (string) ($block['id'] ?? ''),
                        'type' => 'function',
                        'function' => [
                            'name' => (string) ($block['name'] ?? ''),
                            'arguments' => '',
                        ],
                    ]],
                ], $model);
            }

            return null;
        }

        if ($type === 'message_delta') {
            $completionTokens = (int) data_get($event, 'usage.output_tokens', $completionTokens);
            $reason = data_get($event, 'delta.stop_reason');

            return [
                'id' => $model,
                'object' => 'chat.completion.chunk',
                'created' => time(),
                'model' => $model,
                'choices' => [[
                    'index' => 0,
                    'delta' => [],
                    'finish_reason' => $reason === null ? null : self::finishReason((string) $reason),
                ]],
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private static function delta(array $delta, string $model): array
    {
        return [
            'id' => 'chatcmpl-'.$model,
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $model,
            'choices' => [[
                'index' => 0,
                'delta' => $delta,
                'finish_reason' => null,
            ]],
        ];
    }

    private static function finishReason(string $stopReason): string
    {
        return match ($stopReason) {
            'max_tokens' => 'length',
            'tool_use' => 'tool_calls',
            'stop_sequence', 'end_turn' => 'stop',
            default => 'stop',
        };
    }

    private static function encodeArguments(mixed $input): string
    {
        if (is_string($input) && $input !== '') {
            return $input;
        }

        $encoded = json_encode($input === null || $input === [] ? new \stdClass : $input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '{}' : $encoded;
    }

    /**
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private static function splitSystemAndMessages(mixed $messages): array
    {
        $system = [];
        $converted = [];

        foreach (is_array($messages) ? $messages : [] as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = (string) ($message['role'] ?? 'user');

            if (in_array($role, ['system', 'developer'], true)) {
                $text = self::contentToText($message['content'] ?? '');

                if ($text !== '') {
                    $system[] = $text;
                }

                continue;
            }

            if ($role === 'tool') {
                $converted[] = [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'tool_result',
                        'tool_use_id' => (string) ($message['tool_call_id'] ?? ''),
                        'content' => self::contentToText($message['content'] ?? ''),
                    ]],
                ];

                continue;
            }

            $blocks = self::contentToBlocks($message['content'] ?? '');

            // An assistant turn that called tools carries them beside the text.
            foreach ((array) ($message['tool_calls'] ?? []) as $call) {
                $function = (array) ($call['function'] ?? []);
                $arguments = $function['arguments'] ?? '{}';
                $decoded = is_string($arguments) ? json_decode($arguments, true) : $arguments;

                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => (string) ($call['id'] ?? ''),
                    'name' => (string) ($function['name'] ?? ''),
                    'input' => is_array($decoded) ? $decoded : new \stdClass,
                ];
            }

            if ($blocks === []) {
                continue;
            }

            $converted[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $blocks,
            ];
        }

        return [implode("\n\n", $system), $converted];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function contentToBlocks(mixed $content): array
    {
        if (is_string($content)) {
            return $content === '' ? [] : [['type' => 'text', 'text' => $content]];
        }

        $blocks = [];

        foreach (is_array($content) ? $content : [] as $part) {
            if (is_string($part)) {
                $blocks[] = ['type' => 'text', 'text' => $part];

                continue;
            }

            if (! is_array($part)) {
                continue;
            }

            $type = (string) ($part['type'] ?? '');

            if ($type === 'text') {
                $blocks[] = ['type' => 'text', 'text' => (string) ($part['text'] ?? '')];

                continue;
            }

            if ($type === 'image_url') {
                $url = (string) data_get($part, 'image_url.url', '');
                $image = self::imageBlock($url);

                if ($image !== null) {
                    $blocks[] = $image;
                }
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function imageBlock(string $url): ?array
    {
        if ($url === '') {
            return null;
        }

        if (preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#i', $url, $matches) === 1) {
            return [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => strtolower($matches[1]),
                    'data' => $matches[2],
                ],
            ];
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return ['type' => 'image', 'source' => ['type' => 'url', 'url' => $url]];
        }

        return null;
    }

    private static function contentToText(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        $text = '';

        foreach (is_array($content) ? $content : [] as $part) {
            if (is_string($part)) {
                $text .= $part;

                continue;
            }

            if (is_array($part) && ($part['type'] ?? null) === 'text') {
                $text .= (string) ($part['text'] ?? '');
            }
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>|null}
     */
    private static function toolsFor(array $input): array
    {
        $tools = [];

        foreach ((array) ($input['tools'] ?? []) as $tool) {
            if (! is_array($tool)) {
                continue;
            }

            $function = (array) ($tool['function'] ?? []);
            $name = (string) ($function['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $parameters = $function['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass];

            $tools[] = [
                'name' => $name,
                'description' => (string) ($function['description'] ?? ''),
                'input_schema' => is_array($parameters) ? $parameters : ['type' => 'object', 'properties' => new \stdClass],
            ];
        }

        if ($tools === []) {
            return [[], null];
        }

        $choice = $input['tool_choice'] ?? null;
        $toolChoice = null;

        if (is_string($choice)) {
            $toolChoice = match ($choice) {
                'required', 'any' => ['type' => 'any'],
                'none' => null,
                default => ['type' => 'auto'],
            };
        } elseif (is_array($choice)) {
            $name = (string) data_get($choice, 'function.name', '');

            $toolChoice = $name !== ''
                ? ['type' => 'tool', 'name' => $name]
                : ['type' => 'auto'];
        }

        // `tool_choice: "none"` means the model may not call a tool at all, so
        // the tools must not be sent either.
        if (is_string($choice) && $choice === 'none') {
            return [[], null];
        }

        return [$tools, $toolChoice];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function resolveMaxTokens(array $input, int $maxTokens): int
    {
        $requested = $input['max_tokens'] ?? $input['max_completion_tokens'] ?? null;

        if (is_numeric($requested) && (int) $requested > 0) {
            return (int) $requested;
        }

        return $maxTokens > 0 ? $maxTokens : self::DEFAULT_MAX_TOKENS;
    }
}
