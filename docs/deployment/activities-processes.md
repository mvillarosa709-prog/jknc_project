# Activities queue, scheduler and process supervision

Preparation only. Do not start any process now. Use the real Linux hosting/platform process manager once access and authorization exist; do not assume the local Windows workstation is the production platform.

## Current jobs traced from source

| Job / source | Connection | Queue default | Attempts / timeout | Operational note |
|---|---|---|---|---|
| `ProcessMessagingWebhook` | `messaging.messenger.queue_connection`, env `MESSENGER_QUEUE_CONNECTION`, default database | `MESSENGER_QUEUE=messaging` | 5 / 30s; backoff 5,30,120,600 | When disabled releases in 60s; do not drain an inbound backlog by repeated disabled execution. |
| `SendMessengerMessage` | Same explicit Messenger connection | `MESSENGER_OUTBOUND_QUEUE=messenger-outbound` | 5 / 30s; backoff 30,120,600,1800; failOnTimeout | Disabled outbound cancels queued delivery; it is consumed, not held for surprise activation. Ambiguous sends require provider reconciliation, never blind retry. |
| `SyncGmailMailboxes` | Default `QUEUE_CONNECTION` | `GMAIL_SYNC_QUEUE=gmail-sync` | 3 / 120s; backoff 60,180 | Per-mailbox WithoutOverlapping lock, releaseAfter 30, expireAfter 180. Persisted page/pending-ID checkpoints make retries and later scheduled continuation safe. Disabled job returns. |
| `SendGmailEmail` | Default `QUEUE_CONNECTION` | `GMAIL_SEND_QUEUE=gmail-send` | 1 / 90s; failOnTimeout | Disabled job returns with stored Pending email unchanged; worker can consume queued work without sending. Failed sends record Failed; ambiguous acceptance is a manual reconciliation concern. |
| `ProcessMeetingRecording` | Default connection | default | No custom settings | Current class is a placeholder with an empty handle; do not advertise recording processing as a production feature. Inspect actual recording upload/playback paths separately. |
| `SystemRealtimeNotification` broadcast channel | Framework/default queue for broadcast events | default | Framework defaults | Activity reminders create database notifications and broadcast events; cover default queue when realtime broadcasting is enabled. Notification itself is not ShouldQueue. |

Gmail uses API transport, not the system mailer queue. Calendar sync services run within their existing request/service flows, not on dedicated Calendar queues. Check all shared live jobs on the default queue before choosing concurrency/timeouts.

## Recommended baseline: durable database queues

Choose this only after verifying database queue/cache tables and measured DB load. Redis is a valid alternative: set default/Messenger connection to `redis`, use shared Redis locks, and substitute connection names in worker configs. Do not mix two backends accidentally.

```dotenv
# Example non-secret operational choices; NOT a file to copy over production .env.
QUEUE_CONNECTION=database
MESSENGER_QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=180
GMAIL_SYNC_QUEUE=gmail-sync
GMAIL_SEND_QUEUE=gmail-send
MESSENGER_QUEUE=messaging
MESSENGER_OUTBOUND_QUEUE=messenger-outbound
```

Worker timeout must be below retry_after and must accommodate the job's actual timeout. Job `$tries`/`$timeout` properties override relevant worker defaults. Source configuration clamps database/Redis/Beanstalkd retry_after to an effective minimum of 180s, even if an older environment still says 90. A worker timeout must be strictly below that lease; current job properties override worker defaults. Gmail sync yields at a 60s soft budget between messages with persisted checkpoints; one slow message/attachments can still approach the hard timeout, so benchmark pilot latency. Configure SQS/platform visibility separately >=180s and verify it (no source-side SQS visibility setting exists). Stop activation for a missing async backend, leases below job timeout, unavailable shared locks, missing pcntl/worker timeout enforcement or an unsafe hosting kill policy. No production queue configuration was edited.

