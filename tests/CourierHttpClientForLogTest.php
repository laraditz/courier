<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Http\CourierHttpClient;

class CourierHttpClientForLogTest extends TestCase
{
    public function test_verb_method_without_forlog_throws_logic_exception(): void
    {
        $this->expectException(\LogicException::class);

        (new CourierHttpClient())->post('https://api.example.com');
    }

    public function test_forlog_returns_instance_with_verb_methods_available(): void
    {
        $client = (new CourierHttpClient())->forLog(driver: 'sfexpress', action: 'createShipment');

        $this->assertInstanceOf(CourierHttpClient::class, $client);
    }
}
