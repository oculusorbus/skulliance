/* missions-fastforward-harness.js — node only.
 *
 * Drives the REAL effectiveDays() / daysText() / paintRate() out of
 * missions.php against a DOM stub.
 *
 * THE BUG IT PROTECTS: picking Fast Forward changed nothing on screen.
 * The facts row still read "Runs for 4 days", the launch line still said
 * "back in 4 days", and the only way to find out the item had done
 * anything was to send the mission and look at the countdown.
 *
 * WHY A SECOND HARNESS: the shortened wait is computed in two languages.
 * The server never stores it -- mission_active() and completeMission()
 * pull created_date BACK by ceil(duration / 2) and count the full
 * duration forward from there -- and the drawer has to predict that
 * before the item is spent. missions-harness.php proves the server's
 * number by driving mission_active(); this proves the browser agrees,
 * reading the expected values OUT OF that same file so there is one
 * table, not two.
 */
'use strict';
const fs = require('fs');
const dir = '/Users/jeremiahbarber/Documents/GitHub/skulliance';
const src = fs.readFileSync(dir + '/missions.php', 'utf8');

/* Brace-extract the real functions rather than retyping them. */
function grab(name) {
  const at = src.indexOf('function ' + name + '(');
  if (at < 0) throw new Error('no ' + name + ' in missions.php');
  let d = 0;
  for (let j = src.indexOf('{', at); j < src.length; j++) {
    if (src[j] === '{') d++;
    else if (src[j] === '}') { d--; if (!d) return src.slice(at, j + 1); }
  }
  throw new Error('unbalanced ' + name);
}

/* ONE TABLE, read out of the PHP harness, which proves it against the
   real mission_active(). A copy here could agree with itself forever. */
const php = fs.readFileSync(dir + '/missions-harness.php', 'utf8');
const tbl = php.match(/\$FF_TABLE = array\(([^)]*)\)/);
if (!tbl) throw new Error('no $FF_TABLE in missions-harness.php -- it is the source of truth for these values');
const EXPECT = {};
tbl[1].split(',').forEach(pair => {
  const m = pair.match(/(\d+)\s*=>\s*(\d+)/);
  if (m) EXPECT[+m[1]] = +m[2];
});
if (!Object.keys(EXPECT).length) throw new Error('$FF_TABLE parsed empty');

/* The constant the page emits from PHP. */
const ffm = src.match(/var MS_FAST_FORWARD = <\?php echo \(int\)(\w+); \?>;/);
if (!ffm) throw new Error('MS_FAST_FORWARD is no longer emitted from the PHP constant');
const libm = fs.readFileSync(dir + '/missions-lib.php', 'utf8')
               .match(new RegExp("define\\('" + ffm[1] + "',\\s*(\\d+)\\)"));
if (!libm) throw new Error('cannot resolve ' + ffm[1] + ' in missions-lib.php');
const MS_FAST_FORWARD = +libm[1];

let fails = 0;
function ok(c, w) { if (!c) { fails++; console.log('  FAIL  ' + w); } }

/* ---------- the DOM the drawer writes into ------------------------------ */
function el() {
  return { textContent: '', innerHTML: '', className: '', style: {},
           classList: { toggle() {}, add() {}, remove() {} },
           querySelector() { return { style: {} }; } };
}
function makeDoc() {
  const ids = {};
  ['ms-d-pct','ms-d-meter','ms-d-note','ms-d-days','ms-d-netday','ms-d-summary']
    .forEach(id => { ids[id] = el(); });
  return { ids, getElementById: id => ids[id] || el() };
}

/* ---------- drive it ---------------------------------------------------- */
const code = ['n', 'effectiveDays', 'daysText', 'rateParts', 'paintRate'].map(grab).join('\n');
const run = new Function('document', 'LO', 'picked', 'items', 'MS_FAST_FORWARD',
                         code + '\npaintRate(); return null;');

