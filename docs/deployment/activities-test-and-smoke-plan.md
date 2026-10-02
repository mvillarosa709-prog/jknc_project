# Activities pre-deploy validation and production smoke plan

## Test boundaries

`activities-test-matrix.csv` lists actual existing test paths and extracted Pest test titles; no imaginary Gmail test file is assumed. Gmail coverage currently lives in `tests/Feature/ActivitiesV2RemainingBackendTest.php`. The inventory is not evidence that all tests pass.

Do not run feature suites against production or the ordinary local `.env`. Several test fixtures create/drop tables or call migration `up()` methods even when no `artisan migrate` command is used. Under this preparation's no-migrations constraint they are inventoried only. Later run in a disposable clone/container with an explicit isolated SQLite `:memory:` test connection (or separately provisioned disposable MySQL clone for compatibility tests), mocked network and no real credentials. Verify the effective connection before tests; existing phpunit XML environment entries can be overridden by a process environment, and not all tests contain a database guard. Never use production APP_KEY or tokens in CI.

## Existing test matrix

| Module / concern | Actual files | Required acceptance |
|---|---|---|
| Core CRUD/legacy compatibility/duplicate guards | `tests/Feature/ActivitiesBackendCompatibilityTest.php`, `ActivitiesV2RemainingBackendTest.php` | Core metadata, prepared-by immutability, auth, normalized relationships, source IDs, duplicate prevention and no GET persistence regressions. |
| Tasks | Same two suites; `tests/Frontend/tasks-messages-navigation-regression.cjs` | Main/subtask persistence and details, outline/reorder/bulk/templates/timers, independent List/Board/Calendar, task search/filter main-only rows; recurring/reminder behavior with isolated fixtures. |
| All Activities navigation | `tests/Frontend/all-activities-calendar-regression.cjs` | Despite historical filename, tests now assert no All Activities submenu, normal parent/default/back ledger, preserved Tasks Calendar. |
| Meetings/Events/Calls/Calendar | `ActivitiesBackendCompatibilityTest.php`, `ActivitiesV2RemainingBackendTest.php`, `tests/Feature/MeetingCalendarPopupTest.php` | Schedule/timezone, update/delete idempotency, local persistence on mocked provider failure, minutes/action-item conversion, authorized recording/playback and protected details. |
| Messenger inbound/privacy | `tests/Feature/MessengerFoundationTest.php`, `MessengerReadinessTest.php` | Signature rejection, config/async guards, dedup, sender identity, owner visibility, Page/PSID validation, no secret/payload leak. |
| Messenger outbound/images | `tests/Feature/MessengerOutboundTest.php`, `tests/Frontend/messenger-outbound-regression.cjs`, `message-composer-regression.cjs` | Queue durability, idempotency, provider identity/echo/retry reconciliation, timeout uncertainty, eligible single image, attachment isolation and disabled-send behavior. |
| Gmail/per-employee email | `tests/Feature/ActivitiesV2RemainingBackendTest.php` | Existing tests include employee isolation, OAuth user-bound state, idempotent sync, compose/reply, pending-to-sent provider identity, attachment downloads, reconnect/disabled handling. Focused production-readiness regressions now cover retained company_activities migration data, the exact Google authorization URL, per-mailbox key reuse, private incoming/compose/generic EMAIL attachments, owner/guest denial, cap boundaries and incremental/page/retry continuation, repeated-token rejection, queue lease floors and Blade compilation. Actual local suite evidence is in validation-results.json; provider and MySQL acceptance remain later staging/live gates. |
| O.B. V3 | `tests/Feature/ActivitiesOfficialBusinessTest.php`, `OfficialBusinessV3FoundationTest.php`, `OfficialBusinessV3WorkflowTest.php`, `OfficialBusinessV3TicketDataTest.php`, `OfficialBusinessV3TicketCompletionTest.php`, `OfficialBusinessMasterPersonnelBackfillCommandTest.php` | Legacy/v3 master separation, client tickets/stops/personnel, movement, authorization, finance/travel-credit accounting, private evidence, completion, archival/restore, PDFs/history and personnel backfill conflict/dry-run safety. |
| O.B. frontend | `tests/Frontend/ob-draft-edit-regression.cjs`, `ob-all-activities-master-detail-regression.cjs` | Draft edit fidelity, master/ticket inline projection, correct navigation and action guards. |
| Reports/timelines/exports/privacy | `ActivitiesV2RemainingBackendTest.php`, `ActivitiesBackendCompatibilityTest.php`, `ActivitiesOfficialBusinessTest.php` | Exact filter/selected-ID semantics, canonical company/contact scoping, duration coverage, XLSX/PDF render and finance fields, include flags/body privacy, unauthorized visibility restrictions. |
| Visitor Log | `tests/Feature/ActivitiesVisitorLogTest.php` | Existing Activities routing/permissions/check-in/check-out remain intact. |
| Test harness | `tests/Pest.php`, `tests/TestCase.php`, `phpunit.xml` | Confirm fixtures/isolation and PHP platform before execution. |

The CSV contains full paths for all actual matched files. Existing frontend scripts must be read before execution: some require Playwright/local browser fixtures and may fetch Alpine from CDNs. Pin/install test dependencies separately in disposable tooling; do not run unknown browser fixtures against real mail/Page/server data.

### Later isolated commands

