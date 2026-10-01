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

function makeGame() {
  const g = Object.create(Match3Game.prototype);
  g.width = 8; g.height = 8; g.score = 0; g.isGrandFinale = false; g.isDetonating = false;
  g.specialTypes = { bomb4: 'carbon', bomb5: 'diamond' };
  g.specialIcons = { carbon: 'CARBON', diamond: 'DIAMOND' };
  g.bonusScores = { carbonDetonation:50, diamondDetonation:100, carbonCleared:25,
                    diamondCleared:50, bombComboStep:150 };
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
  ok(m3.fired.length === 0, 'a 3-match detonated something; three bombs in a line must not go off');
  /* And no CREDIT either. Routing a 3-match into the bomb path fires
     nothing -- isUltra is false and a three forges no bomb -- so counting
     detonations alone passes a 3 that is quietly collecting the
     consumed-bomb credit. Three bombs in a line are worth exactly three
     tiles. */
  ok(m3.score === 30,
     'a 3-match of bombs scored ' + m3.score + ', not a plain 30 -- it is '
   + 'being credited for the bombs it consumed, which applies only to a four');

  const m3d = await play([D, D, D], 3);
  ok(m3d.fired.length === 0, 'three DIAMONDS in a 3-match detonated; size decides, not type');

  const m4 = await play([C, C, C, C], 4);
  console.log(`  4 carbons   -> detonated [${m4.fired.join(', ') || 'nothing'}]  score ${m4.score}`);
  ok(m4.fired.length === 1 && m4.fired[0] === 'carbon',
     'a 4-match should fire exactly the Carbon it forged, nothing else; got [' + m4.fired.join(', ') + ']');
  ok(m4.score > 40 + 50, 'a 4-match is not crediting the bombs it consumed');

  const m5 = await play([C, C, C, C, C], 5);
  console.log(`  5 carbons   -> detonated [${m5.fired.join(', ') || 'nothing'}]  score ${m5.score}`);
  ok(m5.fired.length === 6,
     'an ultra match should fire all five matched bombs plus the Diamond it forged; got '
     + m5.fired.length);
  ok(m5.fired[5] === 'diamond', 'the forged Diamond is not the last thing to go off');

  console.log('\nthe four pays for what it swallowed');
  const oneBomb  = await play([C, null, null, null], 4);
  const twoBombs = await play([C, C, null, null], 4);
  console.log(`  1 bomb in a 4 -> ${oneBomb.score}   2 bombs in a 4 -> ${twoBombs.score}`);
  ok(twoBombs.score > oneBomb.score,
     'two consumed bombs credit no more than one, so the credit is not per bomb');
  const diamondIn4 = await play([D, null, null, null], 4);
  ok(diamondIn4.score > oneBomb.score,
     'a consumed Diamond credits the same as a Carbon; it is worth more');

  console.log('\nand a plain match is untouched');
  const plain3 = await play([null, null, null], 3);
  ok(plain3.fired.length === 0 && plain3.score === 30,
     'a plain 3-match no longer scores 30 with nothing firing; got ' + plain3.score);

  console.log(fails ? `\nFAILED: ${fails} check(s)` : '\nall bomb-match rule checks passed');
  process.exit(fails ? 1 : 0);
})();
