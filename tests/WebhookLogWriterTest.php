<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Log;
use Laraditz\Courier\Logging\WebhookLogWriter;
use Laraditz\Courier\Models\CourierWebhookLog;

class WebhookLogWriterTest extends TestCase
{
    public function test_record_persists_a_redacted_row(): void
    {
        config(['courier.logging.redact' => ['secret']]);

        (new WebhookLogWriter())->record([
            'driver' => 'sfexpress',
            'headers' => ['secret' => 'top-secret'],
            'payload' => ['event' => 'order.status.updated'],
            'verified' => true,
            'status' => 'processed',
        ]);

        $log = CourierWebhookLog::first();

        $this->assertNotNull($log);
        $this->assertSame('[REDACTED]', $log->headers['secret']);
        $this->assertSame('order.status.updated', $log->payload['event']);
    }

    public function test_write_failure_is_caught_and_logged(): void
    {
        Log::shouldReceive('error')->once();

        (new WebhookLogWriter())->record([
            'driver' => 'sfexpress',
            // missing required 'verified'/'status' triggers a DB-level failure
        ]);

        $this->assertSame(0, CourierWebhookLog::count());
    }
}
