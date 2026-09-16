# Ops Log: Courier API & Webhook Audit Logging

**Plan:** [docs/iris-ai/plans/2026-07-22-api-webhook-audit-logging-plan.md](../plans/2026-07-22-api-webhook-audit-logging-plan.md)
**Execution mode:** TDD

## Group 1: Foundation — feature/courier-logging-foundation

### Task F1: Add `logging` config section

#### Test Written
`tests/ConfigLoggingTest.php::test_logging_config_has_expected_defaults` — asserts `courier.logging.enabled`, `retention_days`, `redact` match the spec's documented defaults.

#### Implementation
Added `logging` array to `config/courier.php` (`enabled`, `retention_days`, `redact` list).

#### Test Result
All green (55/55 suite).

#### Code Review
- vs spec: pass
- vs plan: pass
- Quality: pass
- Issues: none

#### Status
Done

Commit: feat(logging): add courier logging config defaults [294e088]

### Task F2: Wire migration loading + `courier_api_logs` migration

#### Test Written
`tests/CourierApiLogsMigrationTest.php` — asserts `courier_api_logs` table and all documented columns exist.

#### Implementation
Added `defineDatabaseMigrations()` hook to `tests/TestCase.php` (`loadMigrationsFrom`); created `database/migrations/2026_07_22_000001_create_courier_api_logs_table.php` matching the spec's Data Model exactly.

#### Test Result
All green (56/56 suite).

#### Code Review
- vs spec: pass
- vs plan: pass
- Quality: pass
- Issues: none

#### Status
Done

Commit: feat(logging): add courier_api_logs migration [de6a27b]

### Task F3: `courier_webhook_logs` migration

#### Test Written
`tests/CourierWebhookLogsMigrationTest.php` — asserts `courier_webhook_logs` table and all documented columns exist.

#### Implementation
`database/migrations/2026_07_22_000002_create_courier_webhook_logs_table.php` matching the spec's Data Model.

#### Test Result
All green (57/57 suite).

#### Code Review
- vs spec: pass
- vs plan: pass
- Quality: pass
- Issues: none

#### Status
Done

Commit: feat(logging): add courier_webhook_logs migration [31986fc]

### Task F4: Publish migrations under `courier-migrations` tag

#### Test Written
`tests/CourierServiceProviderPublishTest.php` — asserts `ServiceProvider::pathsToPublish(CourierServiceProvider::class, 'courier-migrations')` includes both migration files.

#### Implementation
Added a second `publishes()` call in `CourierServiceProvider::boot()` under the `courier-migrations` tag, mirroring the existing `courier-config` pattern.

#### Test Result
All green (58/58 suite).

#### Code Review
- vs spec: pass
- vs plan: pass
- Quality: pass
- Issues: none

#### Status
Done

Commit: feat(logging): publish migrations under courier-migrations [2b80095]

### Task F5: `Redactor` utility

#### Test Written
`tests/RedactorTest.php` — case-insensitive exact-key redaction, recursive nested-array redaction, non-JSON string wrapped as `{"_raw": ...}`, JSON string decoded and redacted, `null` passthrough.

#### Implementation
`src/Support/Redactor.php` — static `redact(mixed $value, array $redactKeys): mixed`.

#### Test Result
All green (63/63 suite).

#### Code Review
- vs spec: pass
- vs plan: pass
- Quality: pass
- Issues: none

#### Status
Done

Commit: feat(logging): add Redactor utility for sensitive data [a64ef4d]

## Group 1 Deviation Log
No structural deviations — docs unchanged.

## Group 2: Outgoing API Call Logging — feature/courier-api-call-logging

### Task G2-1: `CourierApiLog` model
Test: JSON columns cast to array, `UPDATED_AT` null. All green (65/65).
vs spec: pass. vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add CourierApiLog model [8a1dd08]

### Task G2-2: Query scopes
Test: `forReference`, `forDriver`, `successful`, `failed`. All green (68/68).
vs spec: pass (FR-17). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add CourierApiLog query scopes [31f2bcb]

### Task G2-3: `ApiLogWriter` service
Test: redacted persistence; DB write failure caught, `Log::error` called, no exception escapes. All green (70/70).
vs spec: pass (FR-06). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add ApiLogWriter with failure isolation [7a3710e]

### Task G2-4: `CourierHttpClient::forLog()` + guard
Test: verb method without `forLog()` throws `LogicException`; `forLog()` returns configured instance. Added `illuminate/http` to composer.json. All green (72/72).
vs spec: pass (FR-02). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add CourierHttpClient forLog guard [ad3c57d]

### Task G2-5: Successful call logging
Test: `Http::fake()`'d `post()` creates one row with correct fields + redacted header. All green (73/73).
vs spec: pass (FR-01/03/04). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): log successful CourierHttpClient calls [a73e7be]

### Task G2-6: `logging.enabled=false` behavior
Test: flag off → zero rows, HTTP call unaffected. Already green from G2-5's implementation — no RED cycle this task. All green (74/74).
vs spec: pass (FR-07). vs plan: pass. Quality: pass. Issues: none.
Commit: test(logging): cover logging.enabled=false behavior [4766536]

