# Official Business V3: Phase 1 domain foundation

## Scope

Adds explicit client Trip Ticket identity without changing existing O.B. routes,
UI, approval/movement behavior, or legacy status values. No automatic backfill,
credit posting, evidence/personnel copying, or workflow activation occurs.

## Database inspection (2026-09-16)

The connected database is MySQL. Inspection confirmed the original master table,
Activities V2 reporting/relationship columns, actual movement timestamps, and
soft deletes match their migration files and recorded applied migrations:

- `2026_05_11_160306_create_official_business_trips_table`
- `2026_08_20_113912_add_activity_v2_core_fields_to_activity_sources`
- `2026_08_24_000002_complete_activities_v2_safe_contracts`
- `2026_08_26_112720_add_actual_travel_timestamps_to_official_business_trips_table`
- `2026_08_26_130000_add_soft_deletes_to_official_business_trips_table`

Five master records were present: three Completed, one Pending, one Approved.
No ticket table existed. Parent/client/user keys are unsigned BIGINT. Existing
master references are nullable and not unique; they are not changed or used as
the ticket uniqueness key. The employee FK currently cascades master deletion.

## Schema and identity

- `official_business_trips.workflow_version`: defaults to 1, including old records
  and records created by the existing UI. Version 1 denotes legacy lifecycle
  semantics, not a claim that a historical record satisfies V3 completion.
  Phase 2 must define explicit activation of version 3; Phase 1 never activates it.
- `official_business_trip_tickets`: required master and Company FKs; unique
  `reference_no`; immutable original `client_name_snapshot`; independent `status`
  initially Draft; nullable creator FK; timestamps and soft deletes.
- Unique `(official_business_trip_id, company_id)` includes archived tickets.
- References use `OBTT-{master database ID}-{ULID}`. The ULID avoids sequence
  races and reference reuse; legacy OBF references remain available on the master.
- Master and Company deletion is restricted while tickets exist (including
  archived tickets). User deletion clears the creator FK without deleting tickets.
- One Company is the canonical client identity. Contact-only/free-text historical
  clients are not guessed or converted. Supporting individual clients without a
  Company requires an explicit later domain decision.

## Internal API and authorization

Use `OfficialBusinessTripTicketService::forClient($actor, $master, $company)`.
It reloads and locks the parent in a transaction, authorizes through Laravel's
auto-discovered policy, and returns an existing active ticket on retry. An archived
ticket produces a validation error instead of being recreated or restored.

The foundation policy permits active internal administrators, O.B. managers,
the master filer, and its employee's linked user. It denies disabled/client users,
unrelated users, archived tickets, and tickets under deleted masters. Legacy
participant names confer no ticket access. No client-facing endpoint, list-all,
update, delete, restore, or Finance capability is introduced in Phase 1.

The model rejects ticket creation for Internal/deleted masters and identity
reassignment. Masters with tickets cannot be switched to Internal through Eloquent.
Direct SQL bypasses model/policy rules, but database uniqueness and FK constraints
remain enforced. Future entry points must use the authorized service and policy.

Relations: master `tripTickets()`; Company `officialBusinessTripTickets()`;
ticket `officialBusinessTrip()`, `company()`, and `createdBy()`.

## Deployment and rollback

Apply only the scoped migration; do not run unrelated pending migrations:

```sh
php artisan migrate --path=database/migrations/2026_09_16_160000_add_official_business_v3_domain_foundation.php
```

Its `down()` removes the new ticket table and workflow-version column, leaving
legacy master fields intact. Rollback removes any newly created tickets, so export
those records first once ticket creation has begun. This is schema reversibility,
not a promise to retain new ticket data after dropping its table.

Restrictive FKs intentionally block the legacy permanent-delete path for a
ticket-bearing master (and employee cascade deletion of that master). A friendly
retention-aware deletion workflow belongs to Phase 10; do not bypass the FK.
Unticketed legacy records retain existing deletion behavior.

## Validation

The focused test file executes the actual original master, movement, soft-delete,
and new Phase 1 migrations in guarded SQLite `:memory:` storage. It covers legacy
preservation, rollback/reapply, multi-client identity, idempotency, database
uniqueness/FKs, authorization, immutable identity, and archive behavior.
Existing O.B. and Activities compatibility suites provide regression coverage.
MySQL row-lock concurrency is designed into the service; SQLite tests do not
simulate simultaneous MySQL requests.

UI, ledger, Finance/Billing, PDFs, evidence workflows, completion checks,
approval transitions, and expanded role capabilities remain deferred.
