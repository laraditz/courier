<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Laraditz\Courier\Support\WebhookRateLimit;

class WebhookRateLimitTest extends TestCase
{
    private function requestFor(string $driver, string $ip = '203.0.113.5'): Request
    {
        $request = Request::create('/courier/webhook/'.$driver, 'POST', server: ['REMOTE_ADDR' => $ip]);

        $route = (new Route('POST', 'courier/webhook/{driver}', []))->bind($request);

        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    /**
     * Remove the courier.webhook block entirely, rather than setting it null.
     *
     * Setting it null means "disabled"; this reproduces an app whose published
     * config predates the block existing at all.
     */
    private function forgetWebhookConfig(): void
    {
        $courier = config('courier');

        unset($courier['webhook']);

        config(['courier' => $courier]);
    }

    public function test_defaults_to_sixty_per_minute_when_unconfigured(): void
    {
        $this->forgetWebhookConfig();

        $limit = WebhookRateLimit::for($this->requestFor('sfexpress'));

        $this->assertNotInstanceOf(Unlimited::class, $limit);
        $this->assertSame(60, $limit->maxAttempts);
        $this->assertSame(60, $limit->decaySeconds);
    }

    public function test_key_is_driver_and_ip(): void
    {
        $limit = WebhookRateLimit::for($this->requestFor('jtexpress', '198.51.100.7'));

        $this->assertSame('jtexpress|198.51.100.7', $limit->key);
    }

    public function test_drivers_resolve_to_different_keys(): void
    {
        $jt = WebhookRateLimit::for($this->requestFor('jtexpress', '198.51.100.7'));
        $sf = WebhookRateLimit::for($this->requestFor('sfexpress', '198.51.100.7'));

        $this->assertNotSame($jt->key, $sf->key);
    }

    public function test_global_rate_is_used_when_no_per_driver_rate_is_set(): void
    {
        config(['courier.webhook.rate_limit' => 120]);

        $limit = WebhookRateLimit::for($this->requestFor('sfexpress'));

        $this->assertSame(120, $limit->maxAttempts);
    }

    public function test_per_driver_rate_overrides_global(): void
    {
        config([
            'courier.webhook.rate_limit' => 120,
            'courier.drivers.jtexpress.webhook.rate_limit' => 300,
        ]);

        $this->assertSame(300, WebhookRateLimit::for($this->requestFor('jtexpress'))->maxAttempts);

        // The override must not leak to any other driver.
        $this->assertSame(120, WebhookRateLimit::for($this->requestFor('sfexpress'))->maxAttempts);
    }

    public function test_null_global_rate_disables_the_limiter(): void
    {
        config(['courier.webhook.rate_limit' => null]);

        $this->assertInstanceOf(Unlimited::class, WebhookRateLimit::for($this->requestFor('sfexpress')));
    }

    public function test_null_per_driver_rate_disables_only_that_driver(): void
    {
        config([
            'courier.webhook.rate_limit' => 120,
            'courier.drivers.jtexpress.webhook.rate_limit' => null,
        ]);

        $this->assertInstanceOf(Unlimited::class, WebhookRateLimit::for($this->requestFor('jtexpress')));
        $this->assertSame(120, WebhookRateLimit::for($this->requestFor('sfexpress'))->maxAttempts);
    }

    public static function malformedRateProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-5],
            'non-numeric string' => ['abc'],
            'empty string' => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('malformedRateProvider')]
    public function test_malformed_rate_falls_back_to_default(mixed $rate): void
    {
        config(['courier.webhook.rate_limit' => $rate]);

        $limit = WebhookRateLimit::for($this->requestFor('sfexpress'));

        $this->assertNotInstanceOf(Unlimited::class, $limit);
        $this->assertSame(60, $limit->maxAttempts);
    }
}
