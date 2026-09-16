<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Models\CourierWebhookLog;
use Laraditz\Courier\Tests\Fixtures\ConfigurableWebhookDriver;
use Laraditz\Courier\Tests\Fixtures\PlainWebhookDriver;

class WebhookResponseContractTest extends TestCase
{
    private function registerDriver(string $name, ConfigurableWebhookDriver $driver): ConfigurableWebhookDriver
    {
        app('courier')->extend($name, fn () => $driver);

        return $driver;
    }

    private function registerPlainDriver(string $name, bool $verifies): void
    {
        $driver = new PlainWebhookDriver(verifies: $verifies);

        app('courier')->extend($name, fn () => $driver);
    }

    public function test_accepted_response_from_contract_driver_is_returned_verbatim(): void
    {
        $this->registerDriver('contract-webhook-driver', new ConfigurableWebhookDriver(
            accepted: fn () => response()->json([
                'code' => '1',
                'msg' => 'success',
                'data' => 'SUCCESS',
                'requestId' => '211212121212',
            ]),
        ));

        $response = $this->postJson('/courier/webhook/contract-webhook-driver', ['event' => 'test']);

        $response->assertStatus(200);
        $response->assertExactJson([
            'code' => '1',
            'msg' => 'success',
            'data' => 'SUCCESS',
            'requestId' => '211212121212',
        ]);
    }

    public function test_rejected_response_from_contract_driver_is_returned_and_still_logged(): void
    {
        $this->registerDriver('rejecting-contract-driver', new ConfigurableWebhookDriver(
            verifies: false,
            rejected: fn () => response()->json([
                'code' => '145003030',
                'msg' => 'headers signature verification failed',
            ], 401),
        ));

        $response = $this->postJson('/courier/webhook/rejecting-contract-driver', ['event' => 'test']);

        $response->assertStatus(401);
        $response->assertExactJson([
            'code' => '145003030',
            'msg' => 'headers signature verification failed',
        ]);

        // FR-07: the rejected row must still be written, before the response is returned.
        $log = CourierWebhookLog::first();
        $this->assertNotNull($log);
        $this->assertSame('rejecting-contract-driver', $log->driver);
        $this->assertFalse($log->verified);
        $this->assertSame('rejected', $log->status);
    }

    public function test_driver_without_contract_keeps_default_responses(): void
    {
        $this->registerPlainDriver('plain-accepting-driver', verifies: true);
        $this->registerPlainDriver('plain-rejecting-driver', verifies: false);

        // FR-04: empty 200, exactly as before the contract existed.
        $accepted = $this->postJson('/courier/webhook/plain-accepting-driver', ['event' => 'test']);
        $accepted->assertStatus(200);
        $this->assertSame('', $accepted->getContent());

        // FR-06: unchanged 401.
        $rejected = $this->postJson('/courier/webhook/plain-rejecting-driver', ['event' => 'test']);
        $rejected->assertStatus(401);
    }

    public function test_contract_methods_are_called_only_on_their_own_path(): void
    {
        $accepting = $this->registerDriver('isolated-accepting-driver', new ConfigurableWebhookDriver(
            verifies: true,
        ));
        $rejecting = $this->registerDriver('isolated-rejecting-driver', new ConfigurableWebhookDriver(
            verifies: false,
        ));

        $this->postJson('/courier/webhook/isolated-accepting-driver', ['event' => 'test']);
        $this->postJson('/courier/webhook/isolated-rejecting-driver', ['event' => 'test']);

        $this->assertSame(['accepted'], $accepting->calls);
        $this->assertSame(['rejected'], $rejecting->calls);
    }

    public function test_handle_webhook_exception_bypasses_contract(): void
    {
        $driver = $this->registerDriver('throwing-handler-driver', new ConfigurableWebhookDriver(
            onHandle: fn () => throw new \RuntimeException('processing blew up'),
        ));

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('processing blew up');

        try {
            $this->postJson('/courier/webhook/throwing-handler-driver', ['event' => 'test']);
        } finally {
            // FR-10: the failed row is written and neither contract method runs.
            $log = CourierWebhookLog::first();
            $this->assertNotNull($log);
            $this->assertSame('failed', $log->status);
            $this->assertSame('processing blew up', $log->error_message);
            $this->assertSame([], $driver->calls);
        }
    }

    public function test_contract_response_exception_is_not_swallowed(): void
    {
        $this->registerDriver('throwing-response-driver', new ConfigurableWebhookDriver(
            accepted: fn () => throw new \RuntimeException('ack builder blew up'),
        ));

        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ack builder blew up');

        $this->postJson('/courier/webhook/throwing-response-driver', ['event' => 'test']);
    }
}
