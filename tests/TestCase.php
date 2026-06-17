<?php

namespace Laraditz\Courier\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Laraditz\Courier\CourierServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [];
    }
}
