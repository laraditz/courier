<?php

namespace Laraditz\Courier\Tests\DTOs;

use Carbon\Carbon;
use Laraditz\Courier\DTOs\Results\ShipmentResult;
use Laraditz\Courier\DTOs\Results\TrackingEvent;
use Laraditz\Courier\DTOs\Results\TrackingResult;
use Laraditz\Courier\Tests\TestCase;

class ResultTest extends TestCase
{
    public function test_shipment_result(): void
    {
        $result = new ShipmentResult(
            waybillNumber: 'SF1234567890',
            status: 'pending',
            estimatedDelivery: Carbon::parse('2026-06-19'),
            meta: ['raw_status' => 'ACCEPTED'],
        );

        $this->assertSame('SF1234567890', $result->waybillNumber);
        $this->assertSame('pending', $result->status);
        $this->assertInstanceOf(Carbon::class, $result->estimatedDelivery);
        $this->assertSame(['raw_status' => 'ACCEPTED'], $result->meta());
    }

    public function test_shipment_result_estimated_delivery_nullable(): void
    {
        $result = new ShipmentResult('SF001', 'pending', null);

        $this->assertNull($result->estimatedDelivery);
        $this->assertSame([], $result->meta());
    }

    public function test_tracking_event(): void
    {
        $event = new TrackingEvent(
            timestamp: Carbon::parse('2026-06-17 10:00:00'),
            location: 'Kuala Lumpur Hub',
            description: 'Package picked up',
            status: 'picked_up',
        );

        $this->assertSame('Kuala Lumpur Hub', $event->location);
        $this->assertSame('picked_up', $event->status);
    }

    public function test_tracking_result(): void
    {
        $event = new TrackingEvent(Carbon::now(), 'Hub KL', 'Picked up', 'picked_up');

        $result = new TrackingResult(
            waybillNumber: 'SF1234567890',
            status: 'in_transit',
            estimatedDelivery: null,
            events: [$event],
            meta: ['raw_status' => 'IN_TRANSIT'],
        );

        $this->assertSame('SF1234567890', $result->waybillNumber);
        $this->assertCount(1, $result->events);
        $this->assertInstanceOf(TrackingEvent::class, $result->events[0]);
        $this->assertSame(['raw_status' => 'IN_TRANSIT'], $result->meta());
    }

    public function test_rate_option_and_collection(): void
    {
        $option = new \Laraditz\Courier\DTOs\Results\RateOption(
            serviceCode: 'STANDARD',
            serviceName: 'Standard Delivery',
            price: 12.50,
            currency: 'MYR',
            estimatedDays: 3,
        );

        $collection = new \Laraditz\Courier\DTOs\Results\RateCollection([$option]);

        $this->assertCount(1, $collection->items);
        $this->assertSame('STANDARD', $collection->items[0]->serviceCode);
        $this->assertSame(12.50, $collection->items[0]->price);
        $this->assertSame('MYR', $collection->items[0]->currency);
    }

    public function test_cancel_result(): void
    {
        $result = new \Laraditz\Courier\DTOs\Results\CancelResult(
            success: true,
            message: 'Shipment cancelled successfully.',
            meta: ['ref' => 'SF001'],
        );

        $this->assertTrue($result->success);
        $this->assertSame('Shipment cancelled successfully.', $result->message);
        $this->assertSame(['ref' => 'SF001'], $result->meta());
    }

    public function test_label_result_pdf(): void
    {
        $result = new \Laraditz\Courier\DTOs\Results\LabelResult(
            waybillNumber: 'SF001',
            format: 'pdf',
            content: base64_encode('%PDF-fake'),
            meta: [],
        );

        $this->assertSame('pdf', $result->format);
        $this->assertSame(base64_encode('%PDF-fake'), $result->content);
    }

    public function test_label_result_url(): void
    {
        $result = new \Laraditz\Courier\DTOs\Results\LabelResult(
            waybillNumber: 'SF001',
            format: 'url',
            content: 'https://example.com/label.pdf',
            meta: [],
        );

        $this->assertSame('url', $result->format);
        $this->assertSame('https://example.com/label.pdf', $result->content);
    }

    public function test_service_collection(): void
    {
        $option = new \Laraditz\Courier\DTOs\Results\ServiceOption(
            code: 'STANDARD',
            name: 'Standard',
            description: 'Standard delivery 3-5 days',
            estimatedDays: 4,
        );

        $collection = new \Laraditz\Courier\DTOs\Results\ServiceCollection([$option]);

        $this->assertCount(1, $collection->items);
        $this->assertSame('STANDARD', $collection->items[0]->code);
    }

    public function test_rate_option_meta_defaults_empty(): void
    {
        $option = new \Laraditz\Courier\DTOs\Results\RateOption(
            serviceCode: 'MOTORCYCLE',
            serviceName: 'Motorcycle',
            price: 8.00,
            currency: 'MYR',
            estimatedDays: null,
        );
        $this->assertSame([], $option->meta());
    }

    public function test_rate_option_meta_stores_values(): void
    {
        $option = new \Laraditz\Courier\DTOs\Results\RateOption(
            serviceCode: 'MOTORCYCLE',
            serviceName: 'Motorcycle',
            price: 8.00,
            currency: 'MYR',
            estimatedDays: null,
            meta: ['quotation_id' => 'abc123', 'expires_at' => '2026-06-20T10:05:00Z'],
        );
        $this->assertSame('abc123', $option->meta()['quotation_id']);
        $this->assertSame('2026-06-20T10:05:00Z', $option->meta()['expires_at']);
    }
}
