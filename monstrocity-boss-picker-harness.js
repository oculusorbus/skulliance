/*
 * monstrocity-boss-picker-harness.js — narrowing the boss's extra-collections
 * list to the project.
 *
 * Run: node monstrocity-boss-picker-harness.js
 *
 * The filter is LIFTED OUT OF admin-monstrocity-boss.php AND RUN against a
 * minimal DOM. What matters is which rows end up hidden, and the one rule
 * that is easy to get wrong -- a ticked row must never be hidden, because a
 * hidden checkbox still posts -- is invisible to any source check.
 */
const fs = require('fs');
const path = require('path');

let fail = 0;
function ok(cond, msg) {
	if (cond) { console.log('  ok    ' + msg); return; }
	fail++; console.log('  FAIL  ' + msg);
}

/* ---- the smallest DOM the lifted code actually touches ----------------- */
class Cls {
	constructor() { this.set = new Set(); }
	toggle(n, on) { on ? this.set.add(n) : this.set.delete(n); }
	has(n) { return this.set.has(n); }
}
class El {
	constructor(tag, attrs = {}) {
		this.tag = tag; this.attrs = attrs; this.classList = new Cls();
		this.children = []; this.listeners = {}; this.textContent = '';
		this.value = attrs.value || ''; this.checked = !!attrs.checked;
		this.type = attrs.type || '';
	}
	getAttribute(n) { return n in this.attrs ? String(this.attrs[n]) : null; }
	addEventListener(ev, fn) { (this.listeners[ev] = this.listeners[ev] || []).push(fn); }
	fire(ev, e = {}) { (this.listeners[ev] || []).forEach(fn => fn(e)); }
	querySelector(sel) {
		if (sel === 'input[type=checkbox]') return this.children.find(c => c.type === 'checkbox') || null;
		return null;
	}
	querySelectorAll(sel) {
		if (sel === 'label[data-project]')
			return this.children.filter(c => c.tag === 'label' && c.getAttribute('data-project') !== null);
		return [];
	}
}

/* ---- lift the real filter ---------------------------------------------- */
const src = fs.readFileSync(path.join(__dirname, 'admin-monstrocity-boss.php'), 'utf8');
const at = src.indexOf('  var cols = document.getElementById(\'mb-cols\');');
const end = src.indexOf('  filterCols();', at);
ok(at !== -1 && end !== -1, 'the collection filter could not be lifted from the admin page');
const body = src.slice(at, end + '  filterCols();'.length);

/* Each scenario builds its own document and runs the lifted code over it. */
function run(projectId, rows, { showEvery = false } = {}) {
	const proj = new El('select'); proj.value = String(projectId);
	const cols = new El('div');
	const mine = new El('input', { type: 'radio' }); mine.checked = !showEvery;
	const every = new El('input', { type: 'radio' }); every.checked = showEvery;
	const count = new El('span');
	rows.forEach(r => {
		const label = new El('label', { 'data-project': r.project });
		const box = new El('input', { type: 'checkbox', value: r.id });
		box.checked = !!r.ticked;
		label.children.push(box);
		label.id = r.id;
		cols.children.push(label);
	});
	const byId = { 'mb-cols': cols, 'mb-colmine': mine, 'mb-allcols': every, 'mb-colcount': count };
	const document = { getElementById: id => byId[id] || null };
	new Function('document', 'proj', body)(document, proj);
	return {
		shown: cols.children.filter(c => !c.classList.has('hid')).map(c => c.id),
		count: count.textContent,
		proj, mine, every, cols,
	};
}

const ROWS = [
	{ id: 'a', project: 5 },
	{ id: 'b', project: 5 },
	{ id: 'c', project: 9 },
	{ id: 'd', project: 9 },
	{ id: 'e', project: 0 },
];

console.log('\nthe list narrows to the boss\'s project');
/* THE WHOLE POINT: this was every collection on the platform, hundreds of
   rows, for a field that means "the same project's other chains". */
let r = run(5, ROWS);
ok(JSON.stringify(r.shown) === JSON.stringify(['a', 'b']),
   'the list is not narrowed to the project: ' + JSON.stringify(r.shown));

console.log('\na ticked row is never hidden');
/*
 * A hidden checkbox STILL POSTS. Narrowing a ticked row out of sight would
 * leave the boss accepting a collection with nothing on screen saying so, and
 * no way to untick it. Same rule the single-collection dropdown already has
 * for its chosen option.
 */
r = run(5, [{ id: 'a', project: 5 }, { id: 'c', project: 9, ticked: true }]);
ok(r.shown.includes('c'),
   'a ticked collection from another project was hidden, so it cannot be unticked');
ok(r.shown.includes('a'), 'the project\'s own rows stopped showing');

console.log('\n"every project\'s" shows everything');
/* The escape hatch: a collection filed under a different project is a real
   case, which is why the theme form has the same toggle. */
r = run(5, ROWS, { showEvery: true });
ok(r.shown.length === ROWS.length, 'the show-all toggle does not show every row: ' + JSON.stringify(r.shown));

console.log('\nwith no project picked, nothing is hidden');
/* A new boss has project_id 0. Narrowing to it would empty the list and look
   like there are no collections at all. */
for (const pid of [0, '']) {
	r = run(pid, ROWS);
	ok(r.shown.length === ROWS.length,
	   'with project ' + JSON.stringify(pid) + ' the list came back narrowed: ' + JSON.stringify(r.shown));
}

console.log('\nit re-runs when the project changes');
/* The project picker and this list are two fields on one form; picking a
   project must not leave the list showing the old one's collections. */
r = run(5, ROWS);
r.proj.value = '9';
r.proj.fire('change');
ok(JSON.stringify(r.shown === undefined ? [] : r.cols.children.filter(c => !c.classList.has('hid')).map(c => c.id))
   === JSON.stringify(['c', 'd']),
   'changing the project did not re-narrow the list');

console.log('\nthe count stays honest');
r = run(5, ROWS);
ok(r.count === '2 shown, 0 ticked', 'the count is wrong: ' + r.count);
r = run(5, [{ id: 'a', project: 5, ticked: true }, { id: 'c', project: 9 }]);
ok(r.count === '1 shown, 1 ticked', 'the count is wrong with a tick: ' + r.count);
/* Ticking a row while "every project's" is on must keep it visible after the
   switch back, and the count must follow. */
r = run(5, ROWS, { showEvery: true });
const other = r.cols.children.find(c => c.id === 'c');
other.querySelector('input[type=checkbox]').checked = true;
r.every.checked = false; r.mine.checked = true;
r.mine.fire('change');
const shownNow = r.cols.children.filter(c => !c.classList.has('hid')).map(c => c.id);
ok(shownNow.includes('c'),
   'a row ticked under "every project\'s" vanished when switching back, tick still posting');

console.log(fail ? '\n' + fail + ' check(s) FAILED\n' : '\nall good\n');
process.exit(fail ? 1 : 0);
