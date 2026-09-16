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

    public function test_last_record_returns_the_created_row(): void
    {
        $writer = new WebhookLogWriter();

        $writer->record([
            'driver' => 'sfexpress',
            'headers' => [],
            'payload' => ['event' => 'order.status.updated'],
            'verified' => true,
            'status' => 'processed',
        ]);

        $row = $writer->lastRecord();

        $this->assertNotNull($row);
        $this->assertSame(CourierWebhookLog::first()->id, $row->id);
        $this->assertSame('sfexpress', $row->driver);
        $this->assertSame('processed', $row->status);
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

    public function test_last_record_is_null_when_the_write_failed(): void
    {
        Log::shouldReceive('error')->once();

        $writer = new WebhookLogWriter();

        $this->assertNull($writer->lastRecord(), 'null before any record() call');

        $writer->record([
            'driver' => 'sfexpress',
            // missing required 'verified'/'status' triggers a DB-level failure
        ]);

        $this->assertNull($writer->lastRecord());
    }

    public function test_last_record_is_cleared_by_a_subsequent_failed_write(): void
    {
        Log::shouldReceive('error')->once();

        $writer = new WebhookLogWriter();

        $writer->record([
            'driver' => 'sfexpress',
            'headers' => [],
            'payload' => [],
            'verified' => true,
            'status' => 'processed',
        ]);

        $this->assertNotNull($writer->lastRecord());

        // A failed write must not leave the previous row readable as if it were this one.
        $writer->record(['driver' => 'sfexpress']);

        $this->assertNull($writer->lastRecord());
    }
}
