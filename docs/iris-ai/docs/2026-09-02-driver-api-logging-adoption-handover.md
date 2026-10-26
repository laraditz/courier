# Handover: Adopting `CourierHttpClient` for API Logging in the Driver Packages

**Depends on:** `laraditz/courier` v1.2.0 (API/webhook logging) — see [spec](../specs/2026-07-22-api-webhook-audit-logging-spec.md) and [plan](../plans/2026-07-22-api-webhook-audit-logging-plan.md).

**Related handover:** [Driver Package Updates for On-Demand Capabilities (v1.3.0)](2026-08-27-on-demand-driver-capabilities-handover.md) — Part 1 of that document (`getDeliveryModes()`) is already done in all three packages; verified present at `LalamoveDriver.php:163`, `JtExpressDriver.php:144`, `SfExpressDriver.php:123`.

---

## Status (as of 2026-09-02)

| Item | State |
|---|---|
| C-1 `asForm()` | ✅ done in core |
| C-2 `timeout()` | ✅ done in core |
| C-3 / D1 SF plaintext logging | ⏸️ **D1-c chosen** — SF deferred, decision still open |
| C-4 redact keys | ✅ five keys added to `config/courier.php` |
| C-5 core docs | ✅ README documents `CourierHttpClient` for driver authors; CHANGELOG updated |
| `courier-lalamove` | ✅ done (merged `feature/lalamove-api-logging`, 120 tests green) |
| `courier-jt-express` | ✅ done (47 tests green, 9 new logging tests) |
| `courier-sfexpress` | ❌ **not started** — blocked on D1 |
| D2 item 2: `Redactor` JSON-in-string | ❌ not started (was already out of scope) |

`courier_api_logs` is now populated by two of the three drivers. **SF Express remains the one known gap** — it still calls the `Http` facade directly and writes no rows. Resolve D1 (see C-3) before picking it up.

---

## Why this exists

`courier_api_logs` is currently **written by nothing at all**. The table, `ApiLogWriter`, `CourierApiLog`, the redactor and the prune command all ship and are fully tested, but the only code path that creates a row is `CourierHttpClient::log()` (`src/Http/CourierHttpClient.php:81`), and no driver package uses `CourierHttpClient`.

Verified — `grep -rn "CourierHttpClient\|forLog" src/` returns zero matches in all three driver packages. Every one of them calls the `Http` facade directly:

| Package | Call site | Current call |
|---|---|---|
| `courier-lalamove` | `src/Http/LalamoveClient.php:87` | `Http::withHeaders(...)->withBody($json)->post(...)` |
| `courier-lalamove` | `src/Http/LalamoveClient.php:97` | `Http::withHeaders(...)->withBody($json)->patch(...)` |
| `courier-lalamove` | `src/Http/LalamoveClient.php:106` | `Http::withHeaders(...)->get(...)` |
| `courier-lalamove` | `src/Http/LalamoveClient.php:114` | `Http::withHeaders(...)->delete(...)` |
| `courier-sfexpress` | `src/Http/SfExpressClient.php:31` | `Http::timeout(...)->withHeaders(...)->post(...)` |
| `courier-sfexpress` | `src/Http/SfExpressClient.php:89` | `Http::timeout(...)->get(...)` (token) |
| `courier-sfexpress` | `src/Mappers/LabelMapper.php:17` | `Http::get($url)` (label PDF download) |
| `courier-jt-express` | `src/Http/JtExpressClient.php:25` | `Http::asForm()->timeout(...)->withHeaders(...)->post(...)` |

This is not a regression — the v1.2.0 spec scoped it that way ("a new `CourierHttpClient` wrapper that all **future** drivers build on", spec line 4) and migrating the existing drivers was never listed. This document is that follow-up.

Webhook logging is unaffected and already works: `WebhookController` writes `courier_webhook_logs` directly. That asymmetry (webhook logs populated, API logs empty) is the quickest way to confirm the diagnosis in a live app.

---

## Part 0: Core changes required first (blocking)

`CourierHttpClient` in its current shape cannot carry all three drivers. Two of these are hard blockers; do them in `laraditz/courier` before touching any driver package.

### C-1. `asForm()` support — **blocks `courier-jt-express`**

`JtExpressClient` posts form-encoded (`Http::asForm()`, `JtExpressClient.php:25`) with a single `bizContent` field. `CourierHttpClient::send()` (line 66) does `Http::withHeaders($headers)->{$method}($url, $data)`, which Laravel sends as **JSON**. Switching J&T over without this would silently change the wire format and every request would fail.

Suggested addition to `src/Http/CourierHttpClient.php`:

