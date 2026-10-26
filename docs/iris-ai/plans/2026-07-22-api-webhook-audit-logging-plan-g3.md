# Group 3: Webhook Logging

**Branch:** feature/courier-webhook-logging
**Status:** done
**Parent plan:** 2026-07-22-api-webhook-audit-logging-plan.md

## Tasks

### Task G3-1 — `CourierWebhookLog` model
- **What:** `src/Models/CourierWebhookLog.php` — `protected $table = 'courier_webhook_logs'`, `public const UPDATED_AT = null`, array casts on `headers`/`payload`, boolean cast on `verified`.
- **Test first:** Creating a row and re-fetching returns array-typed JSON columns.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task G3-2 — Query scopes (FR-17)
- **What:** Add `scopeForReference`, `scopeForDriver`, `scopeRejected` (`status='rejected'`), `scopeProcessed` (`status='processed'`), `scopeFailed` (`status='failed'`) to `CourierWebhookLog`.
- **Test first:** Seed rows with varying `reference`/`driver`/`status`; each scope returns exactly the matching subset.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task G3-3 — `WebhookLogWriter` service (FR-16)
- **What:** `src/Logging/WebhookLogWriter.php` — `record(array $data): void` redacts `headers`/`payload` via `Redactor` (using `config('courier.logging.redact')`), then `CourierWebhookLog::create($data)`; wraps the create in try/catch, calling `Log::error()` on failure without rethrowing.
- **Test first:** `record()` persists a redacted row; forcing `CourierWebhookLog::create` to throw is swallowed, `Log::error` called, no exception escapes.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G3-4 — Log `rejected` on verification failure (FR-11/09)
- **What:** In `WebhookController::handle()`, before `abort(401)` on `verifyWebhook() === false`, call `WebhookLogWriter::record([...'verified' => false, 'status' => 'rejected'])`.
- **Test first:** Verification-fail request → one `CourierWebhookLog` row with `verified=false, status='rejected'`, response still `401`. Extend the existing unknown-driver and non-`HandlesWebhooks` 404 tests with an assertion that zero rows were created.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G3-5 — Log `processed` on success (FR-12)
- **What:** After `handleWebhook()` completes without throwing, write a log row with `verified=true, status='processed'`.
- **Test first:** Successful webhook request → row with `status='processed'`, response still `200`, `WebhookReceived` event still dispatched.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min

### Task G3-6 — Log `failed` + rethrow on exception (FR-13)
- **What:** Wrap `handleWebhook()` in try/catch; on exception, write a row with `verified=true, status='failed', error_message=$e->getMessage()`, then rethrow the original exception.
- **Test first:** A `handleWebhook()` implementation that throws → row with `status='failed'` and correct `error_message`, and the exception still propagates out of the controller (framework default 500 handling).
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G3-7 — `ExtractsWebhookReference` contract (FR-14)
- **What:** `src/Contracts/ExtractsWebhookReference.php` — `extractWebhookReference(Request $request): array{reference: ?string, waybillNumber: ?string}`. In `WebhookController`, if the resolved driver implements it, call it and merge `reference`/`waybill_number` into the log data for all three log-write paths (rejected/processed/failed).
- **Test first:** A driver implementing the contract → row's `reference`/`waybill_number` populated correctly; a driver not implementing it → both `null`.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G3-8 — Extraction failure isolation (FR-14 edge case)
- **What:** Wrap the `extractWebhookReference()` call in try/catch; a thrown exception must not block the log write or the webhook response.
- **Test first:** A driver whose `extractWebhookReference()` throws → log row still written (fields `null`), webhook response unaffected.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task G3-9 — Redaction on webhook headers/payload (FR-15)
- **What:** Confirm `WebhookLogWriter` applies `Redactor` to both `headers` and `payload` before persisting (already wired in G3-3 — this task is the dedicated test coverage for the webhook-specific path).
- **Test first:** A webhook payload/header key matching `config('courier.logging.redact')` is stored as `'[REDACTED]'` in the persisted row.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
