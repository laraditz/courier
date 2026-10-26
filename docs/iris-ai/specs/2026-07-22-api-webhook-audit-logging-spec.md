# Spec: Courier API & Webhook Audit Logging

## Overview
Add a persistent, queryable audit trail to `laraditz/courier` covering every outgoing courier API call and every incoming webhook. Outgoing calls are captured through a new `CourierHttpClient` wrapper that all future drivers build on; incoming webhooks are captured by instrumenting the existing `WebhookController` pipeline. Both write to their own database table, with sensitive fields redacted before storage, and neither can ever break the real courier call or webhook response if the log write itself fails.

## Codebase Context
- Stack: Laravel package, PHP ^8.1, `illuminate/support`/`illuminate/contracts` ^10–13, PSR-4 `Laraditz\Courier\`, PHPUnit + Orchestra Testbench.
- Existing pieces this builds on:
  - `src/CourierServiceProvider.php` — will gain a `courier-migrations` publish tag (mirrors the existing `courier-config` tag) and `loadMigrationsFrom()` for tests.
  - `src/Http/Controllers/WebhookController.php` — the exact hook point for webhook logging; currently `abort(401)` on failed verification leaves zero trace, which this feature fixes.
  - `config/courier.php` — gains a new `logging` section.
  - `src/Contracts/CourierDriver.php`, `HandlesWebhooks.php` — driver contracts this feature's new `CourierHttpClient` and `ExtractsWebhookReference` contract sit alongside.
- Nothing existing is being replaced — this is entirely additive since no driver implementations, models, or migrations exist yet.

## Skills & Agents Available
- `test-driven-development` — every functional requirement below is implemented test-first, following the existing `tests/*Test.php` + Testbench convention (see `tests/WebhookTest.php` for the pattern of registering an inline driver via `app('courier')->extend()`).
- `systematic-debugging` — for any Laravel 10–13 migration/Eloquent compatibility issue that surfaces during implementation.
- Flag for iris-ops: the redaction logic (FR-04/FR-15) is the only thing standing between secrets and a database row — worth a focused review pass, not necessarily a full `audit` dispatch.

## Functional Requirements

**Outgoing API call logging**
- FR-01: When `config('courier.logging.enabled')` is `true` (default), every call made through `CourierHttpClient` creates exactly one `courier_api_logs` row — whether the call succeeds, returns a non-2xx status, or throws a connection-level exception.
- FR-02: `CourierHttpClient::forLog(string $driver, string $action, ?string $reference = null, ?string $waybillNumber = null): static` is the **only** way to obtain an instance with HTTP verb methods — verb methods do not exist on a bare/unconfigured instance. Calling a verb method without having gone through `forLog()` first throws a `LogicException` before any HTTP call is attempted, so `driver`/`action` are guaranteed present on every logged row. Verb methods are `get(string $url, array $query = [], array $headers = [])`, `post(string $url, array $data = [], array $headers = [])`, `put`/`patch`/`delete` (same shape as `post`) — each returns the underlying `Illuminate\Http\Client\Response` unchanged. (Deviation from initial "mirrors `PendingRequest`" wording: headers are passed as an explicit trailing array argument rather than via a chained `withHeaders()` call — simpler and fully sufficient for this package's use, since no task requires fluent request building.)
- FR-03: Each `courier_api_logs` row captures: `driver`, `action`, `reference`, `waybill_number`, `method`, `url`, `request_headers`, `request_body`, `status_code` (nullable), `response_headers`, `response_body`, `duration_ms`, `successful`, `error_message` (nullable).
- FR-04: Header/body values whose key case-insensitively matches an entry in `config('courier.logging.redact')` are replaced with `'[REDACTED]'` before the row is persisted, recursively through nested arrays.
- FR-05: A request/response body that is not valid JSON/array-decodable is stored as `{"_raw": "<original string>"}`; redaction is skipped for that body.
- FR-06: A failure while writing a `courier_api_logs` row is caught, reported via `Log::error()`, and never prevents the underlying HTTP response (or exception) from reaching the driver.
- FR-07: When `config('courier.logging.enabled')` is `false`, `CourierHttpClient` performs the HTTP call exactly as normal but writes no `courier_api_logs` row.

**Webhook logging**
- FR-08: Every request to `POST /courier/webhook/{driver}` that resolves to a driver implementing `HandlesWebhooks` creates exactly one `courier_webhook_logs` row, regardless of whether verification succeeds, fails, or `handleWebhook()` throws.
- FR-09: Requests for an unknown driver, or a driver not implementing `HandlesWebhooks` (the existing `404` paths), create no log row.
- FR-10: Each `courier_webhook_logs` row captures: `driver`, `headers`, `payload`, `verified` (bool), `status` (`rejected`|`processed`|`failed`), `error_message` (nullable), `reference` (nullable), `waybill_number` (nullable).
- FR-11: If `verifyWebhook()` returns `false`, the row is written with `verified=false, status='rejected'` before the controller returns its `401`.
- FR-12: If `handleWebhook()` completes without throwing, the row is written with `verified=true, status='processed'`.
- FR-13: If `handleWebhook()` throws, the row is written with `verified=true, status='failed'`, `error_message` set to the exception message, and the original exception is rethrown afterward — the controller's response behaviour (framework default exception handling) is unchanged.
- FR-14: If the resolved driver implements the new `ExtractsWebhookReference` contract, `extractWebhookReference(Request $request): array{reference: ?string, waybillNumber: ?string}` is called (wrapped in try/catch — extraction failure must not block logging) to populate `reference`/`waybill_number`; otherwise both stay `null`.
- FR-15: The same redaction rules as FR-04 apply to `headers` and `payload` before the row is persisted.
- FR-16: A failure while writing a `courier_webhook_logs` row is caught, reported via `Log::error()`, and never alters the webhook response returned to the caller.

**Querying & retention**
- FR-17: `CourierApiLog` and `CourierWebhookLog` expose query scopes: `forReference(string $reference)`, `forDriver(string $driver)`, plus outcome scopes — `successful()`/`failed()` on API logs, `rejected()`/`processed()`/`failed()` on webhook logs.
- FR-18: `php artisan courier:prune-logs` deletes rows older than `config('courier.logging.retention_days')` days from both tables, in chunks; no-ops with a console message when `retention_days` is `null`.
- FR-19: Both migrations are published under a `courier-migrations` tag via `publishes([...], 'courier-migrations')` in `CourierServiceProvider::boot()`, and auto-loaded in tests via `loadMigrationsFrom()` in `tests/TestCase.php`.

## Non-Functional Requirements
- **Reliability:** Logging can never be a single point of failure for shipment creation or webhook receipt (FR-06, FR-16) — this is the most important guarantee in the whole feature.
- **Security:** Redaction (FR-04/FR-15) runs before any write; no encryption-at-rest is implemented — that remains the host app's DB responsibility.
- **Performance:** One synchronous DB insert per outgoing call and per webhook request. Acceptable for typical shipment volumes; not designed for high-frequency use. `driver`, `reference`, `waybill_number`, `created_at`, and `successful`/`status` are indexed to keep lookups and pruning fast as tables grow.
- **Compatibility:** Migrations use only Schema Builder syntax compatible with Laravel 10–13 (the range already declared in `composer.json`).
- **New dependency:** `CourierHttpClient` depends directly on `Illuminate\Http\Client\Factory`. `composer.json` must add `illuminate/http: ^10.0|^11.0|^12.0|^13.0` as an explicit `require` — today it's only pulled in transitively by the host Laravel app, which this package should no longer assume.

## Configuration Defaults
`config/courier.php` gains a `logging` section with these literal defaults (host apps override via `.env`/published config):

```php
'logging' => [
    'enabled' => env('COURIER_LOGGING_ENABLED', true),
    'retention_days' => env('COURIER_LOGGING_RETENTION_DAYS', 90),
    'redact' => [
        'authorization',
        'api_key',
        'apikey',
        'key',
        'secret',
        'token',
        'password',
    ],
],
```
`redact` matches header/body keys case-insensitively (FR-04/FR-15) — the list above covers this package's own `sfexpress` driver config shape (`key`, `secret`) plus the common generic terms. `retention_days` accepts `null` to disable pruning entirely (FR-18).

## Data Model

### `courier_api_logs` (new)
| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| driver | string(100), indexed | |
| action | string(100) | e.g. `createShipment`, `track` |
| reference | string(191), nullable, indexed | from `forLog()` |
| waybill_number | string(191), nullable, indexed | from `forLog()` |
| method | string(10) | GET/POST/PUT/PATCH/DELETE |
| url | text | |
| request_headers | json, nullable | redacted |
| request_body | json, nullable | redacted; `{"_raw": ...}` if non-JSON |
| status_code | unsignedSmallInteger, nullable | null on connection failure |
| response_headers | json, nullable | redacted |
| response_body | json, nullable | redacted; `{"_raw": ...}` if non-JSON |
| duration_ms | unsignedInteger | |
| successful | boolean, indexed | 2xx and no exception |
| error_message | text, nullable | set on connection-level failure |
| created_at | timestamp, indexed | no `updated_at` — rows are immutable |

### `courier_webhook_logs` (new)
| Column | Type | Notes |
|---|---|---|
| id | bigIncrements | |
| driver | string(100), indexed | |
| reference | string(191), nullable, indexed | from `ExtractsWebhookReference`, if implemented |
| waybill_number | string(191), nullable, indexed | from `ExtractsWebhookReference`, if implemented |
| headers | json, nullable | redacted |
| payload | json, nullable | redacted; `$request->all()` |
| verified | boolean | result of `verifyWebhook()` |
| status | string(20), indexed | `rejected` \| `processed` \| `failed` |
| error_message | text, nullable | set when `handleWebhook()` throws |
| created_at | timestamp, indexed | no `updated_at` |

Both models: `CourierApiLog`, `CourierWebhookLog` (new, under `src/Models/`), with `public const UPDATED_AT = null` and array casts on the JSON columns.

## API Contracts
- `POST /courier/webhook/{driver}` — **existing route, unchanged** request/response shape and status codes (`404`/`401`/`200`); this feature only adds a persistence side effect.
- New internal (non-HTTP) PHP surface:
  - `Laraditz\Courier\Http\CourierHttpClient` — new. `forLog(...)`, `get/post/put/patch/delete(...)`.
  - `Laraditz\Courier\Contracts\ExtractsWebhookReference` — new, optional contract for drivers.
  - `Laraditz\Courier\Models\CourierApiLog`, `CourierWebhookLog` — new Eloquent models + scopes.
  - `courier:prune-logs` — new Artisan command.

## Implementation Options

### Option A — Explicit context passing (Chosen)
`CourierHttpClient` mirrors `Http::get/post/put/patch/delete()`; drivers call `->forLog(driver:, action:, reference:, waybillNumber:)` before making the request. Reliable for every contract method, including `cancelShipment($waybillNumber, $reference)` where the reference is a plain argument that may never appear in the HTTP body.

### Option B — Automatic extraction via per-driver field-mapping config
Config-driven dot-notation mapping plucks `reference`/`waybill_number` out of the JSON body automatically. Rejected: breaks silently on API shape changes, and cannot work at all for method-argument-only cases like `cancelShipment`.

### Option C — Hybrid (automatic + explicit override)
B as default, A as override. Rejected: doubles the code paths to build and test for no benefit once A alone already covers every case.

**Recommended and chosen:** Option A — reliable for every contract method, and since no driver implementations exist yet, there's no existing convention it needs to fit around.

## Chosen Implementation Approach
Option A, explicit context passing. `CourierHttpClient::forLog()` is the single source of truth for `driver`/`action`/`reference`/`waybill_number` on outgoing calls. The equivalent explicit pattern is used for webhooks via the new optional `ExtractsWebhookReference` contract (FR-14), keeping the "explicit over inferred" philosophy consistent across both halves of the feature.

## Edge Cases & Error Handling
- **Connection-level failure** (timeout, DNS failure, connection refused) on an outgoing call: no `Response` object exists; the row is still written with `status_code=null`, `error_message` set, `successful=false` — then the original exception is rethrown to the driver (the wrapper only swallows *logging* failures, never real HTTP exceptions).
- **Non-JSON body** (e.g. XML/plain text from a courier API): stored as `{"_raw": "..."}`; redaction is skipped since there's no key structure to redact against.
- **Unknown/invalid driver hitting the webhook route** (existing 404 paths): not logged — avoids filling the audit table with scanning/bot noise against random driver names.
- **`verifyWebhook()` itself throws** (rather than returning `false`): unchanged pre-existing behavior — the exception bubbles to Laravel's default handler and produces no log row. Not introduced or fixed by this feature.
- **Very large bodies** (e.g. base64-encoded label PDFs): stored as-is in the `json` column; no size cap or truncation is implemented — a documented limitation, not solved here.
- **`retention_days = null`**: `courier:prune-logs` no-ops with a console message; nothing is deleted.
- **Redaction matching**: case-insensitive, exact key match only (not substring) — e.g. a `redact` list containing `secret` redacts a `secret` key but not `client_secret` unless that's also listed explicitly.

## Out of Scope
- Async/queued log writes (no `illuminate/queue` dependency introduced).
- A package-provided log viewer, CLI or UI.
- A single unified log table — API and webhook logs stay in separate tables.
- Per-driver logging enable/disable toggles (one global flag only).
- Encryption at rest for log data.
- Fixing `verifyWebhook()`'s unhandled-exception behavior (pre-existing gap, unrelated to this feature).
- Enforcing a max body size or truncation strategy for large payloads.
