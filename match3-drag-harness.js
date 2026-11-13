/*
 * match3-drag-harness.js — node only. Where a drag is allowed to end.
 *
 *     node match3-drag-harness.js
 *
 * THE BUG THIS PINS. Every drag listener used to be on #game-board. The
 * moment the pointer left the board, mousemove stopped firing -- so the
 * tile froze mid-slide -- and the mouseup landed on some other element,
 * so the handler never ran at all: isDragging stayed true and the swap
 * never committed. Pushing a tile to the edge and letting go, which is
 * the natural way to play a corner, simply did nothing, and the player
 * had to drag back over the board to un-wedge it.
 *
 * handleMouseMove has always clamped the target to the last row and
 * column. Binding the continuation to the window is what lets that clamp
 * do its job. So this checks two separate things, because fixing only
 * one of them puts the bug back:
 *   - WHERE the listeners are bound (window, not the board), and
 *   - that the handlers, driven with coordinates well outside the board,
 *     commit to the edge tile rather than bailing out.
 *
 * TWO PAGES, ONE ENGINE, AND THE SET IS CHECKED. skullswap.php and
 * monstrocity.php carry the same slide engine under the same method
 * names; dhcarena.php has a third implementation that drives the board
 * with Pointer Events and setPointerCapture, which is immune to this by
 * construction -- capture keeps delivering pointermove and pointerup to
 * the grid after the pointer has left it. A harness that named only the
 * page it was written for would have passed while the sibling stayed
 * broken, which is exactly how the match-3 intro shipped covering two of
 * three engines. So the engine is detected by signature across every
 * page and the SET is asserted: a third page growing this engine, or one
 * of these two losing it, fails here rather than silently going untested.
 *
 * It drives the shipped handlers directly: bindings are recorded as the
 * real addEventListeners() makes them, then each recorded function is
 * called with a synthetic event. No DOM event propagation is emulated,
 * because the binding target IS the thing under test -- an emulated
 * bubble path would only re-assert whatever this file believes.
 */
const fs = require('fs');

/* ---------------------------------------------------------------- pages */

const PAGES = [
  { file: 'skullswap.php',   cls: 'Match3Game',
    endsBefore: 'const game = new Match3Game();',
    /* Skull Swap refuses a move during the end-of-run finale. */
    ready: g => { g.isGrandFinale = false; } },
  { file: 'monstrocity.php', cls: 'MonstrocityMatch3',
    endsBefore: '\n    function randomChoice(arr) {',
    /* Monstrocity refuses a move unless the game is in the player's turn
       AND it is player 1 holding it -- handleMouseDown checks both, so a
       stub that sets only gameState bails before the drag ever starts and
       every check below reads as a failure of the fix rather than of the
       stub. Learned by watching exactly that happen. */
    ready: g => { g.gameState = 'playerTurn'; g.player1 = { name: 'p1' }; g.currentTurn = g.player1; } },
];

/* The engine, by signature rather than by filename. Deliberately three
   boring markers that all three co-exist only in this implementation --
   a clever detector that misses a page is worse than no detector. */
const SIGNATURE = ['handleMouseMove(e)', 'dragDirection', 'tileSizeWithGap'];
function pagesWithEngine() {
  return fs.readdirSync('.')
    .filter(f => f.endsWith('.php') && !f.includes('harness') && !f.endsWith('-test.php'))
    .filter(f => { const t = fs.readFileSync(f, 'utf8'); return SIGNATURE.every(m => t.includes(m)); })
    .sort();
}

/* --------------------------------------------------------------- stubs */

const bound = { window: {}, board: {} };
const BOARD_LEFT = 100, BOARD_TOP = 100;
const W = 6, H = 10, PITCH = 50;

const mkEl = () => ({ classList:{add(){},remove(){},toggle(){},contains:()=>false}, style:{}, dataset:{},
                      remove(){}, appendChild(){}, addEventListener(){}, querySelector:()=>null,
                      getBoundingClientRect: () => ({ left:0, top:0, width:0, height:0 }),
                      textContent:'', innerHTML:'' });
const boardEl = Object.assign(mkEl(), {
  addEventListener(type, fn) { bound.board[type] = fn; },
  getBoundingClientRect: () => ({ left: BOARD_LEFT, top: BOARD_TOP, width: W*PITCH, height: H*PITCH }),
});

