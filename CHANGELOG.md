# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - <release date>

### Added

- `DeliveryMode` enum (`OnDemand`, `Scheduled`) and `CourierDriver::getDeliveryModes(): array`, letting calling code query whether a driver supports on-demand delivery, scheduled delivery, or both.
- Four optional capability interfaces for on-demand drivers: `LooksUpQuotations`, `ManagesAssignedDriver`, `TracksDriverLocation`, `SupportsOrderEditing`, with matching `QuotationResult` and `DriverLocationResult` DTOs.

### Changed

- **Breaking:** `CourierDriver::getDeliveryModes()` is a new required method. Existing driver implementations (including `courier-lalamove`, `courier-jt-express`, `courier-sfexpress`) must implement it before upgrading to this version, despite the minor version number.

## [1.2.0] - 2026-07-22

### Added

- API call logging: `CourierHttpClient` wraps outbound HTTP requests with an explicit `forLog()` context, records them via `ApiLogWriter`, and redacts sensitive keys (`courier.logging.redact`) before storing.
- `CourierApiLog` model with `forReference`, `forDriver`, `successful`, and `failed` query scopes.
- Webhook logging: `WebhookController` records every inbound webhook (rejected/processed/failed) via `WebhookLogWriter`.
- `CourierWebhookLog` model with `forReference`, `forDriver`, `processed`, `rejected`, and `failed` query scopes.
- `ExtractsWebhookReference` contract, letting a driver associate an incoming webhook with the caller's reference/waybill number.
- `courier.logging` config block (`enabled`, `retention_days`, `redact`) and publishable migrations (`courier-migrations` tag) for the `courier_api_logs` and `courier_webhook_logs` tables.
- `courier:prune-logs` command, deleting API and webhook log rows older than `courier.logging.retention_days` (no-op when `null`).

### Fixed

- Log write failures (API or webhook) are caught and reported to the default log channel instead of breaking the underlying courier call or webhook request.
- Webhook reference extraction failures are isolated so they never block logging or the webhook response.

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
