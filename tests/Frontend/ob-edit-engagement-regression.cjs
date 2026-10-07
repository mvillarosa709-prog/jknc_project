const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const sourceRoot = process.argv.find(arg => arg.startsWith('--source-root='))?.slice('--source-root='.length) || '.';
const view = fs.readFileSync(`${sourceRoot}/resources/views/human-capital/obf.blade.php`, 'utf8');
const form = fs.readFileSync(`${sourceRoot}/resources/views/human-capital/partials/obf-create-form.blade.php`, 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/@json\([^\n]*\)/g, 'null')
    .replace(/\{\{[\s\S]*?\}\}/g, '/test');
const regular = [
    {id: 101, deal_id: 901, name: 'Monthly Compliance Retainer'},
    {id: 102, name: 'Corporate Secretary Retainer'},
    {id: 103, name: 'Accounting & Compliance Retainer'},
];
const projects = [
    {id: 201, name: 'SEC Amendment Project'},
    {id: 202, name: 'Registration Project'},
    {id: 203, name: 'Special Compliance Project'},
];
const requests = [];
let delayedJson;
const context = {
    console, URLSearchParams, document: {},
    window: {confirm: () => true, alert: () => {}},
    fetch: async url => {
        const params = new URL(String(url), 'https://local.test').searchParams;
        const request = {companyId: params.get('company_id'), type: params.get('type')};
        requests.push(request);
        const items = request.companyId === '2'
            ? [{id: request.type === 'Regular' ? 301 : 401, name: 'Other Client Engagement'}]
            : request.type === 'Regular' ? regular : projects;
        const data = {items, travel_credit_balance: 3};
        if (delayedJson) {
            const delay = delayedJson;
            delayedJson = null;
            return {ok: true, json: () => { delay.started(); return delay.promise; }};
        }
        return {ok: true, json: async () => data};
    },
};
vm.createContext(context);
vm.runInContext(script, context);
const plain = value => JSON.parse(JSON.stringify(value));
const deferred = () => {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return {promise, resolve};
};

(async () => {
    const page = context.obfPage();
    page.form = {classification: 'client', detail_rows: []};
    page.companies = [{id: 1, company_name: 'Client One'}, {id: 2, company_name: 'Client Two'}];
    page.personnelEmployees = [];
    let renderedOptions = [];
    page.$nextTick = async () => {
        // Model Alpine's x-for render boundary using the fetched option values.
        renderedOptions = plain(page.engagements);
    };

    for (const [type, id, name, field, incompatible] of [
        ['Regular', 101, regular[0].name, 'project_id', 'deal_id'],
        ['Project', 201, projects[0].name, 'project_id', 'deal_id'],
    ]) {
        await page.openDetailModal();
        await page.changeDetailClient(1);
        await page.changeEngagementType(type);
        page.selectDetailEngagement(String(id), name);
        Object.assign(page.detailDraft, {from_location: 'Office', destination: 'Client', purpose: 'Meeting'});
        page.saveDetail();
        const index = page.form.detail_rows.length - 1;
        page.engagements = [];
        renderedOptions = [];
        await page.openDetailModal(index);
        assert.deepEqual(requests.at(-1), {companyId: '1', type});
        assert.deepEqual(plain(page.engagements), type === 'Regular' ? regular : projects);
        assert.equal(String(page.clientEngagementValue()), String(id));
        assert.equal(page.detailDraft.engagement_name, name);
        page.saveDetail();
        assert.equal(String(page.form.detail_rows[index][field]), String(id));
        assert.equal(page.form.detail_rows[index][incompatible], type === 'Regular' ? 901 : null);
        assert.equal(page.detailRelated(page.form.detail_rows[index]), name);
    }

    // An old response may pass the fetch guard and then finish decoding after a new type load.
    const oldBody = deferred();
    const decoding = deferred();
    delayedJson = {promise: oldBody.promise, started: decoding.resolve};
    const oldEdit = page.openDetailModal(0);
    await decoding.promise;
    await page.changeEngagementType('Project');
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(page.detailDraft.project_id, null);
    assert.equal(page.detailDraft.engagement_name, '');
    oldBody.resolve({items: regular, travel_credit_balance: 3});
    await oldEdit;
    assert.deepEqual(plain(page.engagements), projects, 'late Regular response cannot overwrite Project options');

    // Even reopening a second row for the same client/type must invalidate the first row's load.
    const earlierBody = deferred();
    const earlierDecoding = deferred();
    delayedJson = {promise: earlierBody.promise, started: earlierDecoding.resolve};
    const firstModal = page.openDetailModal(0);
    await earlierDecoding.promise;
    await page.openDetailModal(0);
    earlierBody.resolve({items: [], travel_credit_balance: 0});
    await firstModal;
    assert.deepEqual(plain(page.engagements), regular, 'older edit response cannot empty a reopened modal');
    assert.equal(String(page.detailDraft.project_id), '101');

    // Rehydrate server draft data, then edit each saved detail through the real page methods.
    const draft = {id: 42, workflow_version: 3, status: 'Draft', classification: 'client',
        is_client_travel: true, detail_rows: plain(page.form.detail_rows)};
    page.form = page.hydrateTrip(draft);
    for (const [index, id, name] of [[0, 101, regular[0].name], [1, 201, projects[0].name]]) {
        renderedOptions = [];
        await page.openDetailModal(index);
        assert.equal(String(page.clientEngagementValue()), String(id));
        assert.equal(page.detailDraft.engagement_name, name);
        assert.equal(renderedOptions.length, 3, 'options render before edit selection restoration finishes');
        page.saveDetail();
        assert.equal(page.detailRelated(page.form.detail_rows[index]), name);
    }

    // Older drafts carry only the Deal; resolve it without confusing Deal and workspace IDs.
    page.form.detail_rows[0].project_id = null;
    page.form.detail_rows[0].deal_id = 901;
    await page.openDetailModal(0);
    assert.equal(String(page.clientEngagementValue()), '101');
    assert.equal(page.detailDraft.deal_id, 901);
    page.saveDetail();
    assert.equal(page.serializedDetailRows()[0].project_id, 101);

    // An unavailable stored workspace must survive Edit instead of being silently erased.
    page.form.detail_rows[0].project_id = 999;
    page.form.detail_rows[0].engagement_name = 'Stored workspace';
    await page.openDetailModal(0);
    assert.equal(page.detailDraft.project_id, 999);
    assert.equal(page.detailDraft.engagement_name, 'Stored workspace');
    page.saveDetail();
    assert.equal(page.serializedDetailRows()[0].project_id, 999);

    await page.openDetailModal(0);
    await page.changeDetailClient(2);
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(page.detailDraft.project_id, null);
    assert.equal(page.detailDraft.engagement_name, '');
    assert.deepEqual(plain(page.engagements).map(item => item.id), [301]);
    page.selectDetailEngagement('301', 'Other Client Engagement');
    await page.changeEngagementType('Project');
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(page.detailDraft.project_id, null);
    page.selectDetailEngagement('401', 'Other Client Engagement');
    await page.changeEngagementType('Regular');
    assert.equal(page.detailDraft.deal_id, null);
    assert.equal(page.detailDraft.project_id, null);
    assert.equal(page.detailDraft.engagement_name, '');

    assert.match(form, /<option[^>]*:value="String\(engagement\.id\)"[^>]*:selected="String\(engagement\.id\) === String\(clientEngagementValue\(\)\)"/,
        'fetched options must bind their selected state when Alpine creates them');
    console.log('O.B. edit engagement regression passed: Regular/Project options, preselect, unchanged save, draft reload, clearing, and delayed-response isolation.');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
