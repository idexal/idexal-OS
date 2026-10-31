<?php

namespace App\Services;

use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Support\OutboundHttp;
use Illuminate\Support\Str;
use Throwable;

/**
 * The support assistant. It reads a ticket and writes a suggested reply using
 * the AI provider an operator picked on the support settings page. Drafts are
 * never sent on their own unless the operator turned automatic first replies
 * on, so a human always has the last word.
 */
class SupportAiService
{
    /**
     * Used when the operator leaves the instructions field empty.
     */
    private const DEFAULT_INSTRUCTIONS = 'You are the support assistant for idexal Os, a desktop app for doing work with AI agents. '
        .'Write a short, friendly, and accurate reply to the customer. Use plain text, no markdown headings, '
        .'and never promise refunds or features that are not confirmed. If you are unsure, say a human will follow up.';

    public function __construct(private readonly SettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->settings->boolean('support_ai_enabled', false)
            && $this->provider() instanceof AiProvider
            && $this->model() instanceof AiModel;
    }

    public function autoReply(): bool
    {
        return $this->enabled() && $this->settings->boolean('support_ai_auto_reply', false);
    }

    public function provider(): ?AiProvider
    {
        $id = trim((string) $this->settings->get('support_ai_provider_id'));

        if ($id === '') {
            return null;
        }

        return AiProvider::query()->whereKey($id)->where('is_active', true)->first();
    }

    public function model(): ?AiModel
    {
        $id = trim((string) $this->settings->get('support_ai_model_id'));

        if ($id === '') {
            return null;
        }

        return AiModel::query()->whereKey($id)->where('is_active', true)->first();
    }

    public function instructions(): string
    {
        $instructions = trim((string) $this->settings->get('support_ai_instructions'));

        return $instructions === '' ? self::DEFAULT_INSTRUCTIONS : $instructions;
    }

    /**
     * Ask the configured provider for a suggested reply. Returns null when the
     * assistant is off, misconfigured, or the provider could not answer, so the
     * caller can carry on without it.
     */
    public function draft(SupportTicket $ticket): ?string
    {
        $provider = $this->provider();
        $model = $this->model();

        if (! $provider instanceof AiProvider || ! $model instanceof AiModel) {
            return null;
        }

        $endpoint = $provider->driver === AiProvider::DRIVER_ANTHROPIC ? 'messages' : 'chat/completions';

        try {
            $response = OutboundHttp::client()
                ->withHeaders($this->headers($provider))
                ->connectTimeout(10)
                ->timeout((int) $this->settings->integer('support_ai_timeout', 60))
                ->post(rtrim((string) $provider->base_url, '/').'/'.$endpoint, $this->payload($model, $ticket, $provider));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        $text = $provider->driver === AiProvider::DRIVER_ANTHROPIC
            ? data_get($body, 'content.0.text')
            : data_get($body, 'choices.0.message.content');

        if (is_array($text)) {
            $text = collect($text)
                ->map(fn (mixed $part): string => is_array($part) ? (string) data_get($part, 'text', '') : (string) $part)
                ->implode("\n");
        }

        $text = trim((string) $text);

        return $text === '' ? null : $text;
    }

    /**
     * A reply the assistant wrote but a human has not sent yet.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function storeDraft(SupportTicket $ticket, string $body, array $metadata = []): SupportTicketReply
    {
        return SupportTicketReply::query()->create([
            'support_ticket_id' => $ticket->getKey(),
            'user_id' => null,
            'author_type' => SupportTicketReply::AUTHOR_AI,
            'author_name' => __('ui.support.author_ai'),
            'body' => $body,
            'is_internal' => true,
            'ai_generated' => true,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AiModel $model, SupportTicket $ticket, AiProvider $provider): array
    {
        $transcript = $this->transcript($ticket);
        $system = $this->instructions();

        if ($provider->driver === AiProvider::DRIVER_ANTHROPIC) {
            return [
                'model' => $model->model_id,
                'system' => $system,
                'max_tokens' => min(2048, (int) ($model->max_output_tokens ?: 1024)),
                'stream' => false,
                'messages' => [
                    ['role' => 'user', 'content' => $transcript],
                ],
            ];
        }

        return [
            'model' => $model->model_id,
            'stream' => false,
            'max_tokens' => min(2048, (int) ($model->max_output_tokens ?: 1024)),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $transcript],
            ],
        ];
    }

    private function transcript(SupportTicket $ticket): string
    {
        $lines = [
            'Ticket: '.$ticket->subject,
            'Category: '.$ticket->category,
            'Priority: '.$ticket->priority,
            '',
            'Customer wrote:',
            $ticket->body,
        ];

        $replies = $ticket->publicReplies()->get();

        if ($replies->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Conversation so far:';

            foreach ($replies as $reply) {
                $lines[] = '['.Str::headline((string) $reply->author_type).'] '.$reply->body;
            }
        }

        $lines[] = '';
        $lines[] = 'Write the next reply to the customer.';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private function headers(AiProvider $provider): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'idexal-os-support/1.0',
        ];

        foreach ($provider->headers ?? [] as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $headers[$name] = $value;
            }
        }

        $apiKey = (string) $provider->api_key;

        if ($provider->driver === AiProvider::DRIVER_ANTHROPIC) {
            if ($apiKey !== '') {
                $headers['x-api-key'] = $apiKey;
            }

            $headers['anthropic-version'] = $headers['anthropic-version'] ?? '2023-06-01';
        } elseif ($apiKey !== '') {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        return $headers;
    }
}
