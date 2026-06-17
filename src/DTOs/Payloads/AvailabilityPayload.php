<?php

namespace Laraditz\Courier\DTOs\Payloads;

use Laraditz\Courier\DTOs\Shared\Location;

readonly class AvailabilityPayload
{
    public function __construct(
        public Location $origin,
        public Location $destination,
    ) {}
}
