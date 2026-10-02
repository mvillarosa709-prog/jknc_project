# Activities external integrations and production environment package

All values below are defaults or placeholders, never actual credentials. Do not connect providers as part of preparation. `activities-environment-keys.csv` contains the full traced environment dependency inventory, key classification and default expressions. The following tables clarify required integration subsets and production actions.

## Shared production keys

| Key(s) | Requirement / secrecy | Action |
|---|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_URL` | Required / non-secret | `production`, `false`, verified HTTPS domain. |
| `APP_KEY` | Required / SECRET | Preserve existing production encryption key, securely back it up; mailbox refresh tokens depend on it. Never generate a new key for this release. |
| `APP_TIMEZONE` | Optional config key / non-secret | Current app config uses UTC fallback; Calendar uses Asia/Manila fallback. Confirm business-time rendering consistently; do not invent a timezone change in deployment. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Required / password SECRET | Existing production DB identity/config, supplied privately later. `DB_URL`, SSL/socket keys conditional on hosting; see CSV. |
| `QUEUE_CONNECTION` | Required / non-secret; default `sync` | Set a durable async `database` or `redis` connection. Gmail jobs inherit it. |
| `DB_QUEUE_CONNECTION`, `DB_QUEUE_TABLE`, `DB_QUEUE`, `DB_QUEUE_RETRY_AFTER` | Conditional database backend / non-secret | Default table `jobs`, queue `default`; retry default/effective minimum is now 180 seconds. Use reviewed value >=180; connection must have required tables. |
| `REDIS_QUEUE_CONNECTION`, `REDIS_QUEUE`, `REDIS_QUEUE_RETRY_AFTER`, Redis host/port/database/client keys | Conditional Redis backend / password SECRET | Shared Redis configuration; lease >=180 for Gmail; install appropriate client/extension. |
| `CACHE_STORE`, DB cache/Redis cache keys | Required operational choice / mostly non-secret | Persistent shared cache for locks/restart signals; database cache requires tables. Shared multi-node consistency must be verified. |
| `SESSION_DRIVER`, session cookie/domain/secure settings | Required operational choice / non-secret | Durable session with secure HTTPS cookies; callback must reach the same logged-in user's session. |
| `FILESYSTEM_DISK`, `AWS_*` if selected | Storage-dependent / AWS keys SECRET | Preserve public/private disk access policy; do not expose Gmail or O.B. private attachments. |
| `MAIL_*`, `POSTMARK_API_KEY`, `RESEND_API_KEY`, SES `AWS_*` | Conditional system email / credentials SECRET | Existing meeting/notification workflows, separate from Gmail. Do not assume Gmail OAuth replaces SMTP. Suppress external recipients on staging. |
| `BROADCAST_CONNECTION`, `REVERB_*`/`VITE_REVERB_*`, `PUSHER_*` if used | Realtime-dependent / server secrets SECRET | Database/broadcast notifications use shared application broadcasting; preserve current production architecture. `VITE_*` public values are embedded at build time, never embed secrets. |
| `LOG_*`, queue failed/batch driver keys | Optional shared infrastructure | Redact OAuth codes/tokens, provider payloads and email bodies; monitor queue errors without dumping SQL bindings/HTTP headers. |

## Gmail package — actual implemented names

Source: `config/services.php`, `EmailActivityController`, `EmailActivity`, `EmailMailboxConnection`, `GmailTransport`, `SendGmailEmail`, `SyncGmailMailboxes`, `bootstrap/app.php`.

| KEY | Required / optional | Secret | Default | Production action |
|---|---|---|---|---|
| `GMAIL_ENABLED` | Optional flag; required true for activation | No | false | Initial false. Enable only after code/privacy/OAuth/queue/schema gates. |
| `GOOGLE_CLIENT_ID` | Required when enabled | No (credential identifier) | unset | Web OAuth client for the confirmed production project. |
| `GOOGLE_CLIENT_SECRET` | Required when enabled | YES | unset | Secret environment only; never source/PR/browser bundle. |
| `GOOGLE_REDIRECT_URI` | Required when enabled | No | unset | Exactly `https://<production-domain>/api/email/gmail/callback`. |
| `GMAIL_INITIAL_SYNC_DAYS` | Optional | No | 14 | Review initial mailbox history volume and privacy retention. |
| `GMAIL_SYNC_LIMIT` | Optional | No | 50 | API page size; benchmark before increasing. |
| `GMAIL_INITIAL_SYNC_MAX_MESSAGES` | Optional | No | 500 | Per-invocation cap, including incremental runs; persisted pending IDs/page tokens resume next schedule. Reaching the cap records incomplete status instead of advancing the completed watermark. |
| `GMAIL_ATTACHMENT_MAX_KB` | Optional | No | 10240 | Per-file upload limit; verify PHP `upload_max_filesize`/`post_max_size`, proxy limits and aggregate MIME size. Code permits max 10 files, not an env setting. |
| `GMAIL_ATTACHMENT_MIMES` | Optional | No | PDF, JPEG, PNG, plain text, ZIP, DOCX, XLSX MIME types | Actual default string is in CSV/source. Keep reviewed allowlist. Incoming/compose/generic EMAIL files now use local private storage; downloads require EmailActivity owner authorization. Verify private filesystem access and authenticated route on staging. |
| `GMAIL_SYNC_QUEUE` | Optional | No | gmail-sync | Supervised worker on default async queue connection. |
| `GMAIL_SEND_QUEUE` | Optional | No | gmail-send | Separate supervised send worker; no blanket automatic retries of uncertain deliveries. |

