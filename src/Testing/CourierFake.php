<?php

namespace Laraditz\Courier\Testing;

use Closure;
use Laraditz\Courier\Contracts\CourierDriver;
use Laraditz\Courier\DTOs\Payloads\AvailabilityPayload;
use Laraditz\Courier\DTOs\Payloads\RatePayload;
use Laraditz\Courier\DTOs\Payloads\ShipmentPayload;
use Laraditz\Courier\DTOs\Results\CancelResult;
use Laraditz\Courier\DTOs\Results\LabelResult;
use Laraditz\Courier\DTOs\Results\RateCollection;
use Laraditz\Courier\DTOs\Results\ServiceCollection;
use Laraditz\Courier\DTOs\Results\ShipmentResult;
use Laraditz\Courier\DTOs\Results\TrackingResult;
use PHPUnit\Framework\Assert as PHPUnit;

class CourierFake implements CourierDriver
{
    private array $calls = [];

    public function __construct(private array $responses = []) {}

    public function driver(?string $driver = null): static
    {
        return $this;
    }

    public function createShipment(ShipmentPayload $payload): ShipmentResult
    {
        $this->calls['createShipment'][] = $payload;

        return $this->responses['createShipment']
            ?? new ShipmentResult('FAKE-001', 'pending', null);
    }

    public function track(string $trackingNumber): TrackingResult
    {
        $this->calls['track'][] = $trackingNumber;

        return $this->responses['track']
            ?? new TrackingResult($trackingNumber, 'in_transit', null, []);
    }

    public function getRates(RatePayload $payload): RateCollection
    {
        $this->calls['getRates'][] = $payload;

        return $this->responses['getRates'] ?? new RateCollection([]);
    }

    public function cancelShipment(string $waybillNumber): CancelResult
    {
        $this->calls['cancelShipment'][] = $waybillNumber;

        return $this->responses['cancelShipment']
            ?? new CancelResult(true, 'Cancelled');
    }

    public function getLabel(string $waybillNumber): LabelResult
    {
        $this->calls['getLabel'][] = $waybillNumber;

        return $this->responses['getLabel']
            ?? new LabelResult($waybillNumber, 'pdf', base64_encode('FAKE-PDF'));
    }

    public function getAvailability(AvailabilityPayload $payload): ServiceCollection
    {
        $this->calls['getAvailability'][] = $payload;

        return $this->responses['getAvailability'] ?? new ServiceCollection([]);
    }

    public function assertShipmentCreated(int|Closure|null $countOrCallback = null): void
    {
        $calls = $this->calls['createShipment'] ?? [];

        if ($countOrCallback === null) {
            PHPUnit::assertNotEmpty($calls, 'No shipments were created.');
        } elseif (is_int($countOrCallback)) {
            PHPUnit::assertCount($countOrCallback, $calls,
                "Expected {$countOrCallback} shipment(s) created, got ".count($calls).'.');
        } else {
            PHPUnit::assertTrue(
                collect($calls)->contains($countOrCallback),
                'No shipment was created matching the given callback.'
            );
        }
    }

    public function assertTracked(string|Closure $trackingNumberOrCallback): void
    {
        $calls = $this->calls['track'] ?? [];

        if (is_string($trackingNumberOrCallback)) {
            PHPUnit::assertContains($trackingNumberOrCallback, $calls,
                "Tracking number [{$trackingNumberOrCallback}] was not tracked.");
        } else {
            PHPUnit::assertTrue(
                collect($calls)->contains($trackingNumberOrCallback),
                'No tracking call matched the given callback.'
            );
        }
    }

    public function assertCancelled(string $waybillNumber): void
    {
        PHPUnit::assertContains(
            $waybillNumber,
            $this->calls['cancelShipment'] ?? [],
            "Waybill [{$waybillNumber}] was not cancelled."
        );
    }

    public function assertRatesFetched(): void
    {
        PHPUnit::assertNotEmpty($this->calls['getRates'] ?? [], 'No rates were fetched.');
    }

    public function assertLabelFetched(string $waybillNumber): void
    {
        PHPUnit::assertContains(
            $waybillNumber,
            $this->calls['getLabel'] ?? [],
            "Label for waybill [{$waybillNumber}] was not fetched."
        );
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty(
            $this->calls['createShipment'] ?? [],
            'Unexpected shipment(s) were created.'
        );
    }
}
