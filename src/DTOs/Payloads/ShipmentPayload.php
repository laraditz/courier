<?php

namespace Laraditz\Courier\DTOs\Payloads;

use Laraditz\Courier\DTOs\Shared\Address;
use Laraditz\Courier\DTOs\Shared\Parcel;

readonly class ShipmentPayload
{
    public function __construct(
        public Address $sender,
        public Address $recipient,
        public Parcel $parcel,
        public string $serviceCode,
        public ?string $remarks = null,
    ) {}
}
