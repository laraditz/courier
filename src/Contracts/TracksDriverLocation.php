<?php

namespace Laraditz\Courier\Contracts;

use Laraditz\Courier\DTOs\Results\DriverLocationResult;

interface TracksDriverLocation
{
    public function getDriverLocation(string $orderId, string $driverId): DriverLocationResult;
}
