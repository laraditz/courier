# Group 3: Documentation & Verification

**Status:** done
**Parent plan:** 2026-09-16-webhook-response-contract-plan.md

Covers FR-21 through FR-24. Runs after Groups 1 and 2.

## Tasks

### Task 3.1 — README: the response contract
- **What:** Add a `ProvidesWebhookResponse` subsection to the Webhooks section, after the `ExtractsWebhookReference` subsection, with a worked JSON-ack example. Correct the existing sentence "Requests that fail `verifyWebhook` return `401`" — 401 is now the default, and a driver implementing the contract shapes its own rejection (FR-21, FR-22).
- **Test first:** n/a — documentation. Verified by reading the rendered section against the contract signature.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min
- **Status:** done
- **Commit:** docs: document webhook response contract and throttle [b6a7053]

### Task 3.2 — README: webhook rate limiting
- **What:** Document `courier.webhook.rate_limit`, the per-driver override at `courier.drivers.{driver}.webhook.rate_limit`, `null` to disable, and that the limiter keys on driver + IP rather than IP alone (FR-23).
- **Test first:** n/a — documentation.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** docs: document webhook response contract and throttle [b6a7053]

### Task 3.3 — CHANGELOG
- **What:** Under the unreleased `1.3.0`: `ProvidesWebhookResponse` under **Added**, the per-driver throttle under **Changed**. Note explicitly that both are backward-compatible — 1.3.0 already carries one breaking change (`getDeliveryModes()`) and must not appear to carry a second (FR-24).
- **Test first:** n/a — documentation.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
- **Status:** done
- **Commit:** docs: document webhook response contract and throttle [b6a7053]

### Task 3.4 — Full verification
- **What:** Run the complete suite. Confirm all 111 baseline tests are unmodified and green (NFR-04), and review the diff against the spec: no change to `HandlesWebhooks`, `WebhookLogWriter`, `Redactor`, the log schema, or `courier.logging`; no new composer requirement (NFR-03).
- **Test first:** n/a — verification gate. Output quoted, not summarised.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min
- **Status:** done
- **Commit:** docs: document webhook response contract and throttle [b6a7053]
