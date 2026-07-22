<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Schema;

class CourierWebhookLogsMigrationTest extends TestCase
{
    public function test_courier_webhook_logs_table_has_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('courier_webhook_logs'));

        $this->assertTrue(Schema::hasColumns('courier_webhook_logs', [
            'id',
            'driver',
            'reference',
            'waybill_number',
            'headers',
            'payload',
            'verified',
            'status',
            'error_message',
            'created_at',
        ]));
    }
}
