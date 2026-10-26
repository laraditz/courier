# Handover: Driver Package Updates for On-Demand Capabilities (v1.3.0)

**Depends on:** `laraditz/courier` v1.3.0 (merged to `develop`, commit `c8f3c9d`) — see [spec](../specs/2026-08-27-on-demand-driver-capabilities-spec.md) and [plan](../plans/2026-08-27-on-demand-driver-capabilities-plan.md) for what changed in core.

This covers the follow-up work in the three sibling driver packages, out of scope for the core plan. Two kinds of change:

1. **Required for all three** — `CourierDriver::getDeliveryModes()` is now a required method. Every driver breaks until it implements it.
2. **Optional, Lalamove only** — adopt the four new capability interfaces (`LooksUpQuotations`, `ManagesAssignedDriver`, `TracksDriverLocation`, `SupportsOrderEditing`) in place of its current ad-hoc public methods, so calling code gets a typed, discoverable contract instead of reaching into `LalamoveDriver` directly.

No `composer.json` version bump is needed in any of the three packages to pick this up locally — all three already declare `"laraditz/courier": "@dev"` via a `path` repository pointing at `../courier`, so they resolve against whatever's checked out there. (If any of these packages is ever published/tagged separately, that's the moment to worry about a version constraint — not now.)

---

## Part 1: `getDeliveryModes()` — required in all three

### `courier-lalamove` — `src/LalamoveDriver.php`

Add the import and method. Lalamove is on-demand (auto-assigns a nearby driver, no scheduled next-day option):

```php
use Laraditz\Courier\Enums\DeliveryMode;
```

Insert after `getAvailability()` (currently the last `CourierDriver` method before the `HandlesWebhooks` methods):

```php
    public function getDeliveryModes(): array
    {
        return [DeliveryMode::OnDemand];
    }
```

### `courier-jt-express` — `src/JtExpressDriver.php`

Add the import and method. JT Express is scheduled/express delivery, not on-demand:

```php
use Laraditz\Courier\Enums\DeliveryMode;
```

Insert after `getAvailability()` (line ~141, currently the last `CourierDriver` method before `verifyWebhook()`):

```php
    public function getDeliveryModes(): array
    {
        return [DeliveryMode::Scheduled];
    }
```

### `courier-sfexpress` — `src/SfExpressDriver.php`

Add the import and method. Same reasoning — scheduled, not on-demand:

```php
use Laraditz\Courier\Enums\DeliveryMode;
```

Insert after `getAvailability()` (line ~120, currently the last `CourierDriver` method before `formatAddress()` — note `formatAddress()` is `private`, so the new method should go before it, keeping public contract methods grouped):

```php
    public function getDeliveryModes(): array
    {
        return [DeliveryMode::Scheduled];
    }
```

### No other implementers to fix

Checked: neither `courier-jt-express` nor `courier-sfexpress` has a `CourierFake`-style test double or an anonymous class implementing `CourierDriver` in its test suite — only the real driver class in each package implements the interface, so this is a one-method, one-file change per package. Run each package's test suite (`vendor/bin/phpunit` from that package's root) after adding the method — it should go from a fatal "must implement `getDeliveryModes()`" error straight to green, nothing else should change.

---

## Part 2: Adopt the capability interfaces — `courier-lalamove` only

Lalamove already has working code for all four capabilities as ad-hoc public methods on `LalamoveDriver`. The work here is wiring, not new logic — except where a raw-array return needs to become a typed DTO.

### `ManagesAssignedDriver` — trivial, no logic change

`LalamoveDriver::removeDriver(string $orderId, string $driverId): void` already matches the interface signature exactly. Just add the interface to the class declaration:

```php
class LalamoveDriver implements CourierDriver, HandlesWebhooks, ManagesAssignedDriver
```

(plus the `use Laraditz\Courier\Contracts\ManagesAssignedDriver;` import). Nothing else changes.

### `LooksUpQuotations` — needs a new mapper

Currently `getQuotation(string $quotationId): array` returns the raw Lalamove API response. The interface requires `QuotationResult`. Add a mapper following the existing `RateMapper`/`ShipmentMapper` pattern (`src/Mappers/QuotationMapper.php`):

```php
<?php

namespace Laraditz\Courier\Lalamove\Mappers;

use Carbon\Carbon;
use Laraditz\Courier\DTOs\Results\QuotationResult;

final class QuotationMapper
{
    public static function map(array $response): QuotationResult
    {
        $data      = $response['data'] ?? $response;
        $breakdown = $data['priceBreakdown'] ?? [];

        return new QuotationResult(
            quotationId: $data['quotationId'] ?? '',
            price:       (float) ($breakdown['total'] ?? 0),
            currency:    $breakdown['currency'] ?? '',
            expiresAt:   isset($data['expiresAt']) ? Carbon::parse($data['expiresAt']) : null,
            meta: [
                'stops' => $data['stops'] ?? [],
            ],
        );
    }
}
```

This mirrors `RateMapper::map()` exactly (same `priceBreakdown.total`/`currency` fields, same `quotationId`/`expiresAt` source), just returning `QuotationResult` instead of stuffing those two fields into `RateOption::meta()`. Then in `LalamoveDriver`:

