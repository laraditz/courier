# Spec: On-Demand Driver Capabilities & Delivery Mode

## Overview
Standardize the on-demand-courier methods that `courier-lalamove` currently exposes as ad-hoc public methods (`getQuotation()`, `removeDriver()`, `getDriverLocation()`, `editOrder()`) into typed, optional contracts in `laraditz/courier` core — so any on-demand driver can implement them and calling code gets IDE support and type safety instead of reaching into a driver-specific class. Alongside this, add a `DeliveryMode` enum and a new required `CourierDriver::getDeliveryModes(): array` method so calling code can ask "is this driver on-demand, scheduled, or both" without knowing which capability interfaces are involved.

This is core-package scope only. `courier-lalamove`, `courier-jt-express`, and `courier-sfexpress` each need matching updates (implement `getDeliveryModes()`, and — for Lalamove — adopt the four new capability interfaces), but that work is out of scope here; a handover document listing exactly what each sibling package needs will be produced once this spec is implemented.

## Codebase Context
- Stack: Laravel package, PHP ^8.1, PSR-4 `Laraditz\Courier\`, PHPUnit + Orchestra Testbench.
- Existing pattern this follows: `HandlesWebhooks` / `ExtractsWebhookReference` (`src/Contracts/`) are small, single-purpose interfaces that `WebhookController` checks via `instanceof` — a driver implements only what it supports. The four new interfaces follow the same shape.
- `CourierDriver` (`src/Contracts/CourierDriver.php`) is the mandatory contract every driver implements; it last changed in v1.1.0 (added `getShipment()` and the `reference` param on `cancelShipment()`/`getLabel()`).
- Existing DTOs this reuses: `Address` (`src/DTOs/Shared/Address.php`), `ShipmentResult` (`src/DTOs/Results/ShipmentResult.php`). New DTOs follow the `RateOption`-style pattern: typed fields for what's universal across drivers, a `meta()` array for driver-specific extras.
- Five implementers of `CourierDriver` exist inside this repo and must be updated alongside the contract: `src/Testing/CourierFake.php`, plus four separate anonymous classes in `tests/WebhookTest.php` (`registerWebhookDriver`, `registerExtractingWebhookDriver`, `registerThrowingExtractorWebhookDriver`, `registerFailingWebhookDriver`) — each independently needs `getDeliveryModes()` added or the file fails to compile. (The `createMock(CourierDriver::class)` elsewhere in that file is unaffected — PHPUnit auto-stubs new interface methods.)
- Source of the requirements below: `courier-lalamove`'s `LalamoveClient`/`LalamoveDriver` (methods `getQuotation()`, `removeDriver()`, `getDriverLocation()`, `editOrder()`, and the excluded `addPriorityFee()` — confirmed too Lalamove-specific to standardize).

## Functional Requirements

**Delivery mode**
- FR-01: `Laraditz\Courier\Enums\DeliveryMode` is a string-backed enum with cases `OnDemand = 'on_demand'` and `Scheduled = 'scheduled'`.
- FR-02: `CourierDriver::getDeliveryModes(): array` is added as a required method, returning a non-empty array of `DeliveryMode` cases. A driver supporting both models (e.g. a future hybrid courier) returns both cases in the array; there is no combined "both" enum case.
- FR-03: `CourierFake::getDeliveryModes()` and the inline test driver in `tests/WebhookTest.php` are updated to satisfy the new interface (return `[DeliveryMode::Scheduled]` unless a test specifically needs otherwise).

**New optional capability interfaces** (`src/Contracts/`, checked via `instanceof` by calling code — no core code path currently needs to check these itself)
- FR-04: `LooksUpQuotations::getQuotation(string $quotationId): QuotationResult` — fetch a previously-created quotation's details.
- FR-05: `ManagesAssignedDriver::removeDriver(string $orderId, string $driverId): void` — remove/release the driver currently assigned to an order (there is no "assign" call to standardize — assignment is automatic on the carrier side; removal is what triggers reassignment).
- FR-06: `TracksDriverLocation::getDriverLocation(string $orderId, string $driverId): DriverLocationResult` — fetch the assigned driver's current location.
- FR-07: `SupportsOrderEditing::editOrder(string $orderId, array $stops): ShipmentResult` — replace an in-progress order's stops. `$stops` is an `Address[]`; the return type reuses `ShipmentResult` since an edited order has the same shape as a newly created one.

**New DTOs** (`src/DTOs/Results/`)
- FR-08: `QuotationResult` is a readonly class with `string $quotationId`, `float $price`, `string $currency`, `?Carbon $expiresAt`, and a private `array $meta = []` with a `meta(): array` accessor (same accessor pattern as `RateOption`/`ShipmentResult`) — driver-specific details (e.g. per-stop `stopId`s) go in `meta`, not as typed fields.
- FR-09: `DriverLocationResult` is a readonly class with `string $driverId`, `float $lat`, `float $lng`, `?Carbon $updatedAt`, and a private `array $meta = []` with a `meta()` accessor — same pattern as every other Result DTO in the codebase (`ShipmentResult`, `RateOption`, `CancelResult`, `LabelResult`, `TrackingResult`, `QuotationResult`), for consistency rather than as an intentional exception.

**Explicitly out of scope**
- FR-10: `addPriorityFee()` is not standardized — it stays a Lalamove-only public method on `LalamoveDriver`, not part of any core contract.
- FR-11: No changes to `WebhookController`, `CourierManager`, or any other core code path that consumes `CourierDriver` — the four new interfaces are opt-in capabilities with no current core-side caller, exactly like `HandlesWebhooks` before `WebhookController` was written to check it. (Future core features may check `instanceof` against these; none do yet.)
- FR-12: No changes to `courier-lalamove`, `courier-jt-express`, or `courier-sfexpress` in this spec's implementation — tracked separately via the handover document (see Overview).

## Non-Functional Requirements
- **Compatibility:** This is a breaking change for any existing `CourierDriver` implementation (new required method, no default in a PHP interface). Per the user's explicit decision, it ships as **v1.3.0**, not v2.0.0, despite being breaking under strict SemVer — `CHANGELOG.md` must call this out explicitly so consumers pinned to `^1.2` are warned before upgrading.
- **Consistency:** Interface and DTO naming/shape must match the existing `HandlesWebhooks`/`ExtractsWebhookReference` and `RateOption`/`ShipmentResult` patterns exactly — no new architectural style introduced.
- **No new dependencies.**

## CHANGELOG Entry
Add to `CHANGELOG.md`, above `[1.2.0]`:
```
## [1.3.0] - <release date>

