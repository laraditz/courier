<?php

namespace Laraditz\Courier\Tests;

class ConfigLoggingTest extends TestCase
{
    public function test_logging_config_has_expected_defaults(): void
    {
        $this->assertSame(true, config('courier.logging.enabled'));
        $this->assertSame(90, config('courier.logging.retention_days'));
        $this->assertSame(
            ['authorization', 'api_key', 'apikey', 'key', 'secret', 'token', 'password'],
            config('courier.logging.redact')
        );
    }
}
