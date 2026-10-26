<?php

namespace Laraditz\Courier\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class WebhookRateLimit
{
    /**
     * The ceiling applied when nothing is configured.
     *
     * Matches the rate the webhook route carried before the limiter became
     * per-driver, so upgrading changes no existing deployment's ceiling.
     */
    public const DEFAULT_PER_MINUTE = 60;

    /**
     * Resolve the rate limit for an inbound webhook request.
     *
     * Keyed on driver and IP rather than IP alone, so one carrier pushing hard
     * cannot exhaust another carrier's allowance from the same address.
     */
    public static function for(Request $request): Limit
    {
        $driver = (string) $request->route('driver');

        $rate = self::resolveRate($driver);

        if ($rate === null) {
            return Limit::none();
        }

        return Limit::perMinute($rate)->by($driver.'|'.$request->ip());
    }

    /**
     * Per-driver config wins over the global default; null at either level
     * disables the limiter.
     *
     * A key set to null is distinguishable from an absent key because
     * Config\Repository::has() resolves through array_key_exists().
     */
    private static function resolveRate(string $driver): ?int
    {
        $driverKey = "courier.drivers.{$driver}.webhook.rate_limit";

        if (config()->has($driverKey)) {
            return self::normalize(config($driverKey));
        }

        if (config()->has('courier.webhook.rate_limit')) {
            return self::normalize(config('courier.webhook.rate_limit'));
        }

        return self::DEFAULT_PER_MINUTE;
    }

    /**
     * Null disables the limiter. Anything else that is not a usable ceiling is
     * treated as unconfigured — a typo must never silently disable throttling,
     * nor lock the endpoint out entirely with a ceiling of zero.
     *
     * The null check runs first on purpose: null is non-numeric, so validating
     * numerically before checking for it would cap a deliberately-disabled
     * driver at the default instead.
     */
    private static function normalize(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 1) {
            return self::DEFAULT_PER_MINUTE;
        }

        return (int) $value;
    }
}
