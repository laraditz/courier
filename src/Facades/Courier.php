<?php

namespace Laraditz\Courier\Facades;

use Illuminate\Support\Facades\Facade;
use Laraditz\Courier\Testing\CourierFake;

/**
 * @method static \Laraditz\Courier\DTOs\Results\ShipmentResult createShipment(\Laraditz\Courier\DTOs\Payloads\ShipmentPayload $payload)
 * @method static \Laraditz\Courier\DTOs\Results\TrackingResult track(string $trackingNumber)
 * @method static \Laraditz\Courier\DTOs\Results\RateCollection getRates(\Laraditz\Courier\DTOs\Payloads\RatePayload $payload)
 * @method static \Laraditz\Courier\DTOs\Results\CancelResult cancelShipment(string $waybillNumber)
 * @method static \Laraditz\Courier\DTOs\Results\LabelResult getLabel(string $waybillNumber)
 * @method static \Laraditz\Courier\DTOs\Results\ServiceCollection getAvailability(\Laraditz\Courier\DTOs\Payloads\AvailabilityPayload $payload)
 * @method static \Laraditz\Courier\CourierManager driver(string $driver)
 *
 * @see \Laraditz\Courier\CourierManager
 */
class Courier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'courier';
    }

    public static function fake(array $responses = []): CourierFake
    {
        $fake = new CourierFake($responses);
        static::swap($fake);

        return $fake;
    }
}
