/* skullswap-bombmatch-harness.js -- node, no browser.
 *
 * THE RULE, as decided: a matched bomb only detonates on an ULTRA MATCH
 * of five.
 *
 *   3 -- nothing special. A bomb in a 3-match is just a tile.
 *   4 -- the bombs are CONSUMED, not fired. Credit for what was spent,
 *        and the match forges the Carbon it earned, which detonates.
 *   5 -- every bomb in the match detonates, and so does the Diamond.
 *
 * This exists because 4befb56d made every bomb in ANY match detonate,
 * which was never the rule. Reading the branch is not enough to know what
 * fires -- so this drives the REAL Match3Game out of skullswap.php and
 * counts the detonations the game logs itself.
 *
 * Usage: node skullswap-bombmatch-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(c, w) { if (!c) { fails++; console.log('  FAIL  ' + w); } }

/* node 26 makes globalThis.navigator a getter-only property, so a plain
   assignment throws. The existing skullswap harnesses predate that. */
Object.defineProperty(global, 'navigator', { value: { maxTouchPoints: 0 }, configurable: true });
const mkEl = () => ({ classList:{add(){},remove(){}}, style:{}, remove(){} });
global.document = {
  getElementById: () => ({ textContent:'', style:{}, classList:{add(){},remove(){},toggle(){}},
                           addEventListener(){}, appendChild(){}, querySelector:()=>null, innerHTML:'' }),
  querySelector: () => null, querySelectorAll: () => [], addEventListener(){},
  createElement: () => mkEl(),
};
global.Audio = function(){ return { play(){ return Promise.resolve(); }, cloneNode(){ return this; },
                                    pause(){}, load(){}, volume:1, currentTime:0 }; };
global.requestAnimationFrame = f => setTimeout(f, 0);
global.fetch = () => Promise.resolve({ json: () => Promise.resolve({}), text: () => Promise.resolve('') });
global.XMLHttpRequest = function(){ return { open(){}, send(){}, setRequestHeader(){} }; };
global.IS_LOGGED_IN = false;
global.localStorage = { getItem:()=>null, setItem(){}, removeItem(){} };

const page = fs.readFileSync('skullswap.php', 'utf8');
const cls  = page.slice(page.indexOf('class Match3Game {'), page.indexOf('const game = new Match3Game();'))
                 .replace(/<\?php[\s\S]*?\?>/g, 'null');
const Match3Game = eval(cls + '\n; Match3Game');

/* bonusScores as the constructor actually declares it. */
function REAL_BONUSES() {
  const at = page.indexOf('this.bonusScores = {');
  const open = page.indexOf('{', at);
  let depth = 0, end = open;
  for (let i = open; i < page.length; i++) {
    if (page[i] === '{') depth++;
    else if (page[i] === '}') { depth--; if (!depth) { end = i; break; } }
  }
  return eval('(' + page.slice(open, end + 1) + ')');
}

function makeGame() {
  const g = Object.create(Match3Game.prototype);
  g.width = 8; g.height = 8; g.score = 0; g.isGrandFinale = false; g.isDetonating = false;
  g.specialTypes = { bomb4: 'carbon', bomb5: 'diamond' };
  g.specialIcons = { carbon: 'CARBON', diamond: 'DIAMOND' };
  /* THE REAL NUMBERS, read out of the page. A hand-copied bonusScores
     drifts: multiMatchStep was added to the game and every stub here kept
     its old five keys, so the new bonus scored undefined and the total
     came out NaN -- in a probe, silently. */
  g.bonusScores = REAL_BONUSES();
  g.board = [];
  for (let y=0;y<8;y++){ g.board.push([]); for(let x=0;x<8;x++) g.board[y].push({icon:'skull'+((x+y)%5), special:null, element:mkEl()}); }
  g.playSound = () => {}; g.renderBoard = () => {};
  g.showerTiles = () => { for (let y=0;y<8;y++) for (let x=0;x<8;x++)
      if (!g.board[y][x] || (g.board[y][x].icon===null && !g.board[y][x].special))
        g.board[y][x] = { icon:'skull'+((x*3+y)%5), special:null, element:mkEl() }; };
  g.cascadeTiles = () => {}; g.cascadeTilesWithoutRender = () => {};
  g.updateMatchCounter = () => {};
  return g;
}

/* Run a row of tiles through the REAL resolve path and report what the
   game says it detonated. Going through resolveMatches (not straight to
   handleBombMatches) is the point: the 3-match rule lives in the router. */
