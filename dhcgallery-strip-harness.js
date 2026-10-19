/*
 * dhcgallery-strip-harness.js -- the proven strip's render, lifted out of the
 * JS that dhc-fighter-modal.php actually ships and run on fixtures.
 *
 * Nothing is reimplemented here. /tmp/modal.js is produced by rendering the
 * real modal through PHP (see the harness's sibling PHP file for how), the
 * block between the two markers below is the shipped code, and the stubs
 * supply only `document` and `esc`.
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync } = require('child_process');
let fails = 0;
const ok = (c, w) => { if (!c) { fails++; console.log('FAIL  ' + w); } else console.log('ok    ' + w); };

/* Render the template here, so this harness needs nothing left behind by a
   previous run and always tests the modal as it stands right now. */
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'dhc-strip-'));
try { execFileSync('php', [path.join(__dirname, 'dhcgallery-strip-render.php'), tmp], { stdio: 'pipe' }); }
catch (e) { console.log('FAIL  cannot render the modal: ' + (e.stderr || e.message)); process.exit(2); }

const js = fs.readFileSync(path.join(tmp, 'modal.js'), 'utf8');

console.log('\n-- no CSS escapes its <style> block --');
/*
 * THIS SHIPPED. A block of rules was inserted one line past </style> and the
 * whole lot printed onto the DHC Fighters page as body text -- selectors,
 * braces and a comment, in the middle of the page. PHP was happy, the JS was
 * happy, and every test here passed, because nothing was looking at what the
 * template actually emits.
 *
 * The check is on the rendered HTML with every <style> element removed: what
 * is left must not contain a CSS rule.
 */
{
  const html = fs.readFileSync(path.join(tmp, 'modal.html'), 'utf8');
  const open = (html.match(/<style\b/g) || []).length;
  const close = (html.match(/<\/style>/g) || []).length;
  ok(open === close, `style tags balance (${open} open, ${close} close)`);
  ok(open > 0, 'and the modal ships some');
  const outside = html.replace(/<style[\s\S]*?<\/style>/g, '')
                      .replace(/<script[\s\S]*?<\/script>/g, '');
  const stray = outside.match(/[#.][a-zA-Z][\w-]*\s*\{[^}]*:[^}]*\}/);
  ok(!stray, 'no CSS rule appears outside a style block'
     + (stray ? ' (found "' + stray[0].slice(0, 70) + '")' : ''));
  const strayComment = outside.match(/\/\*[\s\S]{0,60}\*\//);
  ok(!strayComment, 'nor a stray CSS comment'
     + (strayComment ? ' (found "' + strayComment[0].slice(0, 60) + '")' : ''));
  /* And the strip's own rules did make it in. */
  const css = (html.match(/<style[\s\S]*?<\/style>/g) || []).join('');
  ok(css.indexOf('#dhcg-proven{') > -1, 'the proven strip is styled, inside the block');

  /*
   * THE STRIP IS A GRID, AND auto-FILL RATHER THAN auto-fit.
   *
   * It was a wrapping flex row, and three separate things went wrong on
   * a real Fighter once the tiles grew a second line: align-items at the
   * end left a row of tiles sitting at different heights; the heading
   * and the count were ordinary flex items, so a wrap put them halfway
   * up the middle of the strip; and making the tiles grow left a single
   * leftover tile stretched across the full width on its own.
   *
   * auto-fit COLLAPSES the empty tracks, which brings that last problem
   * straight back -- a lone tile on the final row stretches to fill. The
   * difference is one word and it is the whole fix.
   */
  for (const id of ['#dhcg-rank', '#dhcg-proven']) {
    const m = css.match(new RegExp(id.replace('#', '\\#') + '\\{([^}]*)\\}'));
    ok(!!m, id + ' has no rule at all');
    if (!m) continue;
    const rule = m[1];
    ok(/display:\s*grid/.test(rule), id + ' is not a grid: ' + rule.slice(0, 60));
    ok(/auto-fill/.test(rule), id + ' uses auto-fit, so a lone tile stretches across the row');
    ok(!/flex-wrap/.test(rule), id + ' is still a wrapping flex row');
  }
  /* The heading and the count span the whole strip and are pinned to the
     ends, or a wrap drops them between the tiles. */
  for (const sel of ['#dhcg-rank .of', '#dhcg-proven .hd', '#dhcg-proven .of']) {
    const m = css.match(new RegExp(sel.replace(/[#.]/g, (c) => '\\' + c) + '\\{([^}]*)\\}'));
    ok(!!m, sel + ' has no rule');
    if (!m) continue;
    ok(/grid-column:\s*1\/-1/.test(m[1]),
       sel + ' does not span the strip, so it sits in a tile-sized slot mid-row');
    /* A BOUNDARY, because "border:0" contains "order:0" and a bare search
       for it matched the border declaration sitting right beside it --
       so this assertion could not fail. */
    ok(/(^|;)\s*order:\s*\d/.test(m[1]),
       sel + ' has no order, so a wrap lands it wherever it was built');
  }
}
const START = "var pel = document.getElementById('dhcg-proven');";
const END   = "' Fighters with a record</div>';";
const i = js.indexOf(START), j = js.indexOf(END, i);
if (i < 0 || j < 0) { console.log('FAIL  cannot lift the strip block from the shipped JS'); process.exit(2); }
/* through the END marker and on to the brace that closes its else */
const block = js.slice(i, js.indexOf('}', j + END.length) + 1);
ok(block.includes('PAXES.map'), 'lifted block maps over PAXES');
ok(block.includes('beats its build'), 'lifted block carries the over-performing branch');
ok(block.includes('under its build'), 'lifted block carries the under-performing branch');

/* The real axis table, as PHP emits it. */
const PAXES = JSON.parse(fs.readFileSync(path.join(tmp, 'paxes.json'), 'utf8'));
ok(PAXES.length === 3, 'the page emits three proven axes');
let out = '';
const document = { getElementById: (id) => id === 'dhcg-proven'
  ? { set innerHTML(v) { out = v; }, get innerHTML() { return out; } } : null };
const esc = (s) => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;');
const run = (f) => { out = ''; new Function('f','PAXES','document','esc', block)(f, PAXES, document, esc); return out; };

console.log('\n-- an unproven Fighter gets no strip at all --');
ok(run({ prank: {}, crank: {}, perf: null, prankOf: 0 }) === '', 'empty prank renders nothing');
ok(run({ prankOf: 0 }) === '', 'a card with no perf keys at all renders nothing, and does not throw');

console.log('\n-- a Fighter the ledger rates ABOVE its build --');
/* #1 on proven toughness, #3 on the torso it is wearing. */
/* ALL THREE PERF VALUES DIFFER, or an axis reading the wrong key lands on the
   right number by accident and nothing below can tell. */
const over = { prankOf: 31,
  perf:  { battles: 12, dmg: 137, live: 83, real: 114 },
  prank: { real: 4, live: 1,  dmg: 9 },
  crank: { might: 7, tough: 3, power: 11 } };
let h = run(over);
ok(h.includes('In the Arena &middot; 12 battles'), 'heading carries its own battle count, plural');
ok(h.includes('<b>#1</b><span>Toughest</span>'), 'proven toughness is #1');
ok(h.includes('83% survived'), 'the % unit needs no separator: "83% survived"');
ok(h.includes('114 dmg/battle held'), 'the held-damage unit gets a space, and its own value');
ok(h.includes('137 dmg/battle<'), 'the raw-damage axis reads 137, not 114 or 83');
ok(/class="up">beats its build, #3 on traits/.test(h), 'toughest is flagged as beating its build (#3 on traits)');
ok(h.includes('of 31 Fighters with a record'), 'footer names the measured population');
ok(!h.includes('undefined') && !h.includes('NaN'), 'no undefined or NaN in the markup');
/* Every axis over-performs in this fixture, so all three carry the up class. */
ok((h.match(/class="up"/g) || []).length === 3, 'all three axes read as over-performing here');
ok((h.match(/class="dn"/g) || []).length === 0, 'and none as under-performing');

console.log('\n-- the same gaps reversed read the other way --');
const under = { prankOf: 31,
  perf:  { battles: 1, dmg: 20, live: 40, real: 8 },
  prank: { real: 7, live: 3,  dmg: 11 },
  crank: { might: 4, tough: 1, power: 9 } };
h = run(under);
/* "1 battles" CONTAINS "1 battle", so the positive check alone cannot fail. */
ok(h.includes('In the Arena &middot; 1 battle<'), 'one battle is singular');
ok(!h.includes('1 battles'), '...and is not "1 battles"');
ok(/class="dn">under its build, #1 on traits/.test(h), 'the best torso in the collection, 3rd by what survived');
ok((h.match(/class="dn"/g) || []).length === 3, 'all three read as under-performing');
ok((h.match(/class="up"/g) || []).length === 0, 'and none as over-performing');

console.log('\n-- equal ranks say so, rather than picking a direction --');
h = run({ prankOf: 31, perf: { battles: 9, dmg: 50, live: 50, real: 25 },
          prank: { real: 5, live: 5, dmg: 5 }, crank: { might: 5, tough: 5, power: 5 } });
ok((h.match(/exactly as built/g) || []).length === 3, 'three axes, three "exactly as built"');
ok(!h.includes('class="up"') && !h.includes('class="dn"'), 'no arrow class when the gap is zero');

console.log('\n-- a missing claimed rank drops the comparison, keeps the number --');
h = run({ prankOf: 31, perf: { battles: 9, dmg: 50, live: 50, real: 25 },
          prank: { real: 5, live: 5, dmg: 5 }, crank: {} });
ok(h.includes('<b>#5</b>'), 'the proven rank still prints');
ok(!h.includes('on traits') && !h.includes('exactly as built'), 'and no comparison is invented from nothing');

console.log('\n-- the side chips --');
ok(run(Object.assign({}, over, { edge: 'attack' })).includes('Better attacking'), 'edge=attack draws the attacking chip');
ok(run(Object.assign({}, over, { edge: 'defend' })).includes('Better defending'), 'edge=defend draws the defending chip');
const even = run(Object.assign({}, over, { edge: 'even' }));
ok(!even.includes('Better attacking') && !even.includes('Better defending'), 'edge=even draws neither');
ok(!run(over).includes('Better '), 'an absent edge draws neither');

fs.rmSync(tmp, { recursive: true, force: true });
console.log('\n' + (fails ? fails + ' FAILED' : 'all passed'));
process.exit(fails ? 1 : 0);
