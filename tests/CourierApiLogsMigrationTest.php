<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\Facades\Schema;

class CourierApiLogsMigrationTest extends TestCase
{
    public function test_courier_api_logs_table_has_expected_schema(): void
    {
        $this->assertTrue(Schema::hasTable('courier_api_logs'));

        $this->assertTrue(Schema::hasColumns('courier_api_logs', [
            'id',
            'driver',
            'action',
            'reference',
            'waybill_number',
            'method',
            'url',
            'request_headers',
            'request_body',
            'status_code',
            'response_headers',
            'response_body',
            'duration_ms',
            'successful',
            'error_message',
            'created_at',
        ]));
    }
}
