<?php

namespace Tests\Feature;

use App\Models\GatewayRequest;
use Tests\TestCase;

/**
 * `appliedFeatures()` is the single filter between the feature names the
 * gateway records in `gateway_requests.meta` and the badges an operator or a
 * customer sees, so a name the platform no longer knows can never surface as a
 * raw translation key.
 */
class GatewayRequestFeaturesTest extends TestCase
{
    public function test_it_returns_only_known_features_in_recorded_order_without_duplicates(): void
    {
        $request = new GatewayRequest([
            'meta' => ['features' => ['repetition_guard', 'retired_feature', 'vision', 'repetition_guard']],
        ]);

        $this->assertSame(['repetition_guard', 'vision'], $request->appliedFeatures());
    }

    public function test_it_tolerates_a_missing_meta_block_and_non_string_entries(): void
    {
        $this->assertSame([], (new GatewayRequest)->appliedFeatures());

        $request = new GatewayRequest([
            'meta' => ['features' => ['web_search', null, 5, ['nested'], 'unknown_feature']],
        ]);

        $this->assertSame(['web_search'], $request->appliedFeatures());
    }

    public function test_it_returns_idempotent_output_for_the_same_meta(): void
    {
        $request = new GatewayRequest(['meta' => ['features' => ['patient_retry']]]);

        $this->assertSame($request->appliedFeatures(), $request->appliedFeatures());
    }
}
