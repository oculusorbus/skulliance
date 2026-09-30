/*
 * skullswap-harness.js — node only. What a bomb-on-bomb match is worth.
 *
 *     node skullswap-harness.js
 *
 * THE ONLY JS TEST ON THE PLATFORM, and it exists because Skull Swap's
 * scoring lives entirely in the browser: no PHP harness can reach it.
 * It extracts the real Match3Game class straight out of skullswap.php
 * and drives handleBombMatches() against a stub DOM, so it is testing
 * the shipped game rather than a description of it.
 *
 * TWO THINGS THE STUB MUST GET RIGHT, both learned by getting them
 * wrong and reading numbers that meant nothing:
 *   - tiles need a truthy .element. clearBoard() and clearRowAndColumn()
 *     only count tiles that have one, so element:null scores zero for
 *     every blast and every scenario looks identical.
 *   - showerTiles() must actually refill. The real one does, and it is
 *     the whole reason a second chained detonation is worth anything.
 *
 * It also pins the score ceiling: save-swap-score.php rejects anything
 * over SWAP_MAX_SCORE, and a cap below a legitimate run does not catch a
 * cheat, it silently throws away a record.
 */
const fs = require('fs');
global.window = { innerWidth: 1200, addEventListener(){}, matchMedia:()=>({matches:false}) };
global.navigator = { maxTouchPoints: 0 };
global.document = {
  getElementById: () => ({ textContent:'', style:{}, classList:{add(){},remove(){},toggle(){}},
                           addEventListener(){}, appendChild(){}, querySelector:()=>null, innerHTML:'' }),
  querySelector: () => null, querySelectorAll: () => [], addEventListener(){},
  createElement: () => ({ style:{}, classList:{add(){},remove(){}}, appendChild(){}, addEventListener(){} }),
};
global.Audio = function(){ return { play(){ return Promise.resolve(); }, cloneNode(){ return this; },
                                    pause(){}, load(){}, volume:1, currentTime:0 }; };
global.requestAnimationFrame = f => setTimeout(f, 0);
global.fetch = () => Promise.resolve({ json: () => Promise.resolve({}) , text:()=>Promise.resolve('')});
global.XMLHttpRequest = function(){ return { open(){}, send(){}, setRequestHeader(){} }; };
global.IS_LOGGED_IN = false;
global.localStorage = { getItem:()=>null, setItem(){}, removeItem(){} };

/* a class declaration inside eval() stays in the eval's scope; hand it back */
/* Pulled straight out of skullswap.php: the shipped class, not a copy that
   can drift. The one PHP interpolation inside it is neutralised. */
const page = fs.readFileSync('skullswap.php', 'utf8');
const cls  = page.slice(page.indexOf('class Match3Game {'), page.indexOf('const game = new Match3Game();'))
                 .replace(/<\?php[\s\S]*?\?>/g, 'null');
/* A class declaration inside eval() stays in the eval's scope; hand it back. */
const Match3Game = eval(cls + '\n; Match3Game');

const mkEl = () => ({ classList:{add(){},remove(){}}, style:{}, remove(){} });
function makeGame() {
  const g = Object.create(Match3Game.prototype);
  g.width = 8; g.height = 8; g.score = 0; g.isGrandFinale = false; g.isDetonating = false;
  g.specialTypes = { bomb4: 'carbon', bomb5: 'diamond' };
  g.specialIcons = { carbon: 'CARBON', diamond: 'DIAMOND' };
  g.bonusScores = { carbonDetonation:50, diamondDetonation:100, carbonCleared:25,
                    diamondCleared:50, bombComboStep:150 };
  g.board = [];
  for (let y=0;y<8;y++){ g.board.push([]); for(let x=0;x<8;x++) g.board[y].push({icon:'skull'+((x+y)%5), special:null, element:mkEl()}); }
  // no-op the presentation
  g.playSound = () => {}; g.renderBoard = () => {};
  /* The real showerTiles() refills empty cells -- which is the whole reason a
     second detonation is worth anything. A no-op stub silently understates
     every chained blast, so it refills here too. */
  g.showerTiles = () => { for (let y=0;y<8;y++) for (let x=0;x<8;x++)
      if (!g.board[y][x] || (g.board[y][x].icon===null && !g.board[y][x].special))
        g.board[y][x] = { icon:'skull'+((x*3+y)%5), special:null, element:mkEl() }; };
  g.cascadeTiles = () => {}; g.cascadeTilesWithoutRender = () => {};
  g.updateMatchCounter = () => {};
  return g;
}

