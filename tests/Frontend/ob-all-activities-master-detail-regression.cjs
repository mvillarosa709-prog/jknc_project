const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const view = fs.readFileSync(path.join(__dirname, '../../resources/views/activities/index.blade.php'), 'utf8');
const detailView = fs.readFileSync(path.join(__dirname, '../../resources/views/activities/partials/inline-details.blade.php'), 'utf8');
assert.match(view, /officialBusinessCanApproveSelected/);
assert.match(view, /officialBusinessCapabilities\?\.approve/);
assert.match(view, /route\('human-capital\.obf\.approve'/);
assert.match(view, /route\('human-capital\.obf\.revert'/);
assert.match(view, /officialBusinessApprovalUrlTemplates/);
assert.match(view, /officialBusinessApprovalUrl\(action, record\)/);
assert.doesNotMatch(view, /fetch\(`\/obf\/\$\{record\.id\}\/\$\{action\}`/);
assert.match(detailView, />\s*Approve\s*<\/button>/);
assert.match(detailView, />\s*Revert \/ Return for Correction\s*<\/button>/);
assert.match(detailView, /This Trip Ticket is read-only for your account/);
assert.match(detailView, /x-if="!editable"/);
assert.match(detailView, /ticket\?\.status === 'Completed'/);
assert.match(detailView, /x-show="canComplete"/);
const reportBody = view.match(/        reportActivity\(row\) \{([\s\S]*?)\n        \},\r?\n\r?\n        async fetchAllActivitiesReport\(/)?.[1];
const detailBody = view.match(/        async openActivityDetails\(activity\) \{([\s\S]*?)\n        \},\r?\n\r?\n        closeActivityDetails\(/)?.[1];
assert.ok(reportBody && detailBody, 'All Activities mapping and View handlers exist');

const reportActivity = new Function('row', reportBody);
const openActivityDetails = new (Object.getPrototypeOf(async function () {}).constructor)('activity', detailBody);

const master = {
    id: 41,
    employee: 'System Super Admin',
    type: 'Multiple Clients',
    trip_type: 'Client travel',
    clients: 'PRECIOUS RTW, JM VLOGS',
    destination_primary: 'Client office',
    purpose: 'Client visits',
    purpose_options: ['Review records'],
    transportation_mode: 'Car',
    departure: '2026-09-25 08:00',
    expectedReturn: '2026-09-25 17:00',
    status: 'Approved',
};
const opened = [];
const component = {
    officialBusinessRecords: [master, { id: 7, employee: 'Wrong traveler' }],
    unifiedActivities: [],
    tasks: [], events: [], calls: [], meetings: [], emails: [], messages: [],
    beginActivityDetail() {},
    async openOfficialBusinessDetails(record) { opened.push(record); },
};

async function checkView(row) {
    const activity = reportActivity.call(component, row);
    await openActivityDetails.call(component, activity);
    assert.equal(activity.source_id, '41');
    assert.equal(activity.display_status, 'Approved');
    assert.equal(opened.at(-1), master);
    for (const field of ['employee', 'type', 'trip_type', 'clients', 'destination_primary', 'purpose',
        'purpose_options', 'transportation_mode', 'departure', 'expectedReturn']) {
        assert.deepEqual(opened.at(-1)[field], master[field]);
    }
}

(async () => {
    await checkView({
        id: 'official-business-travel-41', source_activity_type: 'OFFICIAL_BUSINESS_TRAVEL',
        source_activity_id: 41, status: 'Approved',
    });
    for (const [ticketId, client] of [[7, 'PRECIOUS RTW'], [8, 'JM VLOGS']]) {
        await checkView({
            id: `official-business-trip-ticket-${ticketId}`,
            source_activity_type: 'OFFICIAL_BUSINESS_TRAVEL', source_activity_id: ticketId,
            source_activity_parent_id: 41, company: client, status: 'Approved',
        });
    }
    const task = reportActivity.call(component, {
        id: 'task-3', source_activity_type: 'TASK', source_activity_id: 3, status: 'Open',
    });
    assert.equal(task.source_id, 3);
    assert.equal(task.display_status, 'Open');
    console.log('All Activities O.B. master detail regression passed');
})().catch(error => { console.error(error); process.exitCode = 1; });
