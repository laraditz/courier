<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laraditz\Courier\Http\CourierHttpClient;
use Laraditz\Courier\Models\CourierApiLog;

class CourierHttpClientRequestOptionsTest extends TestCase
{
    public function test_default_body_format_is_json(): void
    {
        Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

        (new CourierHttpClient())
            ->forLog(driver: 'acme', action: 'create_order')
            ->post('https://api.example.com/orders', ['foo' => 'bar']);

        Http::assertSent(function ($request) {
            $this->assertStringContainsString('application/json', $request->header('Content-Type')[0]);
            $this->assertSame('{"foo":"bar"}', $request->body());

            return true;
        });
    }

    public function test_as_form_sends_form_encoded_body(): void
    {
        Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

        (new CourierHttpClient())
            ->forLog(driver: 'acme', action: 'create_order')
            ->asForm()
            ->post('https://api.example.com/orders', ['bizContent' => '{"a":1}']);

        Http::assertSent(function ($request) {
            $this->assertStringContainsString(
                'application/x-www-form-urlencoded',
                $request->header('Content-Type')[0]
            );
            $this->assertSame('bizContent=' . urlencode('{"a":1}'), $request->body());

            return true;
        });
    }

    public function test_as_form_request_is_still_logged(): void
    {
        Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

        (new CourierHttpClient())
            ->forLog(driver: 'jtexpress', action: 'order/addOrder', reference: 'REF-9')
            ->asForm()
            ->post('https://api.example.com/orders', ['bizContent' => '{"a":1}']);

        $this->assertSame(1, CourierApiLog::count());

        $log = CourierApiLog::first();
        $this->assertSame('jtexpress', $log->driver);
        $this->assertSame('order/addOrder', $log->action);
        $this->assertSame('REF-9', $log->reference);
        $this->assertSame('{"a":1}', $log->request_body['bizContent']);
        $this->assertTrue($log->successful);
    }

    public function test_timeout_is_applied_to_the_pending_request(): void
    {
        $client = new class () extends CourierHttpClient {
            public function exposedPendingRequest(array $headers): PendingRequest
            {
                return $this->pendingRequest($headers);
            }
        };

        $client->forLog(driver: 'acme', action: 'create_order')->timeout(7);

        $this->assertSame(7, $client->exposedPendingRequest([])->getOptions()['timeout']);
    }

    public function test_timeout_defaults_to_laravel_default_when_not_set(): void
    {
        $client = new class () extends CourierHttpClient {
            public function exposedPendingRequest(array $headers): PendingRequest
            {
                return $this->pendingRequest($headers);
            }
        };

        $client->forLog(driver: 'acme', action: 'create_order');

        // Laravel's PendingRequest default is 30; CourierHttpClient must not override it.
        $this->assertSame(30, $client->exposedPendingRequest([])->getOptions()['timeout']);
    }

    public function test_as_form_and_timeout_are_fluent(): void
    {
        $client = (new CourierHttpClient())->forLog(driver: 'acme', action: 'create_order');

        $this->assertInstanceOf(CourierHttpClient::class, $client->asForm());
        $this->assertInstanceOf(CourierHttpClient::class, $client->timeout(5));
    }
}
