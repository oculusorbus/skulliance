/* skullswap-cascade-harness.js -- node, no browser.
 *
 * "I cleared a bunch of diamond bombs and the cascade left a match of 3
 * just sitting on the board." Reported from a real run, and reproducible
 * on demand once you know what to hold still.
 *
 * WHAT WENT WRONG. cascadeTiles() re-checks the board on a timer, and the
 * re-check used to be gated on `moved || matchCheckCount < 2`.
 * matchCheckCount was incremented by EVERY cascade and only ever reset by
 * a check that found nothing -- so a long bomb chain, which runs a cascade
 * per blast, pushed it to 2 or more. The first cascade after that which
 * moved nothing then took the else branch: reset the counter, schedule
 * nothing, and abandon whatever was on the board. A match that arrived in
 * the same cascade that settled the board was never looked at again.
 *
 * The counter was always meant to stop an EMPTY loop spinning, not to cap
 * how much real work one slide may do. It now counts consecutive cascades
 * that achieved nothing.
 *
 * TWO THINGS HAVE TO HOLD AT ONCE: a settled board holding a match must
 * always resolve it, and a settled board holding nothing must stop.
 *
 * A NOTE ON WHAT THESE TESTS CANNOT SEE, so the next person does not read
 * more into a green run than is there. matchCheckCount is a GRACE
 * ALLOWANCE ("nothing moved, look twice more anyway"), not a loop brake --
 * nothing restarts the cascade chain except a handler clearing a match, so
 * a check that finds nothing ends it regardless. That means two plausible
 * mutations are invisible here and were checked by hand: removing the
 * counter's reset, and removing the counter from the condition entirely.
 * Both leave behaviour unchanged given the `pending` check in front of
 * them. The idle-board test below is still worth keeping -- it is what
 * would catch somebody making the scheduled callback re-enter
 * cascadeTiles(), which WOULD spin.
 *
 * Usage: node skullswap-cascade-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(c, w) { if (!c) { fails++; console.log('  FAIL  ' + w); } }

Object.defineProperty(global, 'navigator', { value: { maxTouchPoints: 0 }, configurable: true });
const mkEl = () => ({ classList:{add(){},remove(){},toggle(){}}, style:{}, dataset:{},
                      className:'', appendChild(){}, remove(){}, querySelector:()=>null });
const boardEl = { innerHTML:'', appendChild(){}, style:{}, classList:{add(){},remove(){},toggle(){}} };
global.document = { getElementById: id => id === 'game-board' ? boardEl
    : { textContent:'', style:{}, classList:{add(){},remove(){},toggle(){}},
        addEventListener(){}, appendChild(){}, querySelector:()=>null, innerHTML:'' },
  querySelector:()=>null, querySelectorAll:()=>[], addEventListener(){}, createElement:()=>mkEl() };
global.Audio = function(){ return { play(){return Promise.resolve();}, cloneNode(){return this;},
                                    pause(){}, load(){}, volume:1, currentTime:0 }; };
global.requestAnimationFrame = f => setTimeout(f, 0);
global.fetch = () => Promise.resolve({ json:()=>Promise.resolve({}), text:()=>Promise.resolve('') });
global.XMLHttpRequest = function(){ return { open(){}, send(){}, setRequestHeader(){} }; };
global.IS_LOGGED_IN = false;
global.localStorage = { getItem:()=>null, setItem(){}, removeItem(){} };

const page = fs.readFileSync('skullswap.php', 'utf8');
const cls  = page.slice(page.indexOf('class Match3Game {'), page.indexOf('const game = new Match3Game();'))
                 .replace(/<\?php[\s\S]*?\?>/g, 'null');
const Match3Game = eval(cls + '\n; Match3Game');
function REAL_BONUSES() {
  const at = page.indexOf('this.bonusScores = {'); const o = page.indexOf('{', at);
  let d = 0, e = o;
  for (let i = o; i < page.length; i++) { if (page[i]==='{') d++; else if (page[i]==='}') { d--; if (!d) { e=i; break; } } }
  return eval('(' + page.slice(o, e + 1) + ')');
}

/* A FULL board on a repeating 4-colour diagonal: no three of anything line
   up, and because nothing is empty, cascadeTilesWithoutRender() reports
   moved=false. That is the state the bug needs -- settled. */
