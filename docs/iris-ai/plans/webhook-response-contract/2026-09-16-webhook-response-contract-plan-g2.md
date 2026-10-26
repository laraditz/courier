# Group 2: Per-Driver Throttle

**Status:** done
**Parent plan:** 2026-09-16-webhook-response-contract-plan.md

Covers C-3 — FR-12 through FR-20.

## Tasks

### Task 2.1 — Failing test for the default rate
- **What:** New `tests/WebhookRateLimitTest.php`. With no `courier.webhook` and no per-driver config, `WebhookRateLimit::for($request)` returns a `Limit` of 60/min.
- **Test first:** `test_defaults_to_sixty_per_minute_when_unconfigured` (FR-15 step 3). Red — class does not exist.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** test(webhook): assert default per-minute webhook rate [336764d]

### Task 2.2 — Create `WebhookRateLimit`
- **What:** `src/Support/WebhookRateLimit.php` — stateless statics mirroring `Support/Redactor.php`. `DEFAULT_PER_MINUTE = 60`; `for(Request): Limit` resolving per-driver → global → default via `config()->has()` (which uses `array_key_exists`, so an explicit null is distinguishable from an absent key). Numeric `>= 1` → `Limit::perMinute()`.
- **Test first:** 2.1 goes green.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min
- **Status:** done
- **Commit:** feat(webhook): resolve webhook rate limit per driver [8ccee97]

### Task 2.3 — Key shape and per-driver precedence
- **What:** Assert the returned `Limit`'s key is `{driver}|{ip}`, and that `courier.drivers.{driver}.webhook.rate_limit` wins over `courier.webhook.rate_limit`.
- **Test first:** `test_key_is_driver_and_ip` + `test_per_driver_rate_overrides_global` (FR-14, FR-15 step 1).
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** test(webhook): cover rate resolution branches [27c471b]

### Task 2.4 — Null disables; malformed falls back
- **What:** Assert `null` at either level returns `Limit::none()` (an `Unlimited` instance), and that `0`, `-5` and `"abc"` all fall back to 60. The null check must run **before** numeric validation — `null` is non-numeric, and an implementation that validates first would cap a deliberately-disabled driver at 60 instead.
- **Test first:** `test_null_disables_the_limiter` + `test_malformed_rate_falls_back_to_default` (FR-16, FR-18).
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min
- **Status:** done
- **Commit:** test(webhook): cover rate resolution branches [27c471b]

### Task 2.5 — Config block
- **What:** Add `'webhook' => ['rate_limit' => env('COURIER_WEBHOOK_RATE_LIMIT', 60)]` to `config/courier.php` (FR-19).
- **Test first:** package default config resolves `courier.webhook.rate_limit` to 60.
- **Agent:** iris
- **Subagent:** no
- **Est:** 2 min
- **Status:** done
- **Commit:** feat(config): add webhook rate limit block [8278f38]

### Task 2.6 — Register the named limiter
- **What:** `CourierServiceProvider::boot()` registers `RateLimiter::for('courier-webhook', fn (Request $r) => WebhookRateLimit::for($r))` (FR-12).
- **Test first:** `test_courier_webhook_limiter_is_registered` — `RateLimiter::limiter('courier-webhook')` is not null.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** feat(webhook): register courier-webhook rate limiter [e8fcfde]

### Task 2.7 — Swap the route middleware
- **What:** `routes/webhook.php` — `throttle:60,1` becomes `throttle:courier-webhook`. Route name `courier.webhook` and URI unchanged (FR-13). **Must run after 2.6.**
- **Test first:** full suite stays green; route name and URI assertions unchanged.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** feat(webhook): key the throttle per driver [794f431]

### Task 2.8 — Verify test cache isolation
- **What:** Confirm throttle counters do not leak between tests under testbench's cache store. If they do, add an explicit reset in the throttle test's `setUp()`. Without this, 2.9–2.11 become order-dependent and fail intermittently in a way that reads like an implementation bug.
- **Test first:** two sequential requests in separate tests do not share a bucket.
- **Outcome:** testbench uses the `array` cache store with a fresh app per test — verified by a deliberate pair of ceiling-of-1 tests. No reset code needed.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** test(webhook): cover per-driver throttle behaviour [77db855]

### Task 2.9 — 429 at the configured ceiling
- **What:** With `courier.webhook.rate_limit` lowered (per NFR-05 — not by firing 61 requests), assert the request past the ceiling returns 429.
- **Test first:** `test_requests_past_the_ceiling_are_throttled`.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** test(webhook): cover per-driver throttle behaviour [77db855]

### Task 2.10 — Per-driver bucket isolation
- **What:** With a global ceiling of 1, exhaust driver A, then assert driver B still returns 200 from the same IP. This is the C-3 fix made observable.
- **Test first:** `test_drivers_do_not_share_a_throttle_bucket` (FR-14).
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** test(webhook): cover per-driver throttle behaviour [77db855]

### Task 2.11 — Stale published config
- **What:** Simulate an app whose published `config/courier.php` predates this change by unsetting `courier.webhook` entirely; assert the ceiling resolves to 60. `mergeConfigFrom()` merges shallowly at the top level, so this is the real upgrade path for existing installs (FR-20).
- **Test first:** `test_missing_webhook_config_block_falls_back_to_default`.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** test(webhook): cover per-driver throttle behaviour [77db855]
