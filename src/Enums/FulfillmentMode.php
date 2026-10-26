<?php

namespace Laraditz\Courier\Enums;

/**
 * How a shipment is handed to the courier.
 *
 * Distinct from DeliveryMode, which describes how it leaves them: a scheduled
 * courier can collect at the door or take a walk-in, and the two are priced and
 * dispatched differently. Carriers that expose the distinction expect it at
 * booking time and cannot be told afterwards.
 */
enum FulfillmentMode: string
{
    /** The courier collects from the sender's address. */
    case Pickup = 'pickup';

    /** The sender hands the parcel in at a branch or drop-off point. */
    case Dropoff = 'dropoff';
}
