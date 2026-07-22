<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Log;
use Laraditz\Courier\Logging\ApiLogWriter;
use Laraditz\Courier\Models\CourierApiLog;

class ApiLogWriterTest extends TestCase
{
    public function test_record_persists_a_redacted_row(): void
    {
        config(['courier.logging.redact' => ['secret']]);

        (new ApiLogWriter())->record([
            'driver' => 'sfexpress',
            'action' => 'createShipment',
            'method' => 'POST',
            'url' => 'https://api.example.com',
            'request_headers' => ['secret' => 'top-secret'],
            'request_body' => ['foo' => 'bar'],
            'status_code' => 200,
            'response_headers' => [],
            'response_body' => ['ok' => true],
            'duration_ms' => 50,
            'successful' => true,
        ]);

        $log = CourierApiLog::first();

        $this->assertNotNull($log);
        $this->assertSame('[REDACTED]', $log->request_headers['secret']);
        $this->assertSame('bar', $log->request_body['foo']);
    }

    public function test_write_failure_is_caught_and_logged(): void
    {
        Log::shouldReceive('error')->once();

        (new ApiLogWriter())->record([
            // missing required 'method'/'url'/etc triggers a DB-level failure
            'driver' => 'sfexpress',
        ]);

        $this->assertSame(0, CourierApiLog::count());
    }
}
