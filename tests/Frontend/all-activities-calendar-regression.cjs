const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'resources/views/activities/index.blade.php'), 'utf8');
const calendar = fs.readFileSync(path.join(root, 'resources/views/activities/partials/calendar.blade.php'), 'utf8');

const navStart = source.indexOf('<nav class="flex flex-col');
const allSidebar = source.slice(navStart, source.indexOf('<span>Tasks</span>'));
const allButtonEnd = allSidebar.indexOf('</button>') + '</button>'.length;
const allButton = allSidebar.slice(0, allButtonEnd);
assert.match(allButton, /<span>All Activities<\/span>/);
assert.doesNotMatch(allButton, /chevron|aria-expanded/);
assert.doesNotMatch(allSidebar.slice(allButtonEnd), /Calendar|Board|@click\.stop|x-show/);
assert.doesNotMatch(source, /all-calendar|allActivitiesExpanded/);
assert.match(source, /activeTab: 'all'/);

const parentClick = allButton.match(/@click="([\s\S]*?)"/)[1];
for (const taskView of ['list', 'board', 'calendar']) {
    const state = { activeTab: 'task', taskView, selectedTaskDetails: {}, selectedMeetingDetails: {}, showVideoPlayer: true };
    new Function('state', `with (state) { ${parentClick} }`)(state);
    assert.equal(state.activeTab, 'all');
    assert.equal(state.taskView, taskView);
}

const landingStart = source.indexOf('    x-show="activeTab === \'all\'"');
assert.ok(landingStart >= 0);
const landing = source.slice(landingStart, source.indexOf('     TASKS V2', landingStart));
assert.match(landing, /filteredAllActivities/);
assert.match(landing, /allActivitiesSearch/);
assert.doesNotMatch(landing.slice(0, landing.indexOf('>')), /x-cloak|display: none|x-show="false"/);
assert.doesNotMatch(landing, /activities\.partials\.calendar/);

assert.equal((source.match(/@include\('activities\.partials\.calendar'/g) || []).length, 1);
assert.match(source, /calendarVisible' => "taskView === 'calendar'"/);
assert.match(calendar, /<template x-if="\{\{ \$calendarVisible \}\}">/);
assert.match(calendar, /src="https:\/\/calendar\.google\.com\/calendar\/embed\?src=c_202fb00fab3d252dbc66929418b0edf779372e0165af84c0f5bdce4f1ca7d38d%40group\.calendar\.google\.com&amp;ctz=Asia%2FManila"/);
for (const view of ['list', 'board', 'calendar']) assert.match(source, new RegExp(`@click="taskView = '${view}'"`));
assert.match(source, /taskView: 'list'/);

const backButtons = [...source.matchAll(/<button\b[\s\S]*?<\/button>/g)].map(match => match[0]).filter(button => button.includes('Back to All Activities'));
assert.ok(backButtons.length >= 10);
for (const button of backButtons) {
    const click = button.match(/@click="([^"]+)"/)[1];
    const state = { activeTab: 'task', selectedTaskDetails: {}, selectedMeetingDetails: {}, currentPage: 2, showVideoPlayer: true };
    new Function('state', `with (state) { ${click} }`)(state);
    assert.equal(state.activeTab, 'all');
}
console.log('PASS: All Activities has no submenu/calendar state, parent/back/default open the landing area, and Tasks retains List/Board/Calendar and the original Google Calendar embed.');
