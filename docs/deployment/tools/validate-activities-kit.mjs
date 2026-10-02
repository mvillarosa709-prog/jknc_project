// Static local validation only: never boot Laravel or connect to a database/provider.
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { spawnSync } from 'node:child_process';
const root = path.resolve(import.meta.dirname, '../../..');
const kit = path.join(root, 'docs/deployment');
const read = p => fs.readFileSync(path.join(kit, p), 'utf8');
function csv(source) {
    const rows = []; let row = [], field = '', quoted = false;
    for (let i = 0; i < source.length; i++) {
        const c = source[i];
        if (c === '"') {
            if (quoted && source[i+1] === '"') { field += '"'; i++; }
            else quoted = !quoted;
        } else if (c === ',' && !quoted) { row.push(field); field = ''; }
        else if (c === '\n' && !quoted) { row.push(field.replace(/\r$/, '')); rows.push(row); row = []; field = ''; }
        else field += c;
    }
    assert.equal(quoted, false, 'CSV quote integrity');
    const header = rows.shift();
    return rows.map(r => { assert.equal(r.length, header.length); return Object.fromEntries(header.map((h,i)=>[h,r[i]])); });
}
const summary = JSON.parse(read('inventory-summary.json'));
const manifest = csv(read('activities-deployment-manifest.csv'));
const migrations = csv(read('activities-migration-inventory.csv'));
const excluded = csv(read('activities-do-not-deploy.csv'));
const env = csv(read('activities-environment-keys.csv'));
const tests = csv(read('activities-test-matrix.csv'));
assert.equal(manifest.length, summary.manifest_records);
assert.equal(migrations.length, summary.migrations);
assert.equal(excluded.length, summary.excluded_local_files);
assert.equal(env.length, summary.env_keys);
assert.equal(tests.length, summary.tests);
assert.equal(new Set(manifest.map(r=>r.path)).size,manifest.length);
for (const r of manifest) {
    assert.ok(fs.existsSync(path.join(root,r.path)),`Missing manifest path: ${r.path}`);
    assert.equal(crypto.createHash('sha256').update(fs.readFileSync(path.join(root,r.path))).digest('hex'), r.sha256, `Stale hash: ${r.path}`);
    assert.ok(!excluded.some(e=>e.path === r.path),`Excluded path in deployment manifest: ${r.path}`);
    assert.ok(!/\.bak$|\.zip$|^storage\/|^tmp\/|^database\/data-export\//.test(r.path));
}
for (const name of ['2026_08_18_120000_complete_task_management_schema.php','2026_09_30_180000_create_email_mailbox_connections_table.php','2026_09_30_180001_extend_email_activities_for_gmail.php','2026_09_04_135548_drop_company_activities_table.php','2026_10_01_180000_scope_email_activities_idempotency_to_mailbox.php','2026_10_01_180001_add_email_mailbox_sync_checkpoint.php']) assert.ok(migrations.some(r=>r.filename === name));
assert.ok(migrations.every(r=>r.production_status === 'VERIFY AGAINST PRODUCTION MIGRATIONS TABLE'));
assert.ok(!migrations.some(r=>/candidate|gis_people/.test(r.filename)), 'Unrelated email/interview migrations included');
for (const key of ['GMAIL_ENABLED','GOOGLE_CLIENT_ID','GOOGLE_CLIENT_SECRET','GOOGLE_REDIRECT_URI','GMAIL_INITIAL_SYNC_DAYS','GMAIL_SYNC_LIMIT','GMAIL_INITIAL_SYNC_MAX_MESSAGES','GMAIL_ATTACHMENT_MAX_KB','GMAIL_ATTACHMENT_MIMES','GMAIL_SYNC_QUEUE','GMAIL_SEND_QUEUE','MESSENGER_ENABLED','MESSENGER_OUTBOUND_ENABLED','MESSENGER_APP_SECRET','MESSENGER_PAGE_ACCESS_TOKEN','MESSENGER_VERIFY_TOKEN','MESSENGER_QUEUE_CONNECTION','APP_KEY','QUEUE_CONNECTION']) assert.ok(env.some(r=>r.key === key),`Missing env key ${key}`);
assert.ok(env.filter(r=>r.classification === 'SECRET').every(r=>r.source_default_expression === '(not reproduced)'));

function selectOnly(sql) {
    const stripped = sql.replace(/--[^\n]*/g,'').replace(/\/\*[\s\S]*?\*\//g,'').replace(/'(?:''|[^'])*'/g,"''").replace(/`[^`]*`/g,'identifier');
    const statements = stripped.split(';').map(s=>s.trim()).filter(Boolean);
    assert.ok(statements.length > 0);
    for (const s of statements) {
        assert.match(s,/^SELECT\b/i);
        assert.doesNotMatch(s,/\b(?:INSERT|UPDATE|DELETE|ALTER|CREATE|DROP|TRUNCATE|REPLACE|GRANT|REVOKE|CALL|PREPARE|EXECUTE|SET|USE)\b/i);
        assert.doesNotMatch(s,/\bINTO\s+(?:OUTFILE|DUMPFILE)\b|FOR\s+UPDATE|\b(?:SLEEP|BENCHMARK)\s*\(/i);
    }
    return statements.length;
}
const sql = read('production-schema-readonly.sql');
const sqlStatements = selectOnly(sql);
for (const m of sql.matchAll(/SELECT '((?:''|[^'])+)' AS sql_to_run/g)) selectOnly(m[1].replaceAll("''", "'"));
assert.match(sql,/SELECT VERSION\(\)/);
assert.match(sql,/SELECT DATABASE\(\)/);
assert.match(sql,/information_schema\.statistics/);
assert.match(sql,/information_schema\.referential_constraints/);
assert.match(sql,/HAVING COUNT\(\*\) > 1/);

const files = [];
function walk(dir) { for(const entry of fs.readdirSync(dir,{withFileTypes:true})) { const p=path.join(dir,entry.name); if(entry.isDirectory()) walk(p); else files.push(p); } }
walk(kit);
const secretPatterns = [/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/, /\bAKIA[A-Z0-9]{16}\b/, /\bGOCSPX-[A-Za-z0-9_-]{20,}\b/, /\bya29\.[A-Za-z0-9_-]{30,}/, /\bEA[A-Za-z0-9]{60,}\b/, /\bgh[pousr]_[A-Za-z0-9]{30,}\b/, /\bAPP_KEY\s*=\s*base64:[A-Za-z0-9+/]{30,}/];
for(const p of files) {
    assert.ok(!/\.bak$|\.zip$|\.sql\.gz$/.test(p));
    const s=fs.readFileSync(p,'utf8');
    assert.ok(!s.startsWith('\uFEFF'), `UTF-8 BOM in ${path.relative(kit,p)}`);
    assert.ok(!s.split(/\r?\n/).some(line=>/[\t ]+$/.test(line)), `Trailing whitespace in ${path.relative(kit,p)}`);
    for(const pattern of secretPatterns) assert.ok(!pattern.test(s),`Secret pattern in ${path.relative(kit,p)}`);
}
// New kit files are untracked: ordinary git diff --check alone would skip them.
for(const p of files) {
    const result=spawnSync('git',['diff','--no-index','--check','--',process.platform==='win32'?'NUL':'/dev/null',p],{cwd:root,encoding:'utf8'});
    // --no-index may return 1 for an added/different file even when --check emits no defects.
    assert.ok([0,1].includes(result.status) && !(result.stdout || '').trim() && !/error|fatal/i.test(result.stderr || ''),`Whitespace check failed: ${path.relative(kit,p)}\n${result.stdout || ''}${result.stderr || ''}`);
}
// PHP -l checks syntax only. It never loads/executes migration up()/down().
const remediationPaths = [
    'app/Http/Controllers/ActivityAttachmentController.php', 'app/Http/Controllers/ActivityController.php',
    'app/Http/Controllers/EmailActivityController.php', 'app/Jobs/SendGmailEmail.php', 'app/Jobs/SyncGmailMailboxes.php',
    'app/Models/EmailMailboxConnection.php', 'app/Services/ActivityAttachmentService.php', 'app/Services/Gmail/GmailTransport.php',
    'config/queue.php', 'config/services.php', 'routes/web.php', 'resources/views/activities/index.blade.php',
    'database/migrations/2026_09_04_135548_drop_company_activities_table.php',
    'database/migrations/2026_10_01_180000_scope_email_activities_idempotency_to_mailbox.php',
    'database/migrations/2026_10_01_180001_add_email_mailbox_sync_checkpoint.php',
    'tests/Feature/ActivitiesV2RemainingBackendTest.php',
];
for (const p of remediationPaths) {
    const bytes = fs.readFileSync(path.join(root,p));
    const s = new TextDecoder('utf-8',{fatal:true}).decode(bytes);
    assert.ok(!(bytes[0] === 0xef && bytes[1] === 0xbb && bytes[2] === 0xbf), `BOM in ${p}`);
    for (const pattern of secretPatterns) assert.ok(!pattern.test(s), `Credential pattern in ${p}`);
    const tracked = spawnSync('git',['ls-files','--error-unmatch','--',p],{cwd:root,encoding:'utf8'}).status === 0;
    const args = tracked ? ['diff','--check','--',p] : ['diff','--no-index','--check','--',process.platform==='win32'?'NUL':'/dev/null',p];
    const check = spawnSync('git',args,{cwd:root,encoding:'utf8'});
    assert.ok([0,1].includes(check.status) && !check.stdout.trim() && !/error|fatal/i.test(check.stderr), `Whitespace defect in ${p}: ${check.stdout}`);
}
let phpSyntaxFiles = 0;
const phpBinary = process.env.ACTIVITIES_KIT_PHP_BINARY || 'php'; // Windows Herd may expose only a .bat shim; supply its real php.exe.
for (const r of manifest.filter(r=>r.path.endsWith('.php'))) {
    const result=spawnSync(phpBinary,['-l',r.path],{cwd:root,encoding:'utf8'});
    assert.equal(result.status,0,`PHP syntax failure: ${r.path}\n${result.stdout || ''}${result.stderr || ''}`);
    phpSyntaxFiles++;
}
const frontend = [];
for(const p of ['tests/Frontend/all-activities-calendar-regression.cjs','tests/Frontend/tasks-messages-navigation-regression.cjs','tests/Frontend/messenger-outbound-regression.cjs','tests/Frontend/ob-draft-edit-regression.cjs','tests/Frontend/ob-all-activities-master-detail-regression.cjs']) {
    const result=spawnSync(process.execPath,[p],{cwd:root,encoding:'utf8'});
    assert.equal(result.status,0,`Frontend regression failure: ${p}\n${result.stdout || ''}${result.stderr || ''}`);
    frontend.push({path:p,result:'PASS',output:result.stdout.trim()});
}
const manifestDigest = crypto.createHash('sha256').update(fs.readFileSync(path.join(kit,'activities-deployment-manifest.csv'))).digest('hex');
const previous = fs.existsSync(path.join(kit,'validation-results.json')) ? JSON.parse(read('validation-results.json')) : {};
// Feature suites are run separately with explicit SQLite isolation; never invent a pass here.
// Preserve that evidence only while its complete source manifest matches this snapshot.
const evidence = previous.feature_tests?.source_manifest_sha256 === manifestDigest
    ? previous.feature_tests : {status:'NOT RUN FOR CURRENT SOURCE SNAPSHOT',isolation:'SQLite :memory: required'};
const results = {checked_at:new Date().toISOString(),manifest_paths_and_hashes:'PASS',migration_inventory:'PASS',env_keys_and_secret_default_redaction:'PASS',sql_readonly_static:'PASS',sql_select_statements:sqlStatements,secret_pattern_scan:'PASS (kit and 16 remediation source files; not proof that pre-existing repository/history is secret-free)',utf8_without_bom:'PASS (kit and 16 remediation source files)',git_whitespace_check:'PASS for remediation source and kit, including untracked files via --no-index --check',backups_copied:'NO',php_syntax_files:phpSyntaxFiles,php_syntax:'PASS (php -l only; no migration execution)',frontend,feature_tests:evidence,additional_validation:previous.additional_validation || {},mysql_schema_execution:'NOT RUN: no MySQL database connected',build:'NOT RUN: no asset inputs changed; Blade/frontend regressions checked',live_providers:'NOT CONNECTED',git_writes:'NONE',verdict:evidence.status === 'PASS' ? 'ACTIVITIES PRODUCTION DEPLOYMENT KIT READY FOR LIVE ACCESS' : 'ACTIVITIES PRODUCTION DEPLOYMENT KIT NEEDS FURTHER LOCAL WORK'};
fs.writeFileSync(path.join(kit,'validation-results.json'),JSON.stringify(results,null,2)+'\n');
console.log(JSON.stringify({manifest:manifest.length,migrations:migrations.length,sqlStatements,phpSyntaxFiles,frontend:`${frontend.length} PASS`,secrets:'no matching credentials in kit',verdict:results.verdict}));
