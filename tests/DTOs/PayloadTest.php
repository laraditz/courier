<?php

namespace Laraditz\Courier\Tests\DTOs;

use Laraditz\Courier\DTOs\Payloads\AvailabilityPayload;
use Laraditz\Courier\DTOs\Payloads\RatePayload;
use Laraditz\Courier\DTOs\Payloads\ShipmentPayload;
use Laraditz\Courier\DTOs\Shared\Address;
use Laraditz\Courier\DTOs\Shared\Location;
use Laraditz\Courier\DTOs\Shared\Parcel;
use Laraditz\Courier\Tests\TestCase;

class PayloadTest extends TestCase
{
    private function makeAddress(): Address
    {
        return new Address('Name', '+60123456789', null, 'Line 1', null, null, 'KL', 'WP', '50000', 'MY');
    }

    private function makeParcel(): Parcel
    {
        return new Parcel(1.5, 20.0, 15.0, 10.0, 100.0, 'Goods', 1);
    }

    private function makeLocation(): Location
    {
        return new Location('50000', 'KL', 'WP', 'MY');
    }

    public function test_shipment_payload(): void
    {
        $payload = new ShipmentPayload(
            sender: $this->makeAddress(),
            recipient: $this->makeAddress(),
            parcel: $this->makeParcel(),
            serviceCode: 'STANDARD',
            remarks: 'Fragile',
        );

        $this->assertSame('STANDARD', $payload->serviceCode);
        $this->assertSame('Fragile', $payload->remarks);
        $this->assertInstanceOf(Address::class, $payload->sender);
        $this->assertInstanceOf(Parcel::class, $payload->parcel);
    }

    public function test_shipment_payload_remarks_optional(): void
    {
        $payload = new ShipmentPayload(
            sender: $this->makeAddress(),
            recipient: $this->makeAddress(),
            parcel: $this->makeParcel(),
            serviceCode: 'STANDARD',
        );

        $this->assertNull($payload->remarks);
    }

    public function test_rate_payload(): void
    {
        $payload = new RatePayload(
            origin: $this->makeLocation(),
            destination: $this->makeLocation(),
            parcel: $this->makeParcel(),
        );

        $this->assertInstanceOf(Location::class, $payload->origin);
        $this->assertInstanceOf(Parcel::class, $payload->parcel);
    }

    public function test_availability_payload(): void
    {
        $payload = new AvailabilityPayload(
            origin: $this->makeLocation(),
            destination: $this->makeLocation(),
        );

        $this->assertSame('MY', $payload->origin->country);
        $this->assertSame('MY', $payload->destination->country);
    }
}
