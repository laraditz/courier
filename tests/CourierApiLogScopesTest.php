<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Models\CourierApiLog;

class CourierApiLogScopesTest extends TestCase
{
    private function makeLog(array $overrides = []): CourierApiLog
    {
        return CourierApiLog::create(array_merge([
            'driver' => 'sfexpress',
            'action' => 'createShipment',
            'reference' => 'REF-1',
            'method' => 'POST',
            'url' => 'https://api.example.com',
            'duration_ms' => 10,
            'successful' => true,
        ], $overrides));
    }

    public function test_for_reference_scope(): void
    {
        $this->makeLog(['reference' => 'REF-A']);
        $this->makeLog(['reference' => 'REF-B']);

        $this->assertCount(1, CourierApiLog::forReference('REF-A')->get());
    }

    public function test_for_driver_scope(): void
    {
        $this->makeLog(['driver' => 'sfexpress']);
        $this->makeLog(['driver' => 'other']);

        $this->assertCount(1, CourierApiLog::forDriver('other')->get());
    }

    public function test_successful_and_failed_scopes(): void
    {
        $this->makeLog(['successful' => true]);
        $this->makeLog(['successful' => false]);

        $this->assertCount(1, CourierApiLog::successful()->get());
        $this->assertCount(1, CourierApiLog::failed()->get());
    }
}