```bash
# Only inside the verified disposable test environment; fixtures may run migration code:
php artisan test tests/Feature/ActivitiesBackendCompatibilityTest.php
php artisan test tests/Feature/ActivitiesV2RemainingBackendTest.php
php artisan test tests/Feature/ActivitiesOfficialBusinessTest.php
php artisan test tests/Feature/ActivitiesVisitorLogTest.php
php artisan test tests/Feature/MeetingCalendarPopupTest.php
php artisan test tests/Feature/MessengerFoundationTest.php
php artisan test tests/Feature/MessengerOutboundTest.php
php artisan test tests/Feature/MessengerReadinessTest.php
php artisan test tests/Feature/OfficialBusinessV3FoundationTest.php
php artisan test tests/Feature/OfficialBusinessV3WorkflowTest.php
php artisan test tests/Feature/OfficialBusinessV3TicketDataTest.php
php artisan test tests/Feature/OfficialBusinessV3TicketCompletionTest.php
php artisan test tests/Feature/OfficialBusinessMasterPersonnelBackfillCommandTest.php

node tests/Frontend/all-activities-calendar-regression.cjs
node tests/Frontend/tasks-messages-navigation-regression.cjs
node tests/Frontend/messenger-outbound-regression.cjs
# After verifying browser/mock prerequisites:
node tests/Frontend/message-composer-regression.cjs
node tests/Frontend/ob-draft-edit-regression.cjs
node tests/Frontend/ob-all-activities-master-detail-regression.cjs
```

Also rehearse every approved migration/backfill on a scrubbed production-schema/data restore of the real DB version; SQLite passing cannot establish MySQL FK/index/DDL lock safety. Inspect routes, config/view caches and artifact contents on the integrated branch; retain exact results and unresolved failures in the PR. Do not convert existing failures into claimed passing checks.

## Production-safe smoke — later, after deployment approval

Use existing representative records for read-only checks. Create pilot records/messages only with explicitly agreed production-safe account/recipients and narrow test markers; no bulk data mutation or cleanup deletion is required.

1. **All Activities:** parent/default/refresh/back open the original ledger, no child Calendar/Board; filters, totals, durations and selected export scope match representative data. No unauthorized private email appears for a different employee.
2. **Tasks:** main rows only across search/status/priority; open an existing main task and its subtasks. List/Board/Calendar, templates/outline/bulk/timer controls remain present. If an approved pilot create is permitted, create one main/child once and validate no duplicates; otherwise existing-record checks are read-only.
3. **Events/Meetings/Calls:** open representative detail, related records, recording/files/minutes/action items; verify business timezone. Calendar target/credentials must be verified before any create/edit that would create Google events or notifications. No real recipient mail sent unintentionally.
4. **Messages/Messenger:** only Facebook Messenger sidebar entry, existing conversations/profile identities load for intended inbox owner. After explicit activation, controlled person sends one real message to the verified Page; observe inbound job/conversation/entry. Send one approved reply, confirm provider ID/Sent then echo/delivery/read; verify duplicate event does not create a second entry. No arbitrary outbound test recipient.
5. **Email/Gmail A/B:** after code/OAuth/privacy gates, A connects A's Gmail and B connects B's. Verify each sees only own mailbox, threads and attachments in Emails, All Activities, reports and Company/Contact timelines; test guessed cross-owner IDs and repeated idempotency keys fail without disclosing records. Check sync cadence and last_synced_at, duplicate suppression, sender selection/reply thread, small approved attachment, Pending→Sent and controlled receiver inbox. Verify revoked/disconnected account is local-safe and reconnect-required is visible. Do not test by exposing live tokens or public private-file URLs in reports.
6. **O.B. V3:** open a known authorized representative legacy master, v3 master and client ticket (or explicitly approved safe equivalent), compare personnel/stops/movement/financial treatment/history/private evidence/verification/PDF. Validate separation of employee/travel-controller/approver/finance/admin access. Don't complete/approve/post travel credits solely for a smoke test on a real active record.
7. **Reports:** apply known account/date/type/duration filters; selected-record PDF/XLSX matches screen and canonical IDs; excluded/body privacy flags respected and exports load without runtime errors. Do not export broad sensitive production data to unsecured storage.
8. **Company/Contact timelines:** canonical links show the correct source records once, email privacy honored, O.B. durations/status and report action match current data; retired CompanyActivity is not required for the new timeline.
9. **Permissions/attachments/history:** unprivileged user cannot invoke protected APIs or download another user's/private O.B. files; valid authorized users can; existing logs/audit and attachments remain intact.
10. **Operations:** supervised workers alive on exact connection/queues, one scheduler active after reboot rehearsal, no rapidly growing backlog/failures/reconnect alerts, correct asset/cache release and no secret leakage.

## Mandatory stop conditions

Stop before migration/deployment/activation if: unexpected live branch divergence; ambiguous shared-hunk merge; applied migration name/source mismatch; missing migration history; schema/FK/index incompatibility or partial migration; destructive migration not resolved; duplicates affecting uniqueness; unverified backup/restore; unsupported PHP/Composer/DB/Node platform; unavailable async queue/cache/storage/scheduler/process manager; tests/build/cache/authorization fail; secret/private-data exposure; Gmail OAuth endpoint/redirect/audience/verification mismatch; unverified Meta app/Page/token/PSID/permissions; unknown Calendar target; cross-user privacy/idempotency/public-file/capped-sync gate unresolved; unreviewed O.B. permission/backfill/ledger impact. A documented stop is a release gate, not permission to run corrective production writes automatically.
