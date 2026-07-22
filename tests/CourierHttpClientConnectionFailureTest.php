<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laraditz\Courier\Http\CourierHttpClient;
use Laraditz\Courier\Models\CourierApiLog;

class CourierHttpClientConnectionFailureTest extends TestCase
{
    public function test_connection_failure_is_logged_and_rethrown(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $this->expectException(ConnectionException::class);

        try {
            (new CourierHttpClient())
                ->forLog(driver: 'sfexpress', action: 'createShipment')
                ->post('https://api.example.com/shipments', ['foo' => 'bar']);
        } finally {
            $log = CourierApiLog::first();
            $this->assertNotNull($log);
            $this->assertNull($log->status_code);
            $this->assertFalse($log->successful);
            $this->assertSame('Connection timed out', $log->error_message);
        }
    }
}
