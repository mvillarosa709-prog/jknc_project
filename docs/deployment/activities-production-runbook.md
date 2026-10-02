# Activities production runbook — execute later only

Prerequisite: all gates in `activities-production-readiness.md` resolved with live access and explicit deployment authorization. Commands here are examples to review and adapt, not commands executed by this preparation. Replace `<production-domain>`, `<release>`, `<previous-release>`, PHP binary/user/path and database identifiers with verified values privately.

## 1. Integrate into the live repository safely

1. Obtain read access; identify actual production branch, release commit/tag, CI configuration and release method. Record clean status and remote identities without changing existing remotes.
2. Fresh clone the live repository in a separate directory. Create `integration/activities-upgrade` from the verified production branch. Do not repoint this local project's remote and do not copy the entire local folder.
3. Compare each manifest path against production. Classify as new feature file, shared hunk, already implemented or conflicting. Compare `routes/web.php`/messaging/channels, bootstrap/provider middleware/scheduler, every migration filename and source hash, model relationships/scopes, controller auth/CRUD, services, observers/policies/jobs/commands, views/layouts, config/env names, dependency locks/build inputs and tests. Preserve unknown live changes.
4. For matching migration names with different content, applied schema divergence, shared-controller conflicts, retired CompanyActivity differences or uncertain finance permissions, STOP and obtain a concrete reviewed resolution. Never rename an applied migration merely to rerun it.
5. Port only verified Activities changes and required shared hunks, with no backup/secret/generated files. Review dependency lock diffs rather than running `composer update` or adopting every local dependency change. Run redacted secret/history/artifact checks.
6. Run the isolated test matrix and staging rehearsal with external flags off. PR/review with full migration/backfill/rollback evidence; merge only after authorization. No `git add`, commit or push is performed by this kit.

## 2. Server preflight — inspect later

| Check | Later read/check method and acceptance |
|---|---|
| PHP/Composer/framework | `php -v`, `php -m`, `composer --version`, `composer check-platform-reqs --no-dev`, inspect lockfile; app requires PHP ^8.2 and Laravel ^12.0. Pest 4 dev tests need their own compatible PHP platform. Confirm actual locked framework/dependency versions. |
| Extensions | Laravel-required extensions plus PDO MySQL, cURL/OpenSSL/mbstring/fileinfo, DOM/XML for PDF, ZIP if XLSX implementation requires it; compare Composer platform requirements. Ensure CLI and PHP-FPM versions/settings match. |
| Node/build | Vite ^7, Tailwind ^4; read locked engine constraints and `node --version`/`npm --version`. A compatible build environment is required; do not assume server Node is installed. |
| MySQL/MariaDB | Read-only schema script: version, SQL mode, InnoDB, JSON/type/index compatibility, FK ID signedness, indexes, size and free disk. No target minimum is asserted without a staging rehearsal of the actual version. |
| Queue/cache | Confirm selected async connection; database `jobs`/`failed_jobs`/`job_batches` or reachable Redis. Cache must persist locks across scheduler and worker processes; never use `array` in production. Redis availability is conditional, not mandatory for the database plan. |
| Process manager | Confirm Supervisor/systemd/hosting workers and boot persistence, log rotation and graceful draining. Record PHP path, app user, current symlink and worker count. |
| Scheduler | One scheduler owner across nodes; cron `schedule:run` every minute or hosting equivalent. Current reminders/recurrence do not use `onOneServer`/schedule overlap locks. |
| Storage/permissions | Preserve `storage/app` uploads/private evidence, writable `storage/framework`, logs and `bootstrap/cache`; web/worker user access. Check existing `public/storage` link before `storage:link`. Ensure private evidence cannot be served publicly. |
| HTTPS/domain/proxy | Valid HTTPS/cert/DNS, correct `APP_URL`, OAuth callback/session cookies, Meta callback accessible. Current bootstrap trusts `*` proxies: verify the ingress topology/security before release; do not change it blindly. |
| Environment/cache | `APP_ENV=production`, `APP_DEBUG=false`, stable production `APP_KEY`; current config/cache driver state. Check names privately, never dump credentials/config. Avoid stale cached flags in long-lived workers. |
| Realtime/CDN | Layout loads Tailwind/Font Awesome/Quill/Alpine from CDNs and Vite JS. Validate CSP/network access and chosen broadcast backend; if Reverb is used, supervised Reverb/reverse proxy and Vite public keys are required. |

