# Messaging attachment fix and provider readiness

Repository: C:\Users\dimpa\Herd\jknc_project
(The supplied C:\Users\dimpa\Herd\jknc\_project path does not exist on this machine.)

## Attachment diagnosis and fix

Normal Alpine array push worked in the baseline browser test; it was not the demonstrated cause. The lifecycle had no conversation-session/request guards: a stale attachment-list response could replace the list containing a confirmed upload, and an upload completing after navigation could select its ID in the next conversation. The baseline browser fixture reproduced both failures. Relationship-save also reopened the conversation, intentionally clearing draft state during an otherwise ordinary refresh.

The patch introduces draft-session and attachment-request counters, ignores late responses, immediately upserts the canonical upload response, and preserves selected confirmed uploads across older list snapshots. Selection uses immutable normalized ID arrays. Relationship refresh and successful Store Reply refresh details without reopening. Upload no longer refreshes the entire activities collection. Upload and reply submission cannot overlap.

Exactly one composer remains. Current reply attachments contains selected files, with existing thumbnail/icon, name, size, Open and Remove controls. Conversation Files contains unselected files, starts collapsed, has a count, and now offers Use in reply. Remove changes selection only. Store Reply still sends attachment_ids: [...this.messageDraftAttachmentIds]. Successful storage clears draft text/IDs and reloads entry details. Leaving/reopening/reloading resets the draft explicitly; uploaded files remain stored. Existing search, sidebar, permissions and per-entry pivot rendering are unchanged. Historical files are never backfilled into old bubbles.

## Existing foundation audit

MessageConversation already holds channel, line_account, external_conversation_id, company/contact/owner relationships and technical_metadata. MessageEntry already holds channel, external_message_id, direction, status, sent_at, received_at, delivered_at, read_at and technical_metadata. The existing unique (channel, external_message_id) key is retained.

ActivityDuplicateGuard remains the local create/reply guard. It uses body-based fingerprints and conflict responses; it is not reused for provider retry acknowledgements. Provider retries use durable envelope fingerprints, row locks, provider message IDs and the existing unique key. Case/identity collisions are rejected rather than silently attributed to another peer.

ActivityAttachmentService and message_entry_attachments remain canonical. Existing local files and remote provider references use the same serializer and bubble relationship. No new attachment store or message store was introduced.

Before this patch there were no Messenger/WhatsApp/Viber/SMS provider adapters or webhook routes. line_account was a free-form account field, not a credential vault or verified identity mapping. config/services.php contains unrelated service integrations and was left untouched. Dedicated config/messaging.php holds server-only expectations. Database queue infrastructure exists; this foundation uses its own messaging queue/connection instead of changing the application's global queue behavior.

Current access rules remain: administrators/superadministrators see conversations; other users require existing assigned/prepared ownership and Activities module access. Inbound ownership can be assigned through MESSENGER_INBOX_USER_ID. Unassigned new inbound conversations remain administrator-visible, not globally exposed. PSIDs do not supply verified phone/email identities; contact links are reused only from explicit mappings or an exact existing conversation. No name-based or company-name guessing.

## Provider readiness matrix

| Order / provider | Current support | Missing pieces | Webhook requirements | Outbound API requirements | Credentials/config | Media implications | Cost implications |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 1 Messenger | Unified local channel; new disabled inbound foundation, peer matching, deduplication, media references, delivery/read handling | Meta setup and deployment verification; outbound transport intentionally absent | Public HTTPS callback, verification token, App Secret HMAC-SHA256, Page subscription | Future Page /messages requests, PSID, policy-window checks, safe outbox/retries | App ID/Secret, Page ID/token, verify token, inbox owner, queue | Allowlisted Meta HTTPS media references linked to exact entries; URLs may expire; no server download | Hosting/storage/operations; review any paid messaging/ad or partner features separately; no per-message price assumed |
| 2 WhatsApp | Shared local models, timestamps, attachment pivots and channel UI only | Cloud API adapter, WABA/phone mapping, template/consent handling, media retrieval, status mapping | Meta callback verification/signature and WABA subscription | Phone Number ID /messages, templates when required, recipient policies | Meta business portfolio, WABA ID, Phone Number ID, token, App Secret, verify token; whatsapp_business_messaging and whatsapp_business_management | Media IDs require authorized retrieval; validate MIME/size and store safely | Delivered-message pricing varies by category/market; service and eligible response utility messages have free treatment |
| 3 Viber | Shared local foundation only | Bot adapter, subscriber matching, opt-out handling, callbacks/outbox | HTTPS bot webhook, X-Viber-Content-Signature validation, callback deduplication | Bot send_message and subscriber policy | Commercial bot/account approval, bot authentication token, webhook URL | Provider media constraints and accessible URLs; validate file type/size | Commercial bot terms apply; obtain an account-specific quote |
| 4 SMS | Shared local foundation only; provider not selected | Gateway selection, normalized phone matching, consent/STOP handling, segment accounting, status mapping | Gateway-specific inbound/status callbacks and signature validation | Gateway send API, registered/approved sender and destination rules | For a Twilio option: account SID, API credentials, auth token for webhook validation, sender/Messaging Service | SMS has no binary attachments; MMS/link delivery requires separate coverage/security decisions | Per segment/destination/carrier plus sender/number and possible registration fees |

