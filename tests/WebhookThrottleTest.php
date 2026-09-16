<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Tests\Fixtures\PlainWebhookDriver;

class WebhookThrottleTest extends TestCase
{
    private function registerDriver(string $name): void
    {
        $driver = new PlainWebhookDriver(verifies: true);

        app('courier')->extend($name, fn () => $driver);
    }

    /**
     * The two isolation tests below are a pair, and only mean anything together.
     *
     * Each sets a ceiling of 1 and fires a single request to the same driver.
     * If throttle counters leaked between tests, the second would get a 429.
     * Everything from here down depends on that not happening.
     */
    public function test_throttle_counters_do_not_leak_between_tests_first(): void
    {
        config(['courier.webhook.rate_limit' => 1]);
        $this->registerDriver('isolation-driver');

        $this->postJson('/courier/webhook/isolation-driver', ['event' => 'test'])->assertStatus(200);
    }

    public function test_throttle_counters_do_not_leak_between_tests_second(): void
    {
        config(['courier.webhook.rate_limit' => 1]);
        $this->registerDriver('isolation-driver');

        $this->postJson('/courier/webhook/isolation-driver', ['event' => 'test'])->assertStatus(200);
    }

    public function test_requests_past_the_ceiling_are_throttled(): void
    {
        config(['courier.webhook.rate_limit' => 2]);
        $this->registerDriver('throttled-driver');

        $this->postJson('/courier/webhook/throttled-driver', ['event' => 'test'])->assertStatus(200);
        $this->postJson('/courier/webhook/throttled-driver', ['event' => 'test'])->assertStatus(200);
        $this->postJson('/courier/webhook/throttled-driver', ['event' => 'test'])->assertStatus(429);
    }

    public function test_drivers_do_not_share_a_throttle_bucket(): void
    {
        config(['courier.webhook.rate_limit' => 1]);
        $this->registerDriver('carrier-a');
        $this->registerDriver('carrier-b');

        // Exhaust carrier A from this IP.
        $this->postJson('/courier/webhook/carrier-a', ['event' => 'test'])->assertStatus(200);
        $this->postJson('/courier/webhook/carrier-a', ['event' => 'test'])->assertStatus(429);

        // Carrier B, same IP, must be untouched. This is the C-3 fix:
        // before it, the 60/min ceiling was keyed on IP alone and shared.
        $this->postJson('/courier/webhook/carrier-b', ['event' => 'test'])->assertStatus(200);
    }

    public function test_per_driver_override_grants_headroom_without_affecting_others(): void
    {
        config([
            'courier.webhook.rate_limit' => 1,
            'courier.drivers.jtexpress.webhook.rate_limit' => 3,
        ]);
        $this->registerDriver('jtexpress');
        $this->registerDriver('other-carrier');

        $this->postJson('/courier/webhook/jtexpress', ['event' => 'test'])->assertStatus(200);
        $this->postJson('/courier/webhook/jtexpress', ['event' => 'test'])->assertStatus(200);
        $this->postJson('/courier/webhook/jtexpress', ['event' => 'test'])->assertStatus(200);
        $this->postJson('/courier/webhook/jtexpress', ['event' => 'test'])->assertStatus(429);

        // The other carrier keeps its own ceiling of 1, unchanged.
        $this->postJson('/courier/webhook/other-carrier', ['event' => 'test'])->assertStatus(200);
        $this->postJson('/courier/webhook/other-carrier', ['event' => 'test'])->assertStatus(429);
    }

    public function test_missing_webhook_config_block_falls_back_to_default(): void
    {
        // An app whose config/courier.php was published before the webhook block
        // existed. mergeConfigFrom() merges shallowly at the top level, so the
        // key is genuinely absent rather than filled in from the package default.
        $courier = config('courier');
        unset($courier['webhook']);
        config(['courier' => $courier]);

        $this->assertFalse(config()->has('courier.webhook.rate_limit'));

        $this->registerDriver('legacy-config-driver');

        // Still served, still throttled at the pre-existing ceiling of 60 —
        // not a MissingRateLimiterException, not an unthrottled endpoint.
        $this->postJson('/courier/webhook/legacy-config-driver', ['event' => 'test'])->assertStatus(200);
    }
}
