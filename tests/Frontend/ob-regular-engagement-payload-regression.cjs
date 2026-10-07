const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const root = process.cwd();
const view = fs.readFileSync(root + '/resources/views/human-capital/obf.blade.php', 'utf8');
const form = fs.readFileSync(root + '/resources/views/human-capital/partials/obf-create-form.blade.php', 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/@json\([^\n]*\)/g, 'null')
    .replace(/\{\{[\s\S]*?\}\}/g, '/test');

const requests = [];
const regularByClient = {
    '1': [{id: 101, name: 'Client One Retainer'}],
    '2': [{id: 102, name: 'Client Two Retainer'}],
};
const projectByClient = {
    '1': [{id: 201, name: 'Client One Project'}],
    '2': [{id: 202, name: 'Client Two Project'}],
};
const context = {
    console,
    URLSearchParams,
    window: {confirm: () => true, alert: () => {}},
    document: {},
    fetch: async url => {
        const parsed = new URL(String(url), 'https://local.test');
        const companyId = parsed.searchParams.get('company_id');
        const type = parsed.searchParams.get('type');
        requests.push({companyId, type});
        const items = type === 'Regular' ? regularByClient[companyId] : projectByClient[companyId];
        return {ok: true, json: async () => ({items, travel_credit_balance: 12})};
    },
};
vm.createContext(context);
vm.runInContext(script, context);

