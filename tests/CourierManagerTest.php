<?php

namespace Laraditz\Courier\Tests;

use Laraditz\Courier\CourierManager;
use Laraditz\Courier\Contracts\CourierDriver;

class CourierManagerTest extends TestCase
{
    public function test_resolves_default_driver_from_config(): void
    {
        config(['courier.default' => 'fake-driver']);

        $fakeDriver = $this->createMock(CourierDriver::class);

        $manager = new CourierManager(app());
        $manager->extend('fake-driver', fn ($app, $config) => $fakeDriver);

        $this->assertSame($fakeDriver, $manager->driver());
    }

    public function test_injects_driver_config_into_creator(): void
    {
        config([
            'courier.default' => 'test-driver',
            'courier.drivers.test-driver' => ['key' => 'secret-value'],
        ]);

        $capturedConfig = null;

        $manager = new CourierManager(app());
        $manager->extend('test-driver', function ($app, $config) use (&$capturedConfig) {
            $capturedConfig = $config;
            return $this->createMock(CourierDriver::class);
        });

        $manager->driver();

        $this->assertSame(['key' => 'secret-value'], $capturedConfig);
    }

    public function test_can_switch_driver_per_call(): void
    {
        $driverA = $this->createMock(CourierDriver::class);
        $driverB = $this->createMock(CourierDriver::class);

        $manager = new CourierManager(app());
        $manager->extend('driver-a', fn ($app, $config) => $driverA);
        $manager->extend('driver-b', fn ($app, $config) => $driverB);

        $this->assertSame($driverA, $manager->driver('driver-a'));
        $this->assertSame($driverB, $manager->driver('driver-b'));
    }
}
