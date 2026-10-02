// Local filesystem inventory only. No application boot, database, network or Git writes.
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync } from 'node:child_process';
const root = path.resolve(import.meta.dirname, '../../..');
const out = path.join(root, 'docs/deployment');
const read = p => fs.readFileSync(path.join(root, p), 'utf8');
const exists = p => fs.existsSync(path.join(root, p));
function walk(dir) {
    if (!exists(dir)) return [];
    return fs.readdirSync(path.join(root, dir), { withFileTypes: true }).flatMap(e => {
        const p = `${dir}/${e.name}`.replace(/^\//, '');
        if (e.isSymbolicLink() || ['.git', 'vendor', 'node_modules'].includes(e.name)) return [];
        return e.isDirectory() ? walk(p) : [p];
    });
}
const csv = rows => rows.map(row => row.map(x => `"${String(x ?? '').replaceAll('"', '""')}"`).join(',')).join('\n') + '\n';
const save = (p, s) => fs.writeFileSync(path.join(out, p), s, 'utf8');
const moduleOf = p => /Gmail|Email|email_|gmail/i.test(p) ? 'Email/Gmail' : /Messenger|Messaging|Message|message_|messaging/i.test(p) ? 'Messages/Messenger' : /OfficialBusiness|official[-_]business|TravelCredit|travel_credit|human-capital\/obf/i.test(p) ? 'Official Business' : /Task|task_/i.test(p) ? 'Tasks' : /Meeting|Event|meeting|event/i.test(p) ? 'Meetings/Events' : /Report|report|XlsxExport/i.test(p) ? 'Reports' : /Company|Contact|company|contact|Timeline/i.test(p) ? 'Company/Contact timelines' : 'Activities core';
const featurePattern = /Activity|Activities|Task|Meeting|Event|Call|Message|Messaging|Messenger|Email|Gmail|OfficialBusiness|TravelCredit|Visitor|VisitMovement/;
const appFiles = walk('app').filter(p => p.endsWith('.php'));
const roots = appFiles.filter(p => featurePattern.test(path.basename(p)) && !/Assessment|TownHall|StockTransfer/.test(p));
roots.push('app/Models/Visit.php', 'app/Http/Controllers/VisitController.php', 'app/Providers/AppServiceProvider.php', 'app/Notifications/SystemRealtimeNotification.php');
const selected = new Map();
function add(p, evidence, scope = 'Feature') { if (exists(p) && !selected.has(p)) selected.set(p, { evidence, scope }); }
roots.forEach(p => add(p, 'Named Activities runtime entry point; traced imports below'));
['app/Http/Controllers/CompanyController.php', 'app/Http/Controllers/ContactsController.php', 'app/Http/Controllers/FinanceController.php', 'app/Http/Controllers/PayrollController.php', 'app/Http/Controllers/AdminHumanCapitalDashboardController.php', 'app/Http/Controllers/AdminUserPermissionController.php', 'app/Http/Controllers/RolePermissionController.php', 'app/Models/User.php', 'app/Models/UserPermission.php', 'app/Models/RolePermission.php', 'app/Http/Middleware/HumanCapitalModuleAccess.php', 'app/Http/Middleware/EnsurePasswordChanged.php', 'app/Http/Middleware/PreventBackHistory.php', 'database/seeders/RolePermissionSeeder.php'].forEach(p => add(p, 'Shared timeline, finance, authorization or navigation integration; merge only relevant hunks', 'Shared integration'));
const classFiles = new Map(appFiles.map(p => ['App\\' + p.slice(4, -4).replaceAll('/', '\\'), p]));
for (let changed = true; changed;) {
    changed = false;
    for (const [p] of [...selected]) {
        if (!p.endsWith('.php') || !p.startsWith('app/')) continue;
        const source = read(p);
        const ns = source.match(/namespace\s+([^;]+);/)?.[1];
        const references = [...source.matchAll(/(?:use\s+|\\)(App\\[A-Za-z0-9_\\]+)/g)].map(m => m[1]);
        for (const m of source.matchAll(/\b([A-Z][A-Za-z0-9_]*)::(?:class|query|find|where|create|with|pluck)/g)) references.push(`${ns}\\${m[1]}`);
        for (const ref of references) {
            const target = classFiles.get(ref);
            if (target && !selected.has(target)) { add(target, `App class dependency referenced by ${p}`, 'Shared dependency'); changed = true; }
        }
    }
}
['routes/web.php', 'routes/messaging.php', 'routes/channels.php', 'routes/console.php', 'bootstrap/app.php', 'bootstrap/providers.php', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', 'resources/css/app.css', 'resources/js/app.js', 'resources/js/bootstrap.js', 'resources/js/echo.js'].forEach(p => add(p, 'Shared routing, scheduler, dependency lock or build entry; merge relevant hunks', 'Shared integration'));
const viewSeeds = ['activities.index', 'activities.partials.inline-details', 'activities.partials.calendar', 'company.activities', 'company.company', 'company.partials.company-header', 'contacts.show', 'human-capital.obf', 'layouts.app', 'partials.activities-app', 'partials.activity-relationships'];
for (const p of roots.filter(p => /Controllers|Services/.test(p))) {
    for (const m of read(p).matchAll(/(?:view|loadView)\(\s*['"]([\w.-]+)['"]/g)) viewSeeds.push(m[1]);
}
walk('resources/views').filter(p => p.endsWith('.blade.php') && /official.business|activities/i.test(p)).forEach(p => add(p, 'Activities or O.B. template/export surface'));
viewSeeds.forEach(v => add('resources/views/' + v.replaceAll('.', '/') + '.blade.php', 'Runtime Activities view/layout reference'));
for (let changed = true; changed;) {
    changed = false;
    for (const [p] of [...selected]) if (p.endsWith('.blade.php')) {
        for (const m of read(p).matchAll(/@(?:include|extends|includeIf)\(\s*['"]([\w.-]+)['"]/g)) {
            const target = 'resources/views/' + m[1].replaceAll('.', '/') + '.blade.php';
            if (exists(target) && !selected.has(target)) { add(target, `Blade dependency referenced by ${p}`, 'Shared dependency'); changed = true; }
        }
    }
}
['.env.example', 'config/app.php', 'config/services.php', 'config/messaging.php', 'config/queue.php', 'config/cache.php', 'config/database.php', 'config/filesystems.php', 'config/session.php', 'config/mail.php', 'config/broadcasting.php', 'config/reverb.php', 'config/dompdf.php', 'config/logging.php', 'config/auth.php'].forEach(p => add(p, 'Config dependency of the Activities runtime', 'Shared integration'));
const migrationPattern = /(?:activities|activity|task|events|meetings|calls|message_(?:conversations|entries|entry)|messaging|messenger|email_(?:activities|mailbox)|official_business|company_travel_credit|visitors|visits|visit_movements|create_notes)/i;
const baselinePattern = /create_(?:users|cache|jobs|companies|contacts|employees|deals|projects|branches|departments|user_permissions|role_permissions|human_capital_logs|finance_records|notifications)_table|add_human_capital_module_permissions|add_account_security_fields|add_role_to_users/;
const migrations = walk('database/migrations').filter(p => p.endsWith('.php') && (migrationPattern.test(p) || baselinePattern.test(p) || /access_activities/.test(read(p))));
const migRows = [];
for (const p of migrations.sort()) {
    const s = read(p), up = s.split(/public function down/)[0];
    const retainedLegacy = p.endsWith('2026_09_04_135548_drop_company_activities_table.php');
    const mailboxKey = p.endsWith('2026_10_01_180000_scope_email_activities_idempotency_to_mailbox.php');
    const tables = [...new Set([...up.matchAll(/(?:Schema::(?:create|table|hasTable)|DB::table)\(\s*['"]([^'"]+)/g)].map(m => m[1]))];
    if (retainedLegacy) tables.push('company_activities');
    const created = [...up.matchAll(/Schema::create\(\s*['"]([^'"]+)/g)].map(m => m[1]);
    const foreign = [...new Set([...up.matchAll(/->(?:constrained|on)\(\s*['"]([^'"]+)/g)].map(m => m[1]))];
    const deps = [...new Set([...tables.filter(t => !created.includes(t)), ...foreign])];
    const data = /(?:->update\(|->insert\(|->delete\(|->save\(|->upsert\(|DB::statement|DB::unprepared)/.test(up);
    const drop = /dropIfExists|dropColumn|dropForeign|dropUnique|->drop\(/.test(up);
    const risk = retainedLegacy ? 'LOW: up/down retain legacy table unchanged; reconcile any already-applied former destructive version'
        : mailboxKey ? 'MEDIUM: replace global key index with mailbox-scoped uniqueness; rehearse index DDL, no row deletion'
        : drop ? 'HIGH: destructive operation in up(); STOP for data review' : data ? 'HIGH: data rewrite/backfill; inspect source and rehearse' : /unique\(|change\(/.test(up) ? 'MEDIUM/HIGH: uniqueness/type/locking preflight required' : 'MEDIUM: DDL lock/capacity and FK compatibility review';
    const purpose = path.basename(p).replace(/^\d{4}_\d{2}_\d{2}_\d+_/, '').replace(/\.php$/, '').replaceAll('_', ' ');
    migRows.push([path.basename(p), moduleOf(p), migrationPattern.test(p) ? 'Feature inventory' : 'Shared prerequisite', purpose, tables.join('; ') || 'Dynamic tables: inspect source', deps.join('; ') || 'See dynamic table loops/source', risk, created.length ? 'down() drops created tables: never blindly rollback after live records exist' : 'down() may remove fields/indexes or cannot restore rewritten data; forward-fix preferred', data ? 'Embedded data mutation; inspect complete up() before execution' : /master_personnel/.test(p) ? 'Separate ob:v3-backfill-master-personnel dry-run/review required' : 'No direct data mutation detected; inspect dynamic helpers', 'VERIFY AGAINST PRODUCTION MIGRATIONS TABLE', crypto.createHash('sha256').update(s).digest('hex')]);
    if (retainedLegacy) {
        migRows.at(-1)[3] = 'Retain legacy company_activities; staged archive/mapping/removal deferred';
        migRows.at(-1)[7] = 'No-op down retains rows; cannot restore data lost by an already-applied old drop';
        migRows.at(-1)[8] = 'No verified backfill exists; preserve legacy rows for owner-reviewed mapping in a later release';
    }
    if (mailboxKey) migRows.at(-1)[7] = 'down refuses cross-mailbox duplicate keys before DDL; forward-fix after live use';
    if (p.endsWith('2026_10_01_180001_add_email_mailbox_sync_checkpoint.php')) {
        migRows.at(-1)[7] = 'down discards unfinished sync checkpoints/status; retain fields and forward-fix after sync begins';
        migRows.at(-1)[8] = 'Additive defaulted state fields; no automatic mail backfill or file migration; pre-live feature';
    }
    add(p, 'Reviewed migration source targets and constraints; pending status unknown', migrationPattern.test(p) ? 'Feature migration' : 'Shared prerequisite');
}
const testFiles = walk('tests').filter(p => /\.(php|cjs)$/.test(p) && (featurePattern.test(path.basename(p)) || /all-activities|ob-|messenger-|message-|tasks-messages/.test(p)));
['tests/Pest.php', 'tests/TestCase.php', 'phpunit.xml'].forEach(p => testFiles.push(p));
testFiles.forEach(p => add(p, 'Existing test or shared test harness; not runtime deployment', 'Validation'));
walk('docs').filter(p => !p.startsWith('docs/deployment/') && /activit|official-business|ob-v3|messeng|messaging/i.test(p)).forEach(p => add(p, 'Existing feature documentation; not application runtime', 'Documentation'));
const excluded = walk('').filter(p => /(?:\.bak$|\.before-|\.retired\.|\.zip$|\.log$)|^(?:storage\/|tmp\/|database\/data-export\/|activities-report-preview\/|\.env(?:\.|$)|auth\.json$|public\/(?:build|hot|storage)(?:\/|$))/.test(p) && p !== '.env.example');
const records = [...selected].filter(([p]) => !excluded.includes(p)).map(([p, info]) => {
    const category = p.startsWith('database/migrations/') ? 'B Database migrations' : p.startsWith('config/') || p === '.env.example' ? 'C Configuration' : p.startsWith('tests/') || p === 'phpunit.xml' ? 'D Tests' : p.startsWith('docs/') ? 'E Documentation' : 'A Production application code';
    const runtime = /^[ABC]/.test(category);
    const s = /\.(?:php|cjs|js|css|json)$/.test(p) ? read(p) : '';
    const purpose = `${path.basename(p)}: ${info.evidence}`;
    return [p, category, moduleOf(p), purpose, runtime ? 'YES (review/port; never wholesale overwrite shared files)' : 'NO (build/review artifact only)', /^(app\/|database\/migrations\/|resources\/views\/)/.test(p) ? 'YES: relevant schema/prerequisites' : 'NO / shared baseline', runtime ? 'YES: application config; module keys in environment manifest' : 'NO', /Jobs\/|Console\/Commands\/(?:DispatchActivity|GenerateRecurring)|bootstrap\/app|dispatch\(|ShouldQueue/.test(p + '\n' + s) ? 'YES: inspect queue/scheduler matrix' : 'NO direct; inherited dependencies may apply', info.scope, info.evidence, crypto.createHash('sha256').update(fs.readFileSync(path.join(root,p))).digest('hex')];
}).sort((a,b) => a[2].localeCompare(b[2]) || a[0].localeCompare(b[0]));
save('activities-deployment-manifest.csv', csv([['path','category','module','purpose','required_in_production','migration_dependent','config_dependent','queue_scheduler_dependent','scope','trace_evidence','sha256'], ...records]));
save('activities-migration-inventory.csv', csv([['filename','module','scope','purpose','tables','dependencies','risk','rollback_notes','backfill_implications','production_status','sha256'], ...migRows]));
save('activities-do-not-deploy.csv', csv([['path','category','production','reason'], ...excluded.sort().map(p => [p,'F Local-only/backups/generated','NEVER','Local secrets, backup, uploaded/runtime data or generated artifact; preserve production storage separately'])]));
save('activities-test-matrix.csv', csv([['path','module','test_names','execution_context','status'], ...testFiles.filter(exists).sort().map(p => [p,moduleOf(p),p.endsWith('.php') ? [...read(p).matchAll(/(?:test|it)\(\s*['"]([^'"]+)/g)].map(m=>m[1]).join('; ') : 'Standalone frontend regression script / shared harness',p.endsWith('.cjs') ? 'Node; inspect browser/fixture dependencies before running' : 'Disposable isolated test environment only; fixtures may invoke migrations', 'INVENTORIED; not a claim of passing tests'])]));
// Extract names/default EXPRESSIONS from config source, never real .env values.
const envFiles = [...selected.keys()].filter(p => p.startsWith('config/') || p.startsWith('app/') || p === 'bootstrap/app.php');
const envRows = new Map();
for (const p of envFiles) for (const m of read(p).matchAll(/env\(\s*['"]([A-Z][A-Z0-9_]+)['"]\s*(?:,\s*([^\n]+?))?\)/g)) {
    const key = m[1];
    const secret = /(?:SECRET|TOKEN|PASSWORD)$/.test(key) || ['APP_KEY','APP_PREVIOUS_KEYS','AWS_ACCESS_KEY_ID','ABLY_KEY','DB_URL','REDIS_URL','MAIL_URL','LOG_SLACK_WEBHOOK_URL'].includes(key);
    const conditional = /^(?:AWS_|SQS_|REDIS_|BEANSTALKD_|MAIL_|POSTMARK_|RESEND_|BROADCAST_|REVERB_|PUSHER_|LOG_|DB_QUEUE_|MEMCACHED_|DYNAMODB_)/.test(key);
    const module = /^GMAIL_|^GOOGLE_(?:CLIENT|REDIRECT)/.test(key) ? 'Email/Gmail' : /^MESSENGER_/.test(key) ? 'Messages/Messenger' : /^GOOGLE_CALENDAR_/.test(key) ? 'Tasks; Meetings/Events' : 'Shared infrastructure';
    const required = ['APP_KEY','APP_URL','APP_ENV','APP_DEBUG','DB_CONNECTION','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','QUEUE_CONNECTION','CACHE_STORE','SESSION_DRIVER'].includes(key) ? 'REQUIRED' : secret || /CLIENT_ID|REDIRECT_URI|PAGE_ID|INBOX_USER_ID|CALENDAR_ID|MESSENGER_APP_ID/.test(key) ? 'REQUIRED when corresponding integration/backend is enabled; verify runbook' : 'OPTIONAL / default or backend-dependent';
    let action = conditional ? 'Configure only for selected infrastructure backend; do not copy local values' : 'Review source default against production requirements';
    if (/ENABLED$/.test(key)) action = 'Initial false; activate only after integration gates pass';
    if (key === 'QUEUE_CONNECTION') action = 'Async database or Redis; never sync for production Gmail';
    if (/QUEUE_RETRY_AFTER/.test(key)) action = 'Use at least 180 seconds for 120-second Gmail sync jobs; worker timeout must be lower';
    if (key === 'APP_KEY') action = 'Preserve production key; required to decrypt Gmail tokens; never regenerate';
    if (key === 'GOOGLE_REDIRECT_URI') action = 'https://<production-domain>/api/email/gmail/callback';
    if (secret) action += '; inject through protected secrets manager/environment';
    if (envRows.has(key)) envRows.get(key)[7] += `; ${p}`;
    else envRows.set(key,[key,module,required,secret ? 'SECRET' : 'NON-SECRET',secret ? '(not reproduced)' : (m[2]?.trim() || '(unset)'),action,exists('.env.example') && new RegExp(`^${key}=`, 'm').test(read('.env.example')) ? 'YES' : 'NO',p]);
}
save('activities-environment-keys.csv',csv([['key','module','requirement','classification','source_default_expression','production_action','in_env_example','source_files'],...[...envRows.values()].sort((a,b)=>a[1].localeCompare(b[1])||a[0].localeCompare(b[0]))]));
const head = execFileSync('git',['rev-parse','HEAD'],{cwd:root,encoding:'utf8'}).trim();
save('inventory-summary.json',JSON.stringify({ generated_at:new Date().toISOString(),head,warning:'Dirty local working tree; hashes identify this snapshot, not a deployable commit or a production delta. Shared imports are a dependency closure and require integration review. No live baseline exists.',manifest_records:records.length,migrations:migRows.length,excluded_local_files:excluded.length,tests:testFiles.filter(exists).length,env_keys:envRows.size,by_module:Object.fromEntries([...new Set(records.map(r=>r[2]))].map(m=>[m,records.filter(r=>r[2]===m).length]))},null,2)+'\n');
const tables = [...new Set(['migrations','users','companies','contacts','employees','projects','deals','user_permissions','role_permissions','tasks','events','calls','meetings','notes','task_templates','task_timers','task_assignments','task_dependencies','activity_links','activity_attachments','activity_audit_logs','meeting_action_items','meeting_minutes_versions','message_conversations','message_entries','message_entry_attachments','messaging_webhook_events','messaging_provider_peers','messaging_provider_receipts','messenger_outbound_attempts','email_activities','email_mailbox_connections','official_business_trips','official_business_trip_tickets','official_business_trip_ticket_stops','official_business_trip_ticket_personnel','official_business_trip_ticket_financial_treatments','official_business_trip_ticket_costs','official_business_trip_personnel','company_travel_credit_accounts','company_travel_credit_entries','company_activities','visitors','visits','visit_movements','jobs','job_batches','failed_jobs','cache','cache_locks','notifications',...migRows.flatMap(r=>r[4].split('; ').filter(t=>/^[a-z][a-z0-9_]+$/.test(t)))])].sort();
const tableList = tables.map(t=>`'${t}'`).join(',\n    ');
save('production-schema-readonly.sql',`-- MySQL/MariaDB: LOCAL PREPARATION ONLY. Never executed by this kit.
-- Run with a read-only account. No application records or tokens are selected.
-- Stage 1: metadata. table_rows is approximate for InnoDB.
SELECT VERSION() AS server_version;
SELECT DATABASE() AS selected_database;
SELECT @@sql_mode AS sql_mode, @@character_set_database AS database_charset, @@collation_database AS database_collation;
SELECT table_name, engine, table_rows AS approximate_rows, data_length, index_length,
       ROUND((data_length + index_length) / 1024 / 1024, 2) AS total_mb, table_collation
FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name IN (${tableList}) ORDER BY table_name;
SELECT table_name, column_name, column_type, is_nullable, column_default, character_set_name, collation_name, extra
FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name IN (${tableList})
ORDER BY table_name, ordinal_position;
SELECT table_name, index_name, non_unique, seq_in_index, column_name, sub_part, index_type
FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name IN (${tableList})
ORDER BY table_name, index_name, seq_in_index;
SELECT k.table_name, k.constraint_name, k.column_name, k.referenced_table_name, k.referenced_column_name,
       r.update_rule, r.delete_rule
FROM information_schema.key_column_usage k
LEFT JOIN information_schema.referential_constraints r ON r.constraint_schema = k.constraint_schema AND r.constraint_name = k.constraint_name AND r.table_name = k.table_name
WHERE k.table_schema = DATABASE() AND k.table_name IN (${tableList}) AND k.referenced_table_name IS NOT NULL
ORDER BY k.table_name, k.constraint_name, k.ordinal_position;
-- Required Gmail base FK types: users.id and email_activities.mailbox_owner_user_id.
SELECT table_name, column_name, column_type, is_nullable
FROM information_schema.columns WHERE table_schema = DATABASE()
AND ((table_name = 'users' AND column_name = 'id') OR
     (table_name = 'email_activities' AND column_name IN ('id','mailbox_owner_user_id','provider','mailbox_key','provider_message_id','mailbox_connection_id','thread_id','idempotency_key')) OR
     (table_name = 'email_mailbox_connections' AND column_name IN ('id','user_id','provider','email_address','sync_checkpoint','sync_status','initial_sync_status','initial_sync_count')));
SELECT expected.table_name, expected.column_name, c.column_type,
       CASE WHEN c.column_name IS NULL THEN 'STOP: prerequisite missing' ELSE 'PRESENT: review type/charset/nullability' END AS compatibility
FROM (
    SELECT 'users' AS table_name, 'id' AS column_name
    UNION ALL SELECT 'email_activities','id'
    UNION ALL SELECT 'email_activities','mailbox_owner_user_id'
    UNION ALL SELECT 'email_activities','provider'
    UNION ALL SELECT 'email_activities','mailbox_key'
    UNION ALL SELECT 'email_activities','provider_message_id'
) expected LEFT JOIN information_schema.columns c ON c.table_schema=DATABASE() AND c.table_name=expected.table_name AND c.column_name=expected.column_name;
SELECT table_name, column_name, column_type,
       CASE WHEN LOWER(column_type) LIKE 'bigint%unsigned' THEN 'TYPE MATCH: BIGINT UNSIGNED' ELSE 'STOP: verify Gmail FK type mismatch' END AS gmail_fk_compatibility
FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='users' AND column_name='id') OR (table_name='email_activities' AND column_name='mailbox_owner_user_id'));
SELECT table_name, column_name, 'IF MIGRATION UNRECORDED: STOP for partial-schema reconciliation' AS partial_migration_check
FROM information_schema.columns WHERE table_schema=DATABASE() AND
((table_name='email_mailbox_connections') OR (table_name='email_activities' AND column_name IN ('mailbox_connection_id','thread_id','rfc_message_id','in_reply_to','references_header','bcc_recipients','delivery_status','sent_at','provider_error','idempotency_key')));
-- Non-idempotent Gmail migrations: any Gmail target fields already present while their migration
-- is unrecorded require reconciliation, not a rerun. All users FK IDs must match BIGINT UNSIGNED.
-- Stage 2: generated SELECTs. Copy and run the returned sql_to_run statements using the same
-- read-only session. Generation gates missing tables/columns; no PREPARE/EXECUTE/DDL is used.
SELECT CONCAT('SELECT COUNT(*) AS exact_rows FROM ', CHAR(96), table_name, CHAR(96), ';') AS sql_to_run
FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (${tableList}) ORDER BY table_name;
SELECT 'SELECT id, migration, batch FROM migrations ORDER BY batch, migration;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='migrations');
SELECT 'SELECT provider, SHA2(mailbox_key,256) AS mailbox_hash, SHA2(provider_message_id,256) AS message_hash, COUNT(*) AS duplicate_count FROM email_activities GROUP BY provider, mailbox_key, provider_message_id HAVING COUNT(*) > 1;' AS sql_to_run
WHERE (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name IN ('provider','mailbox_key','provider_message_id')) = 3;
SELECT 'SELECT provider, user_id, COUNT(*) AS duplicate_count FROM email_mailbox_connections GROUP BY provider, user_id HAVING COUNT(*) > 1;' AS sql_to_run
WHERE (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_mailbox_connections' AND column_name IN ('provider','user_id')) = 2;
SELECT 'SELECT SHA2(idempotency_key,256) AS key_hash, COUNT(*) AS duplicate_count FROM email_activities WHERE idempotency_key IS NOT NULL GROUP BY idempotency_key HAVING COUNT(*) > 1;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name='idempotency_key');
-- Global duplicates are legitimate after mailbox-scoped migration; block only same-mailbox duplicates.
SELECT 'SELECT mailbox_connection_id, SHA2(idempotency_key,256) AS key_hash, COUNT(*) AS duplicate_count FROM email_activities WHERE idempotency_key IS NOT NULL AND mailbox_connection_id IS NOT NULL GROUP BY mailbox_connection_id, idempotency_key HAVING COUNT(*) > 1;' AS sql_to_run
WHERE (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name IN ('mailbox_connection_id','idempotency_key')) = 2;
SELECT 'SELECT COUNT(*) AS orphan_mailbox_owners FROM email_activities e LEFT JOIN users u ON u.id=e.mailbox_owner_user_id WHERE e.mailbox_owner_user_id IS NOT NULL AND u.id IS NULL;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_activities' AND column_name='mailbox_owner_user_id')
AND EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='users' AND column_name='id');
SELECT 'SELECT SHA2(ob_reference_no,256) AS reference_hash, COUNT(*) AS duplicate_count FROM official_business_trips WHERE ob_reference_no IS NOT NULL GROUP BY ob_reference_no HAVING COUNT(*) > 1;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='official_business_trips' AND column_name='ob_reference_no');
SELECT 'SELECT COUNT(*) AS legacy_rows_requiring_preservation_review FROM company_activities;' AS sql_to_run
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='company_activities');
-- Stage 3: only after migrations table has been confirmed. Missing history is a STOP condition.
-- This expected list is an inventory, not an instruction to apply every row.
SELECT e.migration, m.batch, CASE WHEN m.migration IS NULL THEN 'VERIFY: not recorded' ELSE 'RECORDED: compare schema/source too' END AS status
FROM (
${migRows.map((r,i)=>`    SELECT '${r[0].replace(/\.php$/,'')}'${i === 0 ? ' AS migration' : ''}`).join('\n    UNION ALL\n')}
) e LEFT JOIN migrations m ON m.migration=e.migration ORDER BY e.migration;
`);
console.log(JSON.stringify({files:records.length,migrations:migRows.length,excluded:excluded.length,tests:testFiles.filter(exists).length,env:envRows.size}));
