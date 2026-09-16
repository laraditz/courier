# Spec: Webhook Response Contract & Per-Driver Throttle

Brief: [2026-09-16-webhook-response-contract-brief.md](../briefs/2026-09-16-webhook-response-contract-brief.md)
Source handover: [2026-09-15-courier-webhook-response-contract-handover.md](../../2026-09-15-courier-webhook-response-contract-handover.md) (C-1, C-2, C-3)

## Overview

Add an opt-in `ProvidesWebhookResponse` contract so a driver can shape the body and status of both webhook exits, and replace the globally-shared `throttle:60,1` with a named limiter keyed per driver. Both changes are inert for any driver that does not opt in.

## Codebase Context

| File | Status | Role in this change |
|---|---|---|
| `src/Http/Controllers/WebhookController.php` | **extended** | Both hardcoded exits become contract-aware. Return type widens. |
| `src/Contracts/ProvidesWebhookResponse.php` | **new** | The contract. |
| `src/Support/WebhookRateLimit.php` | **new** | Stateless rate resolution, mirroring `Support/Redactor.php`. |
| `src/CourierServiceProvider.php` | **extended** | Registers the `courier-webhook` named limiter in `boot()`. |
| `routes/webhook.php` | **extended** | `throttle:60,1` becomes `throttle:courier-webhook`. |
| `config/courier.php` | **extended** | New `webhook.rate_limit` block. |
| `src/Contracts/ExtractsWebhookReference.php` | **reused as pattern** | Single-method opt-in interface, `instanceof` check at `WebhookController.php:36`. |
| `src/Support/Redactor.php` | **reused as pattern** | Stateless static helper, unit-tested directly in `tests/RedactorTest.php`. |
| `src/CourierManager.php` | **unchanged** | Already reads `courier.drivers.{driver}` — establishes where per-driver config lives. |
| `src/Contracts/HandlesWebhooks.php` | **unchanged** | Stays a two-method interface. |
| `src/Logging/WebhookLogWriter.php` | **unchanged** | Call sites and ordering preserved. |

**Verified in vendor, not assumed** (laravel/framework v12.62.0):

- `Limit::none()` returns `Illuminate\Cache\RateLimiting\Unlimited`; `ThrottleRequests::handleRequestUsingNamedLimiter()` short-circuits on `instanceof Unlimited` straight to `$next($request)`. The null-disables design is supported natively.
- An **unregistered** named limiter does not no-op — it falls through to `resolveMaxAttempts()` and throws `MissingRateLimiterException`. Registration in `boot()` is mandatory.
- `Illuminate\Http\JsonResponse` extends `Symfony\Component\HttpFoundation\JsonResponse`, a **sibling** of `Illuminate\Http\Response`. Typing the contract to `Illuminate\Http\Response` would make `response()->json()` a `TypeError` — the exact shape J&T requires.
- `Config\Repository::has()` resolves through `Arr::has()`, which uses `array_key_exists`. An explicitly-null config value returns `true`, so "set to null" is distinguishable from "absent". The whole fallback chain depends on this.

**Baseline:** 111 tests, 264 assertions, green (one pre-existing PHPUnit deprecation, unrelated).

## Chosen Implementation Approach

**Option B** — rate resolution extracted to `Laraditz\Courier\Support\WebhookRateLimit`, with `CourierServiceProvider` registering a thin limiter that delegates to it.

Rationale: the fallback chain (per-driver, then global, then default, with `null` disabling at either level) is the only branchy logic in this change. As an inline provider closure, every branch would need a full HTTP round trip and a 429 to observe. As a stateless static it follows the `Redactor` convention this package already established, and each branch is a single assertion. The 429 path still gets integration coverage on top.

## Functional Requirements

### Contract (C-1 / C-2)

