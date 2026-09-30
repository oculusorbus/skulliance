/* Drives the REAL paintOverview() out of missions.php against a DOM stub.
 * The bug it protects: "4 ready to collect" and the In-the-field figure
 * were first-paint PHP that nothing ever updated, so after collecting they
 * disagreed with the nav badge sitting next to them. */
'use strict';
const fs = require('fs');
const src = fs.readFileSync('/Users/jeremiahbarber/Documents/GitHub/skulliance/missions.php', 'utf8');

/* Brace-extract the real functions rather than retyping them. */
function grab(name) {
  const at = src.indexOf('function ' + name + '(');
  if (at < 0) throw new Error('no ' + name);
  let i = src.indexOf('{', at), d = 0;
  for (let j = i; j < src.length; j++) {
    if (src[j] === '{') d++;
    else if (src[j] === '}') { d--; if (!d) return src.slice(at, j + 1); }
  }
}
const code = ['figValue','setFig','paintOverview'].map(grab).join('\n');

let fails = 0;
function ok(c, w) { if (!c) { fails++; console.log('  FAIL  ' + w); } }

function makeDoc(ready, active) {
  const figs = {};
  function fig(name, val, cls) {
    const b = { textContent: String(val), appendChild(){}, };
    return { name, b, className: cls || 'ms-fig', removed: false,
             querySelector: () => b, remove() { this.removed = true; delete figs[name]; },
             setAttribute(){}, parentNode: { insertBefore(el){ figs[el._n] = el; } },
             nextSibling: null };
  }
  figs.active    = fig('active', active);
  figs.completed = fig('completed', 7252);
  figs.levels    = fig('levels', 435);
  if (ready > 0) figs.ready = fig('ready', ready, 'ms-fig ms-fig-go');
  const title = { textContent: (ready > 0 ? ready + ' ready to collect' : active + ' in the field') };
  return {
    figs, title,
    getElementById: id => (id === 'ms-title' ? title : null),
    querySelector(sel) {
      const m = /\[data-fig="([a-z]+)"\]( b)?/.exec(sel);
      if (!m) return null;
      const f = figs[m[1]];
      if (!f) return null;
      return m[2] ? f.b : f;
    },
    createElement() { const b = {textContent:''};
      return { _n:'ready', className:'', innerHTML:'', b,
               setAttribute(k,v){ if(k==='data-fig') this._n=v; },
               querySelector: () => b, remove(){ delete figs.ready; }, appendChild(){} };
    }
  };
}

function run(label, startReady, startActive, ready, ov, wantTitle, wantActive) {
  const doc = makeDoc(startReady, startActive);
  const n = v => Number(v || 0).toLocaleString();
  new Function('document', 'n', code + '\npaintOverview(' + JSON.stringify(ready) + ',' + JSON.stringify(ov) + ');')(doc, n);
  ok(doc.title.textContent === wantTitle,
     label + ': title is "' + doc.title.textContent + '", wanted "' + wantTitle + '"');
  if (wantActive !== undefined) {
    const a = doc.figs.active ? doc.figs.active.b.textContent : null;
    ok(a === wantActive, label + ': In-the-field figure is ' + a + ', wanted ' + wantActive);
  }
  return doc;
}

console.log('\nthe headline follows the page');
run('collected all four', 4, 200, 0, {active: 196, success: 7256, levels_open: 435, levels_top: 455},
    '196 in the field', '196');
run('some still ready', 4, 200, 2, {active: 198, success: 7254, levels_open: 435, levels_top: 455},
    '2 ready to collect', '198');
run('nothing left at all', 4, 4, 0, {active: 0, success: 7256, levels_open: 435, levels_top: 455},
    'Send your NFTs out to work.', '0');
run('no overview: falls back to the figure on the page', 1, 12, 0, null,
    '12 in the field');

console.log('\nthe Ready chip');
let d = run('drops when nothing is ready', 4, 200, 0,
            {active: 196, success: 1, levels_open: 1, levels_top: 2}, '196 in the field');
ok(!d.figs.ready, 'the Ready chip is still on the page with nothing ready');
d = run('is created when one lands', 0, 200, 3,
        {active: 200, success: 1, levels_open: 1, levels_top: 2}, '3 ready to collect');
ok(!!d.figs.ready, 'the Ready chip was not created when something became ready');

console.log(fails ? '\nFAILED: ' + fails + ' check(s)' : '\nall missions headline checks passed');
process.exit(fails ? 1 : 0);
