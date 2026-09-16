# Group 4: Log Retention & Pruning

**Branch:** feature/courier-log-retention
**Status:** done
**Parent plan:** 2026-07-22-api-webhook-audit-logging-plan.md

## Tasks

### Task G4-1 — Prune `courier_api_logs` (FR-18)
- **What:** `src/Console/Commands/PruneCourierLogsCommand.php` registered as `courier:prune-logs`; deletes `CourierApiLog` rows where `created_at < now()->subDays(config('courier.logging.retention_days'))`, using `chunkById`/`each`-style chunked deletes to avoid loading the whole table.
- **Test first:** Seed rows both older and newer than the retention window; run the command; assert only the old rows are deleted.
- **Agent:** iris
- **Subagent:** no
- **Est:** 5 min

### Task G4-2 — Prune `courier_webhook_logs` (FR-18)
- **What:** Extend the same command to apply the identical chunked-delete logic to `CourierWebhookLog`.
- **Test first:** Same seed/run/assert pattern against `courier_webhook_logs`.
- **Agent:** iris
- **Subagent:** no
- **Est:** 4 min

### Task G4-3 — No-op when `retention_days` is `null` (FR-18 edge case)
- **What:** If `config('courier.logging.retention_days')` is `null`, the command exits early with a console info message and deletes nothing from either table.
- **Test first:** Set `retention_days=null`, seed old rows in both tables, run the command; assert zero rows deleted and the expected console output.
- **Agent:** iris
- **Subagent:** no
- **Est:** 3 min
