# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-07-15

### Added

- `getShipment()` method on the `CourierDriver` contract, letting drivers with an order-inquiry endpoint (e.g. J&T Express) look up a shipment by the caller's own reference.
- Optional `reference` parameter on `cancelShipment()` and `getLabel()` for couriers whose cancel/label APIs key off the caller's reference rather than the waybill number.
- Optional `reference` property on `ShipmentPayload` and `ShipmentResult`, so a caller-supplied (or driver-generated) order reference can be passed in and echoed back for later use.

### Fixed

- `CourierFake` and the `WebhookTest` driver double updated to conform to the expanded `CourierDriver` contract.

## [1.0.2] - 2026-06-23

### Added

- Webhook infrastructure: `HandlesWebhooks` interface, `WebhookReceived` event, `WebhookController`, and `POST /courier/webhook/{driver}` route.
- `serviceCode` on `RatePayload` and `meta()` on `RateOption`.
- `lat`/`lng` on `Address` and `Location`, and `scheduledAt` on `ShipmentPayload`.

## [1.0.1] - 2026-06-19

### Added

- README with installation instructions, configuration reference, full API documentation, usage examples, testing guide, and custom driver guide.

## [1.0.0] - 2026-06-18

### Added

- `CourierDriver` contract defining the unified carrier interface (`createShipment`, `track`, `getRates`, `cancelShipment`, `getLabel`, `getAvailability`).
- Exception hierarchy: `CourierException`, `UnsupportedOperationException`.
- Shared DTOs: `Address`, `Location`, `Parcel`.
- Payload DTOs: `ShipmentPayload`, `RatePayload`, `AvailabilityPayload`.
- Result DTOs: `ShipmentResult`, `TrackingResult`, `TrackingEvent`, `RateCollection`, `RateOption`, `CancelResult`, `LabelResult`, `ServiceCollection`, `ServiceOption`.
- `CourierManager` with config-injecting driver resolution via `extend()`.
- `CourierServiceProvider` with auto-discovery and publishable config (`courier-config` tag).
- `Courier` facade.
- `CourierFake` testing helper with preset responses and assertion methods (`assertShipmentCreated`, `assertTracked`, `assertCancelled`, `assertRatesFetched`, `assertLabelFetched`, `assertNothingSent`).
- Support for PHP 8.1+ and Laravel 10, 11, 12, and 13.

### Fixed

- Removed hardcoded `sfexpress` fallback from `CourierManager::getDefaultDriver()`.
- Corrected nullable type declaration on `CourierFake::driver()`.