```php
public function getQuotation(string $quotationId): QuotationResult
{
    return QuotationMapper::map($this->client->getQuotation($quotationId));
}
```

Add `implements LooksUpQuotations` to the class declaration and the corresponding import. **Note:** `createShipment()` internally calls `$this->client->getQuotation($quotationId)` too (for the `withQuotationId()` reuse path) and uses the raw array directly (`$quotationResponse['data']['stops']`) — that internal call is to `LalamoveClient::getQuotation()`, not the driver method being changed here, so it's unaffected. Don't confuse the two.

### `TracksDriverLocation` — needs a new mapper, **verify the response shape first**

Currently `getDriverLocation(string $orderId, string $driverId): array` returns the raw response from `GET /v3/orders/{orderId}/drivers/{driverId}`. No existing test or code in this repo exercises this method's actual response shape (it was added in commit `e13e52a` but never given a fixture-backed test) — **check Lalamove's actual API docs or a real sandbox response before writing the mapper**, don't guess field names from this handover alone. Once confirmed, the mapper follows the same shape as `QuotationMapper`:

```php
<?php

namespace Laraditz\Courier\Lalamove\Mappers;

use Carbon\Carbon;
use Laraditz\Courier\DTOs\Results\DriverLocationResult;

final class DriverLocationMapper
{
    public static function map(array $response): DriverLocationResult
    {
        $data = $response['data'] ?? $response;
        // Verify these field names against Lalamove's actual API response
        // before relying on this — placeholder based on the quotation stops'
        // coordinate shape (lat/lng as strings), not a confirmed contract.
        $location = $data['location'] ?? [];

        return new DriverLocationResult(
            driverId:  $data['driverId'] ?? '',
            lat:       (float) ($location['lat'] ?? 0),
            lng:       (float) ($location['lng'] ?? 0),
            updatedAt: isset($data['updatedAt']) ? Carbon::parse($data['updatedAt']) : null,
        );
    }
}
```

Then in `LalamoveDriver`:

```php
public function getDriverLocation(string $orderId, string $driverId): DriverLocationResult
{
    return DriverLocationMapper::map($this->client->getDriverLocation($orderId, $driverId));
}
```

Add `implements TracksDriverLocation` and the import. Write a fixture-backed test for this mapper once the real response shape is confirmed — this is the one piece of Part 2 that needs external verification before it can be trusted.

### `SupportsOrderEditing` — the one real signature change

This is the only capability where the interface's signature doesn't match what Lalamove already has. Currently:

```php
public function editOrder(string $orderId, array $stops): array   // $stops is Lalamove's raw stop shape, returns raw array
```

The interface requires:

```php
public function editOrder(string $orderId, array $stops): ShipmentResult   // $stops is Address[] (core's typed DTO)
```

Two changes needed:

1. **Build Lalamove's raw stop shape from `Address[]`.** `buildShipmentQuotationBody()` already does this conversion for `createShipment()`'s stops (`coordinates` + `address` string per stop) — extract that per-stop mapping into a small private helper and reuse it for both call sites, rather than duplicating the coordinate/address-string logic a third time. Something like:

```php
private function addressToStop(Address $address): array
{
    return [
        'coordinates' => ['lat' => (string) $address->lat, 'lng' => (string) $address->lng],
        'address'     => implode(', ', array_filter([$address->line1, $address->city])),
    ];
}
```

   (then both `buildShipmentQuotationBody()` and the new `editOrder()` map their stops through this helper instead of inlining the array shape).

2. **Reuse `ShipmentMapper::map()` for the return value** — `PATCH /v3/orders/{orderId}` returns the same order shape as `POST /v3/orders` (both are "the current state of this order"), so no new mapper is needed:

```php
public function editOrder(string $orderId, array $stops): ShipmentResult
{
    $rawStops = array_map($this->addressToStop(...), $stops);
    return ShipmentMapper::map($this->client->editOrder($orderId, $rawStops));
}
```

Add `implements SupportsOrderEditing` and the `use Laraditz\Courier\Contracts\SupportsOrderEditing;` / `use Laraditz\Courier\DTOs\Shared\Address;` imports (the latter may already be imported elsewhere in the file — check before adding a duplicate).

**Any existing caller of `editOrder()`** (application code outside this package, if any exists) that currently passes Lalamove's raw stop-array shape will break — it now needs to pass `Address[]` instead. This is a breaking change to `LalamoveDriver::editOrder()`'s public signature, separate from and in addition to the `getDeliveryModes()` breakage covered in Part 1. If this package is versioned independently, that's another reason for a major/notable bump, not just "pick up new core interfaces."

### Suggested order

1. `getDeliveryModes()` first (Part 1) — gets the package compiling again against core v1.3.0 with zero behavior change, safe to ship on its own.
2. `ManagesAssignedDriver` — free, zero-risk, just an interface label.
3. `LooksUpQuotations` — self-contained new mapper, low risk.
4. `SupportsOrderEditing` — the stop-mapping refactor, medium risk (touches `createShipment()`'s helper too), but no external API shape uncertainty.
5. `TracksDriverLocation` last — blocked on confirming the real response shape first.

### Not in scope

`addPriorityFee()` stays exactly as-is — a Lalamove-only public method, not part of any core contract (per the original spec's FR-10).
