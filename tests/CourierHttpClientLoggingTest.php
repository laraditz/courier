<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Http;
use Laraditz\Courier\Http\CourierHttpClient;
use Laraditz\Courier\Models\CourierApiLog;

class CourierHttpClientLoggingTest extends TestCase
{
    public function test_successful_call_creates_one_log_row_with_redacted_header(): void
    {
        config(['courier.logging.redact' => ['secret']]);

        Http::fake([
            'api.example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $response = (new CourierHttpClient())
            ->forLog(driver: 'sfexpress', action: 'createShipment', reference: 'REF-1')
            ->post('https://api.example.com/shipments', ['secret' => 'x', 'foo' => 'bar']);

        $this->assertTrue($response->successful());
        $this->assertSame(1, CourierApiLog::count());

        $log = CourierApiLog::first();
        $this->assertSame('sfexpress', $log->driver);
        $this->assertSame('createShipment', $log->action);
        $this->assertSame('REF-1', $log->reference);
        $this->assertSame('POST', $log->method);
        $this->assertSame('https://api.example.com/shipments', $log->url);
        $this->assertSame(200, $log->status_code);
        $this->assertTrue($log->successful);
        $this->assertSame('[REDACTED]', $log->request_body['secret']);
        $this->assertSame('bar', $log->request_body['foo']);
        $this->assertIsInt($log->duration_ms);
    }
}
