<?php

namespace Laraditz\Courier\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Laraditz\Courier\Contracts\CourierDriver;
use Laraditz\Courier\Contracts\HandlesWebhooks;
use Laraditz\Courier\DTOs\Payloads\AvailabilityPayload;
use Laraditz\Courier\DTOs\Payloads\RatePayload;
use Laraditz\Courier\DTOs\Payloads\ShipmentPayload;
use Laraditz\Courier\DTOs\Results\CancelResult;
use Laraditz\Courier\DTOs\Results\LabelResult;
use Laraditz\Courier\DTOs\Results\RateCollection;
use Laraditz\Courier\DTOs\Results\ServiceCollection;
use Laraditz\Courier\DTOs\Results\ShipmentResult;
use Laraditz\Courier\DTOs\Results\TrackingResult;
use Laraditz\Courier\Enums\DeliveryMode;
use RuntimeException;

/**
 * A webhook driver that does NOT implement ProvidesWebhookResponse.
 *
 * Stands in for every existing driver, so tests can prove the default
 * responses are unchanged. ConfigurableWebhookDriver extends this and adds
 * the contract, keeping the CourierDriver stubs in one place.
 */
class PlainWebhookDriver implements CourierDriver, HandlesWebhooks
{
    public function __construct(
        protected bool $verifies = true,
        protected ?Closure $onHandle = null,
    ) {}

    public function verifyWebhook(Request $request): bool
    {
        return $this->verifies;
    }

    public function handleWebhook(Request $request): void
    {
        if ($this->onHandle) {
            ($this->onHandle)($request);
        }
    }

    public function createShipment(ShipmentPayload $payload): ShipmentResult
    {
        throw new RuntimeException;
    }

    public function getShipment(string $reference): ShipmentResult
    {
        throw new RuntimeException;
    }

    public function track(string $trackingNumber): TrackingResult
    {
        throw new RuntimeException;
    }

    public function getRates(RatePayload $payload): RateCollection
    {
        throw new RuntimeException;
    }

    public function cancelShipment(string $waybillNumber, ?string $reason = null): CancelResult
    {
        throw new RuntimeException;
    }

    public function getLabel(string $waybillNumber, ?string $reference = null): LabelResult
    {
        throw new RuntimeException;
    }

    public function getAvailability(AvailabilityPayload $payload): ServiceCollection
    {
        throw new RuntimeException;
    }

    public function getDeliveryModes(): array
    {
        return [DeliveryMode::Scheduled];
    }
}
