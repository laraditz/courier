<?php

namespace Laraditz\Courier\Contracts;

interface ManagesAssignedDriver
{
    public function removeDriver(string $orderId, string $driverId): void;
}
