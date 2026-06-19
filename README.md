# Laravel Courier

A unified interface for multiple courier and shipping carrier services in Laravel.

## Overview

This package provides a driver-based abstraction layer for courier integrations. Define your shipments once using strongly-typed DTOs — the driver handles the carrier-specific API calls and returns normalized results.

Each carrier ships as a separate Composer package. Install only what you need.

## Requirements

- PHP 8.1+
- Laravel 10, 11, 12, or 13

## Installation

```bash
composer require laraditz/courier
```

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=courier-config
```

## Available Drivers

| Package                                                                     | Carrier    |
| --------------------------------------------------------------------------- | ---------- |
| [laraditz/courier-sfexpress](https://github.com/laraditz/courier-sfexpress) | SF Express |

## Configuration

`config/courier.php`:

```php
return [
    'default' => env('COURIER_DRIVER', 'sfexpress'),

    'drivers' => [
        'sfexpress' => [
            'key'              => env('SFEXPRESS_KEY'),
            'secret'           => env('SFEXPRESS_SECRET'),
            'customer_code'    => env('SFEXPRESS_CUSTOMER_CODE'),
            'encoding_aes_key' => env('SFEXPRESS_AES_KEY'),
            'pay_month_card'   => env('SFEXPRESS_PAY_MONTH_CARD'),
            'country'          => env('SFEXPRESS_COUNTRY', 'MY'),
            'scope_name'       => env('SFEXPRESS_SCOPE', 'OSMY'),
            'sandbox'          => env('SFEXPRESS_SANDBOX', false),
        ],
    ],
];
```

## Available Methods

| Method            | Parameters                     | Returns             | Description                                            |
| ----------------- | ------------------------------ | ------------------- | ------------------------------------------------------ |
| `createShipment`  | `ShipmentPayload $payload`     | `ShipmentResult`    | Book a new shipment and get a waybill number           |
| `track`           | `string $trackingNumber`       | `TrackingResult`    | Get current status and full tracking history           |
| `getRates`        | `RatePayload $payload`         | `RateCollection`    | Fetch available service options and prices for a route |
| `cancelShipment`  | `string $waybillNumber`        | `CancelResult`      | Cancel an existing shipment                            |
| `getLabel`        | `string $waybillNumber`        | `LabelResult`       | Retrieve the shipping label (PDF bytes or ZPL)         |
| `getAvailability` | `AvailabilityPayload $payload` | `ServiceCollection` | List services available between two locations          |

> **Driver support:** Not all drivers implement every method. Calling an unsupported method throws `Laraditz\Courier\Exceptions\UnsupportedOperationException`. Check the driver's documentation for which methods are available.

### Result DTOs

| DTO                 | Key Properties                                                       |
| ------------------- | -------------------------------------------------------------------- |
| `ShipmentResult`    | `waybillNumber`, `status`, `estimatedDelivery`, `meta()`             |
| `TrackingResult`    | `waybillNumber`, `status`, `estimatedDelivery`, `events[]`, `meta()` |
| `TrackingEvent`     | `timestamp`, `location`, `description`, `status`                     |
| `RateCollection`    | `items[]` → `RateOption`                                             |
| `RateOption`        | `serviceCode`, `serviceName`, `price`, `currency`, `estimatedDays`   |
| `CancelResult`      | `success`, `message`, `meta()`                                       |
| `LabelResult`       | `waybillNumber`, `format`, `content`, `meta()`                       |
| `ServiceCollection` | `items[]` → `ServiceOption`                                          |
| `ServiceOption`     | `code`, `name`, `description`, `estimatedDays`                       |

### Payload DTOs

| DTO                   | Properties                                                                                           |
| --------------------- | ---------------------------------------------------------------------------------------------------- |
| `ShipmentPayload`     | `sender: Address`, `recipient: Address`, `parcel: Parcel`, `serviceCode: string`, `remarks: ?string` |
| `RatePayload`         | `origin: Location`, `destination: Location`, `parcel: Parcel`                                        |
| `AvailabilityPayload` | `origin: Location`, `destination: Location`                                                          |
| `Address`             | `name`, `phone`, `email`, `line1`, `line2`, `line3`, `city`, `state`, `postcode`, `country`          |
| `Location`            | `postcode`, `city`, `state`, `country`                                                               |
| `Parcel`              | `weight`, `length`, `width`, `height`, `declaredValue`, `description`, `quantity`                    |

---

## Usage

### Create a Shipment

```php
use Laraditz\Courier\Facades\Courier;
use Laraditz\Courier\DTOs\Shared\Address;
use Laraditz\Courier\DTOs\Shared\Parcel;
use Laraditz\Courier\DTOs\Payloads\ShipmentPayload;