### Task G2-7: Connection-level exception handling
Test: `ConnectionException` during fake call → row logged (`status_code=null`, `error_message` set), exception still propagates. All green (75/75).
vs spec: pass (Edge Case). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): log and rethrow connection failures [b00891f]

### Task G2-8: Non-JSON body handling
Test: XML response body → `response_body = {"_raw": ...}`. Already green from Redactor/ApiLogWriter wiring — no RED cycle. All green (76/76).
vs spec: pass (FR-05). vs plan: pass. Quality: pass. Issues: none.
Commit: test(logging): cover non-JSON response body wrapping [4427d10]

### Task G2-9: End-to-end resilience
Test: dropped `courier_api_logs` table mid-test → `CourierHttpClient` call still returns the real successful `Response`, `Log::error` called once. All green (77/77).
vs spec: pass (FR-06 integration). vs plan: pass. Quality: pass. Issues: none.
Commit: test(logging): verify DB failure never blocks response [095e5c0]

## Group 2 Deviation Log
- `CourierHttpClient` verb methods take an explicit `array $headers = []` trailing parameter instead of a chained `withHeaders()` call (the spec's initial "mirrors `PendingRequest`" wording was loose on this point) — updated spec FR-02 and plan Task G2-4 to state the actual signature.

## Group 3: Webhook Logging — feature/courier-webhook-logging

### Task G3-1: `CourierWebhookLog` model
Test: JSON columns cast to array, `UPDATED_AT` null. All green (79/79).
vs spec: pass. vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add CourierWebhookLog model [77db797]

### Task G3-2: Query scopes
Test: `forReference`, `forDriver`, `rejected`, `processed`, `failed`. All green (82/82).
vs spec: pass (FR-17). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add CourierWebhookLog query scopes [900eb82]

### Task G3-3: `WebhookLogWriter` service
Test: redacted persistence; DB write failure caught, `Log::error` called. All green (84/84).
vs spec: pass (FR-16). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add WebhookLogWriter with failure isolation [899061f]

### Task G3-4: Log `rejected` on verification failure
Test: verification-fail → row with `verified=false, status='rejected'`, still 401; existing 404 tests extended to assert zero rows. All green (84/84).
vs spec: pass (FR-09/11). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): log rejected webhooks in WebhookController [1c06259]

### Task G3-5: Log `processed` on success
Test: successful webhook → row with `status='processed'`, still 200. All green (85/85).
vs spec: pass (FR-12). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): log processed webhooks on success [e94f609]

### Task G3-6: Log `failed` + rethrow on exception
Test: `handleWebhook()` throws → row with `status='failed'`, `error_message` set, exception still propagates (`withoutExceptionHandling()`). All green (86/86).
vs spec: pass (FR-13). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): log failed webhooks and rethrow exception [efd2735]

### Task G3-7: `ExtractsWebhookReference` contract
Test: driver implementing it → row's `reference`/`waybill_number` populated; not implementing → both null. All green (88/88).
vs spec: pass (FR-14). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): extract webhook reference when supported [f097cbc]

### Task G3-8: Extraction failure isolation
Test: `extractWebhookReference()` throws → row still written (fields null), response unaffected. All green (89/89).
vs spec: pass (FR-14 edge case). vs plan: pass. Quality: pass. Issues: none.
Commit: fix(logging): isolate webhook reference extraction failures [94200f3]

### Task G3-9: Webhook redaction end-to-end
Test: payload key matching redact list stored as `[REDACTED]`. Already green from G3-3's wiring — no RED cycle. All green (90/90).
vs spec: pass (FR-15). vs plan: pass. Quality: pass. Issues: none.
Commit: test(logging): cover webhook payload redaction end-to-end [004f017]

## Group 3 Deviation Log
No structural deviations — docs unchanged.

## Group 4: Log Retention & Pruning — feature/courier-log-retention

### Task G4-1: Prune `courier_api_logs`
Test: old rows deleted, recent rows kept. All green (91/91).
vs spec: pass (FR-18). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): add courier:prune-logs command for API logs [b48901b]

### Task G4-2: Prune `courier_webhook_logs`
Test: same pattern for webhook logs. All green (92/92).
vs spec: pass (FR-18). vs plan: pass. Quality: pass. Issues: none.
Commit: feat(logging): extend prune command to webhook logs [b50190f]

### Task G4-3: No-op when `retention_days` is `null`
Test: null retention → zero deletions, console message. Already green from G4-1's implementation — no RED cycle. All green (93/93).
vs spec: pass (FR-18 edge case). vs plan: pass. Quality: pass. Issues: none.
Commit: test(logging): cover retention_days=null no-op [85d4b66]

## Group 4 Deviation Log
No structural deviations — docs unchanged.

## Final State
All 4 groups complete. 93/93 tests green. All 19 FRs implemented and covered.