global.window = { innerWidth: 1200, innerHeight: 900, matchMedia: () => ({ matches: false }),
                  addEventListener(type, fn) { bound.window[type] = fn; } };
global.navigator = { maxTouchPoints: 0 };
global.document = {
  getElementById: id => (id === 'game-board' ? boardEl : mkEl()),
  querySelector: () => null, querySelectorAll: () => [], addEventListener(){}, createElement: () => mkEl(),
};
global.getComputedStyle = () => ({ paddingTop:'0px', paddingBottom:'0px', paddingLeft:'0px', paddingRight:'0px' });
global.Audio = function(){ return { play(){ return Promise.resolve(); }, cloneNode(){ return this; },
                                    pause(){}, load(){}, volume:1, currentTime:0 }; };
global.Image = function(){ return { addEventListener(){}, style:{} }; };
global.requestAnimationFrame = f => f();
global.fetch = () => Promise.resolve({ json: () => Promise.resolve({}), text: () => Promise.resolve('') });
global.XMLHttpRequest = function(){ return { open(){}, send(){}, setRequestHeader(){} }; };
global.IS_LOGGED_IN = false;
global.localStorage = { getItem:()=>null, setItem(){}, removeItem(){} };
global.opponentsConfig = [];
global.p1Image = mkEl(); global.p2Image = mkEl();

/* ------------------------------------------------------------ reporting */

let fails = 0;
function ok(cond, msg) { console.log((cond ? '  ok   ' : '  FAIL ') + msg); if (!cond) fails++; }
function eq(got, want, msg) {
  const good = JSON.stringify(got) === JSON.stringify(want);
  ok(good, msg + (good ? '' : '  — got ' + JSON.stringify(got) + ', want ' + JSON.stringify(want)));
}

/* ------------------------------------------------------------ the class */

/* Pulled straight out of the page: the shipped class, not a copy that can
   drift. PHP interpolations inside it are neutralised, as the other JS
   harnesses do. The method check below is what makes a bad slice loud --
   an extraction that quietly grabbed the wrong span would otherwise show
   up as a mysterious runtime error pages later. */
function loadClass(page) {
  const src = fs.readFileSync(page.file, 'utf8');
  const start = src.indexOf('class ' + page.cls + ' {');
  const stop  = src.indexOf(page.endsBefore, start);
  if (start < 0 || stop < 0) throw new Error(page.file + ': cannot locate class ' + page.cls);
  const body = src.slice(start, stop).replace(/<\?php[\s\S]*?\?>/g, 'null');
  const Cls = eval(body + '\n; ' + page.cls);
  for (const m of ['addEventListeners','handleMouseDown','handleMouseMove','handleMouseUp','cancelDrag','getTileFromEvent'])
    if (typeof Cls.prototype[m] !== 'function') throw new Error(page.file + ': extracted class has no ' + m + '()');
  return Cls;
}

function makeGame(page, Cls) {
  for (const k of Object.keys(bound.window)) delete bound.window[k];
  for (const k of Object.keys(bound.board))  delete bound.board[k];
  const g = Object.create(Cls.prototype);
  g.width = W; g.height = H; g.tileSizeWithGap = PITCH;
  g.isTouchDevice = false; g.gameOver = false;
  g.isDragging = false; g.selectedTile = null; g.targetTile = null; g.dragDirection = null;
  g.offsetX = 0; g.offsetY = 0;
  g.board = [];
  for (let y=0;y<H;y++){ g.board.push([]); for (let x=0;x<W;x++) g.board[y].push({icon:'i'+((x+y)%3), special:null, element:mkEl()}); }
  g.slides = []; g.renders = 0; g.sounds = [];
  g.slideTiles = (a,b,c,d) => g.slides.push([a,b,c,d]);
  g.renderBoard = () => { g.renders++; };
  g.playSound = s => { g.sounds.push(s); };
  page.ready(g);
  g.addEventListeners();
  return g;
}

const at = (cx, cy) => ({ clientX: cx, clientY: cy, preventDefault(){} });
function drag(g, fromX, fromY, toClientX, toClientY) {
  bound.board.mousedown(at(BOARD_LEFT + fromX*PITCH + PITCH/2, BOARD_TOP + fromY*PITCH + PITCH/2));
  bound.window.mousemove(at(toClientX, toClientY));
  bound.window.mouseup(at(toClientX, toClientY));
}

