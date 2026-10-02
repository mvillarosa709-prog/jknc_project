const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const view = fs.readFileSync('resources/views/human-capital/obf.blade.php', 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/@json\([^\n]*\)/g, 'null')
    .replace(/\{\{[\s\S]*?\}\}/g, '/test');
const context = { console, window: {}, document: {} };
vm.createContext(context);
vm.runInContext(script, context);
const page = context.obfPage();
page.hydrateClientRouteMap = () => {};
page.setPreviewFromTrip = () => {};
page.openEdit({ id: 42, workflow_version: 3, status: 'Draft', can_edit: true, movement_status: 'Departed' });
assert.equal(page.form.id, 42);
assert.equal(page.isEdit, true);
assert.equal(page.isView, false);
assert.equal(page.form.status, 'Draft');
assert.equal(page.form.movement_status, 'Departed');
page.openEdit({ id: 99, can_edit: false });
assert.equal(page.form.id, 42);
assert.match(view, /:action="isEdit \?[^\n]*\$\{form.id\}/);
assert.match(view, /<template x-if="isEdit">\s*<input type="hidden" name="_method" value="PUT">/);
assert.match(view, /type="submit" name="intent" value="save_draft"/);
assert.match(view, /type="submit" name="intent" value="submit"/);
assert.match(view, /x-if="isView && form.can_edit"/);
console.log('O.B. Draft edit regression: 11 assertions passed; full Blade script parsed.');
