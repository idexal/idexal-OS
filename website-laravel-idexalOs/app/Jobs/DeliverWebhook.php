<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\OutboundHttp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $deliveryId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if (! $delivery instanceof WebhookDelivery || $delivery->delivered_at !== null) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if (! $endpoint instanceof WebhookEndpoint || ! $endpoint->is_active) {
            return;
        }

        $json = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$json, $endpoint->secret);
        $attempts = $delivery->attempts + 1;

        try {
            $response = OutboundHttp::client()->connectTimeout(5)
                ->timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Idexal-Event' => $delivery->event,
                    'X-Idexal-Delivery' => $delivery->getKey(),
                    'X-Idexal-Signature' => 't='.$timestamp.',v1='.$signature,
                ])
                ->withBody($json, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException $exception) {
            $this->markAttempt($delivery, $attempts, null, $exception->getMessage());

            throw $exception;
        }

        $delivery->forceFill([
            'attempts' => $attempts,
            'response_status' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 4000),
            'delivered_at' => $response->successful() ? now() : null,
            'next_retry_at' => $response->successful() ? null : now()->addSeconds(60),
        ])->save();

        if ($response->successful()) {
            $endpoint->forceFill([
                'last_success_at' => now(),
                'failure_count' => 0,
            ])->save();

            return;
        }

        $endpoint->increment('failure_count');

        throw new RuntimeException('Webhook endpoint returned HTTP '.$response->status().'.');
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if (! $delivery instanceof WebhookDelivery) {
            return;
        }

        $delivery->forceFill([
            'next_retry_at' => null,
            'response_body' => mb_substr((string) ($exception?->getMessage() ?? $delivery->response_body), 0, 4000),
        ])->save();

        $delivery->endpoint?->increment('failure_count');
    }

    private function markAttempt(WebhookDelivery $delivery, int $attempts, ?int $status, string $body): void
    {
        $delivery->forceFill([
            'attempts' => $attempts,
            'response_status' => $status,
            'response_body' => mb_substr($body, 0, 4000),
            'next_retry_at' => now()->addSeconds(60),
        ])->save();
    }
}