Sync cadence is **hard-coded every two minutes**, schedule name `gmail:sync-mailboxes`; there is no implemented Gmail cadence env key or separate Gmail queue-connection env key. Token/API endpoints and scopes are hard-coded configuration, not new environment keys. Existing callback state is random and user/session-bound; connection uniqueness is provider+JKNC user. Data visibility is owner-only, including admins. Browser authorization now uses https://accounts.google.com/o/oauth2/v2/auth; token exchange/refresh uses https://oauth2.googleapis.com/token and account profile uses https://gmail.googleapis.com/gmail/v1/users/me/profile. URL/state/scope regressions pass; no secret enters the browser authorization URL.

### One-time Google Cloud setup — later

1. Create/select the organization's approved Google Cloud project. Establish owners/billing/contact and production-domain ownership; record the project identity privately.
2. Enable **Gmail API** in APIs & Services. No domain-wide delegation/service-account impersonation is implemented or requested.
3. Configure Google Auth Platform/OAuth consent branding, support/developer contacts, authorized domains and required privacy/homepage links.
4. Decide **Internal** only for an eligible Google Workspace organization with employees in that organization. Otherwise use **External**. Workspace administrators may need to allow the app; internal classification is not a bypass of organizational access controls. [Google audience guidance](https://support.google.com/cloud/answer/15549945?hl=en).
5. For External Testing, explicitly add pilot employees A/B as test users. Testing is not a durable unattended-production strategy: Google's published guidance says refresh tokens for this scope combination expire after seven days in External Testing. Resolve publication/verification status before broad rollout. [Google OAuth token rules](https://developers.google.com/identity/protocols/oauth2).
6. Create an OAuth client of type **Web application**. Do not reuse a desktop client or a developer's personal project by accident.
7. Set the exact authorized redirect URI: `https://<production-domain>/api/email/gmail/callback`. Match scheme, host, path and slash exactly to `GOOGLE_REDIRECT_URI`; no wildcard. Set any required origins for the real hosting domain, without altering callback routes.
8. Request only the two implemented scopes: `https://www.googleapis.com/auth/gmail.readonly` and `https://www.googleapis.com/auth/gmail.send`.
9. `gmail.readonly` is **restricted**; `gmail.send` is **sensitive**. External production may require OAuth verification, and server storage/transmission of restricted data may require a security assessment unless an applicable exception is confirmed. Obtain a concrete organization-specific decision; do not promise approval based on Internal/Testing labels alone. [Gmail scope classifications](https://developers.google.com/workspace/gmail/api/auth/scopes), [restricted verification](https://developers.google.com/identity/protocols/oauth2/production-readiness/restricted-scope-verification).
10. Store Client ID/Secret and redirect URI in the protected production environment. Preserve `APP_KEY`. Confirm the tested authorization/token endpoint separation on the integrated release, then rebuild config and restart workers through the approved release flow; do not print secrets.
11. After all gates and activation approval, enable Gmail only for a controlled pilot window. Logged-in employee A starts `/api/email/gmail/connect` from the existing UI, consents with A's real account, and completes callback. Validate worker/sync/send before employee B connects. No real Google connection is made by this kit.

## Messenger package — actual implemented names

| KEY | Required / optional | Secret | Default | Production action |
|---|---|---|---|---|
| `MESSENGER_ENABLED` | Activation flag | No | false | Keep false through schema/config validation; enable inbound pilot first. |
| `MESSENGER_OUTBOUND_ENABLED` | Activation flag | No | false | Remain false until inbound identity is verified; enable controlled reply later. |
| `MESSENGER_APP_ID` | Required operational app identity | No | unset | Confirm owning Meta app; code's callback guard depends on secret/Page/verify token, not App ID alone. |
| `MESSENGER_APP_SECRET` | Required inbound signature verification | YES | unset | Secret store; must match the subscribing app. |
| `MESSENGER_PAGE_ID` | Required | No | unset | Confirm intended real Facebook Page; no local Page value copied. |
| `MESSENGER_PAGE_ACCESS_TOKEN` | Required for send/profile lookup | YES | unset | Authorized Page token with confirmed scopes/app mode; lifecycle/rotation owner. |
| `MESSENGER_VERIFY_TOKEN` | Required webhook verification | YES | unset | High-entropy secret matching Meta verification setup. |
| `MESSENGER_INBOX_USER_ID` | Required operational inbox ownership | No | unset | Existing active authorized JKNC user ID, not a Meta sender ID. Verify non-admin inbox visibility. |
| `MESSENGER_QUEUE_CONNECTION` | Required durable choice | No | database | Explicit Messenger connection, distinct from Gmail's default connection. |
| `MESSENGER_QUEUE` | Optional | No | messaging | Inbound worker queue. |
| `MESSENGER_OUTBOUND_QUEUE` | Optional | No | messenger-outbound | Outbound worker queue. |
| `MESSENGER_GRAPH_VERSION` | Optional version selection | No | v22.0 | Reconfirm Meta supported version and app permissions at activation; do not silently upgrade API version. |
| `MESSENGER_PUBLIC_MEDIA_URL` | Required for supported outgoing image delivery | No | unset | Approved HTTPS public media origin/path; verify expiry/access rules and avoid exposing unrelated private attachments. |

### One-time Meta setup — later

1. Confirm intended app/Page ownership and an authorized Page identity. Configure app's Messenger/messaging use case and verify `pages_messaging` and applicable Page subscription/profile lookup permissions in current dashboard; review mode/app-review requirements for public users.
2. Inject the keys above into the protected production environment with both flags off. Verify Page ID, inbox owner, token scope/lifetime and signature secret without logging their values.
3. Apply reviewed schema and configure supervised inbound/outbound workers. `MessengerWebhookController` requires an async connection and rejects missing config. Run `php artisan messenger:readiness --diagnostics` later; keep diagnostic output private/redacted.
4. After activation approval, set inbound flag true, cache config and restart the appropriate worker. Configure public HTTPS callback `https://<production-domain>/webhooks/messenger` and matching Verify Token. GET verifies; POST uses Meta signature verification and CSRF exemption on this webhook only.
5. Subscribe the actual Page to the correct app, not just configure an app callback. Check current supported fields matching repository ingest logic: `messages`, `message_echoes`, `message_deliveries`, `message_reads`.
6. Verify inbound conversation creation and sender/profile identity from a controlled person messaging the Page. Replaying the same provider event must not create another canonical entry.
7. After inbound passes, enable outbound flag, recache/restart and send one approved reply within applicable messaging policy/window; verify Sent/provider ID and echo/delivery/read reconciliation. No bulk outreach or historical Pending replay.

Meta dashboard names/permissions must be rechecked at live setup. Primary references: [Meta-owned Messenger API collection](https://www.postman.com/meta/messenger-platform-api/documentation/iyp204x/messenger-platform-api), [Meta sample HTTPS deployment](https://github.com/fbsamples/messenger-platform-samples/blob/main/node/README.md), [Meta Page SDK field definitions](https://github.com/facebook/facebook-python-business-sdk/blob/main/facebook_business/adobjects/page.py). The send window and Page-token rules depend on actual app permissions; an app-role test does not prove public production access.

## Calendar and progressive rollout

`GOOGLE_CALENDAR_ID` (non-secret), `GOOGLE_CALENDAR_ACCESS_TOKEN` (SECRET), `GOOGLE_CALENDAR_BRIDGE_URL` (non-secret), `GOOGLE_CALENDAR_BRIDGE_SECRET` (SECRET), `GOOGLE_CALENDAR_API_BASE` (default Google Calendar v3 URL), `GOOGLE_CALENDAR_TIMEZONE` (default Asia/Manila), `GOOGLE_CALENDAR_TIMEOUT` (default 15) are the current Calendar keys. They are optional for core local persistence but required for the chosen external Calendar path. Bridge URL+secret activates the bridge; direct calendar ID+token activates direct calls. No dedicated Calendar flag exists. The Tasks embed is a separate, currently hard-coded calendar source; confirm/port the correct target in the live integration review.

Initial release: all three Gmail/Messenger flags false; no candidate Calendar credentials until verified. Validate local module/schema/permissions first, then Calendar separately if required, Messenger inbound, Messenger outbound, Gmail A, Gmail B, wider rollout. Each change requires config rebuild and worker reload; monitor before the next flag. Disable independently on failure, preserve all records, and consult rollback guidance for already-queued jobs.