- **FR-01** — `Laraditz\Courier\Contracts\ProvidesWebhookResponse` exists with exactly two methods: `webhookAcceptedResponse(Request $request): Response` and `webhookRejectedResponse(Request $request): Response`, both typed to `Symfony\Component\HttpFoundation\Response`.
- **FR-02** — `WebhookController::handle()`'s return type is widened to `Symfony\Component\HttpFoundation\Response`.
- **FR-03** — On successful verification and processing, a driver implementing the contract has its `webhookAcceptedResponse()` returned verbatim — status, headers and body unaltered.
- **FR-04** — On successful verification and processing, a driver **not** implementing the contract still receives `response()->noContent(200)`. Status and empty body unchanged from today.
- **FR-05** — On failed verification, a driver implementing the contract has its `webhookRejectedResponse()` returned verbatim.
- **FR-06** — On failed verification, a driver **not** implementing the contract still gets `abort(401)`. Unchanged from today.
- **FR-07** — The rejected log row is written **before** the rejection response is produced or returned, preserving the existing `logWriter->record()` call at `WebhookController.php:44`. A driver-shaped rejection is logged exactly as an `abort(401)` rejection is.
- **FR-08** — Contract methods are called only on their own path: `webhookAcceptedResponse()` is never called for a rejected request, and `webhookRejectedResponse()` is never called for an accepted one.
- **FR-09** — A driver returning `response()->json([...])` from either method works. This is the acceptance test for FR-01's typing decision.
- **FR-10** — `handleWebhook()` throwing behaves exactly as today: `status: failed` logged with `error_message`, exception rethrown, no contract method called. The contract does not cover this path.
- **FR-11** — Exceptions thrown by either contract method propagate; they are **not** caught. See Edge Cases for rationale.

### Per-driver throttle (C-3)

- **FR-12** — `CourierServiceProvider::boot()` registers a named rate limiter `courier-webhook`.
- **FR-13** — `routes/webhook.php` applies `throttle:courier-webhook` in place of `throttle:60,1`. Route name `courier.webhook` and the URI are unchanged.
- **FR-14** — The limiter key is `{driver}|{ip}`, so two drivers pushing from the same IP consume separate buckets.
- **FR-15** — `WebhookRateLimit::for(Request): Limit` resolves the rate in this order:
  1. `courier.drivers.{driver}.webhook.rate_limit` if the key **exists** (even when null)
  2. `courier.webhook.rate_limit` if the key **exists** (even when null)
  3. `WebhookRateLimit::DEFAULT_PER_MINUTE` (60)
- **FR-16** — A resolved value of `null` returns `Limit::none()` — the limiter is disabled for that driver. **This check runs first**, before any numeric validation. `null` is non-numeric, so an implementation that validates numerically before checking for null would capture `null` under FR-18 and cap the driver at 60 instead of disabling it.
- **FR-17** — A resolved **non-null** value that is numeric and `>= 1` returns `Limit::perMinute((int) $value)`.
- **FR-18** — A resolved **non-null** value that is non-numeric or `< 1` is treated as unconfigured and falls back to `DEFAULT_PER_MINUTE`. A config typo must never silently disable the limiter, nor lock the endpoint out entirely. FR-18 never applies to `null`, which FR-16 has already claimed.
- **FR-19** — `config/courier.php` gains `'webhook' => ['rate_limit' => env('COURIER_WEBHOOK_RATE_LIMIT', 60)]`.
- **FR-20** — An application whose published `config/courier.php` predates this change (no `webhook` key at all) falls through to FR-15 step 3 and gets 60/min — today's exact ceiling. `mergeConfigFrom()` merges shallowly at the top level, so this case is real and must be covered by a test.

### Documentation

- **FR-21** — README's Webhooks section documents `ProvidesWebhookResponse` with a worked JSON-ack example, placed after the `ExtractsWebhookReference` subsection.
- **FR-22** — README line 277 ("Requests that fail `verifyWebhook` return `401`") is corrected to state that 401 is the default and that a driver implementing the contract shapes its own rejection.
- **FR-23** — README documents `courier.webhook.rate_limit`, the per-driver override under `courier.drivers.{driver}.webhook.rate_limit`, `null` to disable, and that the key is per driver plus IP.
- **FR-24** — CHANGELOG records both under the unreleased `1.3.0`: the contract under **Added**, the throttle change under **Changed**.

## Non-Functional Requirements

- **NFR-01 — Backward compatibility.** No existing driver acquires a new required method. `sfexpress` and `lalamove` receive identical status codes, bodies and rate ceilings. This is the governing constraint; any FR that conflicts with it is wrong.
- **NFR-02 — Version range.** Only APIs present across `illuminate/*` `^10|^11|^12|^13`. `RateLimiter::for()`, named limiters and `Limit::none()` all predate 10.0.
- **NFR-03 — No new composer requirement.** The contract types against `Symfony\Component\HttpFoundation\Response`, which `illuminate/http` already requires. An explicit `symfony/http-foundation` constraint is deliberately **not** added — pinning one range across four Laravel majors risks an unsatisfiable install for no safety gain.
- **NFR-04 — Suite stays green.** All 111 existing tests pass unmodified. Any existing test needing a change is a signal the change is not backward-compatible.
- **NFR-05 — Test speed.** Throttle integration tests lower the configured rate rather than firing 61 requests.
- **NFR-06 — Availability.** A malformed rate-limit config degrades to the default ceiling, never to a locked-out endpoint (FR-18). A deliberate `null` is not malformed and is honoured as "disabled" (FR-16).

