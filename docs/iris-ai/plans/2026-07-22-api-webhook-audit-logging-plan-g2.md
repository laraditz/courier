# Group 2: Outgoing API Call Logging

**Branch:** feature/courier-api-call-logging
**Status:** done
**Parent plan:** 2026-07-22-api-webhook-audit-logging-plan.md

## Tasks

### Task G2-1 — `CourierApiLog` model
- **What:** `src/Models/CourierApiLog.php` — `protected $table = 'courier_api_logs'`, `public const UPDATED_AT = null`, array casts on `request_headers`, `request_body`, `response_headers`, `response_body`; boolean cast on `successful`; integer casts on `status_code`/`duration_ms`.
- **Test first:** Creating a row and re-fetching it returns array-typed JSON columns.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task G2-2 — Query scopes (FR-17)
- **What:** Add `scopeForReference`, `scopeForDriver`, `scopeSuccessful`, `scopeFailed` to `CourierApiLog`.
- **Test first:** Seed rows with varying `reference`/`driver`/`successful`; each scope returns exactly the matching subset.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task G2-3 — `ApiLogWriter` service (FR-06)
- **What:** `src/Logging/ApiLogWriter.php` — `record(array $data): void` redacts `request_headers`/`request_body`/`response_headers`/`response_body` via `Redactor` (using `config('courier.logging.redact')`), then `CourierApiLog::create($data)`; wraps the create in try/catch, calling `Log::error()` on failure without rethrowing.
- **Test first:** `record()` persists a row with redacted fields; forcing `CourierApiLog::create` to throw is swallowed and `Log::error` is called, no exception escapes.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G2-4 — `CourierHttpClient::forLog()` + guard (FR-02)
- **What:** `src/Http/CourierHttpClient.php` — `forLog(string $driver, string $action, ?string $reference = null, ?string $waybillNumber = null): static` is the only way to obtain an instance with verb methods; calling `get/post/put/patch/delete` without it throws `LogicException`. Verb methods take `(string $url, array $data = [], array $headers = [])` — headers passed as an explicit argument, not via chained `withHeaders()`. Add `illuminate/http: ^10.0|^11.0|^12.0|^13.0` to `composer.json` `require`.
- **Test first:** Calling a verb method on a bare instance throws `LogicException`; calling it after `forLog()` does not.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min

### Task G2-5 — Successful call logging (FR-01/03/04)
- **What:** Implement `post()` (and by extension the other verbs, same code path) to perform the real `Http` call, time it, and call `ApiLogWriter::record()` with `driver`, `action`, `reference`, `waybill_number`, `method`, `url`, headers, bodies, `status_code`, `duration_ms`, `successful`.
- **Test first:** `Http::fake()` a response; `CourierHttpClient::forLog(...)->post($url, $data)` results in exactly one `CourierApiLog` row with correct fields and a configured redact-list header replaced with `'[REDACTED]'`.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G2-6 — `logging.enabled=false` behavior (FR-07)
- **What:** Skip the `ApiLogWriter::record()` call entirely when `config('courier.logging.enabled')` is `false`; the underlying HTTP call still executes normally.
- **Test first:** With the flag off, `Http::fake()` a call, assert zero `CourierApiLog` rows and that the returned `Response` is unaffected.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task G2-7 — Connection-level exception handling
- **What:** Catch `Illuminate\Http\Client\ConnectionException` around the underlying `Http` call; log a row with `status_code=null`, `error_message` set to the exception message, `successful=false`, then rethrow the original exception.
- **Test first:** `Http::fake()` throwing a `ConnectionException`; assert a log row is written with the expected fields and the exception still propagates to the caller.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G2-8 — Non-JSON body handling (FR-05)
- **What:** Ensure request/response bodies that aren't JSON/array-decodable pass through `Redactor` and land in the DB as `{"_raw": "..."}`.
- **Test first:** `Http::fake()` a plain-text/XML response body; assert `response_body` is `['_raw' => '<original>']`.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min

### Task G2-9 — End-to-end resilience (FR-06 integration)
- **What:** Verify the full `CourierHttpClient` → `ApiLogWriter` path never lets a logging failure affect the real response.
- **Test first:** Force `CourierApiLog::create` to throw (e.g. via a DB constraint or mock); assert `CourierHttpClient::forLog(...)->post(...)` still returns the real `Response` from the faked HTTP call.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
