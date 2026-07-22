<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Http;
use Laraditz\Courier\Http\CourierHttpClient;
use Laraditz\Courier\Models\CourierApiLog;

class CourierHttpClientNonJsonBodyTest extends TestCase
{
    public function test_non_json_response_body_is_wrapped_as_raw(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response('<xml>not json</xml>', 200, ['Content-Type' => 'text/xml']),
        ]);

        (new CourierHttpClient())
            ->forLog(driver: 'sfexpress', action: 'getLabel')
            ->get('https://api.example.com/label');

        $log = CourierApiLog::first();

        $this->assertSame(['_raw' => '<xml>not json</xml>'], $log->response_body);
    }
}
