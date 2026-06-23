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

class WebhookTest extends TestCase
{
    private function registerWebhookDriver(bool $verifies = true): void
    {
        $driver = new class($verifies) implements CourierDriver, HandlesWebhooks {
            public function __construct(private bool $verifies) {}
            public function createShipment(ShipmentPayload $p): ShipmentResult { throw new \RuntimeException; }
            public function track(string $t): TrackingResult { throw new \RuntimeException; }
            public function getRates(RatePayload $p): RateCollection { throw new \RuntimeException; }
            public function cancelShipment(string $w): CancelResult { throw new \RuntimeException; }
            public function getLabel(string $w): LabelResult { throw new \RuntimeException; }
            public function getAvailability(AvailabilityPayload $p): ServiceCollection { throw new \RuntimeException; }
            public function verifyWebhook(Request $request): bool { return $this->verifies; }
            public function handleWebhook(Request $request): void {}
        };
        app('courier')->extend('test-webhook-driver', fn () => $driver);
    }

    public function test_webhook_route_returns_404_for_unknown_driver(): void
    {
        $response = $this->postJson('/courier/webhook/nonexistent', []);
        $response->assertStatus(404);
    }

    public function test_webhook_route_returns_404_when_driver_does_not_handle_webhooks(): void
    {
        $driver = $this->createMock(CourierDriver::class);
        app('courier')->extend('no-webhook-driver', fn () => $driver);

        $response = $this->postJson('/courier/webhook/no-webhook-driver', []);
        $response->assertStatus(404);
    }

    public function test_webhook_route_returns_401_when_verification_fails(): void
    {
        $this->registerWebhookDriver(verifies: false);

        $response = $this->postJson('/courier/webhook/test-webhook-driver', ['event' => 'test']);
        $response->assertStatus(401);
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
}