async function play(bombTypes, matchSize) {
  const g = makeGame();
  for (let i = 0; i < matchSize; i++) {
    g.board[0][i] = bombTypes[i]
      ? { icon:null, special:bombTypes[i], element:mkEl() }
      : { icon:'skullA', special:null, element:mkEl() };
  }
  const matches = new Set();
  for (let i = 0; i < matchSize; i++) matches.add(`${i},0`);
  /* Stand in for checkMatches so the row under test is what resolves. */
  g.checkMatches = () => ({ hasMatches:true, matches,
                            bombType: matchSize === 4 ? 'bomb4' : matchSize >= 5 ? 'bomb5' : null,
                            bombX: 0, bombY: 0 });

  const fired = [];
  const real = console.log;
  console.log = (m) => { const s = String(m);
    const d = s.match(/Detonating (\w+) bomb/); if (d) fired.push(d[1]); };
  await g.resolveMatches(0, 0);
  console.log = real;
  return { fired, score: g.score };
}

(async () => {
  console.log('\nwhat a matched bomb does at each size');
  const C = 'carbon', D = 'diamond';

  const m3 = await play([C, C, C], 3);
  console.log(`  3 carbons   -> detonated [${m3.fired.join(', ') || 'nothing'}]  score ${m3.score}`);
  ok(m3.fired.length === 1 && m3.fired[0] === 'carbon',
     'a 3-match should fire exactly ONE bomb, as the original game did; got ['
   + m3.fired.join(', ') + ']');
  /* And no CREDIT. The consumed-bomb payment belongs to the four; a three
     is paid in the blast it just set off. */
  ok(m3.score < 30 + 50 + 1000,
     'a 3-match looks like it is collecting the four\'s consumed-bomb credit '
   + 'on top of its detonation');

  const m3d = await play([D, D, D], 3);
  console.log(`  3 diamonds  -> detonated [${m3d.fired.join(', ') || 'nothing'}]  score ${m3d.score}`);
  ok(m3d.fired.length === 1 && m3d.fired[0] === 'diamond',
     'three DIAMONDS must fire one Diamond -- a full-board clear; got ['
   + m3d.fired.join(', ') + ']');
  ok(m3d.score > m3.score,
     'three Diamonds score no more than three Carbons, so the bomb that fired '
   + 'was not the Diamond');

  const m4 = await play([C, C, C, C], 4);
  console.log(`  4 carbons   -> detonated [${m4.fired.join(', ') || 'nothing'}]  score ${m4.score}`);
  ok(m4.fired.length === 2,
     'a 4-match should fire TWO bombs -- the original detonation plus the '
   + 'Carbon it forged; got [' + m4.fired.join(', ') + ']');
  ok(m4.score > 40 + 50, 'a 4-match is not crediting the bombs it wasted');
  const m4d = await play([D, D, D, D], 4);
  console.log(`  4 diamonds  -> detonated [${m4d.fired.join(', ') || 'nothing'}]  score ${m4d.score}`);
  ok(m4d.fired[0] === 'diamond' && m4d.fired[1] === 'carbon',
     'a 4-match of Diamonds should fire a Diamond first, then the forged '
   + 'Carbon; got [' + m4d.fired.join(', ') + ']');

  /* THE WHOLE POINT: a harder shape must never score less. Four Diamonds
     paying less than three was the trap that caused the original overreach,
     and it came straight back the moment the four stopped firing its bomb. */
  ok(m4d.score > m3d.score,
     'four Diamonds (' + m4d.score + ') score less than three (' + m3d.score
   + ') -- the harder shape is the worse move again');

  const m5 = await play([C, C, C, C, C], 5);
  console.log(`  5 carbons   -> detonated [${m5.fired.join(', ') || 'nothing'}]  score ${m5.score}`);
  ok(m5.score > m4.score, 'five carbons score no more than four');
  ok(m5.fired.length === 6,
     'an ultra match should fire all five matched bombs plus the Diamond it forged; got '
     + m5.fired.length);
  ok(m5.fired[5] === 'diamond', 'the forged Diamond is not the last thing to go off');

  console.log('\nthe four pays for what it swallowed');
  const oneBomb  = await play([C, null, null, null], 4);
  const twoBombs = await play([C, C, null, null], 4);
  console.log(`  1 bomb in a 4 -> ${oneBomb.score}   2 bombs in a 4 -> ${twoBombs.score}`);
  /* With one bomb in the match, that bomb FIRES and nothing is wasted, so
     the credit is zero. The second bomb is the first wasted one. */
  ok(twoBombs.score > oneBomb.score,
     'a second bomb in a 4-match adds nothing, so the waste credit is not '
   + 'being paid per extra bomb');
  ok(oneBomb.fired.length === 2,
     'a lone bomb in a 4-match did not fire its own detonation');

  console.log('\nand a plain match is untouched');
  const plain3 = await play([null, null, null], 3);
  ok(plain3.fired.length === 0 && plain3.score === 30,
     'a plain 3-match no longer scores 30 with nothing firing; got ' + plain3.score);
  /* One bomb in a 3-match still fires: the rule is about the match holding
     a bomb at all, not about all three being bombs. */
  const one3 = await play([C, null, null], 3);
  ok(one3.fired.length === 1,
     'a single bomb caught in a 3-match did not fire; got [' + one3.fired.join(', ') + ']');

  /* ---- separate matches off one slide ------------------------------ */
  console.log('\nseparate matches off one slide');
  /* Paint whole shapes on a board of unique icons so only what is painted
     can match, then resolve for real and read what the game forged. */
  async function slide(paint) {
    const g = makeGame();
    for (let y=0;y<8;y++) for (let x=0;x<8;x++) g.board[y][x] = { icon:'u'+(y*8+x), special:null, element:mkEl() };
    paint(g);
    const made = [], real = console.log;
    let groups = '?';
    console.log = (m) => { const s2 = String(m);
      const c = s2.match(/^Created (\w+)/); if (c) made.push(c[1]);
      const gm = s2.match(/Separate matches this slide: \d+ \[([^\]]*)\]/); if (gm) groups = gm[1]; };
    await g.resolveMatches(0, 0);
    console.log = real;
    return { made, groups, score: g.score };
  }
  const row = (g, y, n, icon, special) => { for (let i=0;i<n;i++)
      g.board[y][i] = special ? {icon:null, special, element:mkEl()} : {icon, special:null, element:mkEl()}; };

  const two3 = await slide(g => { row(g,0,3,'A'); row(g,4,3,'B'); });
  console.log(`  two 3s        matches=[${two3.groups}] forged=[${two3.made.join(', ')||'-'}] score=${two3.score}`);
  ok(two3.made.filter(m => m === 'diamond').length === 1,
     'two separate 3-matches must forge one Diamond; forged [' + two3.made.join(', ') + ']');

  const three3 = await slide(g => { row(g,0,3,'A'); row(g,3,3,'B'); row(g,6,3,'E'); });
  console.log(`  three 3s      matches=[${three3.groups}] forged=[${three3.made.join(', ')||'-'}] score=${three3.score}`);
  ok(three3.made.filter(m => m === 'diamond').length === 2,
     'three separate 3-matches must forge two Diamonds; forged [' + three3.made.join(', ') + ']');

  const four3 = await slide(g => { row(g,0,4,'A'); row(g,4,3,'B'); });
  console.log(`  a 4 and a 3   matches=[${four3.groups}] forged=[${four3.made.join(', ')||'-'}] score=${four3.score}`);
  ok(four3.made.filter(m => m === 'carbon').length === 1 && four3.made.filter(m => m === 'diamond').length === 1,
     'a 4 beside a 3 must forge the Carbon the four earned AND an escalation '
   + 'Diamond; forged [' + four3.made.join(', ') + ']');

  const five3 = await slide(g => { row(g,0,5,'A'); row(g,4,3,'B'); });
  console.log(`  a 5 and a 3   matches=[${five3.groups}] forged=[${five3.made.join(', ')||'-'}] score=${five3.score}`);
  ok(five3.made.filter(m => m === 'diamond').length === 2,
     'a 5 beside a 3 must forge the five\'s Diamond AND an escalation Diamond; '
   + 'forged [' + five3.made.join(', ') + ']');

  /* An L is ONE match of five, not two threes -- the runs share a corner. */
  const ell = await slide(g => { row(g,0,3,'A'); for (let i=0;i<3;i++) g.board[i][0] = {icon:'A',special:null,element:mkEl()}; });
  console.log(`  an L          matches=[${ell.groups}] forged=[${ell.made.join(', ')||'-'}] score=${ell.score}`);
  ok(ell.groups === '5', 'an L should read as one match of 5, not two of 3; got [' + ell.groups + ']');
  ok(ell.made.filter(m => m === 'diamond').length === 1,
     'an L forges one Diamond for being a five, and no escalation Diamond on top');

  console.log('\nmultiple matches are paid for, not just tolerated');
  const single3 = await slide(g => { row(g,0,3,'A'); });
  ok(two3.score > single3.score * 2,
     'two matches off one slide (' + two3.score + ') score no better than two '
   + 'separate single matches would -- the whole point is that the harder '
   + 'move is recognised');
  ok(three3.score > two3.score, 'a third match adds nothing');

  console.log(fails ? `\nFAILED: ${fails} check(s)` : '\nall bomb-match rule checks passed');
  process.exit(fails ? 1 : 0);
})();
