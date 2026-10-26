<?php

namespace Laraditz\Courier\DTOs\Payloads;

use Carbon\Carbon;
use Laraditz\Courier\DTOs\Shared\Address;
use Laraditz\Courier\DTOs\Shared\Parcel;
use Laraditz\Courier\Enums\FulfillmentMode;

readonly class ShipmentPayload
{
    /**
     * @param  Carbon|null  $scheduledAt  Start of the requested collection window.
     * @param  FulfillmentMode|null  $fulfillment  How the parcel reaches the courier. Null
     *                                            leaves the choice to the driver's default —
     *                                            carriers that price or dispatch differently
     *                                            per mode need this set at booking time,
     *                                            since none of them accept it afterwards.
     * @param  Carbon|null  $scheduledUntil  End of the requested collection window.
     *
     * New parameters are appended, never inserted: this is a readonly class with promoted
     * constructor properties, so a mid-list addition would silently shift every positional
     * caller onto the wrong argument.
     */
    public function __construct(
        public Address $sender,
        public Address $recipient,
        public Parcel $parcel,
        public string $serviceCode,
        public ?string $remarks = null,
        public ?Carbon $scheduledAt = null,
        public ?string $reference = null,
        public array $meta = [],
        public ?FulfillmentMode $fulfillment = null,
        public ?Carbon $scheduledUntil = null,
    ) {}
}
