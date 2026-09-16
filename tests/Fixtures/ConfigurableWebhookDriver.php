<?php

namespace Laraditz\Courier\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Laraditz\Courier\Contracts\CourierDriver;
use Laraditz\Courier\Contracts\HandlesWebhooks;
use Laraditz\Courier\Contracts\ProvidesWebhookResponse;
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
use Symfony\Component\HttpFoundation\Response;

/**
 * A webhook driver whose behaviour is supplied per test via closures.
 *
 * Exists so the eight CourierDriver stub methods are written once instead of
 * once per test variant, and so tests can assert which contract method ran.
 */
class ConfigurableWebhookDriver implements CourierDriver, HandlesWebhooks, ProvidesWebhookResponse
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(
        private bool $verifies = true,
        private ?Closure $onHandle = null,
        private ?Closure $accepted = null,
        private ?Closure $rejected = null,
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

    public function webhookAcceptedResponse(Request $request): Response
    {
        $this->calls[] = 'accepted';

        return $this->accepted
            ? ($this->accepted)($request)
            : response()->json(['code' => '1', 'msg' => 'success']);
    }

    public function webhookRejectedResponse(Request $request): Response
    {
        $this->calls[] = 'rejected';

        return $this->rejected
            ? ($this->rejected)($request)
            : response()->json(['code' => '145003030', 'msg' => 'headers signature verification failed'], 401);
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
