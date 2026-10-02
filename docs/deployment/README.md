# Operations → Activities deployment kit

Local preparation only. Start with [readiness and blockers](activities-production-readiness.md); do not deploy merely because these files exist.

| Artifact | Purpose |
|---|---|
| [Readiness](activities-production-readiness.md) | Scope, code findings, production gates, snapshot caveats |
| [Runbook](activities-production-runbook.md) | Git integration, server preflight, backups, ordered migration/release/build/cache plan |
| [Integrations](activities-integrations.md) | Gmail/Google Cloud and Messenger/Meta checklists, actual keys/defaults, flag rollout |
| [Processes](activities-processes.md) | Exact queues/jobs/schedules, worker commands, cron and boot persistence |
| [Rollback](activities-production-rollback.md) | Application/database/provider/worker recovery and destructive rollback warnings |
| [Tests and smoke](activities-test-and-smoke-plan.md) | Actual test suites, isolation, pilot A/B and full-module smoke checks, stop conditions |
| `activities-deployment-manifest.csv` | Exact grouped source/dependency path manifest with hashes and deployment classifications |
| `activities-migration-inventory.csv` | All matched Activities migrations plus explicit shared prerequisites; pending state unknown |
| `activities-environment-keys.csv` | Full traced env dependency keys; no real .env values |
| `activities-do-not-deploy.csv` | Exact local backup/generated/runtime path exclusion inventory; no file contents |
| `activities-test-matrix.csv` | Actual existing test paths and titles, not a passing-test claim |
| `inventory-summary.json` | Counts, local HEAD and snapshot caveats |
| [Read-only SQL](production-schema-readonly.sql) | MySQL metadata, staged gated count/duplicate checks and expected migration history |
| `supervisor-activities.conf.example` | Linux Supervisor example for four worker pools, no credentials |
| `tools/generate-activities-inventories.mjs` | Reproducible local source/path inventory + SQL generator |
| `tools/validate-activities-kit.mjs` | Local static kit/SQL/hash/secret-pattern validator |
| `validation-results.json` | Actual local preparation validation results and explicit unexecuted checks |

## Read-only SQL execution later

Use a SELECT-only account with permission to inspect its own schema metadata. Stage 1 lists metadata and approximate InnoDB counts; it selects no email bodies, tokens or credentials. Stage 2 emits `sql_to_run` strings only for existing tables/columns. Copy/run those **SELECT-only** statements in the same read-only session to obtain exact row counts/duplicates/orphans. This two-pass approach handles older production schemas without dynamic PREPARE/EXECUTE or DDL. Stage 3 reads `migrations` directly only after Stage 1 confirmed it exists; absence is a STOP condition. Compare all results with the inventory and source; do not assume an unrecorded row is safe to apply. Exact count scans can be expensive: coordinate with the DBA and use off-peak/read-replica metadata where accurate for the deployment target.

No SQL was run against a database in preparation. No PHP helper is added; only local Node tooling. No commit, push, remote change, provider setup or process start was performed.
