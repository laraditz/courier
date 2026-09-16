# Group 1: Response Contract

**Status:** done
**Parent plan:** 2026-09-16-webhook-response-contract-plan.md

Covers C-1 and C-2 — FR-01 through FR-11.

## Tasks

### Task 1.1 — Failing test for the accepted response
- **What:** New `tests/WebhookResponseContractTest.php`. A driver implementing `ProvidesWebhookResponse` returns `response()->json(['code' => '1', 'msg' => 'success', 'data' => 'SUCCESS'])` from `webhookAcceptedResponse()`; assert the controller returns that body and status verbatim.
- **Test first:** `test_accepted_response_from_contract_driver_is_returned_verbatim` (FR-03, FR-09). Red — `ProvidesWebhookResponse` does not exist.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** test(webhook): assert driver-shaped accepted response [8ef3616]

### Task 1.2 — Create the contract and the test fixture
- **What:** `src/Contracts/ProvidesWebhookResponse.php` with `webhookAcceptedResponse()` and `webhookRejectedResponse()`, both typed to `Symfony\Component\HttpFoundation\Response`. Plus the closure-configured test fixture. **Revised during 1.6:** shipped as two classes, not one — `tests/Fixtures/PlainWebhookDriver.php` (`CourierDriver + HandlesWebhooks`, holding the 8 stubs) and `tests/Fixtures/ConfigurableWebhookDriver.php` extending it with `ProvidesWebhookResponse`. Task 1.6 needs a driver that does *not* implement the contract, and an interface cannot be un-implemented.
- **Test first:** 1.1 still red — controller not yet wired.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min
- **Status:** done
- **Commit:** feat(contracts): add ProvidesWebhookResponse [68711cc]

### Task 1.3 — Wire the accepted branch
- **What:** Widen `WebhookController::handle()`'s return type to `Symfony\Component\HttpFoundation\Response` (FR-02) and replace line 86 with the contract-aware ternary, falling back to `response()->noContent(200)` (FR-03).
- **Test first:** 1.1 goes green.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** feat(webhook): return driver-shaped accepted response [853a569]

### Task 1.4 — Failing test for the rejected response
- **What:** Assert a contract driver's `webhookRejectedResponse()` is returned on failed verification, **and** that the `rejected` row was still written.
- **Test first:** `test_rejected_response_from_contract_driver_is_returned_and_still_logged` (FR-05, FR-07). Red.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** test(webhook): assert driver-shaped rejection [6424f69]

### Task 1.5 — Wire the rejection branch
- **What:** Replace `abort(401)` at line 54 with the contract check, placed **after** the existing `$this->logWriter->record([...])` call so the ordering constraint holds (FR-05, FR-07).
- **Test first:** 1.4 goes green.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** feat(webhook): return driver-shaped rejection [816773d]

### Task 1.6 — Regression: non-implementing drivers unchanged
- **What:** Assert a driver without the contract still gets 200 with an empty body, and still gets 401 on failed verification. This is the NFR-01 guard.
- **Test first:** `test_driver_without_contract_keeps_default_responses` (FR-04, FR-06).
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** test(webhook): guard default responses for plain drivers [cfa4686]

### Task 1.7 — Path isolation
- **What:** Using call counters on the G1.2 fixture, assert `webhookAcceptedResponse()` is never called for a rejected request and `webhookRejectedResponse()` is never called for an accepted one.
- **Test first:** `test_contract_methods_are_called_only_on_their_own_path` (FR-08).
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** test(webhook): cover contract path isolation and throws [0281b75]

### Task 1.8 — Throwing `handleWebhook()` with a contract driver
- **What:** Assert the exception still propagates, the row is logged `failed` with `error_message`, and **neither** contract method is called.
- **Test first:** `test_handle_webhook_exception_bypasses_contract` (FR-10).
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** test(webhook): cover contract path isolation and throws [0281b75]

### Task 1.9 — Contract method throwing propagates
- **What:** Assert an exception thrown inside `webhookAcceptedResponse()` propagates rather than falling back to `noContent(200)`. This is the FR-11 decision made executable — a silent fallback would reintroduce the empty-body bug C-1 exists to fix.
- **Test first:** `test_contract_response_exception_is_not_swallowed` (FR-11).
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** test(webhook): cover contract path isolation and throws [0281b75]
