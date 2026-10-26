# Debrief: Courier API & Webhook Audit Logging

**Brief:** [docs/iris-ai/briefs/2026-07-22-api-webhook-audit-logging-brief.md](../briefs/2026-07-22-api-webhook-audit-logging-brief.md)
**Spec:** [docs/iris-ai/specs/2026-07-22-api-webhook-audit-logging-spec.md](../specs/2026-07-22-api-webhook-audit-logging-spec.md)
**Plan:** [docs/iris-ai/plans/2026-07-22-api-webhook-audit-logging-plan.md](../plans/2026-07-22-api-webhook-audit-logging-plan.md)

## What Was Built
`laraditz/courier` now has a persistent audit trail for both halves of the problem you started with — nothing to check when something goes wrong, no more.

- **Outgoing API calls**: a new `CourierHttpClient` wrapper is the foundation every future courier driver will build on. Drivers call `->forLog(driver:, action:, reference:, waybillNumber:)` before making a request, and every call — success, non-2xx, or connection failure — is written to `courier_api_logs` with method, URL, headers, bodies, status, duration, and outcome.
- **Incoming webhooks**: the existing `WebhookController` now logs every attempt that reaches a valid driver to `courier_webhook_logs` — rejected (failed signature verification), processed (success), or failed (an exception during `handleWebhook()`, which still propagates exactly as before).
- **Redaction**: a shared `Redactor` utility strips configured sensitive keys (API keys, secrets, tokens, `Authorization`, etc.) from both request/response bodies and webhook payloads before anything touches the database, and wraps non-JSON bodies safely instead of discarding them.
- **Searchability**: both tables carry `reference`/`waybill_number` columns, populated explicitly by the driver (outgoing calls) or via an optional new `ExtractsWebhookReference` contract (webhooks) — the "search by shipment" workflow you asked for.
- **Resilience**: logging failures are caught and reported via `Log::error()`, and can never block a real shipment creation call or webhook response — verified end-to-end, not just at the unit level.
- **Housekeeping**: `php artisan courier:prune-logs` deletes rows past a configurable retention window (90 days by default), with a clean no-op when retention is disabled.

## Decisions Made
| Decision | Rationale |
|---|---|
| Database table, not log files | You need to *query* — "what happened to shipment X" — not just tail a log |
| Explicit `forLog()` context over automatic field-mapping | Reliable for every contract method, including `cancelShipment($waybillNumber, $reference)` where the reference is a plain argument, never guaranteed to appear in the HTTP body |
| Two separate tables, not one unified table | Troubleshooting means following a timeline (request → webhook), but request/webhook schemas diverge enough (`successful` vs `verified`/`status`) that forcing one table would add nullable-column noise |
| Synchronous writes, failures swallowed | No queue infrastructure required; audit logging must never become a single point of failure for real courier functionality |
| Logging on by default | The whole point is to never be caught without a trail again — opt-out, not opt-in |
| `illuminate/http` added as an explicit composer dependency | `CourierHttpClient` now depends on it directly, rather than assuming it's pulled in transitively by the host app |
| Verb methods take `array $headers` instead of chained `withHeaders()` | Simpler, fully sufficient — no task needed fluent request building (tracked as a deviation, spec updated) |

## Deviations from Plan
- **`CourierHttpClient` header handling** (Group 2): the spec's original "mirrors `PendingRequest`" wording implied fluent `->withHeaders()->post()` chaining. Built instead as an explicit trailing `array $headers = []` argument on each verb method — simpler, and no task actually required fluent chaining. Spec FR-02 and plan Task G2-4 were updated to reflect this during the Group 2 doc sync.
- Several tasks (G2-6, G2-8, G3-9, G4-3) landed green on the first run with no RED state, because the behavior they covered had already been built as a natural part of an earlier task in the same group (e.g. the `logging.enabled` check was written alongside the main logging path in G2-5). Flagged honestly in each task's report rather than staged as artificial failures — no code changed as a result, so no spec/plan sync was needed for these.

## Test Coverage
93 tests, 217 assertions, all green on `develop`. Every one of the spec's 19 functional requirements has direct test coverage, plus the documented edge cases (connection failures, non-JSON bodies, unknown-driver webhook 404s, extraction failures, null retention).

## Known Issues / Open Items
- `verifyWebhook()` throwing an exception (instead of returning `false`) is pre-existing, unhandled behavior — not introduced or fixed by this feature. It still produces no log row if it happens. Worth a follow-up if it ever becomes a real occurrence.
- No size cap or truncation on stored request/response bodies (e.g. base64 label PDFs) — documented as a known limitation in the spec, not solved here.
- This package still has no concrete courier driver implementations (e.g. an actual SF Express driver) — `CourierHttpClient` and the logging pipeline are built and tested, but nothing in this codebase exercises them against a real courier API yet.
- No package-provided log viewer (CLI or UI) — by design, per the brief. Host apps query `CourierApiLog`/`CourierWebhookLog` directly.

## Next Steps
- Build the first real courier driver (e.g. SF Express) using `CourierHttpClient`, to prove the logging pipeline against actual API traffic rather than fakes.
- In a consuming app, schedule `courier:prune-logs` (e.g. daily in the app's scheduler) — the package doesn't self-schedule.
- Consider a Filament/Nova resource or simple Blade view in a consuming app for browsing `CourierApiLog`/`CourierWebhookLog` day-to-day.
- Run `composer update` in this package's dev environment to sync `composer.lock` with the newly added `illuminate/http` requirement before tagging a release.

## Docs Generated
- [Brief](../briefs/2026-07-22-api-webhook-audit-logging-brief.md)
- [Spec](../specs/2026-07-22-api-webhook-audit-logging-spec.md)
- [Plan](../plans/2026-07-22-api-webhook-audit-logging-plan.md)
- [Implementation Notes](../docs/2026-07-22-api-webhook-audit-logging-ops.md)
- [Debrief](2026-07-22-api-webhook-audit-logging-debrief.md)