## 3. Back up before any change

- Full consistent DB snapshot/dump, including migration history, attachment references, queues, audit/permission and travel-credit records. Verify recovery to a disposable instance and record restoration time/retention/encryption. For mysqldump use a protected option file/managed snapshot, not an inline password; InnoDB consistent snapshots need appropriate binlog/DDL coordination.
- Current deployed commit/tag, built asset manifest and immutable application release; archive package locks and process/reverse-proxy configuration.
- Production `.env` and any secret-store versions in a secure encrypted destination outside webroot/repo; keep the original `APP_KEY`.
- Backup persistent uploads/storage and private O.B. evidence; preserve permissions/symlinks. Coordinate DB/files at a recoverable point. Never package local `storage/db-backups` or email/Meta data into the release.
- Without a verified restore-capable backup, STOP. Creating a new empty table on rollback does not restore lost data.

## 4. Exact release order after approval

1. Verify the resolved local findings against the live branch/schema, including retained company_activities migration history, private storage, mailbox idempotency and checkpoint fields; resolve integration conflicts and test failures. Record the reviewed chronological pending migration allowlist from production history; inventory rows are not all necessarily pending.
2. Take and verify the backups above. Prepare the new immutable release/build before switching live traffic. Keep production uploads and environment separate from the artifact.
3. Disable external integrations in the candidate environment: `GMAIL_ENABLED=false`, `MESSENGER_ENABLED=false`, `MESSENGER_OUTBOUND_ENABLED=false`. Withhold candidate Calendar bridge/token values; retain existing production credentials securely for later restoration. Prevent candidate/staging outbound SMTP and broadcast effects during smoke fixtures.
4. For the reviewed DDL window, pause the scheduler and drain/stop affected workers. Use `php artisan down --retry=60` only when schema/code incompatibility, table locks or backfills require excluding requests; this release's FK/column additions and unique indexes must be rehearsed before claiming zero downtime. Maintenance mode alone does not stop all external request paths or running workers. Coordinate Meta webhook handling/retries before disabling an already-live integration.
5. Place reviewed code on the server/new release. Install locked runtime dependencies with `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`; never `composer run setup` (it generates keys/migrates), `composer update`, or data-import scripts. Run `composer check-platform-reqs --no-dev`.
6. Verify non-secret environment configuration, production key retention, database/queue connections, storage, callback paths, flags and shared permissions. Do not connect employees yet.
7. Run `production-schema-readonly.sql` stages using a SELECT-only account. Execute the generated Stage 2 SELECTs as instructed; save redacted results privately. Compare history/schema with the migration CSV/source, including partial columns, duplicates and type/index/FK prerequisites. Run `php artisan migrate:status` later under controlled credentials only if needed; it is not run now.
8. After reviewing each `up()` for PHP/file/network side effects, preview only its approved path:

   ```bash
   php artisan migrate --path=database/migrations/<approved-file.php> --pretend --force
   ```

   `--pretend` executes migration PHP while suppressing SQL writes: it is not a sandbox for other side effects. Rehearse reviewed migrations/backfills on a restored staging copy first. The former CompanyActivity drop is now a retention no-op. Compare applied source hashes: an already-applied old drop requires separate recovery/reconciliation; do not assume editing its source restored data. Avoid a blanket migration command that includes unrelated pending app migrations.

