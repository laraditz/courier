<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Models\CourierApiLog;

class CourierApiLogTest extends TestCase
{
    public function test_json_columns_are_cast_to_array(): void
    {
        $log = CourierApiLog::create([
            'driver' => 'sfexpress',
            'action' => 'createShipment',
            'method' => 'POST',
            'url' => 'https://api.example.com/shipments',
            'request_headers' => ['Content-Type' => 'application/json'],
            'request_body' => ['foo' => 'bar'],
            'status_code' => 200,
            'response_headers' => ['X-Request-Id' => 'abc'],
            'response_body' => ['ok' => true],
            'duration_ms' => 123,
            'successful' => true,
        ]);

        $fresh = CourierApiLog::find($log->id);

        $this->assertIsArray($fresh->request_headers);
        $this->assertIsArray($fresh->request_body);
        $this->assertIsArray($fresh->response_headers);
        $this->assertIsArray($fresh->response_body);
        $this->assertTrue($fresh->successful);
        $this->assertSame(200, $fresh->status_code);
        $this->assertSame(123, $fresh->duration_ms);
    }

    public function test_model_has_no_updated_at_column(): void
    {
        $this->assertNull(CourierApiLog::UPDATED_AT);
    }
}
