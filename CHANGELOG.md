# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - <release date>

### Added

- `CourierHttpClient::asForm()` — sends the request body form-encoded instead of as JSON, for drivers whose API expects `application/x-www-form-urlencoded`.
- `CourierHttpClient::timeout(int|float $seconds)` — overrides the request timeout; Laravel's default of 30 seconds applies when unset.
- README now documents `CourierHttpClient` for driver authors (`forLog()`, the verb methods, `asForm()`, `timeout()`), including that `forLog()` mutates the instance rather than cloning it.
- `DeliveryMode` enum (`OnDemand`, `Scheduled`) and `CourierDriver::getDeliveryModes(): array`, letting calling code query whether a driver supports on-demand delivery, scheduled delivery, or both.
- Four optional capability interfaces for on-demand drivers: `LooksUpQuotations`, `ManagesAssignedDriver`, `TracksDriverLocation`, `SupportsOrderEditing`, with matching `QuotationResult` and `DriverLocationResult` DTOs.
- `ShipmentPayload::$meta` (`array`, defaults to `[]`) — an optional last constructor argument carrying driver-specific options that have no place in the normalized payload, e.g. `['isPODEnabled' => true]`. Existing positional callers are unaffected, and drivers that do not read it send an unchanged request body.
- `PodResult` DTO (`status`, `imageUrl: ?string`, `deliveredAt: ?Carbon`) for drivers that expose proof of delivery. `status` is a plain string rather than an enum so an undocumented carrier status never throws.
- `ProvidesWebhookResponse` contract — an optional interface letting a driver shape the webhook endpoint's response on both exits (`webhookAcceptedResponse()`, `webhookRejectedResponse()`). Previously `WebhookController` hardcoded an empty `200` and a bare `abort(401)`, so a carrier that parses the ack body could not be satisfied by any driver. J&T Express checks the body for `code == "1"` and was recording every successfully handled push as a failed one. Both methods return `Symfony\Component\HttpFoundation\Response` so `response()->json()` is accepted. Fully additive — a driver that does not implement it gets byte-identical responses to before.
- `courier.webhook.rate_limit` config (`COURIER_WEBHOOK_RATE_LIMIT`, default `60`), with an optional per-driver override at `courier.drivers.{driver}.webhook.rate_limit`. `null` at either level disables throttling; a value that is not a usable ceiling falls back to the default rather than disabling it or locking the endpoint out.

### Changed

- The webhook route's throttle is now keyed per driver instead of per IP alone. `courier/webhook/{driver}` takes the driver as a route *parameter*, so Laravel's default signature (`domain|ip`) meant the 60/min ceiling was shared across every courier driver at once — one carrier's burst cost every other carrier its allowance, and the overflow 429s counted as failed pushes. The default ceiling is unchanged at 60/min, so no existing deployment's limit moves on upgrade; drivers simply stop competing for it. Applications whose published `config/courier.php` predates the new `webhook` block fall back to the same 60 automatically.
- Five keys added to the default `courier.logging.redact` list: `appkey`, `appsecret`, `signature`, `digest`, `apiaccount`. Redaction matches exact key names, so `apikey` did not cover `appKey`/`appSecret` — these were being stored unredacted by any driver sending them.
- **Breaking:** `CourierDriver::getDeliveryModes()` is a new required method. Existing driver implementations (including `courier-lalamove`, `courier-jt-express`, `courier-sfexpress`) must implement it before upgrading to this version, despite the minor version number.

  This remains the *only* breaking change in 1.3.0. `ProvidesWebhookResponse` is opt-in via `instanceof` and adds no required method, and the throttle rework keeps the existing 60/min ceiling — a driver that changes nothing behaves exactly as it does today.

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
