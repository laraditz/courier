<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\DTOs\Payloads\RatePayload;
use Laraditz\Courier\DTOs\Payloads\ShipmentPayload;
use Laraditz\Courier\DTOs\Results\ShipmentResult;
use Laraditz\Courier\DTOs\Shared\Address;
use Laraditz\Courier\DTOs\Shared\Location;
use Laraditz\Courier\DTOs\Shared\Parcel;
use Laraditz\Courier\Facades\Courier;
use Laraditz\Courier\Testing\CourierFake;

class CourierFakeTest extends TestCase
{
    private function makeAddress(): Address
    {
        return new Address('Name', null, null, 'Line 1', null, null, 'KL', 'WP', '50000', 'MY');
    }

    private function makeParcel(): Parcel
    {
        return new Parcel(1.0, 10.0, 10.0, 10.0, 50.0, 'Goods', 1);
    }

    public function test_fake_returns_instance(): void
    {
        $fake = Courier::fake();

        $this->assertInstanceOf(CourierFake::class, $fake);
    }

    public function test_assert_shipment_created(): void
    {
        $fake = Courier::fake();

        $payload = new ShipmentPayload($this->makeAddress(), $this->makeAddress(), $this->makeParcel(), 'STANDARD');
        Courier::createShipment($payload);

        $fake->assertShipmentCreated();
    }

    public function test_assert_shipment_created_with_count(): void
    {
        $fake = Courier::fake();

        $payload = new ShipmentPayload($this->makeAddress(), $this->makeAddress(), $this->makeParcel(), 'STANDARD');
        Courier::createShipment($payload);
        Courier::createShipment($payload);

        $fake->assertShipmentCreated(2);
    }

    public function test_assert_shipment_created_with_callback(): void
    {
        $fake = Courier::fake();

        $payload = new ShipmentPayload($this->makeAddress(), $this->makeAddress(), $this->makeParcel(), 'EXPRESS');
        Courier::createShipment($payload);

        $fake->assertShipmentCreated(fn (ShipmentPayload $p) => $p->serviceCode === 'EXPRESS');
    }

    public function test_assert_tracked(): void
    {
        $fake = Courier::fake();

        Courier::track('SF1234567890');

        $fake->assertTracked('SF1234567890');
    }

    public function test_assert_tracked_with_callback(): void
    {
        $fake = Courier::fake();

        Courier::track('SF1234567890');

        $fake->assertTracked(fn (string $n) => str_starts_with($n, 'SF'));
    }

    public function test_assert_cancelled(): void
    {
        $fake = Courier::fake();

        Courier::cancelShipment('SF1234567890');

        $fake->assertCancelled('SF1234567890');
    }

    public function test_assert_rates_fetched(): void
    {
        $fake = Courier::fake();

        $payload = new RatePayload(
            new Location('50000', 'KL', 'WP', 'MY'),
            new Location('10000', 'Georgetown', 'Penang', 'MY'),
            $this->makeParcel(),
        );
        Courier::getRates($payload);

        $fake->assertRatesFetched();
    }

    public function test_assert_label_fetched(): void
    {
        $fake = Courier::fake();

        Courier::getLabel('SF1234567890');

        $fake->assertLabelFetched('SF1234567890');
    }

    public function test_assert_nothing_sent(): void
    {
        $fake = Courier::fake();

        $fake->assertNothingSent();
    }

    public function test_fake_returns_custom_response(): void
    {
        $customResult = new ShipmentResult('CUSTOM-001', 'pending', null);
        $fake = Courier::fake(['createShipment' => $customResult]);

        $payload = new ShipmentPayload($this->makeAddress(), $this->makeAddress(), $this->makeParcel(), 'STANDARD');
        $result = Courier::createShipment($payload);

        $this->assertSame('CUSTOM-001', $result->waybillNumber);
    }

    public function test_driver_call_returns_fake(): void
    {
        $fake = Courier::fake();

        $payload = new ShipmentPayload($this->makeAddress(), $this->makeAddress(), $this->makeParcel(), 'STANDARD');
        Courier::driver('sfexpress')->createShipment($payload);

        $fake->assertShipmentCreated();
    }
}
