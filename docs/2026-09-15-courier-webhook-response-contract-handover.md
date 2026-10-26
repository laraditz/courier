# Handover: Webhook Response Contract & Rejection Diagnostics in `laraditz/courier`

**Target repo:** `laraditz/courier` (core). Nothing in this document is fixable from a driver package.
**Version in vendor at time of writing:** 1.3.0 (unreleased — see its `CHANGELOG.md`).
**Raised by:** J&T Express Malaysia callback compliance audit against
[Tracking Info Callback](https://ylopen.jtexpress.my/apiDoc/logistics/statusFeedback)
(local scrape: `courier-jt-express/docs/j&t-api/7 Tracking Info Callback.md`).

**Related handover:** [Adopting `CourierHttpClient` for API Logging](2026-09-02-driver-api-logging-adoption-handover.md).

---

## Why this exists

`WebhookController` hardcodes both of its responses. A courier that inspects the response
body — J&T Express does — cannot be satisfied by any driver, because `HandlesWebhooks`
gives a driver no way to shape what goes back on the wire:

```php
interface HandlesWebhooks
{
    public function verifyWebhook(Request $request): bool;   // bool
    public function handleWebhook(Request $request): void;   // void
}
```

Verified — `grep -rn "Response" src/Contracts/` returns zero matches. There is no
response hook of any kind.

This matters beyond J&T. Any carrier with an ack-body contract (most Chinese-origin
logistics APIs, and several SEA carriers) hits the same wall.

---

## C-1. Success response has no body — blocking for J&T

`src/Http/Controllers/WebhookController.php:86`

```php
return response()->noContent(200);   // HTTP 200, zero-length body
```

J&T mandates all four fields, and its pusher parses the body for `code == "1"`:

```json
{"code":"1","msg":"success","data":"SUCCESS","requestId":"211212121212"}
```

An empty body is a parse failure on their side. Every successful delivery is recorded by
J&T as a **failed push** — which means retries (duplicate domain events for us) and
eventual suspension of the callback account. We currently look broken to J&T on 100% of
the pushes we actually handled correctly.

## C-2. Rejection response has no body

`src/Http/Controllers/WebhookController.php:54` — `abort(401)` returns Laravel's error
page. J&T documents a coded error table it expects to receive:

| code | msg |
|---|---|
| `145003030` | headers signature verification failed |
| `145003051` | apiAccount is empty! |
| `145003052` | digest is empty! |
| `145003053` | timestamp is empty! |
| `145003050` | Illegal parameters |

Carrier support cannot diagnose a rejection from an HTML 401. This is currently blocking a
live investigation (see C-4).

### Suggested shape for C-1/C-2

A new optional contract, mirroring how `ExtractsWebhookReference` is already opt-in at
`WebhookController.php:36`:

```php
namespace Laraditz\Courier\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

interface ProvidesWebhookResponse
{
    public function webhookAcceptedResponse(Request $request): Response;
    public function webhookRejectedResponse(Request $request): Response;
}
```

In the controller, replacing line 54 and line 86:

```php
// rejection (was: abort(401))
if ($instance instanceof ProvidesWebhookResponse) {
    return $instance->webhookRejectedResponse($request);
}
abort(401);

// success (was: return response()->noContent(200))
return $instance instanceof ProvidesWebhookResponse
    ? $instance->webhookAcceptedResponse($request)
    : response()->noContent(200);
```

Default behaviour is unchanged for every existing driver — this is additive and
backward-compatible. The driver-side implementation stays in `courier-jt-express`.

**Ordering constraint:** the rejection response must still be emitted *after* the
`$this->logWriter->record([...])` call at line 44, so a rejected push is logged before the
early return.

---

## C-3. Webhook throttle is shared across all drivers

`routes/webhook.php`

```php
Route::post('courier/webhook/{driver}', [WebhookController::class, 'handle'])
    ->middleware('throttle:60,1');
```

Verified against `Illuminate\Routing\Middleware\ThrottleRequests::resolveRequestSignature()`
(line 222): for unauthenticated requests the key is
`sha1($route->getDomain().'|'.$request->ip())`. Because the driver is a **route parameter,
not a separate route**, the 60/min ceiling is shared across every courier driver at once,
keyed only by domain and source IP.

A hub sorting run pushes well past 60/min. Overflow gets a 429, which every carrier counts
as a failed push. Two carriers pushing concurrently makes it worse.

Suggested: key the limiter per driver and make the rate configurable —

```php
->middleware('throttle:courier-webhook')
```

with a named limiter registered in the service provider, keyed on
`$request->route('driver').'|'.$request->ip()` and reading its rate from
`config('courier.webhook.rate_limit', 300)`.

---

## C-4. Default redaction makes signature failures undebuggable

`config/courier.php:20-33` redacts `digest` and `apiaccount` by default, and
`WebhookLogWriter::record()` applies that to rejected rows too.

Correct for retention — but it removes the only two fields needed to diagnose a signature
mismatch. A rejected row currently records *that* verification failed and nothing about
*why*: the incoming digest is `[REDACTED]`, so it cannot be compared against a locally
recomputed one.

We are hitting this right now on a live 401, and the only workaround is to temporarily
edit the app's redact list and wait for another push.

Suggested: keep redaction on by default, but record a non-reversible comparison aid on
rejected rows only — e.g. an `error_message` of
`signature mismatch (received sha256:ab12cd…, expected sha256:ef34ab…)` using short hashes
of both digests. That confirms or eliminates "wrong key" without ever persisting the real
credential.

Worth considering alongside: `Redactor::redact()` (`src/Support/Redactor.php:14-22`) wraps
any non-JSON string as `['_raw' => $value]`. For a webhook payload that is a bare form
field this is harmless, but it means a redacted payload's shape differs from
`$request->all()`, which is surprising when reading logs back.

---

## Resolved: the reported 401 was not a defect

Investigated against `courier_webhook_logs` in the `lara12-livewire` app on 2026-09-15.
Recording it here because the diagnosis depended entirely on core's webhook logging, and
because it is the argument for C-4.

Of 9 `jtexpress` rows, the 2 rejected ones carried `user-agent: bruno-runtime/1.40.0` with
no `digest`, `apiaccount` or `timestamp` headers and an empty `bizContent`. They were
manual API-client tests, and `verifyWebhook()` rejected them correctly. All 6 genuine J&T
pushes (`user-agent: Apache-HttpClient/4.5.5`) verified and processed. **Signature
verification is working.**

Two things this surfaced that *are* worth core's attention:

- **It took direct database access to establish that.** From the 401 alone — and from the
  redacted log rows — "our test client is misconfigured" and "our signature logic is
  broken" were indistinguishable. That is precisely the gap C-4 describes.
- **One row (id=25) was logged `verified=1` despite having no `digest` header and a null
  `bizContent`.** Replaying its exact recorded inputs against current code returns `false`,
  so this was a transient from an in-flight local edit during debugging, not a core defect.
  Flagged only so it is not mistaken for evidence of a verification bypass if someone reads
  the table later.

### Supporting (not conclusive) evidence for C-1

Among the 6 genuine pushes, 4 carry the **same `billCode` and the same `scanTypeCode` (10)**,
three of them within 76 seconds of each other (ids 19, 21, 23 at 04:10:51 / 04:11:47 /
04:12:07). That pattern is consistent with J&T retrying because our empty response body
failed its `code == "1"` check — but these are sandbox pushes on a single test waybill and
may equally have been fired manually from J&T's console. Treat it as corroboration, not
proof. Worth re-checking after C-1 lands: if duplicates stop, C-1 was the cause.

---

## Suggested order

| | Item | Severity | Why |
|---|---|---|---|
| 1 | C-1 success response body | **Critical** | J&T integration correctness; likely cause of duplicate pushes |
| 2 | C-2 rejection response body | High | Carrier-side supportability |
| 3 | C-4 diagnostics on rejected rows | Medium | A 401 is currently undiagnosable without DB access |
| 4 | C-3 per-driver throttle key | Medium | Push loss under load |

C-1 and C-2 land together behind one contract, so they are effectively a single change.

---

## Out of scope for this repo

These came out of the same audit but are fixed in `courier-jt-express`, not core:

- ~~`bizContent` shape not normalised~~ — **fixed 2026-09-15.** J&T Malaysia sends a single
  order *object*; the published example shows an *array*. The driver assumed the array and
  silently emitted zero `TrackingUpdated` events for all 6 live pushes while still
  returning 200. Now normalises both shapes.
- ~~`timestamp` / `apiAccount` headers not validated~~ — **fixed 2026-09-15.** Timestamp
  freshness is enforced by default (900s, configurable, 0 disables); apiAccount matching
  ships opt-in because its value is redacted in `courier_webhook_logs` and could not be
  confirmed against live traffic. **This is a direct consequence of C-4** — with the
  diagnostics C-4 describes, that check could have shipped on by default.
- ~~12 of 27 documented `scanTypeCode` values unmapped~~ — **fixed 2026-09-15.** All 27
  now map. The `700`–`704` readings are inferred from a table whose columns do not align
  and are flagged in the README as needing J&T confirmation.
- ~~`ExtractsWebhookReference` not implemented~~ — **fixed 2026-09-15.** Webhook log rows
  now carry `waybill_number`. `reference` stays null because J&T Malaysia does not send
  `txlogisticId` in callbacks.
