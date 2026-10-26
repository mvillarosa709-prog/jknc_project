# ORDO Transmittal V2

Laravel module for public transmittal submissions and a protected administrator review workflow.

## Setup

Requirements: PHP 8.3+, Composer, Node.js/npm, and a database supported by Laravel.

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Open `/transmittals` (or `/`). Public visitors do not need to log in or create accounts. They can submit transmittals, browse the register, and read transmittal records. Uploaded files and PDF exports require authorized workspace access or a valid, unexpired recipient link.

Set `ORDO_OFFICE_NAME` to the office name shown as the default sender in the transmittal form. The Add drawer supports manual entry and session-protected drafts. The dedicated `/transmittals/smart-upload` page supports bulk drag-and-drop and OCR classification; opening an uploaded draft displays a split document-preview and editing workspace. Draft editing is available in the same browser session that created the draft; submitting it moves it into the administrator review queue.

## Administrator access — no public signup

Administrator sign-in is at `/admin/login`. There is no signup or account creation screen, and regular user accounts cannot use the administrator login. Create or provision the administrator from the server terminal only:

```powershell
php artisan ordo:admin:create admin@example.com --name="Workspace Administrator"
```

The command asks for the password twice without echoing it. Use at least 12 characters. Keep server/terminal access restricted. Administrators can approve or reject pending submissions and transmit approved records. A rejection requires a reason and is recorded in the public record history.

## Safe local browser lifecycle testing

The browser lifecycle fixtures are disposable and are created only when the application environment is `local` or `testing` and the configured database driver is SQLite. They cannot be created or cleaned by these commands in production or against a non-SQLite database. Do not change the environment guard to run these fixtures elsewhere.

From a local checkout using SQLite, run:

```powershell
php artisan migrate
php artisan ordo:browser-test:setup
```

Enter a test-only password of at least 12 characters twice when prompted. The password is not echoed or printed. The command prints a random eight-character tag, the generated administrator email, and three `BROWSER-TEST-{tag}-*` tracking numbers. Sign in at `/admin/login` with that email and the password you entered. The fixture records and PDF attachments are disposable local test data; do not use the command on business data.

In the browser, use the `RESTORE` record to test soft delete and restore, the `PERMANENT` record to test reason and exact tracking-number confirmation, and the `PROTECTED` record to confirm its proof, receipt, and acknowledgment prevent permanent deletion. The generated PDF files are private and contain only minimal placeholder content.

To remove only the fixture records, their matching private test files, their test-only lifecycle archive entries, and the generated test administrator, run:

```powershell
php artisan ordo:browser-test:cleanup {tag}
```

Replace `{tag}` with the exact tag printed by setup. Cleanup accepts only an eight-character lowercase alphanumeric tag and only matches the fixture's `BROWSER-TEST-{tag}-` tracking-number prefix and generated administrator identity. It refuses to run outside local/testing SQLite. If any matching private attachment cannot be removed, cleanup stops and retains the database records.

There is no regular-user sign-in flow: the login endpoint intentionally accepts administrators only. Verify anonymous register restrictions in the browser and regular-user authorization boundaries through the automated feature tests.

## Public access notice

The public register and transmittal details are readable without authentication. Uploaded attachments and generated PDFs are private: administrators and the same browser session that owns an editable draft can access workspace files, while an external recipient can access the delivered attachments and their own TRR using the secure, expiring acknowledgment link. Do not submit sensitive or restricted metadata to a publicly accessible deployment. Administrator decisions remain protected behind the administrator login.

## Review workflow

1. A visitor submits a transmittal; it enters the administrator review queue immediately.
2. An administrator approves or rejects it. Approval generates a frozen snapshot, SHA-256 digest, immutable `ORDO-AUTH-*` authority code, and audit entry. Rejection stores the administrator, timestamp, reason, and history entry.
3. An administrator may transmit approved transmittals through Brevo. Each attempt receives a `TXN-*` reference and the email contains an expiring secure external recipient link; it does not include the attached documents. Sent, Viewed, and Acknowledged are separate immutable Proof of Transmission events.
4. Recipients submit electronic acknowledgments, retained with technical audit data and an official Transmittal Receipt (TRR) reference/PDF linked to the TRN.
5. The permanent workspace contains Transmittal, Receipt, Proof of Transmission, Attachments, and History tabs. PDF export includes a confidentiality-notice cover page followed by the official form.
6. Administrators can add manual Proof of Transmission evidence using a PDF, image, or video upload. The file is stored privately with an integrity hash, OCR suggestions are retained for supported files, and receiver/reference details are recorded as an immutable proof event.