This order follows the user's preference; inspection found no stronger reason to reorder it. SMS/Twilio is an audit example, not an installed or selected provider.

Provider requirement sources: [Meta Messenger collection](https://www.postman.com/meta/messenger-platform-api/documentation/iyp204x/messenger-platform-api), [Meta WhatsApp collection](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api), [WhatsApp pricing](https://whatsappbusiness.com/products/platform-pricing/), [Viber API](https://developers.viber.com/docs/api/rest-bot-api/), [Viber commercial model](https://help.viber.com/hc/en-us/articles/15247629658525-Bot-commercial-model), [Twilio webhook guidance](https://www.twilio.com/docs/messaging/guides/webhook-request), [Twilio Philippines pricing](https://www.twilio.com/en-us/sms/pricing/ph). Prices were not hardcoded.

## Messenger foundation implemented

- MessagingProvider contract and MessengerProvider adapter. send() always throws; configuring credentials cannot enable sending.
- GET /webhooks/messenger handles verification. POST at the same path checks the raw-body X-Hub-Signature-256, configured Page ID, envelope shape and payload limit before accepting.
- New additive tables: messaging_webhook_events, messaging_provider_peers, messaging_provider_receipts. Only this new migration was applied locally.
- Durable encrypted webhook payloads, queue jobs containing event IDs only, transactional ingestion, locks and duplicate checks. Processed payloads are purged; failed payloads remain encrypted for investigation/retry.
- Five job attempts with bounded backoff; redacted failure logs/error codes. Database queue defaults isolate this from the existing global sync queue. Repeated Meta delivery can redispatch an unprocessed event; Laravel failed-job retry can reprocess exhausted jobs.
- Exact Page ID + PSID identity matching; incoming canonical MessageEntry creation. Existing contact/company links remain intact.
- Explicit image/audio/video/file attachments become canonical external references and pivots. Only HTTPS Meta CDN subdomains are accepted; no arbitrary server fetch occurs. MIME is not invented. Unsupported payloads remain a descriptive message rather than executable content.
- Page-origin echoes create separate provider-observed records. Delivery/read receipts are monotonic and retained for out-of-order echoes. They never promote existing local Pending messages.
- No changes to current local message controller, models, attachment serializer, permissions, Calendar, Meet, Task timing, production messaging or .env.

Media limitation: these are remote media references, not durable downloaded copies. Meta URLs can expire; reliable long-term media retention requires a separately reviewed fetch/storage implementation. Unsupported non-CDN URLs are deliberately not rendered. This is an inbound foundation, not a claim of production-complete media archival.

## Configuration boundary

No real credentials were read, written or connected. The new config is disabled by default.

| Setting | Obtain / purpose |
| --- | --- |
| MESSENGER_ENABLED | Keep false until explicitly approving inbound activation |
| MESSENGER_APP_ID | Meta App Dashboard app identifier; recorded configuration, not required to compute signatures |
| MESSENGER_APP_SECRET | Same app's secret; required for webhook HMAC verification |
| MESSENGER_PAGE_ID | Facebook Page being connected; required account boundary |
| MESSENGER_PAGE_ACCESS_TOKEN | Page token for a person/system identity authorized for the Page; reserved for future outbound API use, unused by this foundation |
| MESSENGER_VERIFY_TOKEN | Generate a private random verification string yourself; enter identical value in Meta webhook setup and server config |
| MESSENGER_GRAPH_VERSION | v22.0 placeholder/pinned future transport contract; no Graph API call is currently made; verify supported version before implementing outbound |
| MESSENGER_INBOX_USER_ID | Existing JK&C user ID to own new inbound conversations; blank means administrator-only until assigned |
| MESSENGER_QUEUE_CONNECTION | database by default |
| MESSENGER_QUEUE | messaging by default |

Callback: https://<your-public-host>/webhooks/messenger, with trusted HTTPS. The local Herd hostname is not a public callback. Configure the Page subscription for message, echo, delivery and read events in the Meta app. Dashboard subscription field availability and app access/review must be checked on the actual app before activation; no remote subscription was created here.

Meta's current collection specifies pages_messaging and a Page access token obtained by someone with the Page MESSAGE task. Future Send API responses must respect the standard 24-hour window or an allowed exception. This foundation does not implement login, Page discovery, automated subscription management or conversations API access, so it does not request extra permissions for those flows. Those flows would need a separate scope review. The verify-token and signature implementation follows [Meta's quick start](https://raw.githubusercontent.com/fbsamples/messenger-platform-samples/main/quick-start/app.js) and [signature helper](https://raw.githubusercontent.com/fbsamples/messenger-platform-samples/main/utils/webhook-signature.js). Some developers.facebook.com reference pages were rate-limited; no unverified extra scope list is presented as mandatory.

Before inbound live connectivity: provision public HTTPS, supply app/Page configuration, approve inbound activation, subscribe the Page and run the messaging queue worker. Validate with an authorized test account and complete Meta app access/review requirements for the intended audience. Credentials alone cannot substitute for these deployment steps.

Before live sending: explicit approval, an outbound adapter/outbox with idempotency and timeout/retry handling, policy/permission checks, attachment upload handling, controlled test recipients and production acceptance testing. Existing Pending history must never be replayed automatically. send() remains intentionally unavailable.

## Changed files

Existing files:
- resources/views/activities/index.blade.php
- resources/views/activities/partials/inline-details.blade.php
- routes/web.php (only includes the new route file)

New files:
- config/messaging.php
- routes/messaging.php
- app/Contracts/MessagingProvider.php
- app/Http/Controllers/MessengerWebhookController.php
- app/Jobs/ProcessMessagingWebhook.php
- app/Models/MessagingWebhookEvent.php
- app/Models/MessagingProviderPeer.php
- app/Services/Messaging/MessengerProvider.php
- database/migrations/2026_09_14_170000_create_messaging_provider_foundation.php
- tests/Feature/MessengerFoundationTest.php
- tests/Frontend/message-composer-regression.cjs
- docs/messaging-provider-readiness.md

Pre-change copies of existing files are retained in the task workspace's work/messaging-baseline directory. Unrelated dirty repository work was preserved.

## Verification

- Actual-source Alpine/Edge fixture: ordinary baseline push works; baseline stale-list and late-upload defects reproduced; corrected version passes. Visible draft card, Remove, collapsed Conversation Files, Use in reply and no browser errors checked.
- Conversation 2 local transaction: actual attachment upload and Store Reply controllers; Outgoing / Pending / external_delivery_attempted=false; exactly one pivot; linked file in canonical detail response; conversation file retained. Rolled back and removed only the newly uploaded test file.
- Actual local database Messenger transaction: incoming entry, duplicate job, explicit media pivot, canonical safe_url and encrypted payload purge; rolled back all test records.
- Isolated SQLite Messenger tests cover verification, signature/account rejection, queue payload, encryption/purge, deduplication, media URL rejection, out-of-order receipts, Pending preservation, hard-disabled outbound, transaction rollback, relationship preservation and malformed envelope rejection.
- Browser verification uses the actual composer markup/methods with mocked HTTP; it is not an authenticated whole-application browser session. Backend linkage was independently exercised through the actual local controllers.
- The checked-in browser runner requires Playwright (PLAYWRIGHT_MODULE may point to an existing installation), Edge, and network access to the same Alpine CDN used by the application. No dependency manifest was changed.

### Final validation results

- PHP lint: all 11 changed PHP files passed.
- php artisan view:clear: passed.
- php artisan view:cache: passed.
- Messenger tests: 8 passed, 62 assertions.
- Checked-in Edge composer regression: passed; no browser errors.
- Both actual-local-database transactional checks passed and rolled back.
- Relevant messaging, attachment and webhook routes were inspected.
- Scoped git diff --check reports one existing routes/web.php blank line at EOF. Comparing each edited file with its pre-task backup reports no newly introduced whitespace defects; that unrelated existing line was preserved. CRLF conversion warnings are Git configuration notices.
