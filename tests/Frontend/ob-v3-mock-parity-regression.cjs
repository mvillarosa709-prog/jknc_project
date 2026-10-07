const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = process.cwd();
const view = fs.readFileSync(path.join(root, 'resources/views/human-capital/obf.blade.php'), 'utf8');
const form = fs.readFileSync(path.join(root, 'resources/views/human-capital/partials/obf-create-form.blade.php'), 'utf8');
const preview = fs.readFileSync(path.join(root, 'resources/views/human-capital/partials/obf-create-preview.blade.php'), 'utf8');
const sectionOrder = ['O.B. Classification', 'Personnel Details', 'detailSectionTitle()', 'Travel Details', 'Mode of Transportation', 'Attachments', 'Remarks'];
let previous = -1;
for (const section of sectionOrder) {
    const next = form.indexOf(section, previous + 1);
    assert.ok(next > previous, `form section order includes ${section}`);
    previous = next;
}
for (const label of ['Client O.B.', 'Internal / Company O.B.', 'Social / Marketing / Non-Creditable']) assert.ok(view.includes(`'${label}'`), `classification label ${label}`);
assert.match(form, /classificationDescription\(option\)/);
assert.match(form, /Employee ID<\/th>[\s\S]*Position<\/th>[\s\S]*Department \/ Unit<\/th>[\s\S]*Nature of Travel<\/th>[\s\S]*Immediate Supervisor<\/th>[\s\S]*Action<\/th>/);
assert.match(form, /\+ Add Team Member/);
assert.match(form, /x-show="teamModalOpen"[\s\S]*Employee \*<\/label>[\s\S]*Immediate Supervisor/);
for (const action of ['+ Add Client', '+ Add Internal Activity', '+ Add Activity']) assert.ok(view.includes(`'${action}'`), `classification add label ${action}`);
for (const header of ['Client', 'From', 'To / Destination', 'Purpose', 'Type', 'Related Engagement', 'Travel Credit Balance', 'Internal Activity Type', 'Related Department / Office', 'Related Internal Record', 'Activity / Event', 'Organization / Host', 'Related Contact']) assert.ok(form.includes(header), `master table/modal supports ${header}`);
assert.match(form, /openTravelModal\(index\)/);
assert.match(form, /openTransportationModal\(index\)/);
assert.match(view, /ensureDetailChildren\(\{\.\.\.this\.detailDraft\}\)/);
assert.match(form, /detailTripLabel\(row\)/);
assert.match(form, /detailRoute\(row\)/);
assert.match(form, /Departure Date \*<\/label>[\s\S]*Departure Time \*<\/label>[\s\S]*Expected Arrival Date \*<\/label>[\s\S]*Expected Arrival Time \*<\/label>[\s\S]*Estimated Expense/);
assert.doesNotMatch(form, /Travel Notes/);
for (const field of ['Company Vehicle', 'Private Vehicle', 'Ride-Hailing', 'Taxi', 'Public Transportation', 'Rental Vehicle', 'Air', 'Sea/Ferry', 'Walking', 'Other']) assert.ok(form.includes(field), `transport mode ${field}`);
assert.match(form, /clearTransportationDraftForMode\(\$event\.target\.value\)/);
assert.match(form, /\+ Add Attachment[\s\S]*Attachment Type[\s\S]*Related Client \/ Trip[\s\S]*Upload File[\s\S]*Description/);
assert.match(form, /General O\.B\./);
assert.match(form, /name="remarks"/);
assert.equal((form.match(/name="remarks"/g) || []).length, 1, 'one form Remarks field');
assert.match(view, /JSON\.stringify\(serializedDetailRows\(\)\)/);
assert.match(view, /Discard unsaved changes\?/);
assert.match(view, /value="save_draft"[\s\S]*Save Draft/);
assert.match(view, /value="submit"[\s\S]*Submit O\.B\./);
assert.match(view, /obv3-create-layout/);
assert.match(view, /\.obv3-data-table \{ min-width: 720px; \}/);
assert.match(view, /@media \(max-width: 1150px\)/);
assert.match(view, /\.obv3-create-form-panel \{ order: 1/);
assert.match(view, /\.obv3-create-preview-panel \{ order: 2/);
assert.match(view, /\.obv3-modal-body \{ min-height: 0; overflow-y: auto/);
assert.match(view, /John Kelly<br><span>&amp;<\/span> Company/);
assert.match(view, /<strong>JK&amp;C INC\.<\/strong><br>{{ \$companyHeader\['company_address'\] }}/);
assert.match(view, /<div class="obv3-preview-rule"><\/div>[\s\S]*OFFICIAL BUSINESS TRAVEL FORM[\s\S]*OB Reference No\.[\s\S]*O\.B\. Classification:[\s\S]*Date &amp; Time:/);
assert.doesNotMatch(preview, /section-title">O\.B\. Classification/);
const previewOrder = ['Personnel Details', 'detailSectionTitle()', 'Travel Details', 'Mode of Transportation', 'Attachments', 'Remarks'];
previous = -1;
for (const section of previewOrder) {
    const next = preview.indexOf(section, previous + 1);
    assert.ok(next > previous, `preview section order includes ${section}`);
    previous = next;
}
assert.match(preview, /form\.classification === 'client'/);
assert.match(preview, /form\.classification === 'internal'/);
assert.match(preview, /form\.classification === 'social'/);
assert.match(preview, /detailTripLabel\(row\)/);
assert.match(preview, /transportationDescription\(row\.transportation/);
assert.match(preview, /form\.remarks/);
assert.match(form, /<label class="label">Related Engagement \*<\/label><select :value="clientEngagementValue\(\)"/);
assert.doesNotMatch(form, /Related Regular Engagement|Related Project/);
assert.match(form, /@change="selectDetailEngagement\(\$event\.target\.value, \$event\.target\.selectedOptions\[0\]\?\.textContent\)"/);
assert.match(form, /:value="String\(engagement\.id\)"/);
assert.match(form, /:name="`attachment_files\[\$\{index\}\]`"/);

const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/@json\([^\n]*\)/g, 'null')
    .replace(/\{\{[\s\S]*?\}\}/g, '/test');
const requests = [];
const regular = {'1': [{id: 101, name: 'Client One Retainer'}], '2': [{id: 102, name: 'Client Two Retainer'}]};
const projects = {'1': [{id: 201, name: 'Client One Project'}], '2': [{id: 202, name: 'Client Two Project'}]};
let alertMessage = '';
const context = {
    console, URLSearchParams,
    window: {confirm: () => true, alert: message => { alertMessage = message; }, crypto: {randomUUID: () => `stable-client-${requests.length + 1}`}},
    document: {},
    fetch: async url => {
        const parsed = new URL(String(url), 'https://local.test');
        const companyId = parsed.searchParams.get('company_id');
        const type = parsed.searchParams.get('type');
        requests.push({companyId, type});
        return {ok: true, json: async () => ({items: type === 'Regular' ? regular[companyId] : projects[companyId], travel_credit_balance: 7})};
    },
};
vm.createContext(context);
vm.runInContext(script, context);

(async () => {
    const page = context.obfPage();
    page.form = {classification: 'client', detail_rows: [], team_rows: [], create_attachments: [], remarks: ''};
    page.companies = [{id: 1, company_name: 'Client One'}, {id: 2, company_name: 'Client Two'}];
    page.departments = [{id: 3, department_name: 'Operations'}];
    page.personnelEmployees = [{id: 4, full_name: 'Team Member', employee_code: 'E4', position: 'Associate', department: 'Operations', immediate_superior: 'Supervisor'}];
    page.form.employee_id = 9;
    page.teamDraft = {employee_id: 4, nature_of_travel: 'Field Work'};
    page.addTeamMember();
    assert.equal(page.form.team_rows.length, 1, 'real employee row adds through team handler');
    page.teamDraft = {employee_id: 4, nature_of_travel: 'Field Work'};
    page.addTeamMember();
    assert.equal(page.form.team_rows.length, 1, 'duplicate team employee is rejected');
    page.openTeamMemberModal(0);
    page.teamDraft.nature_of_travel = 'Audit';
    page.addTeamMember();
    assert.equal(page.form.team_rows[0].nature_of_travel, 'Audit', 'team member edit saves updated nature');
    page.removeTeamMember(0);
    assert.equal(page.form.team_rows.length, 0, 'team member can be removed');

    page.openDetailModal();
    await page.changeDetailClient(1);
    await page.changeEngagementType('Regular');
    assert.deepEqual(requests.at(-1), {companyId: '1', type: 'Regular'});
    page.selectDetailEngagement('101');
    Object.assign(page.detailDraft, {from_location: 'Office', destination: 'Client Site', purpose: 'Regular review'});
    assert.equal(String(page.detailDraft.project_id), '101');
    assert.equal(page.detailDraft.deal_id, null);
    page.saveDetail();
    let regularRow = page.form.detail_rows[0];
    assert.equal(regularRow.engagement_name, 'Client One Retainer', 'selected engagement is visible on saved master row');
    assert.ok(regularRow.client_key && regularRow.travel && regularRow.transportation, 'master row receives stable key and linked children');

    await page.openDetailModal(0);
    assert.equal(String(page.clientEngagementValue()), '101', 'Regular edit selector restores selected workspace id');
    page.saveDetail();
    await page.openDetailModal(0);
    await page.changeEngagementType('Project');
    assert.equal(page.detailDraft.deal_id, null, 'Regular to Project clears deal_id');
    assert.equal(page.detailDraft.project_id, null, 'Regular to Project does not preselect a project');
    page.selectDetailEngagement('201');
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(String(page.detailDraft.project_id), '201');
    page.saveDetail();
    await page.openDetailModal(0);
    assert.equal(String(page.clientEngagementValue()), '201', 'Project edit selector restores project id');
    await page.changeEngagementType('Regular');
    assert.equal(page.detailDraft.project_id, null, 'Project to Regular clears project_id');
    assert.equal(page.detailDraft.deal_id, null, 'Project to Regular leaves deal unselected');
    page.selectDetailEngagement('101');
    page.saveDetail();

    page.openDetailModal();
    await page.changeDetailClient(1);
    await page.changeEngagementType('Project');
    page.selectDetailEngagement('201');
    Object.assign(page.detailDraft, {from_location: 'Office', destination: 'Project Site', purpose: 'Project kickoff'});
    page.saveDetail();
    await page.openDetailModal(1);
    assert.equal(String(page.clientEngagementValue()), '201');
    page.saveDetail();
    const browserPayload = JSON.parse(JSON.stringify(page.serializedDetailRows()));
    assert.equal(String(browserPayload[0].project_id), '101');
    assert.equal(browserPayload[0].deal_id, null);
    assert.equal(browserPayload[1].deal_id, null);
    assert.equal(String(browserPayload[1].project_id), '201');

    page.openTravelModal(0);
    page.travelDraft = {departure_date: '2026-10-08', departure_time: '08:00', expected_arrival_date: '2026-10-08', expected_arrival_time: '17:00', estimated_expense: '1250'};
    page.saveTravelModal();
    assert.equal(page.form.detail_rows[0].travel.departure_date, '2026-10-08');
    assert.equal(page.travelActionLabel(page.form.detail_rows[0]), 'Edit');
    assert.equal(page.detailRoute(page.form.detail_rows[0]), 'Office -> Client Site');

    page.openTransportationModal(0);
    page.transportationDraft = {mode: 'Company Vehicle', vehicle: 'Van', plate_number: 'ABC-123', driver: 'Driver'};
    page.clearTransportationDraftForMode('Air');
    assert.equal(page.transportationDraft.vehicle, '', 'transport mode switch clears stale values');
    page.transportationDraft.airline = 'Airline';
    page.transportationDraft.flight_number = 'AB123';
    page.saveTransportationModal();
    assert.equal(page.form.detail_rows[0].transportation.mode, 'Air');
    assert.equal(page.transportationDescription(page.form.detail_rows[0].transportation), 'Airline: Airline / Flight: AB123');

    page.addCreateAttachment();
    const attachmentIndex = page.attachmentModalIndex;
    page.form.create_attachments[attachmentIndex].type = 'Memo';
    page.form.create_attachments[attachmentIndex].detail_key = page.detailReference(page.form.detail_rows[0]);
    page.setCreateAttachmentFile(attachmentIndex, {target: {files: [{name: 'memo.pdf'}]}});
    page.form.create_attachments[attachmentIndex].description = 'Trip memo';
    page.saveCreateAttachment();
    assert.equal(page.attachmentTripLabelForKey(page.form.create_attachments[0].detail_key), page.attachmentTripLabel(page.form.detail_rows[0]));
    assert.equal(page.form.create_attachments[0].filename, 'memo.pdf');
    const payload = JSON.stringify(page.serializedDetailRows());
    assert.equal(String(JSON.parse(payload)[0].project_id), '101', 'actual hidden details_json binding serializes the workspace ID');

    page.form.remarks = 'Meet at the client office.';
    assert.match(preview, /x-text="form\.remarks \|\|/);
    assert.equal(page.form.remarks, 'Meet at the client office.');

    const secondMaster = page.form.detail_rows[1];
    const secondTravel = secondMaster.travel;
    const secondTransportation = secondMaster.transportation;
    page.removeDetail(0);
    assert.match(alertMessage, /linked attachments/);
    page.removeCreateAttachment(0);
    page.removeDetail(0);
    assert.equal(page.form.detail_rows.length, 1, 'removing an unlinked master removes only its own children');
    assert.equal(page.form.detail_rows[0], secondMaster, 'the other master row remains stable');
    assert.equal(page.form.detail_rows[0].travel, secondTravel, 'the other Travel row remains linked');
    assert.equal(page.form.detail_rows[0].transportation, secondTransportation, 'the other Transportation row remains linked');

    console.log('O.B. V3 mock parity structure and UI-state interaction regression passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