Reviewed later worker commands (one separate supervised process per line initially):

```bash
php artisan queue:work database --queue=messaging,messenger-outbound --sleep=3 --tries=5 --timeout=60 --max-time=3600
php artisan queue:work database --queue=gmail-sync --sleep=3 --tries=3 --timeout=130 --max-time=3600
php artisan queue:work database --queue=gmail-send --sleep=3 --tries=1 --timeout=100 --max-time=3600
php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=60 --max-time=3600
```

Queue names are prioritized left-to-right. Increase/partition Messenger workers if inbound traffic starves outbound replies. Do not run this combined Messenger command while only outbound is enabled or while a disabled inbound backlog is being released. Separate pools can be used per queue after hosting review.

## Scheduler traced from `bootstrap/app.php`

| Schedule | Current command/callback | Overlap protection |
|---|---|---|
| Every minute | `activities:dispatch-reminders` | No schedule overlap lock; per-task/recipient Cache::add dedup window. |
| Hourly | `activities:generate-recurring` | No schedule overlap/single-server guard; mutates tasks/pivots. Run one scheduler owner. |
| Every two minutes | Callback named `gmail:sync-mailboxes`; dispatches connected Gmail mailbox IDs with refresh tokens | `withoutOverlapping(1)` for dispatch callback; per-mailbox job lock separately. Gmail flag checked before querying. |
| Every minute, shared app | `townhall:archive-expired`, `reports:generate-scheduled`, `natgov:sync-reminders` | No explicit overlap locks in bootstrap. These affect other application areas; review their live ownership before enabling the global scheduler. Scheduled reports here are the existing project SOW/RSAT command, not a new Activities report queue. |

Gmail schedule name is not an Artisan command; do not run `php artisan gmail:sync-mailboxes`. There is no external Gmail push/watch job in the implementation.

Install one later cron entry for the app user (adapt paths/PHP/log rotation):

```cron
* * * * * cd /srv/jknc/current && /usr/bin/php artisan schedule:run >> /srv/jknc/shared/logs/scheduler.log 2>&1
```

Use cron's OS service enabled at boot or the hosting scheduler. Do not also run `schedule:work` or duplicate cron on another node. A systemd timer/managed platform scheduler is acceptable if it guarantees the same once-per-minute single-owner execution, working directory/user/environment and persistent boot recovery.

## Supervision requirements

`supervisor-activities.conf.example` contains four Linux Supervisor programs. Adapt executable/path/user/connection/queues/log directory, worker count, stopwaitsecs and resource limits after server access. Ensure Supervisor starts at boot; enable cron at boot. `--max-time=3600` intentionally exits workers periodically, so autorestart is mandatory.

Use persistent shared upload storage across releases, readable protected app environment, writable logs/cache and a common production APP_KEY. On a deployment or flag change, drain jobs and restart workers so old code/config is not retained. Reconnect/stale jobs must be reconciled before replay; do not use `queue:retry all`.

Health checks: process alive, latest scheduler execution, queue age/depth per connection/queue, failed_jobs/event status, Gmail last_synced_at/error/status, sync_status/initial_sync_status/initial_sync_count, checkpoint backlog and delivery reconciliation. Connected means OAuth connected, not complete history; capped/continuing must remain visible. Checkpoints stay private and survive worker retries/restarts. Alert on missing scheduler for >two cadence intervals, growing backlog, repeated reconnect_required, ambiguous sends, lock starvation and disk errors. Keep bodies/tokens/provider payloads out of alerts.

Acceptance after a staging reboot: Supervisor starts all configured workers, cron resumes exactly once per minute, the controlled inbound/outbound/Gmail pilots recover without employee CLI activity, and the current release/config is used. No production reboot or service restart occurs during this preparation.

References: [Laravel 12 queue deployment](https://laravel.com/framework/docs/12.x/queues), [Laravel scheduler](https://laravel.com/framework/docs/12.x/scheduling).
