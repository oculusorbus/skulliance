/*
 * skullswap-drag-harness.js — node only. Where a drag is allowed to end.
 *
 *     node skullswap-drag-harness.js
 *
 * THE BUG THIS PINS. Every drag listener used to be on #game-board. The
 * moment the pointer left the board, mousemove stopped firing -- so the
 * tile froze mid-slide -- and the mouseup landed on some other element,
 * so the handler never ran at all: isDragging stayed true and the swap
 * never committed. Pushing a tile to the edge and letting go, which is
 * the natural way to play a corner, simply did nothing.
 *
 * handleMouseMove has always clamped the target to the last row and
 * column. Binding the continuation to the window is what lets that clamp
 * do its job. So this harness checks two separate things, because fixing
 * only one of them puts the bug back:
 *   - WHERE the listeners are bound (window, not the board), and
 *   - that the handlers, driven with coordinates well outside the board,
 *     commit to the edge tile rather than bailing out.
 *
 * It drives the shipped handlers directly: the bindings are recorded as
 * the real addEventListeners() makes them, and each recorded function is
 * then called with a synthetic event. No DOM event propagation is
 * emulated, because the binding target IS the thing under test -- an
 * emulated bubble path would just re-assert whatever this file believes.
 */
const fs = require('fs');

/* Where each listener was bound, recorded as the game binds it. */
const bound = { window: {}, board: {} };
const BOARD_LEFT = 100, BOARD_TOP = 100;

const mkEl = () => ({ classList:{add(){},remove(){},toggle(){}}, style:{}, remove(){},
                      appendChild(){}, addEventListener(){} });
const boardEl = Object.assign(mkEl(), {
  addEventListener(type, fn) { bound.board[type] = fn; },
  getBoundingClientRect: () => ({ left: BOARD_LEFT, top: BOARD_TOP, width: 300, height: 500 }),
  innerHTML: '', textContent: '',
});

global.window = { innerWidth: 1200, innerHeight: 900, matchMedia: () => ({ matches: false }),
                  addEventListener(type, fn) { bound.window[type] = fn; } };
global.navigator = { maxTouchPoints: 0 };
global.document = {
  getElementById: (id) => (id === 'game-board' ? boardEl : Object.assign(mkEl(), {
      textContent:'', innerHTML:'', querySelector:()=>null, getBoundingClientRect:()=>({left:0,top:0,width:0,height:0}) })),
  querySelector: () => null, querySelectorAll: () => [], addEventListener(){},
  createElement: () => mkEl(),
};
global.getComputedStyle = () => ({ paddingTop:'0px', paddingBottom:'0px', paddingLeft:'0px', paddingRight:'0px' });
global.Audio = function(){ return { play(){ return Promise.resolve(); }, cloneNode(){ return this; },
                                    pause(){}, load(){}, volume:1, currentTime:0 }; };
global.requestAnimationFrame = f => f();
global.fetch = () => Promise.resolve({ json: () => Promise.resolve({}), text: () => Promise.resolve('') });
global.XMLHttpRequest = function(){ return { open(){}, send(){}, setRequestHeader(){} }; };
global.IS_LOGGED_IN = false;
global.localStorage = { getItem:()=>null, setItem(){}, removeItem(){} };

/* Pulled straight out of skullswap.php: the shipped class, not a copy
   that can drift. The one PHP interpolation inside it is neutralised. */
const page = fs.readFileSync('skullswap.php', 'utf8');
const cls  = page.slice(page.indexOf('class Match3Game {'), page.indexOf('const game = new Match3Game();'))
                 .replace(/<\?php[\s\S]*?\?>/g, 'null');
const Match3Game = eval(cls + '\n; Match3Game');

const W = 6, H = 10, PITCH = 50;
function makeGame() {
  for (const k of Object.keys(bound.window)) delete bound.window[k];
  for (const k of Object.keys(bound.board))  delete bound.board[k];
  const g = Object.create(Match3Game.prototype);
  g.width = W; g.height = H; g.tileSizeWithGap = PITCH;
  g.isTouchDevice = false; g.gameOver = false; g.isGrandFinale = false;
  g.isDragging = false; g.selectedTile = null; g.targetTile = null; g.dragDirection = null;
  g.offsetX = 0; g.offsetY = 0;
  g.board = [];
  for (let y=0;y<H;y++){ g.board.push([]); for (let x=0;x<W;x++) g.board[y].push({icon:'i'+((x+y)%3), special:null, element:mkEl()}); }
  g.slides = []; g.renders = 0; g.sounds = [];
  g.slideTiles = (a,b,c,d) => g.slides.push([a,b,c,d]);
  g.renderBoard = () => { g.renders++; };
  g.playSound = (s) => { g.sounds.push(s); };
  g.addEventListeners();
  return g;
}

