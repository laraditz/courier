<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Tests\Fixtures\ConfigurableWebhookDriver;

class WebhookResponseContractTest extends TestCase
{
    private function registerDriver(string $name, ConfigurableWebhookDriver $driver): ConfigurableWebhookDriver
    {
        app('courier')->extend($name, fn () => $driver);

        return $driver;
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
}
