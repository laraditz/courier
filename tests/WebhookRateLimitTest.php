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
}
