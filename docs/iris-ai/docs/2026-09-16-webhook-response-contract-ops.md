# Ops Log: Webhook Response Contract & Per-Driver Throttle

**Plan:** [2026-09-16-webhook-response-contract-plan.md](../plans/webhook-response-contract/2026-09-16-webhook-response-contract-plan.md)
**Branch:** feature/webhook-response-contract
**Mode:** TDD
**Baseline before any change:** 111 tests, 264 assertions, green

---

## Task 1.1 — Failing test for the accepted response

### Test Written
`tests/WebhookResponseContractTest.php` — `test_accepted_response_from_contract_driver_is_returned_verbatim`. A driver implementing `ProvidesWebhookResponse` returns J&T's mandated ack shape (`code`, `msg`, `data`, `requestId`) from `webhookAcceptedResponse()`; the test asserts status 200 and an exact JSON match.

### Test Result
Red, as designed:

```
Error: Class "Laraditz\Courier\Tests\Fixtures\ConfigurableWebhookDriver" not found
Tests: 1, Assertions: 0, Errors: 1
```

### Code Review
- vs spec: pass — covers FR-03 (returned verbatim) and FR-09 (a `JsonResponse` survives the contract's return type)
- vs plan: pass — new test file, fails on the missing contract and fixture
- Quality: pass
- Issues: none

### Status
Done. Suite intentionally red until Task 1.3 — the plan declares 1.1→1.3 a strict red-green pair.

---

## Task 1.2 — Create the contract and the test fixture

### Implementation
- `src/Contracts/ProvidesWebhookResponse.php` — two methods, both returning `Symfony\Component\HttpFoundation\Response`. Docblock records *why* the Symfony base type is used rather than `Illuminate\Http\Response`.
- `tests/Fixtures/ConfigurableWebhookDriver.php` — one closure-configured driver implementing `CourierDriver + HandlesWebhooks + ProvidesWebhookResponse`, with a public `$calls` array recording which contract method ran. Writes the eight `CourierDriver` stubs once for all of Group 1.

### Test Result
Still red, and now for the right reason — the class-not-found error is gone, status 200 asserts clean, and the failure is the empty body:

```
Invalid JSON was returned from the route.
Tests: 1, Assertions: 2, Failures: 1
```

### Code Review
- vs spec: pass — FR-01 satisfied exactly; signature matches the spec's API Contracts block verbatim
- vs plan: pass — contract and fixture together, controller deliberately untouched
- Quality: pass — fixture is the DRY resolution from the plan's self-review; existing tests untouched, so NFR-04 holds
- Issues: none

### Status
Done. Red until Task 1.3.

---

## Task 1.3 — Wire the accepted branch

### Implementation
`WebhookController`: swapped the `Illuminate\Http\Response` import for `Symfony\Component\HttpFoundation\Response` (FR-02 — widens `handle()`'s return type by import, no signature edit needed), and replaced the hardcoded success exit with the contract-aware ternary (FR-03).

```php
return $instance instanceof ProvidesWebhookResponse
    ? $instance->webhookAcceptedResponse($request)
    : response()->noContent(200);
```

### Test Result
Green, full suite:

```
Tests: 112, Assertions: 266, PHPUnit Deprecations: 1
```

112 = the 111 baseline tests, all passing unmodified, plus the new contract test. NFR-04 holds.

### Code Review
- vs spec: pass — FR-02 and FR-03 both satisfied; the `instanceof` check mirrors `ExtractsWebhookReference` at line 36 as the spec's Codebase Context requires
- vs plan: pass
- Quality: pass — widening the return type via the import keeps the diff to two lines and needs no change at the `abort()` call sites, which throw rather than return
- Issues: none

### Status
Done.

---

## Task 1.4 — Failing test for the rejected response

### Test Written
`test_rejected_response_from_contract_driver_is_returned_and_still_logged` — a contract driver that fails verification returns J&T's documented `145003030` error body. The test asserts both halves of FR-05/FR-07: the driver's response goes out, **and** the `rejected` row is still written.

### Test Result
Red, for the right reason — `abort(401)` is emitting Laravel's own error body:

```
-    "code": "145003030",
-    "msg": "headers signature verification failed"
+    "message": ""
Tests: 1, Assertions: 2, Failures: 1
```

The 401 status passes only incidentally; `abort(401)` produces the same status with the wrong body. That is precisely C-2: carrier support cannot diagnose a rejection from this.

### Code Review
- vs spec: pass — asserts FR-05 and FR-07 together, so a wiring that returns the response *before* logging cannot pass
- vs plan: pass
- Quality: pass
- Issues: none

### Status
Done. Red until Task 1.5.

---

## Task 1.5 — Wire the rejection branch

### Implementation
`WebhookController`, inside the failed-verification block — placed **after** the existing `$this->logWriter->record([...])` call, before `abort(401)`:

```php
if ($instance instanceof ProvidesWebhookResponse) {
    return $instance->webhookRejectedResponse($request);
}

abort(401);
```

The ordering is the FR-07 constraint made structural: the early return sits below the log write, so a rejected push cannot escape unlogged.

### Test Result
Green, full suite:

```
Tests: 113, Assertions: 272, PHPUnit Deprecations: 1
```

### Code Review
- vs spec: pass — FR-05 and FR-07. `abort(401)` still stands as the fallback, so FR-06 is untouched
- vs plan: pass
- Quality: pass — guard-clause form rather than a ternary here, because `abort()` throws and does not return a value to pair with
- Issues: none

### Status
Done.

---

## Task 1.6 — Regression: non-implementing drivers unchanged

### Test Written
`test_driver_without_contract_keeps_default_responses` — asserts a driver with no contract still gets a 200 with a genuinely empty body (FR-04) and still gets 401 on failed verification (FR-06). This is the NFR-01 guard: it is the test that fails if `sfexpress` or `lalamove` behaviour ever drifts.

### Implementation (refactor)
Task 1.6 needs a driver that does *not* implement the contract, which `ConfigurableWebhookDriver` cannot be — an interface cannot be un-implemented. Rather than a second copy of the eight `CourierDriver` stubs, the fixture was split:

- `tests/Fixtures/PlainWebhookDriver.php` — `CourierDriver + HandlesWebhooks`, holds the stubs
- `tests/Fixtures/ConfigurableWebhookDriver.php` — now `extends PlainWebhookDriver implements ProvidesWebhookResponse`

Both shapes, one set of stubs. Named arguments at call sites are unaffected.

### Test Result
Green, full suite:

```
Tests: 114, Assertions: 275, PHPUnit Deprecations: 1
```

Passed on first run, as a regression guard should — it asserts what must *not* change, so there is no red phase to observe.

### Code Review
- vs spec: pass — FR-04 and FR-06, and it is the executable form of NFR-01
- vs plan: pass
- Quality: pass — the fixture split keeps stub duplication at zero across three driver shapes
- Issues: none

### Status
Done.

---

## Tasks 1.7 – 1.9 — Path isolation, throw-path bypass, no-swallow

### Tests Written
- `test_contract_methods_are_called_only_on_their_own_path` (FR-08) — asserts `$driver->calls` is exactly `['accepted']` on the accepting driver and exactly `['rejected']` on the rejecting one. Uses the `$calls` recorder added to the fixture in 1.2.
- `test_handle_webhook_exception_bypasses_contract` (FR-10) — a contract driver whose `handleWebhook()` throws: exception propagates, row logged `failed` with `error_message`, and `$driver->calls` is `[]`. The contract must not touch the throw path at all.
- `test_contract_response_exception_is_not_swallowed` (FR-11) — an exception thrown *inside* `webhookAcceptedResponse()` propagates rather than falling back to `noContent(200)`.

FR-11's test is a guard against a future "helpful" try/catch. There is no catch in the controller today, so it passes trivially — its value is that it fails the day someone adds one, which would silently reintroduce the empty-body bug C-1 exists to fix.

### Test Result
Green, full suite:

```
Tests: 117, Assertions: 285, PHPUnit Deprecations: 1
```

### Code Review
- vs spec: pass — FR-08, FR-10, FR-11
- vs plan: pass
- Quality: pass
- Issues: none

### Deviation
All three were written in a single edit and land as **one commit**, not three. They are pure test additions to one file with no implementation between them, so the batching hides nothing — but it departs from the one-commit-per-task rule and is recorded here rather than left implicit.

### Status
Done (1.7, 1.8, 1.9).

---

## Group 1 Deviation Log

- **Test fixture shipped as two classes, not one** — `PlainWebhookDriver` (base, holds the `CourierDriver` stubs) and `ConfigurableWebhookDriver extends PlainWebhookDriver implements ProvidesWebhookResponse`. Task 1.6 requires a driver that does *not* implement the contract, which a single fixture cannot provide — an interface cannot be un-implemented. Plan task 1.2's description updated to match.
- **Tasks 1.7–1.9 landed as one commit** rather than three. Pure test additions to a single file with no implementation between them. Process deviation only; no spec or plan content changed.

No changes to the spec — every FR was implemented as written.

---

## Task 2.1 — Failing test for the default rate

### Test Written
`tests/WebhookRateLimitTest.php` — `test_defaults_to_sixty_per_minute_when_unconfigured`. Two helpers carry the group:

- `requestFor($driver, $ip)` builds a `Request` with a bound `courier/webhook/{driver}` route, so `$request->route('driver')` resolves without an HTTP round trip.
- `forgetWebhookConfig()` **removes** the `courier.webhook` key rather than setting it null — null means "disabled", so setting null would test the wrong branch. This is also the FR-20 stale-config shape, reused in 2.11.

### Test Result
Red: `Class "Laraditz\Courier\Support\WebhookRateLimit" not found`.

### Status
Done.

---

## Task 2.2 — Create `WebhookRateLimit`

### Implementation
`src/Support/WebhookRateLimit.php` — stateless statics, mirroring `Support/Redactor.php`. `DEFAULT_PER_MINUTE = 60`, matching the ceiling the route carried before, so upgrading changes no deployment's limit. `for()` resolves the rate, returns `Limit::none()` on null, otherwise `Limit::perMinute($rate)->by($driver.'|'.$request->ip())`.

`normalize()` checks null **first**, before `is_numeric()`. The docblock records why: null is non-numeric, so validating numerically first would capture a deliberately-disabled driver and cap it at 60. That is the FR-16/FR-18 contradiction the spec review caught, and this ordering is the fix.

### Test Result
Green, full suite:

```
Tests: 118, Assertions: 288, PHPUnit Deprecations: 1
```

### Code Review
- vs spec: pass — FR-15, FR-16, FR-17, FR-18. Key shape matches FR-14
- vs plan: pass
- Quality: pass — `config()->has()` used deliberately over `config(...) !== null`, since only the former distinguishes an explicit null from an absent key
- Issues: none

### Status
Done.

---

## Tasks 2.3 – 2.4 — Key shape, precedence, null and malformed values

### Tests Written
- `test_key_is_driver_and_ip` / `test_drivers_resolve_to_different_keys` (FR-14)
- `test_global_rate_is_used_when_no_per_driver_rate_is_set` (FR-15 step 2)
- `test_per_driver_rate_overrides_global` (FR-15 step 1) — also asserts the override does **not** leak to another driver
- `test_null_global_rate_disables_the_limiter` / `test_null_per_driver_rate_disables_only_that_driver` (FR-16)
- `test_malformed_rate_falls_back_to_default` (FR-18), data-provided over `0`, `-5`, `'abc'`, `''`

### Test Result
Green, full suite:

```
Tests: 128, Assertions: 304, PHPUnit Deprecations: 1
```

### Verification of the FR-16 guard
The null tests only earn their place if they fail when the ordering is wrong, so the ordering was inverted in `normalize()` and the suite re-run:

```
1) test_null_global_rate_disables_the_limiter
Failed asserting that an instance of Limit is an instance of Unlimited.
2) test_null_per_driver_rate_disables_only_that_driver
Failed asserting that an instance of Limit is an instance of Unlimited.
Tests: 11, Assertions: 18, Failures: 2
```

Exactly two failures, both the intended ones, nothing else disturbed. Implementation restored and re-run green. The contradiction the spec review caught is now held shut by a test that provably detects it.

### Code Review
- vs spec: pass — FR-14, FR-15, FR-16, FR-18 all covered
- vs plan: pass
- Quality: pass — data provider keeps the four malformed cases from becoming four near-identical tests
- Issues: none

### Status
Done (2.3, 2.4). Landed as one commit — both are test-only additions to the same file.

---

## Task 2.5 — Config block

### Implementation
`config/courier.php` gains a `webhook` block above `logging`, with an inline comment stating the unit (per driver per IP), the null-disables rule, and where the per-driver override lives.

```php
'webhook' => [
    'rate_limit' => env('COURIER_WEBHOOK_RATE_LIMIT', 60),
],
```

### Test Result
`test_package_config_ships_a_default_rate_limit` green. Full suite green.

### Status
Done.

---

## Task 2.6 — Register the named limiter

### Implementation
`CourierServiceProvider::boot()`, above `loadRoutesFrom()`:

```php
RateLimiter::for('courier-webhook', fn (Request $request) => WebhookRateLimit::for($request));
```

The comment records why the ordering matters — an unregistered named limiter is not a silent no-op, it throws `MissingRateLimiterException`.

### Test Result
`test_courier_webhook_limiter_is_registered` green — resolves the limiter and asserts it produces the `jtexpress|198.51.100.7` key, so registration and delegation are both covered.

```
Tests: 130, Assertions: 307, PHPUnit Deprecations: 1
```

### Code Review
- vs spec: pass — FR-19 and FR-12
- vs plan: pass — 2.6 landed before 2.7, per the Sequencing Note
- Quality: pass — provider stays thin; all resolution logic remains in the testable static
- Issues: none

### Status
Done.

---

## Task 2.7 — Swap the route middleware

### Implementation
`routes/webhook.php` — `throttle:60,1` becomes `throttle:courier-webhook`. Route name and URI untouched.

### Test Result
Full suite green at 130, unchanged count. Every existing webhook test now runs through the named limiter and none needed editing.

### Status
Done.

---

## Task 2.8 — Verify test cache isolation

### Finding
Testbench's default cache store is `array` (`vendor/orchestra/testbench-core/laravel/config/cache.php:18`) — in-memory, and a fresh app is built per test. That predicts no leakage, but the plan flagged this precisely because predicting is not proving.

### Test Written
A deliberate pair in `tests/WebhookThrottleTest.php` — `..._first` and `..._second`, each setting a ceiling of **1** and firing one request at the same driver. If counters leaked, the second would 429.

### Test Result
Both green. No per-test reset needed. Everything in 2.9–2.11 rests on this.

### Status
Done — no reset code required.

---

## Tasks 2.9 – 2.11 — Throttle behaviour end to end

### Tests Written
- `test_requests_past_the_ceiling_are_throttled` — ceiling of 2, third request 429 (NFR-05: rate lowered rather than firing 61 requests)
- `test_drivers_do_not_share_a_throttle_bucket` (FR-14) — carrier A exhausted at a ceiling of 1, carrier B on the **same IP** still 200. This is C-3 itself: before the change the ceiling was keyed on IP alone and shared across every driver
- `test_per_driver_override_grants_headroom_without_affecting_others` — J&T at 3, everyone else at 1, both enforced independently. The governing constraint, executable
- `test_missing_webhook_config_block_falls_back_to_default` (FR-20) — asserts `config()->has('courier.webhook.rate_limit')` is genuinely false, then that the endpoint still serves: no `MissingRateLimiterException`, no unthrottled endpoint

### Test Result
Green, full suite:

```
Tests: 136, Assertions: 323, PHPUnit Deprecations: 1
```

### Code Review
- vs spec: pass — FR-13, FR-14, FR-20, NFR-05
- vs plan: pass
- Quality: pass
- Issues: none

### Status
Done (2.9, 2.10, 2.11).

---

## Group 2 Deviation Log

No structural deviations — docs unchanged. Every FR was implemented as specified.

One plan detail was enriched rather than changed: task 2.8's outcome ("testbench uses the `array` store with a fresh app per test — no reset code needed") was recorded in the group file, since the task was posed as an open question.

---

## Tasks 3.1 – 3.3 — Documentation

### Implementation
**README** (FR-21, FR-22, FR-23):
- New `#### Shaping the webhook response` subsection after `ExtractsWebhookReference`, with a worked J&T-shaped JSON ack. States why the return type is Symfony's `Response`, and lists the four behaviours a driver author needs: the interface is optional, rejection is logged before the response is built, the throw path bypasses the contract, and exceptions are not swallowed.
- New `#### Webhook rate limiting` subsection — config, per-driver override, `null` to disable, malformed-value fallback, and that a 429 cannot be shaped because middleware runs before the controller.
- Corrected "Requests that fail `verifyWebhook` return `401`" → "`401` by default", with a link to the new subsection. This sentence became untrue the moment Task 1.5 landed.
- Added the `webhook` block to the top-level Configuration example — it is a new top-level config key, so omitting it there would hide it.
- `Building a Custom Driver` now points at `ProvidesWebhookResponse` alongside `HandlesWebhooks`.

**CHANGELOG** (FR-24): contract under **Added** with the J&T `code == "1"` motivation; throttle rework under **Changed** with the route-parameter explanation and an explicit note that the default ceiling does not move. Added a line under the existing `getDeliveryModes()` entry confirming it remains the *only* breaking change in 1.3.0 — that mattered because a reader scanning a minor release needs to know these two additions did not add a second.

### Status
Done (3.1, 3.2, 3.3).

---

## Task 3.4 — Full verification

### Test Result

```
Tests: 136, Assertions: 323, PHPUnit Deprecations: 1
```

136 = 111 baseline + 25 new. The single deprecation is the pre-existing one present before any work started.

### Diff scope, verified against the spec's Out of Scope list

```
 config/courier.php                         |  9 ++++
 routes/webhook.php                         |  2 +-
 src/Contracts/ProvidesWebhookResponse.php  | 23 +++++++++
 src/CourierServiceProvider.php             |  7 +++
 src/Http/Controllers/WebhookController.php | 11 +++-
 src/Support/WebhookRateLimit.php           | 80 ++++++++++++++++++++++++++++++
 6 files changed, 129 insertions(+), 3 deletions(-)
```

- **No pre-existing test file modified** — confirmed by diffing `tests/` and excluding only the three new files and the fixtures. NFR-04 holds, and it holds by evidence rather than assertion.
- **`HandlesWebhooks`, `src/Logging/`, `Redactor`, `database/` untouched** — the spec's exclusions.
- **`composer.json` unchanged** — NFR-03. No `symfony/http-foundation` constraint added.
- `WebhookController`'s entire diff is 11 lines: one import swapped, one import added, one guard clause, one ternary.

### Code Review
- vs spec: pass — all 24 FRs implemented; all six NFRs verified
- vs plan: pass — 24 tasks, all accounted for
- Quality: pass
- Security: no change to the auth, credential or redaction surface. Verification logic untouched; rejected rows carry exactly the fields they did before
- Issues: none

### Status
Done.

---

## Group 3 Deviation Log

No structural deviations — spec and plan unchanged.

Two README edits beyond the literal task text, both consequences of FR-22 rather than new scope: the top-level Configuration example gained the `webhook` block (a new top-level key would otherwise be undiscoverable), and `Building a Custom Driver` now names `ProvidesWebhookResponse` where it already named `HandlesWebhooks`.