9. Apply only the approved pending files in ascending timestamp order, with predecessors/foreign-table baseline already present:

   ```bash
   php artisan migrate --path=database/migrations/<approved-file.php> --force
   ```

   Order: core table prerequisites → normalized/core fields/task schema → links/attachments/audit/meeting backend contracts → email base → Calendar fields → messaging entry attachments/provider/outbound → O.B. foundation/drafts/details/completion/permissions → archival/travel-credit ledger → master-reference uniqueness → master personnel/movement → Gmail mailbox connections `2026_09_30_180000` → Gmail extension `2026_09_30_180001`, then mailbox-scoped key index `2026_10_01_180000`, then mailbox checkpoint/status fields `2026_10_01_180001`. The last two depend on both Gmail predecessors; deploy all four before activating new Gmail code. The checkpoint migration is additive; no mail backfill is required before this pre-live feature. Retain company_activities throughout this release; any legacy copy/mapping/removal is a future reviewed release. Use actual CSV order for all files, including older O.B./employee/HC prerequisites. Do not repeat already-recorded files or force a partially applied migration.

10. Inspect O.B. permission/data backfills already embedded in `up()`. Do not run all seeders. After `2026_09_28_000002`, run:

    ```bash
    php artisan ob:v3-backfill-master-personnel --dry-run
    # Only after reviewing zero conflicts/errors and authorizing this backfill:
    php artisan ob:v3-backfill-master-personnel
    ```

    This command changes only reviewed legacy/master-personnel relationships; preserve its output privately. No Gmail history import or real Gmail connect is a deployment prerequisite. Read-only preflight must distinguish legitimate cross-mailbox key reuse from invalid duplicate (mailbox_connection_id,idempotency_key) pairs, and detect partial new checkpoint schema/index changes. Validate local private storage and authenticated owner download before pilot activation.

11. Build/release assets as below, switch the release using the hosting platform's atomic mechanism, restore shared storage/symlink, then run reviewed cache commands. Check all module pages/API contracts while external flags remain off.
12. Install/start reviewed supervised queue workers with async connection/retry intervals; restart workers after each config/release change. Resume the single production scheduler only after migrations/backfills and global schedule effects are reviewed.
13. `php artisan up` if maintenance was used. Run the production-safe smoke plan; require clean logs and no privacy/authorization errors.
14. Activate integrations independently: confirmed Calendar credentials/target → Messenger inbound pilot → Messenger outbound controlled reply → Gmail pilot employee A → employee B isolation → broader employee rollout. Pause/disable at the first failure. No automated enable step is included.

## 5. Build and cache commands

Production needs a Vite asset build: `resources/views/layouts/app.blade.php` uses `@vite(['resources/js/app.js'])`; `vite.config.js` also builds CSS and Tailwind scans Activities Blade. Preserve runtime CDN dependencies.

```bash
# In CI/build release, with reviewed lockfiles and compatible Node:
npm ci
npm run build
# Retain public/build/manifest.json and hashed outputs; never publish public/hot.

# Later, in the deployed release with production config/storage ready:
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

`optimize:clear` can clear shared application cache/locks; run while coordinated workers/scheduler are paused. `queue:restart` uses cache signaling and a supervisor restart policy; it is not a guaranteed immediate halt. `storage:link` is conditional on a missing/correct link, not a blind overwrite.

`route:cache` is **not yet certified** for this repository's closure routes/large route set. In disposable staging run `php artisan route:cache`, validate the route inventory and all module endpoints, then `php artisan route:clear` if it fails; include in the real release only after that exact integration branch passes. Do not ship stale route/config/view cache generated locally. Do not run `optimize` blindly or `key:generate`.

## 6. Process ownership

Hosting-specific paths, user/service names, PHP/Node versions, domain/SSL, database sizing and release hooks remain to be filled from real access. `activities-processes.md` and `supervisor-activities.conf.example` define durable worker/scheduler requirements. Employees never run CLI commands. The administrator must verify an actual reboot test on staging: workers recover, one scheduler runs, retries/locks and integration backlogs are visible.
