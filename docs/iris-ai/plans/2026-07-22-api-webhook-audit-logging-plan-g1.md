# Group 1: Foundation (config, migrations, redaction)

**Branch:** feature/courier-logging-foundation
**Status:** done
**Parent plan:** 2026-07-22-api-webhook-audit-logging-plan.md

## Tasks

### Task F1 — Add `logging` config section
- **What:** Add `logging` array to `config/courier.php` with `enabled` (env `COURIER_LOGGING_ENABLED`, default `true`), `retention_days` (env `COURIER_LOGGING_RETENTION_DAYS`, default `90`), and `redact` (default list: `authorization`, `api_key`, `apikey`, `key`, `secret`, `token`, `password`).
- **Test first:** `config('courier.logging')` returns the documented defaults array.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task F2 — Wire migration loading + `courier_api_logs` migration
- **What:** Add `loadMigrationsFrom(__DIR__.'/../database/migrations')` to `tests/TestCase.php`; create `database/migrations/xxxx_create_courier_api_logs_table.php` per the spec's Data Model table (`driver`, `action`, `reference`, `waybill_number`, `method`, `url`, `request_headers`, `request_body`, `status_code`, `response_headers`, `response_body`, `duration_ms`, `successful`, `error_message`, `created_at`; indexes on `driver`, `reference`, `waybill_number`, `created_at`, `successful`).
- **Test first:** After test boot, `Schema::hasTable('courier_api_logs')` and `Schema::hasColumns('courier_api_logs', [...])` are true.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task F3 — `courier_webhook_logs` migration
- **What:** Create `database/migrations/xxxx_create_courier_webhook_logs_table.php` per spec Data Model (`driver`, `reference`, `waybill_number`, `headers`, `payload`, `verified`, `status`, `error_message`, `created_at`; indexes on `driver`, `reference`, `waybill_number`, `created_at`, `status`).
- **Test first:** `Schema::hasTable('courier_webhook_logs')` and `Schema::hasColumns('courier_webhook_logs', [...])` are true.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task F4 — Publish migrations under `courier-migrations` tag
- **What:** In `CourierServiceProvider::boot()`, add `$this->publishes([...], 'courier-migrations')` for both migration files, mirroring the existing `courier-config` publish pattern.
- **Test first:** `ServiceProvider::pathsToPublish(CourierServiceProvider::class, 'courier-migrations')` returns both migration source/destination paths.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min

### Task F5 — `Redactor` utility
- **What:** `src/Support/Redactor.php` — `redact(mixed $headersOrBody, array $redactKeys): array` recursively walks arrays, replacing values whose key case-insensitively (exact match, not substring) matches an entry in `$redactKeys` with `'[REDACTED]'`; if the input isn't array/JSON-decodable, wraps it as `['_raw' => $original]` (FR-05) with redaction skipped.
- **Test first:** Redacting `['secret' => 'x', 'client_secret' => 'y']` against `['secret']` yields `secret` redacted, `client_secret` untouched; a nested array redacts recursively; a raw string input becomes `['_raw' => $input]`.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min