function paint(duration, ff, opts) {
  opts = opts || {};
  const doc = makeDoc();
  const cost = opts.cost === undefined ? 100 : opts.cost;
  const reward = opts.reward === undefined ? 300 : opts.reward;
  const LO = { duration: duration, cost: cost, reward: reward, currency: 'STAR',
               net_per_day: duration > 0 ? (reward - cost) / duration : 0,
               squad: [{}, {}] };
  /* Fast Forward's boost is 0, which is the trap: a truthiness test for
     "is this item picked" reads it as not picked. */
  const items = ff ? { [MS_FAST_FORWARD]: 0 } : {};
  run(doc, LO, { 7: 30 }, items, MS_FAST_FORWARD);
  return doc.ids;
}

console.log('the drawer predicts the shortened wait');

Object.keys(EXPECT).map(Number).forEach(d => {
  const plain = paint(d, false), fast = paint(d, true);
  const want = EXPECT[d];

  ok(plain['ms-d-days'].innerHTML === (d === 1 ? '1 day' : d + ' days'),
     'without the item a ' + d + '-day mission reads "' + plain['ms-d-days'].innerHTML + '"');
  ok(plain['ms-d-days'].className === '',
     'a mission with no item is being flagged as changed');
  ok(/back in /.test(plain['ms-d-summary'].textContent)
     && !/instead of/.test(plain['ms-d-summary'].textContent),
     'the launch line claims a saving with no item picked');

  /* THE POINT: the new figure is the one mission_active() will honour. */
  const shown = fast['ms-d-days'].innerHTML;
  const head = want <= 0 ? 'No wait' : (want === 1 ? '1 day' : want + ' days');
  ok(shown.indexOf(head) === 0,
     'with Fast Forward a ' + d + '-day mission reads "' + shown + '" but lands in ' + want);
  /* And it still says what it WAS, or there is nothing to compare. */
  ok(/<em>/.test(shown) && shown.indexOf(d === 1 ? '1 day' : d + ' days') > 0,
     'the old duration is gone, so the saving is invisible: "' + shown + '"');
  ok(fast['ms-d-days'].className === 'ms-cut',
     'the changed figure is not marked, so it reads as the mission\'s own duration');

  /* Net per day moves with it -- a halved wait that leaves the old
     per-day figure beside it contradicts itself on screen. The exception
     is a ONE-day mission: the item removes the wait without changing what
     a day pays, so that figure must stay put AND stay unmarked. */
  const moved = fast['ms-d-netday'].textContent !== plain['ms-d-netday'].textContent;
  if (want === 0) {
    ok(!moved, 'nothing is left to wait on a ' + d + '-day mission, yet net per day moved');
    ok(fast['ms-d-netday'].className === '',
       'net per day is flagged as changed while showing the same figure');
  } else {
    ok(moved, 'net per day did not move on a ' + d + '-day mission, so the two facts disagree');
    ok(fast['ms-d-netday'].className === 'ms-cut', 'net per day is not marked as changed');
  }
});

/* The launch line is what they read with their thumb on the button. */
const s4 = paint(4, true)['ms-d-summary'].textContent;
ok(/back in 2 days/.test(s4) && /instead of 4 days/.test(s4),
   'the launch line does not spell out the saving: "' + s4 + '"');
const s1 = paint(1, true)['ms-d-summary'].textContent;
ok(/lands the moment you send it/.test(s1) && !/back in 0/.test(s1),
   'a mission with nothing left to wait reads as "back in 0 days": "' + s1 + '"');
ok(/instead of 1 day/.test(s1), 'and it does not say what it saved: "' + s1 + '"');

/* A free mission still leads with its cost line, item or no item. */
ok(/^Free to run · /.test(paint(4, true, {cost: 0})['ms-d-summary'].textContent),
   'the cost half of the launch line was lost');

/* Nothing to shorten: a mission that was already instant must not claim
   a saving it did not make. */
const z = paint(0, true);
ok(z['ms-d-days'].className === '' && !/instead of/.test(z['ms-d-summary'].textContent),
   'a zero-day mission claims Fast Forward saved it something');

console.log('');
if (fails) { console.log(fails + ' check(s) FAILED'); process.exit(1); }
console.log('all fast-forward checks passed');
