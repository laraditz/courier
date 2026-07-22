<?php

namespace Laraditz\Courier\Tests;

use Illuminate\Support\ServiceProvider;
use Laraditz\Courier\CourierServiceProvider;

class CourierServiceProviderPublishTest extends TestCase
{
    public function test_migrations_are_registered_under_courier_migrations_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(CourierServiceProvider::class, 'courier-migrations');

        $this->assertNotEmpty($paths);

        $sources = array_keys($paths);

        $this->assertTrue(
            collect($sources)->contains(fn ($path) => str_ends_with($path, 'create_courier_api_logs_table.php'))
        );
        $this->assertTrue(
            collect($sources)->contains(fn ($path) => str_ends_with($path, 'create_courier_webhook_logs_table.php'))
        );
    }
}
