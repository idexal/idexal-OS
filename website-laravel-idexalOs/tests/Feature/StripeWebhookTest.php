<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\StripeEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    public function test_signed_events_are_processed_once(): void
    {
        Setting::put('stripe_webhook_secret', self::SECRET, true, 'billing');

        $payload = json_encode([
            'id' => 'evt_test_idempotent',
            'object' => 'event',
            'api_version' => '2025-01-27',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'sub_unknown',
                    'object' => 'subscription',
                    'metadata' => [],
                ],
            ],
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => ['id' => null, 'idempotency_key' => null],
            'type' => 'customer.subscription.updated',
        ], JSON_THROW_ON_ERROR);

        $signature = $this->signature($payload);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('received', true)
            ->assertJsonMissingPath('duplicate');

        $this->assertNotNull(StripeEvent::query()->find('evt_test_idempotent')?->processed_at);

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);
    }

    public function test_invalid_signatures_are_rejected_without_processing(): void
    {
        Setting::put('stripe_webhook_secret', self::SECRET, true, 'billing');

        $this->call('POST', '/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=123,v1=invalid',
        ], '{"id":"evt_bad"}')
            ->assertStatus(400)
            ->assertJsonPath('received', false)
            ->assertJsonPath('error', 'invalid_signature');

        $this->assertDatabaseCount('stripe_events', 0);
    }

    private function signature(string $payload): string
    {
        $timestamp = time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, self::SECRET);
    }
}