(async () => {
    const page = context.obfPage();
    page.form = {classification: 'client', detail_rows: []};
    page.companies = [{id: 1, company_name: 'Client One'}, {id: 2, company_name: 'Client Two'}];

    page.detailDraft = {};
    page.detailDraft.company_id = 1;
    await page.changeDetailClient(1);
    await page.changeEngagementType('Regular');
    assert.deepEqual(requests.at(-1), {companyId: '1', type: 'Regular'});
    assert.deepEqual(page.engagements, regularByClient['1'], 'Regular selector contains only the selected client Regular engagements');
    page.selectDetailEngagement('101', 'Client One Retainer');
    assert.equal(page.detailDraft.engagement_name, 'Client One Retainer', 'selection immediately stores its visible name');
    Object.assign(page.detailDraft, {
        from_location: 'Main Office',
        destination: 'Client Site',
        purpose: 'Regular account review',
        travel: {departure_date: '2026-10-08', departure_time: '08:00'},
        transportation: {mode: 'Walking'},
    });
    assert.equal(page.detailDraft.engagement_type, 'Regular');
    assert.equal(String(page.detailDraft.project_id), '101');
    assert.equal(page.detailDraft.deal_id, null);
    page.saveDetail();
    const regularRow = page.form.detail_rows[0];
    assert.equal(regularRow.engagement_type, 'Regular');
    assert.equal(String(regularRow.project_id), '101', 'Add Entry retains the Regular workspace ID');
    assert.equal(regularRow.deal_id, null);
    assert.equal(regularRow.engagement_name, 'Client One Retainer');
    assert.equal(page.detailRelated(regularRow), 'Client One Retainer', 'Client table displays the selected Regular engagement name');
    page.engagements = [];
    assert.equal(page.detailRelated(regularRow), 'Client One Retainer', 'table keeps the saved name after the options list changes');

    page.detailDraft = {};
    page.detailDraft.company_id = 1;
    await page.changeDetailClient(1);
    await page.changeEngagementType('Project');
    assert.deepEqual(page.engagements, projectByClient['1'], 'Project selector contains only the selected client Projects');
    page.selectDetailEngagement('201', 'Client One Project');
    assert.equal(page.detailDraft.engagement_name, 'Client One Project');
    Object.assign(page.detailDraft, {
        from_location: 'Main Office',
        destination: 'Project Site',
        purpose: 'Project kickoff',
        travel: {departure_date: '2026-10-09', departure_time: '09:00'},
        transportation: {mode: 'Walking'},
    });
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(String(page.detailDraft.project_id), '201');
    page.saveDetail();
    const projectRow = page.form.detail_rows[1];
    assert.equal(projectRow.engagement_type, 'Project');
    assert.equal(projectRow.deal_id, null);
    assert.equal(String(projectRow.project_id), '201', 'Add Entry retains the Project ID');
    assert.equal(projectRow.engagement_name, 'Client One Project');
    assert.equal(page.detailRelated(projectRow), 'Client One Project', 'Client table displays the selected Project name');

    const submittedRows = () => JSON.parse(JSON.stringify(page.serializedDetailRows()));
    let payload = submittedRows();
    assert.equal(payload[0].engagement_type, 'Regular');
    assert.equal(String(payload[0].project_id), '101');
    assert.equal(payload[0].deal_id, null);
    assert.equal(payload[0].engagement_name, 'Client One Retainer');
    assert.equal(payload[1].engagement_type, 'Project');
    assert.equal(payload[1].deal_id, null);
    assert.equal(String(payload[1].project_id), '201');
    assert.equal(payload[1].engagement_name, 'Client One Project');

    await page.openDetailModal(0);
    assert.equal(String(page.detailDraft.project_id), '101');
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(String(page.clientEngagementValue()), '101', 'Regular Edit restores the selected dropdown value');
    assert.equal(page.detailDraft.engagement_name, 'Client One Retainer');
    page.detailDescription(page.form.detail_rows[0]);
    page.detailTripLabel(page.form.detail_rows[0]);
    page.saveDetail();
    payload = submittedRows();
    assert.equal(String(payload[0].project_id), '101', 'Regular ID survives edit modal save');
    assert.equal(payload[0].deal_id, null, 'edit modal does not restore an submitted Deal ID');

    await page.openDetailModal(1);
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(String(page.detailDraft.project_id), '201');
    assert.equal(String(page.clientEngagementValue()), '201', 'Project Edit restores the selected dropdown value');
    assert.equal(page.detailDraft.engagement_name, 'Client One Project');
    page.detailDescription(page.form.detail_rows[1]);
    page.saveDetail();
    payload = submittedRows();
    assert.equal(payload[1].deal_id, null, 'Project ID remains type-specific after edit');
    assert.equal(String(payload[1].project_id), '201', 'Project ID survives edit modal save');

    await page.openDetailModal(0);
    await page.changeEngagementType('Project');
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(page.detailDraft.project_id, null);
    assert.equal(page.detailDraft.engagement_name, '', 'Regular to Project clears the old name');
    await page.changeEngagementType('Regular');
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(page.detailDraft.project_id, null);
    assert.equal(page.detailDraft.engagement_name, '', 'Project to Regular clears the old name');
    page.selectDetailEngagement('101', 'Client One Retainer');
    assert.equal(String(page.detailDraft.project_id), '101');
    assert.equal(page.detailDraft.deal_id, null);

    await page.changeDetailClient(2);
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(page.detailDraft.project_id, null);
    assert.equal(page.detailDraft.engagement_name, '', 'Client change clears the previous engagement name');
    assert.deepEqual(requests.at(-1), {companyId: '2', type: 'Regular'});
    assert.deepEqual(page.engagements.map(item => item.id), [102]);
    page.selectDetailEngagement('102', 'Client Two Retainer');
    assert.equal(String(page.detailDraft.project_id), '102');
    assert.equal(page.detailDraft.deal_id, null);

    assert.match(form, /label class="label">Related Engagement \*<\/label><select :value="clientEngagementValue\(\)"/);
    assert.match(form, /@change="selectDetailEngagement\(\$event\.target\.value, \$event\.target\.selectedOptions\[0\]\?\.textContent\)"/);
    assert.match(form, /:value="String\(engagement\.id\)"/);
    assert.match(form, /Related Engagement \*<\/label><select :value="clientEngagementValue\(\)" @change="selectDetailEngagement\(\$event\.target\.value, \$event\.target\.selectedOptions\[0\]\?\.textContent\)"/);
    assert.match(view, /JSON\.stringify\(serializedDetailRows\(\)\)/);
    assert.match(view, /name="details_json" :value="JSON\.stringify\(serializedDetailRows\(\)\)"/);
    assert.doesNotMatch(view, /engagementId \? `#\$\{engagementId\}`/);

    console.log('O.B. Regular/Project engagement selection, edit, switching, preview, and payload regression passed.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
