<?php

namespace Laraditz\Courier\Contracts;

use Laraditz\Courier\DTOs\Payloads\AvailabilityPayload;
use Laraditz\Courier\DTOs\Payloads\RatePayload;
use Laraditz\Courier\DTOs\Payloads\ShipmentPayload;
use Laraditz\Courier\DTOs\Results\CancelResult;
use Laraditz\Courier\DTOs\Results\LabelResult;
use Laraditz\Courier\DTOs\Results\RateCollection;
use Laraditz\Courier\DTOs\Results\ServiceCollection;
use Laraditz\Courier\DTOs\Results\ShipmentResult;
use Laraditz\Courier\DTOs\Results\TrackingResult;

interface CourierDriver
{
    public function createShipment(ShipmentPayload $payload): ShipmentResult;
    public function track(string $trackingNumber): TrackingResult;
    public function getRates(RatePayload $payload): RateCollection;
    public function cancelShipment(string $waybillNumber): CancelResult;
    public function getLabel(string $waybillNumber): LabelResult;
    public function getAvailability(AvailabilityPayload $payload): ServiceCollection;
}