```php
    private string $bodyFormat = 'json';

    public function asForm(): static
    {
        $this->bodyFormat = 'form';

        return $this;
    }
```

and in `send()`, build the request before dispatching:

```php
        $request = Http::withHeaders($headers);

        if ($this->bodyFormat === 'form') {
            $request = $request->asForm();
        }

        if ($this->timeout !== null) {
            $request = $request->timeout($this->timeout);
        }

        $response = $request->{$method}($url, $data);
```

### C-2. `timeout()` support — recommended, not blocking

Both `SfExpressClient` and `JtExpressClient` apply `->timeout($this->config['timeout'] ?? 30)`. Laravel's `PendingRequest` default is already `timeout => 30` (`vendor/laravel/framework/src/Illuminate/Http/Client/PendingRequest.php:269`), so dropping the call is behaviourally identical **for the shipped configs**, which both default `timeout` to 30. The only regression is that a host app overriding `courier.drivers.*.timeout` would be silently ignored. Add a `timeout(int|float $seconds): static` setter alongside C-1 rather than leaving a config key that does nothing.

### C-3. Decision needed: SF Express bodies are encrypted (**D1**)

`SfExpressClient::dispatch()` AES-encrypts the business payload before sending and decrypts the response:

- request body on the wire is `['encrypt' => '<ciphertext>']`
- response `apiResultData` is ciphertext; the useful `$inner` array only exists after `$this->encryptor->decrypt(...)`

`CourierHttpClient` logs exactly what it sends and receives, so SF Express rows would be **audit-useless** — a ciphertext blob in `request_body` and `response_body`, with only `driver`, `action`, `status_code`, `duration_ms` and `successful` being readable.

Three options, pick one before implementing SF:

- **D1-a (recommended):** add an explicit opt-in to core, e.g. `withLoggedBody(array $plaintext)` / `withLoggedResponse(array $plaintext)`, that overrides only what gets *logged*, never what gets sent. Keeps the audit trail meaningful and stays consistent with the spec's "explicit context passing" philosophy (spec line 134).
- **D1-b:** accept ciphertext logs for SF. Cheapest, but `courier_api_logs` then can't answer "what did we actually send to SF" — which is the main reason the table exists.
- **D1-c:** skip SF Express entirely for now, do Lalamove + J&T.

**Note on D1-a:** logging SF plaintext puts full sender/recipient addresses and phone numbers into `courier_api_logs`, which the transport encryption was previously hiding at rest. That's the same exposure Lalamove and J&T already have (their bodies are plaintext on the wire), so it's consistent — but it is a deliberate PII decision, not a neutral one. `courier.logging.retention_days` (default 90) plus `courier:prune-logs` is the existing mitigation.

### C-4. Redaction gaps (**D2**)

`ApiLogWriter::record()` redacts `request_headers`, `request_body`, `response_headers`, `response_body` via `Redactor`, which matches on **exact lowercased key equality** against `courier.logging.redact`. The shipped list is `authorization, api_key, apikey, key, secret, token, password`. Against these three drivers:

| Credential | Where | Redacted today? |
|---|---|---|
| Lalamove `Authorization: hmac <key>:<ts>:<sig>` | header | ✅ `authorization` matches |
| SF Express `appSecret` | token-call query → logged as `request_body` | ❌ `appsecret` ≠ `secret` |
| SF Express `appKey` | token-call query + `appKey` header | ❌ `appkey` ≠ `apikey` |
| SF Express `token` | header | ✅ matches |
| SF Express `signature` | header | ❌ not in list |
| J&T `apiAccount` | header | ❌ not in list |
| J&T `digest` | header | ❌ not in list |
| J&T hashed `password` | inside the `bizContent` **JSON string** | ❌ see below |

Two distinct problems:

1. **Missing keys.** Add `appkey`, `appsecret`, `signature`, `digest`, `apiaccount` to the default `courier.logging.redact` in `config/courier.php`. Cheap, and it must land before SF's token call is ever logged — `appSecret` in plaintext in a 90-day-retained table is the single worst outcome of this whole change.

2. **`Redactor` does not descend into JSON held in a string value.** `Redactor::redact()` json-decodes only the *top-level* value; inside `redactArray()` a string value is left untouched (`src/Support/Redactor.php:38-40` — it only recurses when `is_array($value)`). So J&T's `['bizContent' => '{"password":"<hash>",...}']` keeps the hashed password verbatim. It's a hash, not the raw password, so severity is moderate — but the limitation is general and will bite any driver that nests JSON in a field. Either accept it and document it, or extend `Redactor` to attempt a decode on string values. **Extending `Redactor` is a core behaviour change with its own tests — treat it as a separate task, not part of this handover.**