## Database

The eight core tables are `transmittals`, `transmittal_recipients`, `transmittal_documents`, `proof_of_transmissions`, `transmittal_receipts`, `approval_authority_codes`, `transmittal_histories`, and `external_acknowledgments`. The transmittal workspace also uses `transmittal_items` for editable line items and their attachment links.

Additional migrations add administrator role data, document references, rejection decision fields, and nullable internal-user references for public submissions. Apply pending migrations with `php artisan migrate`. Back up any existing database before migrating a deployed installation.

## Smart Upload / OCR

The dedicated upload workspace supports bulk drag-and-drop, reviewable per-file categories, and server-side OCR classification/field suggestions for PDF, JPEG, PNG, and TIFF files. OCR can detect reference/unique ID, subject/description, company, recipient name/email/address, and date; each detected value is labeled with its source and a qualitative confidence level. Suggestions can auto-fill the transmittal subject, reference, company, recipient, and address, and remain editable before a browser-session-protected draft is created. Each uploaded document also creates a List of Items row with available OCR ID/description values. PDF OCR rasterizes at most the first five pages at 150 DPI. OCR text and detected fields are bound to the uploaded content and persisted with its integrity hash. Office files can be attached and categorized manually.

Smart Upload uses private, resumable chunk storage. By default, each file is limited to 1 GiB, chunks are 5 MiB, and the maximum chunk count is capped at 4096 (configurable); unfinished or consumed temporary uploads expire after 24 hours. Each upload has a random capability token whose hash is stored in the database; the raw token is kept only in the server-side browser session and is never sent to or returned to the browser. The browser keeps only the upload ID and chunk metadata in session storage so the user can reselect the same file to resume after a page refresh. Uploads require both the creating session and its capability token. Chunks are size-checked and SHA-256 verified, and identical retries are idempotent. Configure limits with `ORDO_UPLOAD_MAX_BYTES`, `ORDO_UPLOAD_CHUNK_BYTES`, `ORDO_UPLOAD_MAX_CHUNKS`, `ORDO_UPLOAD_EXPIRY_HOURS`, and `ORDO_UPLOAD_COMPLETION_LOCK_SECONDS`; the completion lock must be at least 300 seconds and long enough for the largest expected file assembly. The `ordo:uploads:cleanup` command removes expired private files and rows, and is scheduled daily; ensure Laravel's scheduler is running in each deployment.

Install Tesseract OCR and Poppler (`pdftoppm`) on the application server. Configure executable paths if they are not on `PATH`:

```dotenv
ORDO_TESSERACT_BINARY=tesseract
ORDO_PDFTOPPM_BINARY=pdftoppm
ORDO_OCR_TIMEOUT=30
ORDO_OCR_MAX_FILES=10
ORDO_UPLOAD_MAX_BYTES=1073741824
ORDO_UPLOAD_CHUNK_BYTES=5242880
ORDO_UPLOAD_MAX_CHUNKS=4096
ORDO_UPLOAD_EXPIRY_HOURS=24
ORDO_UPLOAD_COMPLETION_LOCK_SECONDS=3600
```

OCR runs locally. The endpoint reports a configuration/engine error rather than claiming extraction succeeded when OCR is unavailable.

## Brevo notifications

Set the transactional email configuration in `.env`:

```dotenv
BREVO_API_KEY=
BREVO_SENDER_EMAIL=
BREVO_SENDER_NAME="${APP_NAME}"
ORDO_ACKNOWLEDGMENT_DAYS=30
APP_URL=https://your-application.example
```