## Data Model

No change. `courier_webhook_logs` is untouched — same columns, same values, same write points. C-4 (which would have written `error_message` on rejected rows) is out of scope.

## API Contracts

`POST /courier/webhook/{driver}` — route, name and URI unchanged.

| Condition | Today | After |
|---|---|---|
| Unknown driver | 404 | 404 (unchanged) |
| Driver without `HandlesWebhooks` | 404 | 404 (unchanged) |
| `verifyWebhook()` false, no contract | 401 HTML | 401 HTML (unchanged) |
| `verifyWebhook()` false, contract | — | driver-supplied response |
| Processed, no contract | 200, empty body | 200, empty body (unchanged) |
| Processed, contract | — | driver-supplied response |
| `handleWebhook()` throws | 500 | 500 (unchanged) |
| Over rate limit | 429, shared 60/min per IP | 429, 60/min per driver+IP (configurable) |

New PHP contract:

```php
namespace Laraditz\Courier\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface ProvidesWebhookResponse
{
    public function webhookAcceptedResponse(Request $request): Response;
    public function webhookRejectedResponse(Request $request): Response;
}
```

New config:

```php
'webhook' => [
    'rate_limit' => env('COURIER_WEBHOOK_RATE_LIMIT', 60),
],
```

Per-driver override:

```php
'drivers' => [
    'jtexpress' => [
        // ...
        'webhook' => ['rate_limit' => 300],
    ],
],
```

## Skills & Agents Available

- `superpowers:test-driven-development` — red-green per task; matches the iris-ops implementation loop.
- `superpowers:verification-before-completion` — full suite run and quoted output before any completion claim.
- `audit` agent — not dispatched. No auth, credential or data-handling surface changes; the redaction path is untouched and C-4 is out of scope.
- `probe` agent — on standby for any unexplained test failure.

## Edge Cases & Error Handling

| Case | Handling |
|---|---|
| Contract method throws | **Propagates.** Not caught. `ExtractsWebhookReference` is swallowed because its output is decorative — a log field — and the request completes correctly without it. A response is not optional: catching here and falling back to `noContent(200)` would silently reintroduce the empty-body bug C-1 exists to fix, on the one path nobody is watching. Fail loudly. |
| Driver implements contract but not `HandlesWebhooks` | 404 before any contract method is reached. Unchanged ordering. |
| `webhookRejectedResponse()` returns a 200 | Returned as-is. The driver owns its ack semantics; core does not police the status. The row is still logged `rejected`, so the log stays truthful regardless of what went on the wire. |
| Rejected log write fails | Already swallowed inside `WebhookLogWriter::record()`. The rejection response is still returned. |
| Unknown driver hits the limiter | `route('driver')` is populated for any matched route, so the key resolves. Config lookups miss and fall to the default 60. |
| `rate_limit` set to `null` | FR-16: `Limit::none()`, limiter disabled. Checked **before** numeric validation — `null` is non-numeric and would otherwise be swallowed by FR-18. |
| `rate_limit` set to `0` or `-5` | FR-18: treated as unconfigured, falls back to 60. A ceiling of zero would block every push. |
| `rate_limit` set to a non-numeric string | FR-18: falls back to 60. |
| App published `config/courier.php` before this change | FR-20: no `webhook` key, falls through to the 60 default. Zero-touch upgrade. |
| Two drivers, same source IP, both at their ceiling | Independent buckets — neither can exhaust the other's. This is the C-3 fix. |
| 429 response body | Laravel's default. Middleware runs before the controller, so the contract cannot reach it. Out of scope, documented. |

## Out of Scope

- **C-4 rejection diagnostics.** Deferred by decision; needs its own driver-side hook and its own brief.
- **Driver-side implementation in `courier-jt-express`.** Separate package and version. Verified that no references to `ProvidesWebhookResponse` or either method name exist there yet, so core is free to define the shape. Compatibility check follows after core lands.
- **A response hook for the `handleWebhook()` throw path.** 500 stays.
- **Any change to `HandlesWebhooks`, `WebhookLogWriter`, `Redactor`, the log schema, or `courier.logging`.**
- **Shaping the 429 body.**