function settled(withMatch) {
  const g = Object.create(Match3Game.prototype);
  Object.assign(g, { width:8, height:8, score:0, isGrandFinale:false, isDetonating:false,
    gameOver:false, isDragging:false, selectedTile:null, tileSizeWithGap:50, matchCount:0,
    specialTypes:{bomb4:'carbon',bomb5:'diamond'}, specialIcons:{carbon:'CARBON',diamond:'DIAMOND'},
    iconColorMap:{}, icons:['s0','s1','s2','s3'], bonusScores:REAL_BONUSES() });
  g.board = [];
  for (let y=0;y<8;y++){ g.board.push([]); for (let x=0;x<8;x++) g.board[y].push({icon:'s'+((x+2*y)%4),special:null,element:mkEl()}); }
  if (withMatch) for (let x=0;x<3;x++) g.board[5][x] = { icon:'STUCK', special:null, element:mkEl() };
  g.playSound = () => {}; g.updateMatchCounter = () => {};
  return g;
}
const quiet = async fn => { const real = console.log; console.log = () => {};
                            try { return await fn(); } finally { console.log = real; } };
const settle = ms => new Promise(r => setTimeout(r, ms));

(async () => {
  console.log('a settled board still holding a match always resolves it');
  /* The counter is the whole bug: at 0 and 1 it worked, at 2 and above the
     board was abandoned. Every value has to behave the same. */
  for (const count of [0, 1, 2, 3, 7]) {
    const g = settled(true);
    g.matchCheckCount = count;
    ok(await quiet(() => g.checkMatches().hasMatches), 'the fixture has no match in it to begin with');
    await quiet(async () => { g.cascadeTiles(); await settle(900); });
    /* ASKED ABOUT THE PLANTED MATCH, not about "is there any match" --
       refills are random, so a fresh three can appear while the loop is
       still legitimately working and a bare hasMatches() check is flaky
       in both directions. The STUCK icon exists nowhere else on the
       board, so its absence is unambiguous. */
    const left = g.board.flat().filter(t => t.icon === 'STUCK').length;
    console.log(`  matchCheckCount ${count} -> ${left ? 'STILL THERE (' + left + ' tiles)' : 'resolved'}`);
    ok(left === 0, `with matchCheckCount at ${count} a match on a settled board was never resolved -- `
            + 'it just sits there, which is the reported bug');
  }

  console.log('\nand a settled board with nothing on it stops looking');
  /* The other half. The cap exists so an idle board does not reschedule
     itself forever; removing it to fix the above would burn a timer every
     100ms for the rest of the session. */
  for (const count of [0, 3]) {
    const g = settled(false);
    g.matchCheckCount = count;
    let checks = 0;
    const realResolve = g.resolveMatches.bind(g);
    g.resolveMatches = function (...a) { checks++; return realResolve(...a); };
    await quiet(async () => { g.cascadeTiles(); await settle(1200); });
    console.log(`  matchCheckCount ${count} -> ${checks} re-check(s), then stopped`);
    ok(checks <= 3, `an idle board re-checked ${checks} times; the loop guard is gone and it is spinning`);
  }

  console.log('\nand a board that CAN still fall is never abandoned');
  {
    const g = settled(false);
    g.board[7][2] = { icon:null, special:null, element:null };   // a hole: tiles must fall
    g.matchCheckCount = 5;                                        // far past the old cap
    let checks = 0;
    const realResolve = g.resolveMatches.bind(g);
    g.resolveMatches = function (...a) { checks++; return realResolve(...a); };
    await quiet(async () => { g.cascadeTiles(); await settle(900); });
    const holes = g.board.flat().filter(t => !t.icon && !t.special).length;
    console.log(`  ${checks} re-check(s), ${holes} hole(s) left`);
    ok(checks >= 1, 'a board with a hole in it scheduled no re-check at all');
    ok(holes === 0, 'the hole was never filled');
  }

  console.log('\n' + (fails ? `FAILED (${fails})\n` : 'skullswap cascade: ok\n'));
  process.exit(fails ? 1 : 0);
})();
