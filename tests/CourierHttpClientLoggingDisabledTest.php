<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Http;
use Laraditz\Courier\Http\CourierHttpClient;
use Laraditz\Courier\Models\CourierApiLog;

class CourierHttpClientLoggingDisabledTest extends TestCase
{
    public function test_no_row_written_when_logging_disabled(): void
    {
        config(['courier.logging.enabled' => false]);

        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $response = (new CourierHttpClient())
            ->forLog(driver: 'sfexpress', action: 'createShipment')
            ->post('https://api.example.com/shipments');

        $this->assertTrue($response->successful());
        $this->assertSame(0, CourierApiLog::count());
    }
}
