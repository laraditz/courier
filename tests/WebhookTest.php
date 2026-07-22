<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
use Laraditz\Courier\Events\WebhookReceived;
use Laraditz\Courier\Models\CourierWebhookLog;

class WebhookTest extends TestCase
{
    private function registerWebhookDriver(bool $verifies = true): void
    {
        $driver = new class($verifies) implements CourierDriver, HandlesWebhooks {
            public function __construct(private bool $verifies) {}
            public function createShipment(ShipmentPayload $p): ShipmentResult { throw new \RuntimeException; }
            public function getShipment(string $r): ShipmentResult { throw new \RuntimeException; }
            public function track(string $t): TrackingResult { throw new \RuntimeException; }
            public function getRates(RatePayload $p): RateCollection { throw new \RuntimeException; }
            public function cancelShipment(string $w, ?string $r = null): CancelResult { throw new \RuntimeException; }
            public function getLabel(string $w, ?string $r = null): LabelResult { throw new \RuntimeException; }
            public function getAvailability(AvailabilityPayload $p): ServiceCollection { throw new \RuntimeException; }
            public function verifyWebhook(Request $request): bool { return $this->verifies; }
            public function handleWebhook(Request $request): void {}
        };
        app('courier')->extend('test-webhook-driver', fn () => $driver);
    }

    private function registerExtractingWebhookDriver(): void
    {
        $driver = new class implements CourierDriver, HandlesWebhooks, \Laraditz\Courier\Contracts\ExtractsWebhookReference {
            public function createShipment(ShipmentPayload $p): ShipmentResult { throw new \RuntimeException; }
            public function getShipment(string $r): ShipmentResult { throw new \RuntimeException; }
            public function track(string $t): TrackingResult { throw new \RuntimeException; }
            public function getRates(RatePayload $p): RateCollection { throw new \RuntimeException; }
            public function cancelShipment(string $w, ?string $r = null): CancelResult { throw new \RuntimeException; }
            public function getLabel(string $w, ?string $r = null): LabelResult { throw new \RuntimeException; }
            public function getAvailability(AvailabilityPayload $p): ServiceCollection { throw new \RuntimeException; }
            public function verifyWebhook(Request $request): bool { return true; }
            public function handleWebhook(Request $request): void {}
            public function extractWebhookReference(Request $request): array
            {
                return ['reference' => $request->input('ref'), 'waybillNumber' => $request->input('waybill')];
            }
        };
        app('courier')->extend('extracting-webhook-driver', fn () => $driver);
    }

    private function registerFailingWebhookDriver(): void
    {
        $driver = new class implements CourierDriver, HandlesWebhooks {
            public function createShipment(ShipmentPayload $p): ShipmentResult { throw new \RuntimeException; }
            public function getShipment(string $r): ShipmentResult { throw new \RuntimeException; }
            public function track(string $t): TrackingResult { throw new \RuntimeException; }
            public function getRates(RatePayload $p): RateCollection { throw new \RuntimeException; }
            public function cancelShipment(string $w, ?string $r = null): CancelResult { throw new \RuntimeException; }
            public function getLabel(string $w, ?string $r = null): LabelResult { throw new \RuntimeException; }
            public function getAvailability(AvailabilityPayload $p): ServiceCollection { throw new \RuntimeException; }
            public function verifyWebhook(Request $request): bool { return true; }
            public function handleWebhook(Request $request): void { throw new \RuntimeException('processing blew up'); }
        };
        app('courier')->extend('failing-webhook-driver', fn () => $driver);
    }

    public function test_webhook_route_returns_404_for_unknown_driver(): void
    {
        $response = $this->postJson('/courier/webhook/nonexistent', []);
        $response->assertStatus(404);
        $this->assertSame(0, CourierWebhookLog::count());
    }

    public function test_webhook_route_returns_404_when_driver_does_not_handle_webhooks(): void
    {
        $driver = $this->createMock(CourierDriver::class);
        app('courier')->extend('no-webhook-driver', fn () => $driver);

        $response = $this->postJson('/courier/webhook/no-webhook-driver', []);
        $response->assertStatus(404);
        $this->assertSame(0, CourierWebhookLog::count());
    }

    public function test_webhook_route_returns_401_when_verification_fails(): void
    {
        $this->registerWebhookDriver(verifies: false);

        $response = $this->postJson('/courier/webhook/test-webhook-driver', ['event' => 'test']);
        $response->assertStatus(401);

        $log = CourierWebhookLog::first();
        $this->assertNotNull($log);
        $this->assertSame('test-webhook-driver', $log->driver);
        $this->assertFalse($log->verified);
        $this->assertSame('rejected', $log->status);
    }

    public function test_webhook_route_fires_generic_event_and_returns_200(): void
    {
        Event::fake([WebhookReceived::class]);
        $this->registerWebhookDriver(verifies: true);

        $response = $this->postJson('/courier/webhook/test-webhook-driver', ['event' => 'order.status.updated']);
        $response->assertStatus(200);

        Event::assertDispatched(WebhookReceived::class, function ($e) {
            return $e->driver === 'test-webhook-driver'
                && $e->payload['event'] === 'order.status.updated';
        });
    }

    public function test_successful_webhook_is_logged_as_processed(): void
    {
        $this->registerWebhookDriver(verifies: true);

        $response = $this->postJson('/courier/webhook/test-webhook-driver', ['event' => 'order.status.updated']);
        $response->assertStatus(200);

        $log = CourierWebhookLog::first();
        $this->assertNotNull($log);
        $this->assertSame('test-webhook-driver', $log->driver);
        $this->assertTrue($log->verified);
        $this->assertSame('processed', $log->status);
    }

    public function test_reference_extracted_when_driver_implements_contract(): void
    {
        $this->registerExtractingWebhookDriver();

        $this->postJson('/courier/webhook/extracting-webhook-driver', ['ref' => 'REF-1', 'waybill' => 'WB-1']);

        $log = CourierWebhookLog::first();
        $this->assertSame('REF-1', $log->reference);
        $this->assertSame('WB-1', $log->waybill_number);
    }

    public function test_reference_stays_null_when_driver_does_not_implement_contract(): void
    {
        $this->registerWebhookDriver(verifies: true);

        $this->postJson('/courier/webhook/test-webhook-driver', ['event' => 'test']);

        $log = CourierWebhookLog::first();
        $this->assertNull($log->reference);
        $this->assertNull($log->waybill_number);
    }

    public function test_handle_webhook_exception_is_logged_as_failed_and_rethrown(): void
    {
        $this->registerFailingWebhookDriver();
        $this->withoutExceptionHandling();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('processing blew up');

        try {
            $this->post('/courier/webhook/failing-webhook-driver', ['event' => 'test']);
        } finally {
            $log = CourierWebhookLog::first();
            $this->assertNotNull($log);
            $this->assertTrue($log->verified);
            $this->assertSame('failed', $log->status);
            $this->assertSame('processing blew up', $log->error_message);
        }
    }
}
