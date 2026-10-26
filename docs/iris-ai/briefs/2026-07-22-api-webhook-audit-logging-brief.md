# Brief: Courier API & Webhook Audit Logging

## Goal
Give the `laraditz/courier` package a persistent, queryable audit trail of every outgoing courier API call and every incoming webhook, so there's somewhere to look when something goes wrong.

## Context
Today the package has no database footprint at all — no models, no migrations, and no shared HTTP client wrapper (driver implementations don't exist yet, only the `CourierDriver` contract and `CourierManager`). Webhook receiving already exists (`routes/webhook.php` → `WebhookController` → `WebhookReceived` event), but nothing is persisted; a failed signature check or a processing exception currently leaves no trace. The package also already treats `reference` (merchant shipment reference) and `waybill_number` as first-class concepts across `getShipment()`, `cancelShipment()`, `getLabel()`, `ShipmentPayload`, and `ShipmentResult` — the audit trail should key off the same concept so logs are searchable by shipment.

## Scope

### In
- A shared HTTP client wrapper (built on `Http`) that all future courier drivers use to make outgoing API calls — this is the single point where request/response logging happens, so no driver author can forget to log a call.
- `courier_api_logs` table (own migration, published via the existing `courier-migrations`-style `publishes([...])` pattern): method, URL, headers, request body, response status, response body, duration, driver, extracted `reference`/`waybill_number`.
- `courier_webhook_logs` table (separate migration/model): driver, raw payload, headers, signature verification result (including failures), outcome of `handleWebhook()` (success or exception + message), extracted `reference`/`waybill_number`.
- Automatic redaction of sensitive fields (API keys, secrets, tokens, `Authorization` headers, etc.) before storage, via a configurable redaction key list.
- Two Eloquent models (`CourierApiLog`, `CourierWebhookLog`) with query scopes for filtering by reference, driver, and success/failure — no bundled UI or CLI viewer.
- Synchronous writes wrapped in try/catch — a logging failure must never break a real shipment creation call or webhook response.
- `courier.logging.enabled` config flag, defaulting to `true`.
- Configurable retention (default 90 days) plus a scheduled `courier:prune-logs` Artisan command that host apps wire into their own scheduler.

### Out
- Queued/async log writes (no `illuminate/queue` dependency introduced).
- A package-provided log viewer, whether CLI (`courier:logs`) or UI (Filament/Nova resource) — host apps build their own on top of the models.
- A single unified log table — API and webhook logs are deliberately separate tables.
- Per-driver logging toggles (single global on/off flag only, for this iteration).

## Constraints
- Must fit the package's existing publish pattern (`publishes([...], 'courier-migrations')`, mirroring the existing `courier-config` tag) since it currently ships zero migrations.
- No new hard dependency on a queue system — package only requires `illuminate/support` and `illuminate/contracts` today.
- Must support the host app's default DB connection (package has never assumed a specific connection).
- Redaction list must be configurable per host app, not hardcoded.

## Open Questions Resolved
| Question | Answer |
|---|---|
| Where does the audit trail live? | Database tables (queryable), not log files |
| How are outgoing calls captured? | Shared HTTP client wrapper all future drivers build on |
| What data is captured, and is it redacted? | Full request/response capture with configurable automatic redaction of sensitive fields |
| One table or two? | Two separate tables: `courier_api_logs` and `courier_webhook_logs` |
| What does a webhook log entry capture? | Everything, including failed signature verifications and `handleWebhook()` exceptions |
| Can logging block or break real courier calls? | No — synchronous writes, failures caught and swallowed |
| Are logs searchable by shipment? | Yes — `reference`/`waybill_number` extracted into indexed columns on both tables |
| Retention policy? | Configurable (default 90 days) with a scheduled `courier:prune-logs` command |
| On by default or opt-in? | On by default (`courier.logging.enabled = true`), can be disabled |
| Does the package ship a viewer? | No — Eloquent models with query scopes only; host app builds its own view |
