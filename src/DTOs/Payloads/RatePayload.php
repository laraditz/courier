<?php

namespace Laraditz\Courier\DTOs\Payloads;

use Laraditz\Courier\DTOs\Shared\Location;
use Laraditz\Courier\DTOs\Shared\Parcel;

readonly class RatePayload
{
    public function __construct(
        public Location $origin,
        public Location $destination,
        public Parcel $parcel,
    ) {}
}
