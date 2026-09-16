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
