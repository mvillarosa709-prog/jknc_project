const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const root = process.cwd();
const read = path => fs.readFileSync(`${root}/${path}`, 'utf8');
const view = read('resources/views/human-capital/obf.blade.php');
const form = read('resources/views/human-capital/partials/obf-create-form.blade.php');
const preview = read('resources/views/human-capital/partials/obf-create-preview.blade.php');
const actualObTemplates = `${view}\n${form}\n${preview}`;

for (const marker of ['\u00C3', '\u00E2', '\u00C2', '\u00EF', '\uFFFD']) {
    assert.ok(!actualObTemplates.includes(marker), `O.B. templates contain no ${marker} mojibake marker`);
}

assert.match(form, /Primary Traveler[\s\S]*&mdash;/);
assert.match(view, /detailRoute\(row\)\s*\{\s*return `\$\{row\.from_location \|\| '-'\} -> \$\{row\.destination \|\| '-'\}`;/);
for (const field of ['departure_date', 'departure_time', 'expected_arrival_date', 'expected_arrival_time']) {
    assert.match(form, new RegExp(`${field} \\|\\| '-'`), `${field} has a clean empty placeholder`);
}
assert.match(view, /transportationDescription\(transportation\)[\s\S]*join\(' \/ '\)[\s\S]*\|\| '-'/);
assert.match(form, /transportation\?\.mode \? transportationModeLabel\(row\.transportation\.mode\) : 'Not selected'/);
assert.match(form, />&times;<\/button>/);
assert.match(form, />&mdash;</);
assert.doesNotMatch(`${form}\n${preview}`, /&(?:amp;)?(?:mdash|times)(?!;)/);
assert.match(form, /classificationDescription\(option\)/, 'classification information tooltips remain wired');

const script = view.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/@json\([^\n]*\)/g, 'null')
    .replace(/\{\{[\s\S]*?\}\}/g, '/test');
const context = {console, window: {confirm: () => true, alert: () => {}}, document: {}, URLSearchParams};
vm.createContext(context);
vm.runInContext(script, context);
const page = context.obfPage();
assert.equal(page.detailRoute({from_location: 'Office', destination: 'Airport'}), 'Office -> Airport');
assert.equal(page.detailRoute({}), '- -> -');
assert.equal(page.transportationDescription({mode: 'Walking'}), '-');
assert.equal(page.transportationDescription({mode: 'Taxi'}), '-');
assert.equal(page.formatExpense(null), '-');

console.log('O.B. mojibake regression passed.');