### Added

- `DeliveryMode` enum (`OnDemand`, `Scheduled`) and `CourierDriver::getDeliveryModes(): array`, letting calling code query whether a driver supports on-demand delivery, scheduled delivery, or both.
- Four optional capability interfaces for on-demand drivers: `LooksUpQuotations`, `ManagesAssignedDriver`, `TracksDriverLocation`, `SupportsOrderEditing`, with matching `QuotationResult` and `DriverLocationResult` DTOs.

### Changed

- **Breaking:** `CourierDriver::getDeliveryModes()` is a new required method. Existing driver implementations (including `courier-lalamove`, `courier-jt-express`, `courier-sfexpress`) must implement it before upgrading to this version, despite the minor version number.
```

## Testing
- `CourierFake` and the `tests/WebhookTest.php` inline driver must be updated or all existing tests using them fail to compile (PHP fatal error: class does not implement interface). This is not optional — it's required just to keep the existing test suite runnable.
- New unit tests for `QuotationResult`/`DriverLocationResult` construction and `meta()` accessors, matching the existing DTO test style (see `tests/` for `RateOption`/`ShipmentResult` equivalents if present, otherwise simple constructor/property assertions).
- New unit test asserting `DeliveryMode` has exactly the two documented cases (guards against silent case additions bypassing this spec's design decision).
- No integration test is needed for the four new interfaces themselves (they have no implementer in this repo, and no core code path calls them) — coverage lives in whichever driver package implements them.
