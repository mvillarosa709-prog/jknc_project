const fs = require('fs');
const path = require('path');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.resolve(__dirname, '../../resources/views/activities/index.blade.php'), 'utf8');
function getter(name, nextName) {
    const start = source.indexOf(`        get ${name}() {`);
    const end = source.indexOf(`        get ${nextName}() {`, start);
    assert.ok(start >= 0 && end > start);
    return Object.getOwnPropertyDescriptor(new Function(`return {${source.slice(start, end)}}`)(), name).get;
}
const filtered = getter('filteredTasksList', 'filteredEventsList');
const counts = getter('taskCounts', 'eventCounts');
const subtasks = getter('taskWorkspaceSubtasks', 'taskWorkspaceCompletedSubtasks');
const state = {
    tasks: [
        { id: 1, name: 'Main task', parent_task_id: null, status: 'Open', priority: 'High', owner: 'owner@example.test' },
        { id: 2, name: 'Child only', parent_task_id: 1, status: 'Open', priority: 'High', owner: 'owner@example.test' },
        { id: 3, name: 'Completed main', status: 'Completed', priority: 'Normal', owner: 'other@example.test' },
        { id: 4, name: 'Nested child', parent_task_id: 2, status: 'Completed', priority: 'Normal' },
    ],
    filterSelection: { task: 'All Tasks' }, taskSearch: '', taskStatusFilter: 'all',
    taskPriorityFilter: 'all', taskOwnerFilter: 'all', currentUser: 'owner@example.test',
    teamUsers: ['owner@example.test'],
};
const ids = () => filtered.call(state).map(task => task.id);
assert.deepEqual(ids(), [1, 3]);
for (const scope of ['All Tasks', 'My Tasks', 'Team Tasks', 'Open Tasks', 'Closed Tasks', 'Overdue Tasks']) {
    for (const status of ['all', 'open', 'completed']) {
        for (const priority of ['all', 'high', 'normal']) {
            for (const search of ['', 'child', 'main']) {
                Object.assign(state, { filterSelection: { task: scope }, taskStatusFilter: status, taskPriorityFilter: priority, taskSearch: search });
                assert.ok(filtered.call(state).every(task => !task.parent_task_id));
                if (search === 'child') assert.deepEqual(ids(), []);
            }
        }
    }
}
Object.assign(state, { filterSelection: { task: 'All Tasks' }, taskStatusFilter: 'all', taskPriorityFilter: 'all', taskSearch: '' });
assert.deepEqual(counts.call(state), { total: 2, open: 1, completed: 1, overdue: 0 });
state.selectedTaskDetails = { id: 1, subtasks: [state.tasks[1]] };
assert.deepEqual(subtasks.call(state).map(task => task.id), [2]);
state.selectedTaskDetails = { id: 2 };
assert.deepEqual(subtasks.call(state).map(task => task.id), [4]);
state.tasks.unshift({ id: 5, parent_task_id: 1, name: 'New subtask' });
assert.deepEqual(ids(), [1, 3]);
state.selectedTaskDetails = { id: 1 };
assert.deepEqual(subtasks.call(state).map(task => task.id).sort((a, b) => a - b), [2, 5]);
state.tasks.unshift({ id: 6, parent_task_id: null, name: 'New main task' });
assert.deepEqual(ids(), [6, 1, 3]);

const sidebarStart = source.indexOf('<div x-data="{ open: false }"');
const sidebarEnd = source.indexOf("@click=\"activeTab = 'official-business'", sidebarStart);
assert.ok(sidebarStart >= 0 && sidebarEnd > sidebarStart);
const sidebar = source.slice(sidebarStart, sidebarEnd);
assert.match(sidebar, /Facebook Messenger/);
assert.match(sidebar, /@click="activeTab = 'messages'; messageTab = 'messenger'"/);
assert.doesNotMatch(sidebar, /All Messages|WhatsApp|Viber|>\s*SMS\s*</);
assert.doesNotMatch(sidebar, /messageTab = '(all|whatsapp|viber|sms)'/);
assert.match(source, /messageTab: 'messenger'/);
assert.match(source, /return this\.filteredTasksList\.filter\(task =>/); // Calendar shares the main-only list.
const conversations = getter('filteredMessages', 'timelineTasks');
const messageState = {
    messages: [{ id: 10, channel: 'Messenger' }, { id: 11, channel: 'SMS' }],
    messageTab: 'messenger', messageClientFilter: 'all', messageHandlingFilter: 'all', messageSearch: '',
    normalizedMessageValue: value => String(value || '').toLowerCase(),
};
assert.deepEqual(conversations.call(messageState).map(conversation => conversation.id), [10]);
console.log('PASS: main-only tasks across 162 search/filter combinations, counts, existing/new/nested subtasks, new main tasks, and Facebook Messenger sidebar routing.');
