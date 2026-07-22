<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\Models\CourierWebhookLog;

class CourierWebhookLogTest extends TestCase
{
    public function test_json_columns_are_cast_to_array(): void
    {
        $log = CourierWebhookLog::create([
            'driver' => 'sfexpress',
            'headers' => ['Content-Type' => 'application/json'],
            'payload' => ['event' => 'order.status.updated'],
            'verified' => true,
            'status' => 'processed',
        ]);

        $fresh = CourierWebhookLog::find($log->id);

        $this->assertIsArray($fresh->headers);
        $this->assertIsArray($fresh->payload);
        $this->assertTrue($fresh->verified);
        $this->assertSame('processed', $fresh->status);
    }

    public function test_model_has_no_updated_at_column(): void
    {
        $this->assertNull(CourierWebhookLog::UPDATED_AT);
    }
}
