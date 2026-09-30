/*
 * skullswap-endgame-harness.js — node only. The last move, and the score
 * that actually gets saved.
 *
 *     node skullswap-endgame-harness.js
 *
 * TWO BUGS, ONE CAUSE, both reported from a real round: a Carbon or
 * Diamond earned on move 25 never appeared, and the score on the
 * game-over panel did not match the one on the leaderboard.
 *
 * The swap handler called resolveMatches() without awaiting it, then
 * immediately counted the match and called endGame() -- which sets
 * isGrandFinale, and both match handlers refuse to forge a bomb while
 * that flag is up. endGame() also finishes with
 * saveSwapScore(this.score) while handlers are still adding to it; its
 * own `await this.handleMatches(...)` was awaiting undefined, because
 * handleMatches was not async.
 *
 * This drives the real class both ways round and shows the difference.
 */
const fs = require('fs');
global.window={innerWidth:1200,addEventListener(){},matchMedia:()=>({matches:false})};
global.navigator={maxTouchPoints:0};
const mkEl=()=>({classList:{add(){},remove(){}},style:{},remove(){}});
let shownScore=0;
global.document={ getElementById:(id)=>({ get textContent(){return '';},
    set textContent(v){ if(id==='score'){ const m=String(v).match(/(\d+)/); if(m) shownScore=+m[1]; } },
    style:{}, classList:{add(){},remove(){},toggle(){}}, addEventListener(){}, appendChild(){},
    querySelector:()=>null, querySelectorAll:()=>[], innerHTML:'' }),
  querySelector:()=>null, querySelectorAll:()=>[], addEventListener(){}, createElement:()=>mkEl() };
global.Audio=function(){return{play(){return Promise.resolve();},cloneNode(){return this;},pause(){},load(){},volume:1,currentTime:0};};
global.requestAnimationFrame=f=>setTimeout(f,0);
global.IS_LOGGED_IN=true;

const page=fs.readFileSync('/Users/jeremiahbarber/Documents/GitHub/skulliance/skullswap.php','utf8');
const cls=page.slice(page.indexOf('class Match3Game {'),page.indexOf('const game = new Match3Game();'))
              .replace(/<\?php[\s\S]*?\?>/g,'null');
const Match3Game=eval(cls+'\n; Match3Game');

function mk(){
  const g=Object.create(Match3Game.prototype);
  g.width=8;g.height=8;g.score=0;g.isGrandFinale=false;g.isDetonating=false;g.gameOver=false;
  g.matchCount=24;g.matchLimit=25;g.matchCheckCount=0;
  g.specialTypes={bomb4:'carbon',bomb5:'diamond'};
  g.specialIcons={carbon:'CARBON',diamond:'DIAMOND'};
  g.bonusScores={carbonDetonation:50,diamondDetonation:100,carbonCleared:25,diamondCleared:50,bombComboStep:150};
  g.icons=['a','b','c','d','e'];
  g.board=[];
  for(let y=0;y<8;y++){g.board.push([]);for(let x=0;x<8;x++)g.board[y].push({icon:'skull'+((x+y)%5),special:null,element:mkEl()});}
  g.playSound=()=>{};
  /* the real renderBoard() rebuilds every tile's DOM node -- code after it
     dereferences .element, so a no-op stub throws where the game does not */
  g.renderBoard=()=>{ for(let y=0;y<8;y++) for(let x=0;x<8;x++)
      if(g.board[y][x] && !g.board[y][x].element) g.board[y][x].element=mkEl(); };
  g.showerTiles=()=>{for(let x=0;x<8;x++)for(let y=0;y<8;y++){if(!g.board[y][x].icon&&!g.board[y][x].special){g.board[y][x]=g.createRandomTile();g.board[y][x].element=mkEl();break;}}};
  g.cascadeTiles=()=>{};g.cascadeTilesWithoutRender=()=>false;g.updateShareLink=()=>{};
  g.getAllBombPositions=function(){const o=[];for(let y=0;y<8;y++)for(let x=0;x<8;x++)if(this.board[y][x].special)o.push({x,y,type:this.board[y][x].special});return o;};
  g.savedScore=null; g.saveSwapScore=function(sc){ this.savedScore=sc; };
  return g;
}

async function play(awaitIt) {
  shownScore = 0;
  const g = mk();
  for (let i=0;i<4;i++) g.board[7][i] = {icon:'sameone', special:null, element:mkEl()};
  const forged = []; const real = console.log;
  console.log = (m) => { const s=String(m); const c=s.match(/Created (\w+) bomb/); if(c) forged.push(c[1]); };

  /* The two orderings. `awaitIt` is what the swap handler does now; the
     other branch is exactly what it did before -- fire the resolution and
     immediately count the match and end the game. */
  if (awaitIt) {
    await g.resolveMatches(0, 7);
    g.matchCount++;
    if (g.matchCount >= g.matchLimit) await g.endGame();
  } else {
    const pending = g.resolveMatches(0, 7);
    g.matchCount++;
    if (g.matchCount >= g.matchLimit) await g.endGame();
    await pending;
  }
  console.log = real;
  return { forged: forged[0] || 'NONE', shown: shownScore, saved: g.savedScore };
}

let fails = 0;
function ok(c, w) { if (!c) { fails++; console.log('  FAIL  ' + w); } }

(async () => {
  console.log('a 4-match landed on move 25\n');
  const before = await play(false);
  console.log('  NOT awaited (the old swap handler)');
  console.log('    bomb the match earned :', before.forged);
  console.log('    score on screen       :', before.shown);
  console.log('    score saved           :', before.saved);

  const after = await play(true);
  console.log('\n  awaited (now)');
  console.log('    bomb the match earned :', after.forged);
  console.log('    score on screen       :', after.shown);
  console.log('    score saved           :', after.saved);

  console.log('\nchecks');
  ok(after.forged !== 'NONE', 'the final move forges its bomb');

  /*
   * THE PROPERTY endGame() DEPENDS ON. It contains
   *     await this.handleMatches(...)
   * inside its settling loop. While handleMatches was a plain function
   * that returned undefined, that await resolved instantly -- the loop
   * ran on while 300ms handlers were still pending, and
   * saveSwapScore(this.score) then read a score they had not finished
   * writing. That is why the number on screen kept climbing past the
   * one on the leaderboard. Assert it is genuinely awaitable.
   */
  const g2 = mk();
  const ret = g2.handleMatches(new Set(['0,0','1,0','2,0']), null, 0, 0);
  ok(ret instanceof Promise, 'handleMatches returns a Promise, so endGame can really wait for it');
  await ret;
  const g3 = mk();
  ok(g3.resolveMatches(0,0) instanceof Promise, 'and so does resolveMatches');
  ok(after.saved === after.shown, 'the saved score is the score on screen');
  ok(after.saved > 0, 'and it is not zero');
  /* If this stops failing, the race is no longer reproducible and these
     checks have stopped meaning anything -- say so rather than going green. */
  const raceStillBroken = (before.forged === 'NONE') || (before.saved !== before.shown);
  if (!raceStillBroken) console.log('  NOTE  the un-awaited ordering no longer misbehaves here;');
  if (!raceStillBroken) console.log('        this test can no longer prove the fix matters.');

  console.log('');
  if (fails) { console.log(fails + ' check(s) FAILED'); process.exit(1); }
  console.log('all end-of-game checks passed');
})();