/* A drag, start to finish, in page coordinates. */
function drag(g, fromX, fromY, toClientX, toClientY) {
  const at = (cx, cy) => ({ clientX: cx, clientY: cy, preventDefault(){} });
  bound.board.mousedown(at(BOARD_LEFT + fromX*PITCH + PITCH/2, BOARD_TOP + fromY*PITCH + PITCH/2));
  bound.window.mousemove(at(toClientX, toClientY));
  bound.window.mouseup(at(toClientX, toClientY));
}

let fails = 0;
function ok(cond, msg) { console.log((cond ? '  ok   ' : '  FAIL ') + msg); if (!cond) fails++; }
function eq(got, want, msg) {
  const good = JSON.stringify(got) === JSON.stringify(want);
  ok(good, msg + (good ? '' : '  — got ' + JSON.stringify(got) + ', want ' + JSON.stringify(want)));
}

console.log('where the listeners live');
let g = makeGame();
ok(typeof bound.board.mousedown === 'function', 'a drag still STARTS on the board');
ok(typeof bound.window.mousemove === 'function', 'it CONTINUES on the window, so leaving the board does not freeze it');
ok(typeof bound.window.mouseup === 'function',   'it ENDS on the window, so a release anywhere is heard');
ok(!bound.board.mousemove && !bound.board.mouseup, 'and nothing is left listening on the board, which is what used to swallow it');

/* Without these the rest cannot run at all, and a crash reads as a broken
   harness rather than a broken game. Say which it is. */
if (typeof bound.window.mousemove !== 'function' || typeof bound.window.mouseup !== 'function') {
  console.log('\n  the drag never reaches the window — the remaining checks cannot run');
  console.log('\n' + (fails || 1) + ' check(s) FAILED');
  process.exit(1);
}

console.log('\nreleasing past the edge of the board');
/* Each of these releases two or more tiles outside the board, which is
   exactly the motion that used to do nothing at all. */
g = makeGame(); drag(g, 4, 3, BOARD_LEFT + 8*PITCH, BOARD_TOP + 3*PITCH + 25);
eq(g.slides, [[4,3,5,3]], 'past the RIGHT edge commits to the last column');
g = makeGame(); drag(g, 2, 8, BOARD_LEFT + 2*PITCH + 25, BOARD_TOP + 13*PITCH);
eq(g.slides, [[2,8,2,9]], 'past the BOTTOM edge commits to the last row');
g = makeGame(); drag(g, 1, 5, BOARD_LEFT - 300, BOARD_TOP + 5*PITCH + 25);
eq(g.slides, [[1,5,0,5]], 'past the LEFT edge, at negative coordinates, commits to column 0');
g = makeGame(); drag(g, 3, 1, BOARD_LEFT + 3*PITCH + 25, BOARD_TOP - 400);
eq(g.slides, [[3,1,3,0]], 'past the TOP edge commits to row 0');

console.log('\nwhat must NOT change');
g = makeGame(); drag(g, 2, 2, BOARD_LEFT + 3*PITCH + 25, BOARD_TOP + 2*PITCH + 25);
eq(g.slides, [[2,2,3,2]], 'an ordinary one-cell drag is unaffected');
/* The end handlers now hear every release on the page. A click on HOW TO
   PLAY must not be mistaken for a released tile. */
g = makeGame(); bound.window.mouseup({ clientX: 5, clientY: 5, preventDefault(){} });
eq([g.slides, g.renders, g.sounds], [[], 0, []], 'a release with no drag in progress is ignored entirely');
/* A press with no movement is still a bad move, exactly as before. */
g = makeGame();
bound.board.mousedown({ clientX: BOARD_LEFT + PITCH + 25, clientY: BOARD_TOP + PITCH + 25, preventDefault(){} });
bound.window.mouseup({ clientX: BOARD_LEFT + PITCH + 25, clientY: BOARD_TOP + PITCH + 25, preventDefault(){} });
eq(g.sounds, ['badMove'], 'a press with no drag still reports a bad move');

console.log('\na drag the system takes away');
g = makeGame();
g.isDragging = true; g.selectedTile = { x:0, y:0 }; g.targetTile = { x:1, y:0 };
g.cancelDrag();
eq([g.isDragging, g.selectedTile, g.targetTile], [false, null, null], 'cancelDrag clears the drag rather than leaving it hung');
eq(g.sounds, [], 'and stays quiet: the player did not make a bad move, the system interrupted one');

console.log('');
if (fails) { console.log(fails + ' check(s) FAILED'); process.exit(1); }
console.log('all Skull Swap drag checks passed');