$result = Courier::createShipment(new ShipmentPayload(
    sender: new Address(
        name: 'Raditz Farhan',
        phone: '+60123456789',
        email: null,
        line1: 'No 1 Jalan Test',
        line2: null,
        line3: null,
        city: 'Kuala Lumpur',
        state: 'Wilayah Persekutuan',
        postcode: '50000',
        country: 'MY',
    ),
    recipient: new Address(/* ... */),
    parcel: new Parcel(
        weight: 1.5,
        length: 20.0,
        width: 15.0,
        height: 10.0,
        declaredValue: 100.0,
        description: 'Goods',
        quantity: 1,
    ),
    serviceCode: 'M102',
));

$result->waybillNumber; // 'MYIU1234715622'
$result->status;        // 'pending'
```

### Track a Shipment

```php
$result = Courier::track('MYIU1234715622');

$result->waybillNumber;  // 'MYIU1234715622'
$result->status;         // 'in_transit'
$result->events;         // TrackingEvent[]

foreach ($result->events as $event) {
    $event->timestamp;   // Carbon
    $event->location;    // 'Kuala Lumpur Hub'
    $event->description; // 'Package picked up'
    $event->status;      // 'picked_up'
}
```

### Get Rates

```php
use Laraditz\Courier\DTOs\Shared\Location;
use Laraditz\Courier\DTOs\Payloads\RatePayload;

$rates = Courier::getRates(new RatePayload(
    origin: new Location('50000', 'Kuala Lumpur', 'Wilayah Persekutuan', 'MY'),
    destination: new Location('10000', 'Georgetown', 'Pulau Pinang', 'MY'),
    parcel: new Parcel(1.5, 20, 15, 10, 100, 'Goods', 1),
));

foreach ($rates->items as $option) {
    $option->serviceCode;    // 'STANDARD'
    $option->serviceName;    // 'Standard Delivery'
    $option->price;          // 12.50
    $option->currency;       // 'MYR'
    $option->estimatedDays;  // 3
}
```

### Other Operations

```php
// Cancel
$result = Courier::cancelShipment('MYIU1234715622');
$result->success;  // true

// Get label — content is raw bytes (PDF or ZPL depending on driver)
$result = Courier::getLabel('MYIU1234715622');
$result->format;   // 'pdf'
$result->content;  // raw bytes

// Check service availability by route
$services = Courier::getAvailability(new AvailabilityPayload(
    origin: new Location('50000', 'Kuala Lumpur', 'Wilayah Persekutuan', 'MY'),
    destination: new Location('10000', 'Georgetown', 'Pulau Pinang', 'MY'),
));
```

### Switching Drivers

```php
// Use a specific driver explicitly
Courier::driver('sfexpress')->track('MYIU1234715622');
```

### Testing

Use `Courier::fake()` to mock courier calls in tests:

```php
use Laraditz\Courier\Facades\Courier;

$fake = Courier::fake();

// Your code under test runs here...
$this->service->bookShipment($order);

$fake->assertShipmentCreated(1);
$fake->assertShipmentCreated(fn ($payload) => $payload->serviceCode === 'STANDARD');
$fake->assertTracked('SF1234567890');
$fake->assertCancelled('SF1234567890');
$fake->assertRatesFetched();
$fake->assertLabelFetched('SF1234567890');
$fake->assertNothingSent();
```

Provide preset responses:

```php
use Laraditz\Courier\DTOs\Results\ShipmentResult;

$fake = Courier::fake([
    'createShipment' => new ShipmentResult('CUSTOM-001', 'pending', null),
]);
```

## Normalized Status Vocabulary

All drivers map their carrier-specific statuses to these values:

| Status             | Meaning                             |
| ------------------ | ----------------------------------- |
| `pending`          | Shipment created, not yet picked up |
| `picked_up`        | Collected by courier                |
| `in_transit`       | Moving through the network          |
| `out_for_delivery` | On the delivery vehicle             |
| `delivered`        | Successfully delivered              |
| `failed_delivery`  | Delivery attempt failed             |
| `returned`         | Returned to sender                  |
| `cancelled`        | Shipment cancelled                  |
| `unknown`          | Status not recognized               |

## Building a Custom Driver

Implement `Laraditz\Courier\Contracts\CourierDriver` and register it:

```php
// In your ServiceProvider
$this->app->make('courier')->extend('mycarrier', function ($app, $config) {
    return new MyCarrierDriver($config);
});
```

## License

MIT
