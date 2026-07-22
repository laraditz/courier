<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Carbon;
use Laraditz\Courier\Models\CourierApiLog;
use Laraditz\Courier\Models\CourierWebhookLog;

class PruneCourierLogsCommandTest extends TestCase
{
    public function test_prunes_api_logs_older_than_retention_days(): void
    {
        config(['courier.logging.retention_days' => 90]);

        $old = CourierApiLog::create([
            'driver' => 'sfexpress',
            'action' => 'createShipment',
            'method' => 'POST',
            'url' => 'https://api.example.com',
            'duration_ms' => 10,
            'successful' => true,
            'created_at' => Carbon::now()->subDays(100),
        ]);

        $recent = CourierApiLog::create([
            'driver' => 'sfexpress',
            'action' => 'createShipment',
            'method' => 'POST',
            'url' => 'https://api.example.com',
            'duration_ms' => 10,
            'successful' => true,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('courier:prune-logs')->assertExitCode(0);

        $this->assertNull(CourierApiLog::find($old->id));
        $this->assertNotNull(CourierApiLog::find($recent->id));
    }

    public function test_prunes_webhook_logs_older_than_retention_days(): void
    {
        config(['courier.logging.retention_days' => 90]);

        $old = CourierWebhookLog::create([
            'driver' => 'sfexpress',
            'verified' => true,
            'status' => 'processed',
            'created_at' => Carbon::now()->subDays(100),
        ]);

        $recent = CourierWebhookLog::create([
            'driver' => 'sfexpress',
            'verified' => true,
            'status' => 'processed',
            'created_at' => Carbon::now()->subDays(10),
        ]);

        $this->artisan('courier:prune-logs')->assertExitCode(0);

        $this->assertNull(CourierWebhookLog::find($old->id));
        $this->assertNotNull(CourierWebhookLog::find($recent->id));
    }

    public function test_noops_when_retention_days_is_null(): void
    {
        config(['courier.logging.retention_days' => null]);

        $old = CourierApiLog::create([
            'driver' => 'sfexpress',
            'action' => 'createShipment',
            'method' => 'POST',
            'url' => 'https://api.example.com',
            'duration_ms' => 10,
            'successful' => true,
            'created_at' => Carbon::now()->subDays(1000),
        ]);

        $this->artisan('courier:prune-logs')
            ->expectsOutputToContain('Retention is disabled')
            ->assertExitCode(0);

        $this->assertNotNull(CourierApiLog::find($old->id));
    }
}