Use a production `APP_KEY`, enforce HTTPS, and keep credentials in a deployment secret store. Each recipient link is random, stored only as a SHA-256 hash, and expires after the configured number of days. Brevo API acceptance is recorded as table-based proof of transmission. Use a tunnel or deployed HTTPS URL for acknowledgment links tested from another device; `localhost` is only reachable from the same machine.

The module uses atomic cache locks for administrator sends and receipt submissions. Multi-server deployments need a shared cache store that supports atomic locks.

## Production deployment and operations checklist

Before exposing the application publicly:

- Set `APP_ENV=production`, `APP_DEBUG=false`, a generated production `APP_KEY`, and an HTTPS `APP_URL`. Force HTTPS at the load balancer/web server and verify forwarded-proxy configuration before relying on secure URL generation.
- Use a server-side session driver (`database` or Redis), `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax`. Keep the cookie domain/path scoped to the application. Never use the `cookie` session driver for resumable-upload capability storage.
- Keep `FILESYSTEM_DISK=local` for private transmittal files, and ensure `storage/app/private` and Laravel's writable `storage`/`bootstrap/cache` directories are writable only by the application service account. Do not symlink the private root into the web root or publish it through a static web-server alias. The public disk is separate and is not for transmittal attachments.
- Configure PHP-FPM and the web server/proxy upload limits to match the route being used. The resumable endpoint needs at least one configured chunk plus multipart overhead (about 6 MiB with the defaults); ordinary transmittal submission accepts up to 20 files at 20 MiB each, so supporting the maximum direct-upload request requires `upload_max_filesize` of at least 20 MiB and `post_max_size`/proxy limits of about 400 MiB plus overhead. Configure `max_file_uploads` for the supported batch size. If that body limit is not acceptable, lower the normal submission batch limits or route those submissions through resumable uploads. For 1 GiB assembly, set PHP/FPM and upstream request timeouts to accommodate slow storage; OCR itself has its own `ORDO_OCR_TIMEOUT`. Verify effective values in the deployed PHP SAPI, not just the CLI.
- Install Tesseract and Poppler (`pdftoppm`) on the application host, configure `ORDO_TESSERACT_BINARY` and `ORDO_PDFTOPPM_BINARY` to executable paths, and verify the service account can execute them. Test representative scanned PDFs and images in staging.
- Store Brevo credentials in the deployment secret store, set `BREVO_API_KEY`, a verified `BREVO_SENDER_EMAIL`/`BREVO_SENDER_NAME`, `APP_URL`, and `ORDO_ACKNOWLEDGMENT_DAYS`. Verify sender/domain authentication and complete a staging send to a controlled inbox, recipient acknowledgment, and revoked/expired-link checks. An accepted Brevo API response is not proof of inbox delivery.
- Configure the Laravel scheduler on one deployment node. For cron-based deployments, run `php artisan schedule:run` every minute from the application directory. Confirm registration with `php artisan schedule:list`; test the cleanup command with `php artisan ordo:uploads:cleanup` and monitor its output/logs. The scheduled daily cleanup uses `withoutOverlapping`, so production cache must support atomic locks and be shared in a multi-server deployment.
- This implementation sends Brevo requests and performs OCR/uploads synchronously; it does not currently require a queue worker. If queued notifications or processing are introduced later, provision and monitor workers before enabling them.
- Set an operational log channel and retention policy appropriate for the deployment. Do not expose debug pages or logs publicly; alert on cleanup errors, filesystem failures, OCR configuration failures, and Brevo API failures. Define retention and backup policies for database audit/history records and private attachment storage together.

The actual Document Management API documentation and credentials/configuration have not been supplied, so no integration is enabled. Do not configure production credentials until the target API's authentication, endpoints, data-retention rules, and test environment are documented.

## Tests

```powershell
php artisan test --filter=TransmittalWorkflowTest
php artisan test
```

Tests cover public access without login, absence of signup/team account creation, Smart Upload drafts and workspace edits, resumable-upload ownership, retries, integrity verification and draft import, private file access for administrators and authorized recipients, administrator-only approval/rejection/manual proof uploads, Brevo notifications and acknowledgments, OCR error reporting, and protected PDF generation.
