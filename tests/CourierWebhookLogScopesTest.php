<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Models\CourierWebhookLog;

class CourierWebhookLogScopesTest extends TestCase
{
    private function makeLog(array $overrides = []): CourierWebhookLog
    {
        return CourierWebhookLog::create(array_merge([
            'driver' => 'sfexpress',
            'reference' => 'REF-1',
            'verified' => true,
            'status' => 'processed',
        ], $overrides));
    }

    public function test_for_reference_scope(): void
    {
        $this->makeLog(['reference' => 'REF-A']);
        $this->makeLog(['reference' => 'REF-B']);

        $this->assertCount(1, CourierWebhookLog::forReference('REF-A')->get());
    }

    public function test_for_driver_scope(): void
    {
        $this->makeLog(['driver' => 'sfexpress']);
        $this->makeLog(['driver' => 'other']);

        $this->assertCount(1, CourierWebhookLog::forDriver('other')->get());
    }

    public function test_rejected_processed_failed_scopes(): void
    {
        $this->makeLog(['status' => 'rejected', 'verified' => false]);
        $this->makeLog(['status' => 'processed']);
        $this->makeLog(['status' => 'failed']);

        $this->assertCount(1, CourierWebhookLog::rejected()->get());
        $this->assertCount(1, CourierWebhookLog::processed()->get());
        $this->assertCount(1, CourierWebhookLog::failed()->get());
    }
}
