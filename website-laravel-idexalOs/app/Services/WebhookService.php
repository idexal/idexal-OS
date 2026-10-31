<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class WebhookService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatchEvent(string $event, Organization $organization, array $payload): int
    {
        $endpoints = $organization->webhookEndpoints()
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($event));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->getKey(),
                'event' => $event,
                'payload' => $payload,
                'attempts' => 0,
                'created_at' => now(),
            ]);

            DeliverWebhook::dispatch($delivery->getKey())->afterCommit();
        }

        return $endpoints->count();
    }
}
