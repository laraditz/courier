# Plan: Courier API & Webhook Audit Logging

**Spec:** [docs/iris-ai/specs/2026-07-22-api-webhook-audit-logging-spec.md](../specs/2026-07-22-api-webhook-audit-logging-spec.md)
**Date:** 2026-07-22

## Groups

| # | Group | Branch | Status | File |
|---|---|---|---|---|
| 1 | Foundation (config, migrations, redaction) | `feature/courier-logging-foundation` | done | [g1](2026-07-22-api-webhook-audit-logging-plan-g1.md) |
| 2 | Outgoing API Call Logging | `feature/courier-api-call-logging` | done | [g2](2026-07-22-api-webhook-audit-logging-plan-g2.md) |
| 3 | Webhook Logging | `feature/courier-webhook-logging` | done | [g3](2026-07-22-api-webhook-audit-logging-plan-g3.md) |
| 4 | Log Retention & Pruning | `feature/courier-log-retention` | done | [g4](2026-07-22-api-webhook-audit-logging-plan-g4.md) |

## Sequencing Notes
- Group 1 is a hard prerequisite for Groups 2 and 3 (shared `logging` config, both migrations, shared `Redactor` utility).
- Groups 2 and 3 are independent of each other — disjoint files (`CourierHttpClient` vs `WebhookController`), can be worked in either order or in parallel.
- Group 4 depends on both Group 2 (`CourierApiLog`) and Group 3 (`CourierWebhookLog`) since it prunes both models — must run last.
