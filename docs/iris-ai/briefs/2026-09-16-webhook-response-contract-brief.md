# Brief: Webhook Response Contract & Per-Driver Throttle

## Goal
Let a courier driver shape the HTTP response its webhook endpoint returns, and stop drivers from sharing a single rate-limit ceiling — without changing behaviour for any driver that does not opt in.

## Context

`WebhookController` hardcodes both of its exits: `abort(401)` on a failed `verifyWebhook()` (line 54) and `response()->noContent(200)` on success (line 86). `HandlesWebhooks` exposes `verifyWebhook(): bool` and `handleWebhook(): void` — neither can influence the wire response, and `grep -rn "Response" src/Contracts/` returns zero matches. There is no response hook of any kind.

J&T Express Malaysia's [Tracking Info Callback](https://ylopen.jtexpress.my/apiDoc/logistics/statusFeedback) parses the ack body for `code == "1"` and mandates four fields:

```json
{"code":"1","msg":"success","data":"SUCCESS","requestId":"211212121212"}
```

An empty body is a parse failure on their side, so every push we handle correctly is recorded by J&T as a **failed push** — driving retries (duplicate domain events for us) and risking suspension of the callback account. Rejections are worse: an HTML 401 error page carries none of the coded error table J&T documents (`145003030` signature verification failed, `145003051` apiAccount empty, and so on), leaving carrier support unable to diagnose anything.

This is not J&T-specific. Any carrier with an ack-body contract — most Chinese-origin logistics APIs and several SEA carriers — hits the same wall.

Separately, `routes/webhook.php` applies `throttle:60,1` to `courier/webhook/{driver}`. Because the driver is a route *parameter* and not a separate route, `ThrottleRequests::resolveRequestSignature()` keys unauthenticated requests on `sha1($route->getDomain().'|'.$request->ip())` — so the 60/min ceiling is shared across every courier driver at once. A hub sorting run pushes past it, and the overflow 429s count as failed pushes for whichever carrier happens to lose the race.

Source: [Webhook Response Contract & Rejection Diagnostics handover](../../2026-09-15-courier-webhook-response-contract-handover.md), items C-1, C-2 and C-3.

## Scope

### In

**C-1 + C-2 — `ProvidesWebhookResponse` contract**
- New optional contract `Laraditz\Courier\Contracts\ProvidesWebhookResponse` with `webhookAcceptedResponse(Request $request)` and `webhookRejectedResponse(Request $request)`.
- Both typed to `Symfony\Component\HttpFoundation\Response` — **not** `Illuminate\Http\Response`, which `JsonResponse` does not extend.
- `WebhookController::handle()`'s return type widened to the same Symfony base type.
- Controller success branch returns the driver's response when the contract is implemented, else `response()->noContent(200)` unchanged.
- Controller rejection branch returns the driver's response when implemented, else `abort(401)` unchanged — **after** the existing `$this->logWriter->record([...])` call, so a rejected push is still logged before the early return.
- Opt-in via `instanceof`, mirroring how `ExtractsWebhookReference` is already checked at `WebhookController.php:36`.

**C-3 — per-driver webhook throttle**
- Named limiter `courier-webhook` registered in `CourierServiceProvider::boot()`, keyed on `driver|ip` rather than ip alone.
- `routes/webhook.php` swaps `throttle:60,1` for `throttle:courier-webhook`.
- Rate resolves per driver: `courier.drivers.{driver}.webhook.rate_limit`, falling back to `courier.webhook.rate_limit`, defaulting to `60`.
- `null` at either level disables the limiter for that driver via `Limit::none()`.
- New `courier.webhook` config block.

**Documentation**
- README Webhooks section: document the contract, and correct line 277 ("Requests that fail `verifyWebhook` return `401`"), which becomes conditional.
- README: document `courier.webhook.rate_limit` and the per-driver override.
- CHANGELOG under the unreleased `1.3.0`.

### Out

- **C-4 rejection diagnostics.** Deferred by decision — revisit later. Core cannot compute an expected digest without the carrier secret and signing algorithm, so it needs its own driver-side hook and its own brief.
- **Driver-side implementation in `courier-jt-express`.** Separate composer package, separate git history, separate version. To be checked for compatibility after core lands.
- **A response hook for the `handleWebhook()` throw path.** Stays as today: log `status: failed`, rethrow, 500. No carrier requirement is driving its shape yet, and swallowing the exception risks hiding real driver bugs.
- **Any change to `HandlesWebhooks`.** It stays a two-method interface.
- **Shaping the 429 response.** Throttle middleware runs before the controller, so the driver contract cannot reach it. Known limitation, not a gap.

## Constraints

- **No behaviour change for any driver except J&T.** `sfexpress` and `lalamove` must get no new required interface, no changed response body or status, and no lowered rate ceiling. This is the governing constraint on every design decision below.
- PHP `^8.1`; `illuminate/*` `^10.0|^11.0|^12.0|^13.0`. Any API used must exist across that whole range.
- PHPUnit `^10.0|^11.0` with `orchestra/testbench`. Existing suite (`tests/WebhookTest.php` and 27 sibling files) must stay green.
- Additive and backward-compatible — lands in the unreleased `1.3.0`, which already carries one documented breaking change (`getDeliveryModes()`); this work must not add a second.
- Core repo `laraditz/courier` only. No edits to sibling packages.

## Open Questions Resolved

| Question | Answer |
|---|---|
| Which handover items are in scope? | C-1, C-2 and C-3. C-4 deferred after discussion — revisit later. |
| C-3 raises the ceiling for every driver. How do we avoid that? | Per-driver limiter key with global default held at today's **60**, plus an optional `courier.drivers.{driver}.webhook.rate_limit` override. Existing drivers keep their exact ceiling and simply stop sharing it; J&T opts into headroom in its own config block. |
| Should the contract cover `handleWebhook()` throwing? | No. Rethrow → 500 stays. Correct HTTP semantics, and the one path where a carrier retry is genuinely desirable. Additive as a third method later if J&T's audit asks for it. |
| What type should the contract return? | `Symfony\Component\HttpFoundation\Response`. `Illuminate\Http\JsonResponse` is a *sibling* of `Illuminate\Http\Response`, not a subclass — the handover's suggested hint would have locked drivers out of `response()->json()`, and `handle()`'s existing `: Response` hint makes that a latent `TypeError`. Widening a return type is covariance-safe for any subclass override. |
| Does this cross into `courier-jt-express`? | No. Core only. Verified `grep -rn "ProvidesWebhookResponse\|webhookAcceptedResponse\|webhookRejectedResponse" src/` in that package returns nothing, so core is free to name the contract and there is no existing driver signature to reconcile. Compatibility check to follow after core lands. |
| Can the rate limit be switched off? | Yes — `null` disables it via `Limit::none()`, matching the `null = disabled` idiom already used by `courier.logging.retention_days` one block up in the same config file. The integer always means a ceiling, never a sentinel. |