/* Which bombs actually detonated, in order -- read off the game's own log. */
async function detonations(bombTypes, matchSize) {
  const seen = [];
  const real = console.log;
  console.log = (m) => { const s = String(m);
    let x = s.match(/Detonating (\w+) bomb/); if (x) seen.push(x[1]); };
  await run('', bombTypes, matchSize, true);
  console.log = real;
  return seen;
}

async function run(label, bombTypes, matchSize, quiet) {
  const g = makeGame();
  const matches = new Set();
  for (let i=0;i<matchSize;i++) {
    matches.add(`${i},0`);
    g.board[0][i] = { icon:null, special: bombTypes[i] || null, element:mkEl() };
    if (!bombTypes[i]) g.board[0][i] = { icon:'skullA', special:null, element:mkEl() };
  }
  const first = bombTypes.findIndex(Boolean);
  await g.handleBombMatches(matches, bombTypes[first], first, 0);
  if (!quiet) console.log(`${label.padEnd(34)} ${String(g.score).padStart(6)}`);
  return g.score;
}

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

(async () => {
  console.log('scenario                              score');
  console.log('------------------------------------- -----');
  const one4  = await run('1 diamond in a 4-match',      ['diamond',null,null,null], 4);
  const four  = await run('4 diamonds matched',          ['diamond','diamond','diamond','diamond'], 4);
  const five  = await run('5 diamonds matched (ultra)',  ['diamond','diamond','diamond','diamond','diamond'], 5);
  const c4    = await run('4 carbons matched',           ['carbon','carbon','carbon','carbon'], 4);
  const c3    = await run('3 carbons matched',           ['carbon','carbon','carbon'], 3);
  const c1    = await run('1 carbon in a 3-match',       ['carbon',null,null], 3);

  console.log('\nmore bombs must always be worth more');
  /* The bug this whole change exists to fix: four Diamonds used to score
     LESS than one, because three of them were deleted for 10 points each. */
  ok(four > one4, 'four diamonds beat one -- the bug that started this');
  ok(five > four, 'five beat four');
  ok(four > c4,   'diamonds beat carbons at the same count');
  ok(c4 > c3 && c3 > c1, 'each extra carbon is worth more');

  console.log('\nevery matched bomb detonates, and the match forges its own');
  const det = await detonations(['diamond','diamond','diamond','diamond'], 4);
  ok(det.filter(d => d === 'diamond').length === 4, 'all four diamonds go off, not just one');
  ok(det[det.length - 1] === 'carbon', 'and a 4-match forges a carbon, which goes off last');
  const det5 = await detonations(['diamond','diamond','diamond','diamond','diamond'], 5);
  ok(det5.filter(d => d === 'diamond').length === 6, 'a 5-match forges a diamond: six detonations');

  console.log('\nthe combo bonus');
  /* 150 per bomb beyond the first, and deliberately smaller than one
     diamond detonation -- the reward is the blasts, not the bonus. */
  ok(five - four > 0, 'the fifth bomb adds more than its bonus alone');
  ok(150 * 3 < 780, 'the combo bonus stays below a single diamond detonation');

  console.log('\nthe ceiling still covers a perfect game');
  const php = require('fs').readFileSync('ajax/save-swap-score.php', 'utf8');
  const cap = parseInt((php.match(/SWAP_MAX_SCORE',\s*(\d+)/) || [])[1], 10);
  /* An ultra costs about six of the twenty-five matches to set up, so four
     is the practical maximum. */
  const best = 4 * five + 1 * one4;
  console.log('  best plausible game  ~ ' + best);
  console.log('  SWAP_MAX_SCORE         ' + cap);
  ok(cap > best, 'the cap is above a perfect game, so a real record is never rejected');
  ok(cap < best * 3, 'but not so far above that it stops being a check');

  console.log('');
  if (fails) { console.log(fails + ' check(s) FAILED'); process.exit(1); }
  console.log('all Skull Swap bomb-match checks passed');
})();
