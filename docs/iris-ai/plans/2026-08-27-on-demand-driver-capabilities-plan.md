# On-Demand Driver Capabilities & Delivery Mode Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `DeliveryMode` enum + required `CourierDriver::getDeliveryModes()` method, plus four optional capability interfaces (`LooksUpQuotations`, `ManagesAssignedDriver`, `TracksDriverLocation`, `SupportsOrderEditing`) and their two supporting DTOs (`QuotationResult`, `DriverLocationResult`) to `laraditz/courier` core, per `docs/iris-ai/specs/2026-08-27-on-demand-driver-capabilities-spec.md`.

**Architecture:** Follows the existing `HandlesWebhooks`/`ExtractsWebhookReference` pattern exactly — small, single-purpose interfaces in `src/Contracts/`, checked via `instanceof` by calling code, with no core code path calling them yet. New DTOs follow the existing `RateOption`/`ShipmentResult` shape (typed public properties + private `meta` array + `meta()` accessor). The one required-contract change (`getDeliveryModes()`) ripples into every `CourierDriver` implementer in this repo — `CourierFake` and four anonymous test-driver classes in `tests/WebhookTest.php` — which must be fixed in the same task that adds the method, or the test suite fails to compile.

**Tech Stack:** PHP 8.1+, PHPUnit 11 (`vendor/bin/phpunit`), Orchestra Testbench. No new dependencies.

---

## File Structure

**New files:**
- `src/Enums/DeliveryMode.php` — string-backed enum, cases `OnDemand`, `Scheduled`
- `src/DTOs/Results/QuotationResult.php` — quotation lookup result DTO
- `src/DTOs/Results/DriverLocationResult.php` — driver location result DTO
- `src/Contracts/LooksUpQuotations.php` — optional capability interface
- `src/Contracts/ManagesAssignedDriver.php` — optional capability interface
- `src/Contracts/TracksDriverLocation.php` — optional capability interface
- `src/Contracts/SupportsOrderEditing.php` — optional capability interface
- `tests/Enums/DeliveryModeTest.php` — new `tests/Enums/` directory, mirrors existing `tests/DTOs/` grouping convention

**Modified files:**
- `src/Contracts/CourierDriver.php` — add `getDeliveryModes(): array` to the interface
- `src/Testing/CourierFake.php` — implement `getDeliveryModes()`
- `tests/WebhookTest.php` — implement `getDeliveryModes()` on all four anonymous `CourierDriver` classes (`registerWebhookDriver`, `registerExtractingWebhookDriver`, `registerThrowingExtractorWebhookDriver`, `registerFailingWebhookDriver`)
- `tests/DTOs/ResultTest.php` — add test methods for `QuotationResult`/`DriverLocationResult` (this file already groups all Result DTO tests together — no new test file, matching its existing convention)
- `CHANGELOG.md` — add `[1.3.0]` entry above `[1.2.0]`

No files are deleted. No changes to `WebhookController`, `CourierManager`, or any other core caller (per spec FR-11 — these interfaces have no current core-side consumer).

---

## Task 1: `DeliveryMode` enum

**Files:**
- Create: `src/Enums/DeliveryMode.php`
- Test: `tests/Enums/DeliveryModeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Laraditz\Courier\Tests\Enums;

use Laraditz\Courier\Enums\DeliveryMode;
use Laraditz\Courier\Tests\TestCase;

class DeliveryModeTest extends TestCase
{
    public function test_has_exactly_two_cases(): void
    {
        $this->assertCount(2, DeliveryMode::cases());
    }

    public function test_case_values(): void
    {
        $this->assertSame('on_demand', DeliveryMode::OnDemand->value);
        $this->assertSame('scheduled', DeliveryMode::Scheduled->value);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Enums/DeliveryModeTest.php`
Expected: FAIL — `Class "Laraditz\Courier\Enums\DeliveryMode" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Laraditz\Courier\Enums;

enum DeliveryMode: string
{
    case OnDemand = 'on_demand';
    case Scheduled = 'scheduled';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Enums/DeliveryModeTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Enums/DeliveryMode.php tests/Enums/DeliveryModeTest.php
git commit -m "feat: add DeliveryMode enum"
```

---

## Task 2: `CourierDriver::getDeliveryModes()` + fix every implementer

This is the breaking-change task. Adding the method to the interface breaks compilation of every existing implementer until each is fixed — that's expected and verified below, not a mistake to work around.

**Files:**
- Modify: `src/Contracts/CourierDriver.php`
- Modify: `src/Testing/CourierFake.php`
- Modify: `tests/WebhookTest.php`

- [ ] **Step 1: Add the method to the contract**

In `src/Contracts/CourierDriver.php`, add the import and method:

```php
use Laraditz\Courier\Enums\DeliveryMode;
```

Add as the last method in the interface:

```php
    /**
     * @return DeliveryMode[]
     */
    public function getDeliveryModes(): array;
```