> One thing SF's token call gets right by accident: `CourierHttpClient::log()` records the bare `$url` argument, and the query array is passed separately as `$data`. So the secret lands in `request_body` (redactable) rather than in the `url` column — which is **never** redacted (`url` is a plain `text` column, migration line 18, and `ApiLogWriter` doesn't touch it). **Never put a credential in a `$url` string** handed to `CourierHttpClient`; it will be stored verbatim.

### C-5. Core docs

Per the project convention, C-1/C-2 change `CourierHttpClient`'s public API, so `README.md` needs the new `asForm()`/`timeout()` methods documented alongside `forLog()` — not just a `CHANGELOG.md` entry. C-4's config change belongs in both.

---

## Part 1: The shared wiring pattern

Same shape in all three packages. Two moving parts: get a `CourierHttpClient` into the client class, and give each request a `forLog()` context.

### Instantiation and the stale-context footgun

Add an injectable `CourierHttpClient` to each client's constructor so tests can supply one with a fake `ApiLogWriter`:

```php
public function __construct(private array $config, ?CourierHttpClient $http = null)
{
    $this->http = $http ?? new CourierHttpClient();
}
```

`CourierHttpClient` is **not** container-bound (`CourierServiceProvider` registers only `courier`/`CourierManager`), so `new` is correct here.

⚠️ **`forLog()` mutates `$this` and returns `$this`** — it is not immutable, and `$this->configured` stays `true` once set. A long-lived instance therefore passes the `guard()` on every later call, so a request that *forgets* `forLog()` won't throw — it will log silently against the **previous** call's `action`/`reference`/`waybill_number`. The guard only protects the very first call.

Two safe patterns; prefer the first:

- **Call `forLog()` on every single request**, immediately before the verb — never rely on inherited context.
- Or construct a fresh `CourierHttpClient` per request, so the guard stays meaningful.

Do not hold a `forLog()`-configured instance as state between calls.

### Choosing `action`

Keep `action` stable and greppable; it is a `string(100)`, indexed only via `driver`. Use one value per logical API operation, and reuse the identifier the client already has:

- **Lalamove:** one per named method — `create_quotation`, `get_quotation`, `create_order`, `get_order`, `cancel_order`, `get_cities`, `remove_driver`, `add_priority_fee`, `get_driver_location`, `edit_order`, `set_webhook_url`.
- **SF Express:** the `$msgType` argument already is the operation (`IUOP_OS_CREATE_ORDER`, `IUOP_OS_QUERY_TRACK`, `IUOP_OS_PRINT_ORDER`, `IUOP_OS_CANCEL_ORDER`). Pass it straight through. Add `get_access_token` for the token call and `download_label` for the mapper's PDF fetch.
- **J&T Express:** the `$path` argument already is the operation (`order/addOrder`, `order/getOrders`, `logistics/trace`, `order/cancelOrder`, `order/printOrder`). Pass it straight through.

### Plumbing `reference` / `waybill_number`

This is the only part needing changes above the client layer. Both SF and J&T funnel everything through a single `dispatch()` that has no idea what the reference is. Follow the spec's Option A (explicit context passing, spec line 134) — add optional trailing parameters rather than a fluent builder:

```php
public function dispatch(
    string $msgType,
    array $body,
    ?string $reference = null,
    ?string $waybillNumber = null,
): array
```

Existing call sites keep working unchanged (both new params default to `null`), so this can land as a pure-addition commit before the call sites are updated.

---

## Part 2: Per-package work

### `courier-lalamove`

The cleanest of the three: bodies are plaintext, one client, four transport methods.

**The one thing that must not break: the HMAC signature.** `LalamoveClient::headers()` signs `"{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}"` where `$body` is the exact string `json_encode(['data' => $body], JSON_THROW_ON_ERROR)`, and `post()`/`patch()` send that same string via `withBody()`. `CourierHttpClient` takes an **array** and lets Laravel/Guzzle encode it.

**Verified safe:** Guzzle encodes the `json` option with `Utils::jsonEncode($value)` → `json_encode($value, 0, 512)` (`vendor/guzzlehttp/guzzle/src/Utils.php:805`, applied at `vendor/guzzlehttp/guzzle/src/Client.php:1120`). `JSON_THROW_ON_ERROR` changes error handling only, not output, so the two encodings are byte-identical and the signature still verifies. Keep computing the string locally for `headers()`, and pass the array to `CourierHttpClient`:

```php
private function post(string $path, array $body): array
{
    $payload = ['data' => $body];
    $json    = json_encode($payload, JSON_THROW_ON_ERROR);   // signature input only

    $response = $this->http
        ->forLog('lalamove', $this->action, waybillNumber: $this->waybillNumber)
        ->post($this->baseUrl() . $path, $payload, $this->headers('POST', $path, $json));

    return $this->handleResponse($response);
}
```

**Pin this with a test.** `Http::fake()`, capture the outgoing request, recompute the HMAC from the *actual sent body* and assert it matches the `Authorization` header. If this assumption ever breaks, every Lalamove call returns 401 and the cause is deeply non-obvious. This is the highest-value test in the whole handover.

Per-method context — pass `action` and `waybillNumber` down from the named methods to the private transport methods (add parameters; don't stash them on `$this`, same stale-state reason as above):

| Named method | `action` | `waybillNumber` |
|---|---|---|
| `createQuotation()` | `create_quotation` | — |
| `getQuotation()` | `get_quotation` | — |
| `createOrder()` | `create_order` | — (order id only exists in the response) |
| `getOrder()` | `get_order` | `$orderId` |
| `cancelOrder()` | `cancel_order` | `$orderId` |
| `getCities()` | `get_cities` | — |
| `removeDriver()` | `remove_driver` | `$orderId` |
| `addPriorityFee()` | `add_priority_fee` | `$orderId` |
| `getDriverLocation()` | `get_driver_location` | `$orderId` |
| `editOrder()` | `edit_order` | `$orderId` |
| `setWebhookUrl()` | `set_webhook_url` | — |

Note `createOrder` can't set `waybill_number` — Lalamove's `orderId` is assigned in the response. So `createShipment()` produces two rows (`create_quotation`, `create_order`) with a null `waybill_number`. If you want them findable, `ShipmentPayload::$reference` is available at the driver level and can be threaded down as `reference`; `LalamoveDriver::createShipment()` currently ignores it entirely. Worth doing — otherwise the most important call in the package is the hardest one to find in the log.

`delete()` returns no body (`handleResponse(..., expectBody: false)`); `CourierHttpClient` logs it fine, `response_body` just ends up empty.

### `courier-jt-express`

Blocked on **C-1** (`asForm()`); do nothing here until that lands.

Single `dispatch(string $path, array $bizContent)`. The `$path` is the `action`. Add the two optional trailing params from Part 1 and update the five call sites in `src/JtExpressDriver.php`:

| Line | Call | `action` | `reference` | `waybillNumber` |
|---|---|---|---|---|
| 43 | `order/addOrder` | `order/addOrder` | `$reference` (local var, line 41) | — |
| 74 | `order/getOrders` | `order/getOrders` | `$reference` (param) | — |
| 84 | `logistics/trace` | `logistics/trace` | — | `$trackingNumber` |
| 112 | `order/cancelOrder` | `order/cancelOrder` | `$reference` | `$waybillNumber` |
| 129 | `order/printOrder` | `order/printOrder` | `$reference` | `$waybillNumber` |

J&T is the best-instrumented of the three once done — `txlogisticId` is the caller's own reference and it's available at every call site, including the two that require it explicitly (`cancelShipment()`/`getLabel()` both throw `InvalidPayloadException` without it).

Keep `json_encode($bizContent, JSON_UNESCAPED_UNICODE)` exactly as-is: that string is both the `digest` input and the form field value, and form encoding transports it verbatim, so there is no encoding-mismatch risk here (unlike Lalamove).

The logged `request_body` will be `['bizContent' => '<json string>']` — readable, but see **D2** about the hashed password inside it.

### `courier-sfexpress`

Blocked on **D1** (see C-3). Largest of the three, and the only one where the audit value is in question.

Three separate call sites, and they are *not* equivalent:

1. **`dispatch()` (`SfExpressClient.php:31`)** — the main one. `action` = `$msgType`. Needs the Part 1 trailing params; update the four call sites in `src/SfExpressDriver.php`:

   | Line | `$msgType` | `reference` | `waybillNumber` |
   |---|---|---|---|
   | 39 | `IUOP_OS_CREATE_ORDER` | `$payload->reference` (currently ignored) | — |
   | 76 | `IUOP_OS_QUERY_TRACK` | — | `$trackingNumber` |
   | 94 | `IUOP_OS_PRINT_ORDER` | `$reference` (param, nullable) | `$waybillNumber` |
   | 105 | `IUOP_OS_CANCEL_ORDER` | `$reference` (param, nullable) | `$waybillNumber` |

   Like Lalamove, `SfExpressDriver::createShipment()` ignores `$payload->reference` today — thread it through so the create call is findable.

2. **`getAccessToken()` (`SfExpressClient.php:89`)** — `action` = `get_access_token`. **Do not log this until C-4's redact keys land.** It's a `GET` with `appKey` and `appSecret` in the query array. Also note the token is memoised per client instance (`$this->accessToken`), so you'll see at most one such row per instance — do not be surprised by the low count.

3. **`LabelMapper::map()` (`src/Mappers/LabelMapper.php:17`)** — `Http::get($url)` downloading a **PDF**. `action` = `download_label`, `waybillNumber` = `$waybillNumber` (already a parameter). ⚠️ **This one needs thought before you wire it:** `CourierHttpClient` logs `$response->body()` unconditionally, so a raw PDF blob goes through `Redactor::redact()`, fails to json-decode, and gets stored as `['_raw' => '<binary PDF>']` in a `json` column. Expect encoding errors or a bloated table. Either leave this call on the raw `Http` facade (simplest, and it's an asset fetch rather than a courier API call), or resolve it properly under the "max body size / truncation" item the v1.2.0 spec explicitly put **out of scope**. Recommendation: **leave `LabelMapper` alone** and note why in a code comment.

   Architecturally this call is also misplaced — a mapper doing HTTP I/O. Moving it into the client is the right fix, but it is a refactor with its own risk and is **out of scope here**.

---

## Part 3: Test-suite setup (all three packages)

None of the three `tests/TestCase.php` files set up a database — no `defineDatabaseMigrations()`, no `RefreshDatabase`. Combined with `ApiLogWriter::record()`'s `catch (Throwable)` swallow (`src/Logging/ApiLogWriter.php:22-26`), this produces the worst possible outcome: **existing driver tests will keep passing while the log write silently fails** on a missing table, with only a `Log::error` nobody reads. Green tests would prove nothing.

Copy the core package's pattern (`courier/tests/TestCase.php`) into each driver package's `TestCase`, pointing at core's migrations through the symlinked path repo (`vendor/laraditz/courier` → `../courier`, verified):

```php
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laraditz/courier/database/migrations');
    }
```

Then per package add at least:

- one test asserting exactly **one** `CourierApiLog` row per request, with the right `driver`, `action`, `waybill_number`/`reference`, `method`, `status_code` and `successful`;
- one test asserting a **failed** call still logs (`successful = false`) and still throws the driver's own exception — all three clients raise `CourierException` on `$response->failed()`, so the log write has to happen before that throw;
- one test asserting `config(['courier.logging.enabled' => false])` writes **no** row while the HTTP call still happens (FR-07).

For `courier-lalamove`, add the HMAC-stability test described in Part 2 — that one is not optional.

No `composer.json` changes needed in any driver package: all three already declare `"laraditz/courier": "@dev"` against a `path` repository pointing at `../courier`, so they resolve to whatever is checked out. `illuminate/http` is already an explicit `require` of core (`composer.json:16`).

---

## Suggested order

1. **C-4 redact keys** — one config line, zero risk, and it must precede any SF token logging. Do this first regardless of what else gets scheduled.
2. **C-1 `asForm()` + C-2 `timeout()`** in core, with tests and the C-5 README/CHANGELOG updates. Unblocks J&T.
3. **Part 3 test setup** in `courier-lalamove` only — proves the harness actually observes log rows before any driver code changes. Without this you cannot tell success from silent failure.
4. **`courier-lalamove`** — plaintext bodies, no core decisions pending. Land the HMAC test with it.
5. **`courier-jt-express`** — mechanical once C-1 is in; best reference coverage of the three.
6. **Resolve D1**, then **`courier-sfexpress`** — leave `LabelMapper` on the raw facade.

Steps 1–5 are independently shippable. If D1 stalls, stopping after step 5 still leaves two of three drivers logging, which is a strictly better position than today's zero.

---

## Out of scope

- **Extending `Redactor` to descend into JSON-in-a-string** (D2 item 2) — core behaviour change, own spec and tests.
- **Body-size limits / truncation** for large payloads — explicitly out of scope in the v1.2.0 spec, and the reason SF's `LabelMapper` PDF download stays unwired.
- **Moving `SfExpress\Mappers\LabelMapper`'s HTTP call into `SfExpressClient`** — correct, but a separate refactor.
- **Binding `CourierHttpClient` in the container** — drivers `new` it; no need so far.
- **Per-driver logging toggles** — out of scope in the v1.2.0 spec (one global flag only).
- **Async/queued log writes** — same, out of scope in v1.2.0.
- **`getDeliveryModes()` and the capability interfaces** — covered by the [v1.3.0 handover](2026-08-27-on-demand-driver-capabilities-handover.md); Part 1 there is already complete in all three packages.
