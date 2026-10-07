const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const root = process.cwd();
const view = fs.readFileSync(`${root}/resources/views/human-capital/obf.blade.php`, 'utf8');
const form = fs.readFileSync(`${root}/resources/views/human-capital/partials/obf-create-form.blade.php`, 'utf8');
const preview = fs.readFileSync(`${root}/resources/views/human-capital/partials/obf-create-preview.blade.php`, 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/@json\([^\n]*\)/g, 'null')
    .replace(/\{\{[\s\S]*?\}\}/g, '/test');
const context = { console, window: { confirm: () => true, alert: () => {} }, document: {}, fetch: async () => ({ ok: false }), URLSearchParams };
vm.createContext(context);
vm.runInContext(script, context);

const page = context.obfPage();
page.form = {
    classification: 'client',
    detail_rows: [
        { company_id: 1, engagement_name: 'Engagement', from_location: 'Office', destination: 'Client', travel: {}, transportation: {} },
        { company_id: 2, from_location: 'Office', destination: 'Airport', travel: {}, transportation: {} },
    ],
};
page.companies = [{ id: 1, company_name: 'Client One' }, { id: 2, company_name: 'Client Two' }];
page.detailDraft = { company_id: 1, engagement_type: 'Project', deal_id: 44, project_id: 55 };
page.changeEngagementType('Regular');
assert.equal(page.detailDraft.deal_id, null);
assert.equal(page.detailDraft.project_id, null);
page.detailDraft = { company_id: 1, engagement_type: 'Regular', deal_id: 66, project_id: 77 };
page.changeEngagementType('Project');
assert.equal(page.detailDraft.deal_id, null);
assert.equal(page.detailDraft.project_id, null);
page.clearTransportationForMode(0, 'Air');
page.form.detail_rows[0].transportation.airline = 'Air Co';
page.form.detail_rows[0].transportation.flight_number = 'AC-1';
page.clearTransportationForMode(0, 'Walking');
assert.equal(page.form.detail_rows[0].transportation.flight_number, '');
assert.equal(page.transportationDescription({ mode: 'Walking' }), '-');
assert.equal(page.detailTripLabel(page.form.detail_rows[0]), 'Client One / Engagement');
page.moveDetail(0, 1);
assert.equal(page.form.detail_rows[0].destination, 'Airport');

for (const field of [
    'departure_date', 'departure_time', 'expected_arrival_date', 'expected_arrival_time', 'estimated_expense',
    'Company Vehicle', 'Private Vehicle', 'Ride-Hailing', 'Taxi', 'Public Transportation', 'Rental Vehicle', 'Air', 'Sea/Ferry', 'Walking', 'Other',
]) {
    assert.ok(form.includes(field), `form renders ${field}`);
}
assert.match(form, /Client \/ Trip/);
assert.match(form, /<label class="label">Route<\/label>/);
assert.match(form, /label class="label">Related Engagement \*<\/label><select :value="clientEngagementValue\(\)"/);
assert.match(form, /@change="selectDetailEngagement\(\$event\.target\.value, \$event\.target\.selectedOptions\[0\]\?\.textContent\)"/);
assert.match(preview, /Expected Arrival/);
assert.match(preview, /transportationDescription/);
assert.match(preview, /Attachments/);
assert.match(preview, /form\.remarks/);
assert.doesNotMatch(preview, /storage_path|official_business_trip_detail_id/);
assert.doesNotMatch(form, /name="(?:travel_notes|transportation_mode|destination)"/);
assert.match(view, /attachment_rows_json/);
assert.match(form, /attachment_files\[\$\{index\}\]/);
assert.match(view, /Discard unsaved changes\?/);
assert.match(view, /Relink or remove each detail-linked attachment/);

page.form = {
    classification: 'client',
    detail_rows: [{ id: 44, company_id: 1, from_location: 'Office', destination: 'Client' }],
    create_attachments: [{ id: 9, detail_key: 'detail:44', filename: 'memo.pdf', remove: false }],
};
let removalWarning = '';
context.window.alert = message => { removalWarning = message; };
page.removeDetail(0);
assert.equal(page.form.detail_rows.length, 1);
assert.match(removalWarning, /linked attachments/);
page.addCreateAttachment();
assert.equal(page.form.create_attachments.length, 2);
assert.equal(page.detailReference(page.form.detail_rows[0]), 'detail:44');

page.showPanel = true;
page.dirty = true;
context.window.confirm = () => false;
page.closePanel();
assert.equal(page.showPanel, true);
context.window.confirm = () => true;
page.closePanel();
assert.equal(page.showPanel, false);

console.log('O.B. Travel and transportation regression passed.');