- [ ] **Step 2: Run the full suite to confirm the expected breakage**

Run: `vendor/bin/phpunit`
Expected: FAIL — fatal errors on `CourierFake` and the four anonymous classes in `tests/WebhookTest.php`, each: `Class ... contains 1 abstract method and must ... implement the remaining methods (Laraditz\Courier\Contracts\CourierDriver::getDeliveryModes)`. This confirms every implementer that needs fixing is accounted for (5 total: `CourierFake` + 4 anonymous classes).

- [ ] **Step 3: Fix `CourierFake`**

In `src/Testing/CourierFake.php`, add the import:

```php
use Laraditz\Courier\Enums\DeliveryMode;
```

Add the method (placed after `getAvailability()`, before the `assert*` helper methods):

```php
    public function getDeliveryModes(): array
    {
        return [DeliveryMode::Scheduled];
    }
```

- [ ] **Step 4: Fix all four anonymous classes in `tests/WebhookTest.php`**

Add the import at the top of the file:

```php
use Laraditz\Courier\Enums\DeliveryMode;
```

In each of `registerWebhookDriver()`, `registerExtractingWebhookDriver()`, `registerThrowingExtractorWebhookDriver()`, `registerFailingWebhookDriver()`, add this line to the anonymous class body (immediately after the existing `getAvailability()` method):

```php
            public function getDeliveryModes(): array { return [DeliveryMode::Scheduled]; }
```

- [ ] **Step 5: Run the full suite to verify everything passes**

Run: `vendor/bin/phpunit`
Expected: PASS — full suite green, same test count as before this task (no tests removed or skipped)

- [ ] **Step 6: Commit**

```bash
git add src/Contracts/CourierDriver.php src/Testing/CourierFake.php tests/WebhookTest.php
git commit -m "feat: add required getDeliveryModes() to CourierDriver contract"
```

---

## Task 3: `QuotationResult` DTO

**Files:**
- Create: `src/DTOs/Results/QuotationResult.php`
- Modify: `tests/DTOs/ResultTest.php`

- [ ] **Step 1: Write the failing tests**

In `tests/DTOs/ResultTest.php`, add the import:

```php
use Laraditz\Courier\DTOs\Results\QuotationResult;
```

Add these two test methods (anywhere among the other `test_*` methods):

```php
    public function test_quotation_result(): void
    {
        $result = new QuotationResult(
            quotationId: 'QUO-001',
            price: 12.50,
            currency: 'MYR',
            expiresAt: Carbon::parse('2026-06-20T10:05:00Z'),
            meta: ['stops' => [['stopId' => 's1'], ['stopId' => 's2']]],
        );

        $this->assertSame('QUO-001', $result->quotationId);
        $this->assertSame(12.50, $result->price);
        $this->assertSame('MYR', $result->currency);
        $this->assertInstanceOf(Carbon::class, $result->expiresAt);
        $this->assertSame(['stops' => [['stopId' => 's1'], ['stopId' => 's2']]], $result->meta());
    }

    public function test_quotation_result_expires_at_nullable_and_meta_defaults_empty(): void
    {
        $result = new QuotationResult('QUO-002', 5.00, 'MYR', null);

        $this->assertNull($result->expiresAt);
        $this->assertSame([], $result->meta());
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/DTOs/ResultTest.php`
Expected: FAIL — `Class "Laraditz\Courier\DTOs\Results\QuotationResult" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class QuotationResult
{
    public function __construct(
        public string $quotationId,
        public float $price,
        public string $currency,
        public ?Carbon $expiresAt,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/DTOs/ResultTest.php`
Expected: PASS (all tests in the file, including the two new ones)

- [ ] **Step 5: Commit**

```bash
git add src/DTOs/Results/QuotationResult.php tests/DTOs/ResultTest.php
git commit -m "feat: add QuotationResult DTO"
```

---

## Task 4: `DriverLocationResult` DTO

**Files:**
- Create: `src/DTOs/Results/DriverLocationResult.php`
- Modify: `tests/DTOs/ResultTest.php`

- [ ] **Step 1: Write the failing tests**

In `tests/DTOs/ResultTest.php`, add the import:

```php
use Laraditz\Courier\DTOs\Results\DriverLocationResult;
```

Add these two test methods:

```php
    public function test_driver_location_result(): void
    {
        $result = new DriverLocationResult(
            driverId: 'DRV-001',
            lat: 3.1390,
            lng: 101.6869,
            updatedAt: Carbon::parse('2026-06-20T10:05:00Z'),
            meta: ['heading' => 90],
        );

        $this->assertSame('DRV-001', $result->driverId);
        $this->assertSame(3.1390, $result->lat);
        $this->assertSame(101.6869, $result->lng);
        $this->assertInstanceOf(Carbon::class, $result->updatedAt);
        $this->assertSame(['heading' => 90], $result->meta());
    }

    public function test_driver_location_result_updated_at_nullable_and_meta_defaults_empty(): void
    {
        $result = new DriverLocationResult('DRV-002', 3.0, 101.0, null);

        $this->assertNull($result->updatedAt);
        $this->assertSame([], $result->meta());
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/DTOs/ResultTest.php`
Expected: FAIL — `Class "Laraditz\Courier\DTOs\Results\DriverLocationResult" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Laraditz\Courier\DTOs\Results;

use Carbon\Carbon;

readonly class DriverLocationResult
{
    public function __construct(
        public string $driverId,
        public float $lat,
        public float $lng,
        public ?Carbon $updatedAt,
        private array $meta = [],
    ) {}

    public function meta(): array
    {
        return $this->meta;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/DTOs/ResultTest.php`
Expected: PASS (all tests in the file)

- [ ] **Step 5: Commit**

```bash
git add src/DTOs/Results/DriverLocationResult.php tests/DTOs/ResultTest.php
git commit -m "feat: add DriverLocationResult DTO"
```

---

## Task 5: Four capability interfaces

Per spec FR-11, these have no current core-side caller and no implementer in this repo, so there is no unit test to write — verification is that the full suite still passes (autoloading/syntax is valid) after adding them. This matches how `HandlesWebhooks` was introduced originally.

**Files:**
- Create: `src/Contracts/LooksUpQuotations.php`
- Create: `src/Contracts/ManagesAssignedDriver.php`
- Create: `src/Contracts/TracksDriverLocation.php`
- Create: `src/Contracts/SupportsOrderEditing.php`

- [ ] **Step 1: Create `LooksUpQuotations`**

```php
<?php

namespace Laraditz\Courier\Contracts;

use Laraditz\Courier\DTOs\Results\QuotationResult;

interface LooksUpQuotations
{
    public function getQuotation(string $quotationId): QuotationResult;
}
```

- [ ] **Step 2: Create `ManagesAssignedDriver`**

```php
<?php

namespace Laraditz\Courier\Contracts;

interface ManagesAssignedDriver
{
    public function removeDriver(string $orderId, string $driverId): void;
}
```

- [ ] **Step 3: Create `TracksDriverLocation`**

```php
<?php

namespace Laraditz\Courier\Contracts;

use Laraditz\Courier\DTOs\Results\DriverLocationResult;

interface TracksDriverLocation
{
    public function getDriverLocation(string $orderId, string $driverId): DriverLocationResult;
}
```

- [ ] **Step 4: Create `SupportsOrderEditing`**

```php
<?php

namespace Laraditz\Courier\Contracts;

use Laraditz\Courier\DTOs\Results\ShipmentResult;
use Laraditz\Courier\DTOs\Shared\Address;

interface SupportsOrderEditing
{
    /**
     * @param Address[] $stops
     */
    public function editOrder(string $orderId, array $stops): ShipmentResult;
}
```

- [ ] **Step 5: Run the full suite to confirm nothing broke**

Run: `vendor/bin/phpunit`
Expected: PASS — same green suite as after Task 4, plus these four files now autoload cleanly

- [ ] **Step 6: Commit**

```bash
git add src/Contracts/LooksUpQuotations.php src/Contracts/ManagesAssignedDriver.php src/Contracts/TracksDriverLocation.php src/Contracts/SupportsOrderEditing.php
git commit -m "feat: add on-demand capability interfaces"
```

---

## Task 6: CHANGELOG entry

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Add the `[1.3.0]` entry above `[1.2.0]`**

Insert directly above the existing `## [1.2.0] - 2026-07-22` line:

```markdown
## [1.3.0] - <release date>

### Added

- `DeliveryMode` enum (`OnDemand`, `Scheduled`) and `CourierDriver::getDeliveryModes(): array`, letting calling code query whether a driver supports on-demand delivery, scheduled delivery, or both.
- Four optional capability interfaces for on-demand drivers: `LooksUpQuotations`, `ManagesAssignedDriver`, `TracksDriverLocation`, `SupportsOrderEditing`, with matching `QuotationResult` and `DriverLocationResult` DTOs.

### Changed

- **Breaking:** `CourierDriver::getDeliveryModes()` is a new required method. Existing driver implementations (including `courier-lalamove`, `courier-jt-express`, `courier-sfexpress`) must implement it before upgrading to this version, despite the minor version number.
```

Replace `<release date>` with the actual release date when this is tagged.

- [ ] **Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: add CHANGELOG entry for v1.3.0"
```

---

## After This Plan

Out of scope here (per spec): updating `courier-lalamove`, `courier-jt-express`, `courier-sfexpress` to implement `getDeliveryModes()` (all three) and, for Lalamove, adopt the four new capability interfaces in place of its current ad-hoc public methods. A handover document for that follow-up work will be written separately once this plan is executed.