/* ------------------------------------------------------------- the run */

console.log('which pages carry this slide engine');
const found = pagesWithEngine();
const want  = PAGES.map(p => p.file).sort();
eq(found, want, 'exactly the pages this harness drives, and no others');
if (JSON.stringify(found) !== JSON.stringify(want)) {
  console.log('\n  a page grew or lost this engine — add it to PAGES, or it goes untested');
}

for (const page of PAGES) {
  const Cls = loadClass(page);
  console.log('\n' + page.file + ' — where the listeners live');
  let g = makeGame(page, Cls);
  ok(typeof bound.board.mousedown === 'function', 'a drag still STARTS on the board');
  ok(typeof bound.window.mousemove === 'function', 'it CONTINUES on the window, so leaving the board does not freeze it');
  ok(typeof bound.window.mouseup === 'function',   'it ENDS on the window, so a release anywhere is heard');
  ok(!bound.board.mousemove && !bound.board.mouseup, 'and nothing is left listening on the board, which is what used to swallow it');

  if (typeof bound.window.mousemove !== 'function' || typeof bound.window.mouseup !== 'function') {
    console.log('  the drag never reaches the window — the remaining checks cannot run');
    fails++;
    continue;
  }

  console.log(page.file + ' — releasing past the edge of the board');
  /* Each of these releases two or more tiles outside the board, which is
     exactly the motion that used to do nothing at all. */
  g = makeGame(page, Cls); drag(g, 4, 3, BOARD_LEFT + 8*PITCH, BOARD_TOP + 3*PITCH + 25);
  eq(g.slides, [[4,3,5,3]], 'past the RIGHT edge commits to the last column');
  g = makeGame(page, Cls); drag(g, 2, 8, BOARD_LEFT + 2*PITCH + 25, BOARD_TOP + 13*PITCH);
  eq(g.slides, [[2,8,2,9]], 'past the BOTTOM edge commits to the last row');
  g = makeGame(page, Cls); drag(g, 1, 5, BOARD_LEFT - 300, BOARD_TOP + 5*PITCH + 25);
  eq(g.slides, [[1,5,0,5]], 'past the LEFT edge, at negative coordinates, commits to column 0');
  g = makeGame(page, Cls); drag(g, 3, 1, BOARD_LEFT + 3*PITCH + 25, BOARD_TOP - 400);
  eq(g.slides, [[3,1,3,0]], 'past the TOP edge commits to row 0');

  console.log(page.file + ' — what must NOT change');
  g = makeGame(page, Cls); drag(g, 2, 2, BOARD_LEFT + 3*PITCH + 25, BOARD_TOP + 2*PITCH + 25);
  eq(g.slides, [[2,2,3,2]], 'an ordinary one-cell drag is unaffected');
  /* The end handlers now hear every release on the page. A click on a
     button must not be mistaken for a released tile. */
  g = makeGame(page, Cls); bound.window.mouseup(at(5, 5));
  eq([g.slides, g.renders, g.sounds], [[], 0, []], 'a release with no drag in progress is ignored entirely');
  /* A press with no movement still ends the same way it always did. */
  g = makeGame(page, Cls);
  bound.board.mousedown(at(BOARD_LEFT + PITCH + 25, BOARD_TOP + PITCH + 25));
  bound.window.mouseup(at(BOARD_LEFT + PITCH + 25, BOARD_TOP + PITCH + 25));
  eq([g.slides, g.renders], [[], 1], 'a press with no drag makes no move and puts the board back');

  console.log(page.file + ' — a drag the system takes away');
  g = makeGame(page, Cls);
  g.isDragging = true; g.selectedTile = { x:0, y:0 }; g.targetTile = { x:1, y:0 };
  g.cancelDrag();
  eq([g.isDragging, g.selectedTile, g.targetTile], [false, null, null], 'cancelDrag clears the drag rather than leaving it hung');
  eq(g.slides, [], 'and makes no move: the system interrupted the drag, the player did not finish it');
}

console.log('');
if (fails) { console.log(fails + ' check(s) FAILED'); process.exit(1); }
console.log('all match-3 drag checks passed');
