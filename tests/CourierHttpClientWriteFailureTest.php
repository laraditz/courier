<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laraditz\Courier\Http\CourierHttpClient;

class CourierHttpClientWriteFailureTest extends TestCase
{
    public function test_db_write_failure_never_blocks_the_real_response(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        Schema::drop('courier_api_logs');

        Log::shouldReceive('error')->once();

        $response = (new CourierHttpClient())
            ->forLog(driver: 'sfexpress', action: 'createShipment')
            ->post('https://api.example.com/shipments', ['foo' => 'bar']);

        $this->assertTrue($response->successful());
        $this->assertSame(['ok' => true], $response->json());
    }
}
