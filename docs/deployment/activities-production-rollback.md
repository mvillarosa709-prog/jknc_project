# Activities rollback and incident controls — later execution only

Never execute rollback against production during preparation. The change window owner must approve commands, account for writes after deployment and retain evidence/backups. Disable provider integrations before switching incompatible code; do not automatically replay queues.

## Application rollback

1. Identify the failure and stop new external sends/ingest and affected writes. Pause the single scheduler and drain/stop impacted worker pools using the actual hosting manager. Maintenance mode is conditional on consistency requirements and does not cancel in-flight jobs.
2. Confirm whether the previous release can read the new schema and whether its job serializers/permissions understand newly stored records. Additive schema is usually safer left in place, but compatibility must be tested; do not downgrade across O.B. workflow versions blindly.
3. Atomically restore the verified previous code + built assets. Keep persistent storage, production APP_KEY, current DB and protected environment. Never overwrite storage/environment with local files or an old unreviewed snapshot.
4. Review flags, clear/rebuild config/views and only use route cache if that release was certified. Restart supervised workers on the restored release after queue compatibility is understood. Resume scheduler/traffic and smoke test; keep external flags off until reviewed.

## Database rollback

Default after live use: **forward-fix with integrations disabled**, not `migrate:rollback`. MySQL DDL often commits implicitly; Laravel transaction wrappers are not a recovery guarantee. A pre-release snapshot restore can discard all subsequent business writes and requires an agreed recovery point/maintenance window and reconciliation of external messages already delivered.

Do not blindly reverse (including the original 2026_03_16_000002 company_activities creation migration, whose down still drops the retained legacy table):

- `2026_09_04_135548_drop_company_activities_table`: current up/down are retention no-ops. If the former destructive version already ran, neither direction restores its rows; recover/reconcile from approved backups, never rerun/rename to conceal history.
- `2026_08_25_000001_create_email_activities_table`, `2026_09_30_180000_create_email_mailbox_connections_table`, `2026_09_30_180001_extend_email_activities_for_gmail`: down drops canonical mail/token table or thread/send/idempotency/FK fields. Preserve live mailbox/token/message data. Also retain `2026_10_01_180000_scope_email_activities_idempotency_to_mailbox`: reverse execution rejects repeated keys across mailboxes before touching indexes. The previous global-key application is incompatible with legitimate cross-user key reuse; keep Gmail disabled or forward-fix rather than downgrade its send flow. `2026_10_01_180001_add_email_mailbox_sync_checkpoint` down discards unfinished history checkpoints/status; do not reverse after import begins. Old sync code can skip unfinished pages and cannot truthfully report capped history.
- `2026_09_16_160000_add_official_business_v3_domain_foundation`, O.B. ticket detail/completion, archival, master personnel and `2026_09_21_000002_create_company_travel_credit_ledger`: down removes ticket/finance/personnel/ledger/evidence metadata; do not destroy live O.B. history or travel-credit accounting.
- Activity links/attachments/audit/meeting backend contract migrations and Messenger provider/outbound/attachment migrations: down can erase history, idempotency, provider delivery evidence or attachment relationships.
- O.B. permissions migration expands existing grants/data and down cannot reconstruct the prior permission matrix. Snapshot and review grants; don't restore an old matrix across ongoing account changes blindly.
- Unique master-reference/index migrations: dropping the index removes an integrity guarantee; rollback is not a duplicate-data remedy.

The migration CSV contains a per-file rollback note. Before any reversal, read the exact `down()` matching the release hash, classify live rows/files/constraints that would be lost, and rehearse recovery on a restored copy. Never use `migrate:fresh`, `migrate:reset`, bulk deletion, `project-data:import`, or automatic full-batch rollback on production.

## Gmail disable

Set `GMAIL_ENABLED=false` in the protected environment, rebuild config and drain/stop Gmail workers/scheduler dispatch as appropriate. Already-running HTTP requests may have been accepted; reconcile provider IDs before resend. Current disabled sync/send jobs return; a send job can be consumed while the email stays Pending. Do not assume reenabling automatically recreates or safely resends those jobs.

Preserve `email_activities`, mailbox connections including sync checkpoints, encrypted refresh tokens, private attachment files and the production APP_KEY. Disabling/worker restart does not discard checkpoints. Do not copy private mail attachments back to public storage during application rollback; restore a privacy-compatible application or keep email access disabled until forward-fixed. User disconnect deliberately revokes/clears that connection; it is distinct from temporarily disabling the feature. Inspect stored Pending/Failed rows and provider acceptance privately; never blanket replay. Disable does not remove historical mail data or the authenticated landing page.

## Messenger disable

Set `MESSENGER_OUTBOUND_ENABLED=false` first, and inbound `MESSENGER_ENABLED=false` if required; rebuild config and reload workers. Outbound jobs with disabled config cancel delivery; inbound jobs release in 60s when disabled, so stop the affected inbound pool to avoid exhausting attempts on a backlog. Disabling the webhook may produce provider retries; coordinate Page subscriptions/retry handling with the owner rather than treating flag changes as a backlog discard mechanism.

Retain conversations, entries, webhook dedup events and outbound attempts. Uncertain/time-out sends require exact provider/echo reconciliation. Do not reuse historical Pending entries for new delivery or use `queue:retry all`. Token compromise may require external revocation/rotation by the owner, separate from this kit; never log old/new values.

## Queue stop/restart and scheduler

Example later controls after process names are confirmed:

```bash
# Graceful hosting/Supervisor stop (after routing/config/flag coordination):
sudo supervisorctl stop jknc-gmail-sync jknc-gmail-send jknc-messenger
# Pause the reviewed scheduler mechanism; do not leave duplicate cron/service entries.
# Restart signaling after code/config is ready; Supervisor must autorestart:
php artisan queue:restart
sudo supervisorctl start jknc-messenger jknc-gmail-sync jknc-gmail-send
```

The default worker may carry unrelated application jobs: stop it only when the release/incident requires it. Worker flags alone cannot undo an accepted provider send. A service restart or server reboot must preserve shared queue/cache/storage state and automatically recover workers/scheduler through the installed manager.
