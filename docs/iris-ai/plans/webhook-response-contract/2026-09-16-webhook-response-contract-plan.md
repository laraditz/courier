# Plan: Webhook Response Contract & Per-Driver Throttle

**Spec:** [2026-09-16-webhook-response-contract-spec.md](../../specs/2026-09-16-webhook-response-contract-spec.md)
**Branch:** feature/webhook-response-contract
**Date:** 2026-09-16

## Groups

| # | Group | Status | File |
|---|---|---|---|
| 1 | Response Contract | done | [2026-09-16-webhook-response-contract-plan-g1.md](2026-09-16-webhook-response-contract-plan-g1.md) |
| 2 | Per-Driver Throttle | done | [2026-09-16-webhook-response-contract-plan-g2.md](2026-09-16-webhook-response-contract-plan-g2.md) |
| 3 | Documentation & Verification | done | [2026-09-16-webhook-response-contract-plan-g3.md](2026-09-16-webhook-response-contract-plan-g3.md) |

## Self-Review Findings

- **Contradictions:** none. One ordering hazard recorded in Sequencing Notes.
- **Gaps:** FR-08 (path isolation) had no driver capable of recording which contract method ran — resolved by building the spy on the G1.2 fixture.
- **Loopholes:** none. All 24 FRs map to at least one task; no task exists without an FR behind it.
- **Feasibility:** confirmed, with one caveat — `RateLimiter` counters live in the cache, so test isolation is verified in G2.8 before any test depends on them.
- **Best practices:** compliant. DRY resolved via a single configurable test fixture instead of a fifth copy of the `CourierDriver` stub boilerplate.

## Refinements Made

- Added **G1.2** fixture (`tests/Fixtures/ConfigurableWebhookDriver`) — `tests/WebhookTest.php` already repeats 8 `CourierDriver` stub methods across 4 anonymous classes; the contract tests would have repeated it another 4 times. One closure-configured fixture covers every new variant, and existing tests stay unmodified so NFR-04 holds.
- Added **G2.8** cache-isolation check — leaked throttle counters would surface as intermittent, order-dependent failures that read like implementation bugs.
- Recorded the **G2.6 → G2.7** ordering constraint explicitly so it survives reordering.

## Sequencing Notes

- Groups 1 and 2 are independent and may run in either order. Group 3 documents both and runs last.
- **G2.6 must precede G2.7.** Swapping the route to `throttle:courier-webhook` before the named limiter is registered throws `MissingRateLimiterException` on all 111 existing tests (verified in `ThrottleRequests::resolveMaxAttempts()`).
- **G2.8 must precede G2.9–G2.11.** Cache isolation is confirmed before any test depends on throttle counters.
- Within Group 1, tasks 1.1→1.3 and 1.4→1.5 are strict red-green pairs and must not be reordered or merged.
