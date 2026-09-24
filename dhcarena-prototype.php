<?php
/**
 * dhcarena-prototype.php — THROWAWAY feel prototype for DHC Arena.
 *
 * REBUILT as a puzzle-battler after the first prototype played as a menu.
 * Playtest verdict on v1, verbatim: "found myself getting bored and
 * unengaged... selecting the best option out of 4 each time and hoping for the
 * best roll." Correct. v1 had no execution layer — all the skill lived in the
 * build, and nothing you did during a battle was done WELL or badly.
 *
 * WHAT MAKES THIS NOT MONSTROCITY. Monstrocity is already a shared-board,
 * alternating-turn match-3, so that shape cannot be the difference. Two rules
 * make Arena its own game, and both come straight out of dhcarena.md:
 *
 *   1. EACH OF YOUR THREE FIGHTERS OWNS A GEM COLOUR. Matching that colour is
 *      how that Fighter acts. The board therefore means something different
 *      depending on who is in your Stable — your team composition literally
 *      rewrites what a good move is. Monstrocity's five tile types mean the
 *      same thing in every battle.
 *   2. MATCH SIZE IS REACH. Three hits their front rank, four reaches their
 *      mid, five or more reaches their back. §3bb's formation becomes the
 *      thing the puzzle is about: their healer is hiding at the back and a
 *      3-match cannot touch it.
 *
 * Together those make the trait economy the game. Your weapon decides what
 * your colour DOES; your Stable decides which colours you have at all.
 *
 * Still deliberately not the real build: no db.php, no skulliance.php, no
 * session, no login, nothing saved. Rules are JS; they port to
 * dhcarena-engine.php once the feel is right. Real traits from dhcrarity.php,
 * real art from the dhc/web tree.
 */
$dhc_rarity = is_file(__DIR__ . '/dhcrarity.php') ? (require __DIR__ . '/dhcrarity.php') : array();
if (!$dhc_rarity) { http_response_code(500); exit('dhcrarity.php missing — nothing to build Fighters from.'); }

$ART = 'dhc/web';
$excl = array();
if (is_file(__DIR__ . '/dhcfighters-config.php')) {
	require_once __DIR__ . '/dhcfighters-config.php';
	if (defined('DHCF_HEADGEAR_EXCLUDED_BY_HEAD')) $excl = DHCF_HEADGEAR_EXCLUDED_BY_HEAD;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>DHC Arena — Prototype</title>
<style>
:root{--ink:#0d0f13;--panel:#151922;--panel2:#1d2230;--line:#2b3345;--bone:#e8e6e1;
  --dim:#8b93a7;--teal:#00c8a0;--ochre:#f5a623;--blood:#e0466b;--shield:#5aa9ff;
  --g0:#e0466b;--g1:#f5a623;--g2:#8b7bd8;--g3:#5aa9ff;--g4:#00c8a0;}
*{box-sizing:border-box}
body{margin:0;background:var(--ink);color:var(--bone);
  font:13px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0);
  -webkit-user-select:none;user-select:none}
.wrap{max-width:1080px;margin:0 auto;padding:12px}
h1{font-size:15px;letter-spacing:.14em;text-transform:uppercase;margin:0 0 2px}
.sub{color:var(--dim);font-size:10.5px;margin:0 0 10px}
button{font:inherit;cursor:pointer;border-radius:3px}
.btn{background:var(--panel2);color:var(--bone);border:1px solid var(--line);padding:6px 12px}
.btn:hover{border-color:var(--ochre);color:var(--ochre)}
.btn.go{background:var(--blood);border-color:var(--blood);color:#fff}
.top{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.turnflag{font-size:10px;letter-spacing:.12em;text-transform:uppercase;padding:3px 9px;
  border-radius:999px;border:1px solid var(--line);color:var(--dim)}
.turnflag.you{border-color:var(--teal);color:var(--teal)}
.turnflag.foe{border-color:var(--blood);color:var(--blood)}

.game{display:grid;grid-template-columns:1fr 340px;gap:12px}
@media (max-width:900px){.game{grid-template-columns:1fr}}

/* ---- teams ---- */
.teamrow{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;margin-bottom:8px}
.tok{background:var(--panel2);border:1px solid var(--line);border-radius:3px;padding:5px;
  position:relative;transition:transform .16s,opacity .3s,border-color .15s}
.tok.mine{border-left:3px solid var(--gem)}
.tok .rk{font-size:7.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);
  display:flex;justify-content:space-between;gap:4px}
.tok .gemdot{width:7px;height:7px;border-radius:50%;background:var(--gem);display:inline-block}
.tok .art{position:relative;width:100%;aspect-ratio:1;overflow:hidden;border-radius:2px;
  background:repeating-conic-gradient(#191419 0% 25%,#201b20 0% 50%) 50%/9px 9px;margin:2px 0}
.tok .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.tok .nm{font-size:9.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tok .kitn{font-size:8px;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hpwrap{position:relative;height:6px;background:#0b0d11;border-radius:2px;overflow:hidden;margin-top:3px}
.hp{position:absolute;inset:0;background:var(--teal);transform-origin:left;
  transition:transform .4s cubic-bezier(.2,.7,.3,1)}
.hp.low{background:var(--ochre)}.hp.crit{background:var(--blood)}
.sh{position:absolute;top:0;left:0;height:100%;background:var(--shield);opacity:.8;transition:width .4s}
.hpn{font-size:8px;color:var(--dim);font-variant-numeric:tabular-nums;display:flex;justify-content:space-between}
.tok.ko{opacity:.3;filter:grayscale(1)}
.tok.hit{animation:hit .3s}
@keyframes hit{0%{transform:translateX(0)}30%{transform:translateX(-5px)}60%{transform:translateX(4px)}100%{transform:translateX(0)}}
.tok.act{animation:act .34s}
@keyframes act{0%{transform:scale(1)}40%{transform:scale(1.08)}100%{transform:scale(1)}}
.flash{position:absolute;inset:0;background:#fff;opacity:0;pointer-events:none;border-radius:3px}
.flash.on{animation:fl .28s}@keyframes fl{0%{opacity:.5}100%{opacity:0}}
.pop{position:absolute;left:50%;top:22%;transform:translateX(-50%);font-size:14px;font-weight:700;
  pointer-events:none;opacity:0;text-shadow:0 2px 6px #000;z-index:5;white-space:nowrap}
.pop.on{animation:pp .9s}.pop.heal{color:var(--teal)}.pop.big{font-size:19px;color:var(--ochre)}
@keyframes pp{0%{opacity:0;transform:translate(-50%,6px)}18%{opacity:1}100%{opacity:0;transform:translate(-50%,-26px)}}

/* ---- board ---- */
.boardwrap{position:relative;border:1px solid var(--line);border-radius:4px;background:var(--panel);
  padding:8px;overflow:hidden}
.terrain{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.13}
.grid{position:relative;z-index:2;display:grid;gap:3px;touch-action:manipulation}
.cell{position:relative;aspect-ratio:1;border-radius:4px;display:flex;align-items:center;
  justify-content:center;cursor:pointer;background:#10131a;border:1px solid transparent;
  transition:transform .12s,border-color .12s}
.cell:hover{border-color:var(--line)}
.cell.sel{border-color:var(--bone);transform:scale(.9)}
.cell .g{width:72%;height:72%;border-radius:50%;background:var(--gc);
  box-shadow:inset 0 -3px 6px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.08);
  transition:transform .18s,opacity .18s}
.cell.sq .g{border-radius:4px}
.cell.di .g{border-radius:3px;transform:rotate(45deg) scale(.82)}
.cell.tri .g{border-radius:2px;clip-path:polygon(50% 8%,96% 92%,4% 92%)}
.cell.hex .g{clip-path:polygon(25% 5%,75% 5%,100% 50%,75% 95%,25% 95%,0 50%)}
.cell.clear .g{transform:scale(0);opacity:0}
.cell.drop{animation:drp .22s}
@keyframes drp{0%{transform:translateY(-16px);opacity:.4}100%{transform:translateY(0);opacity:1}}
.legend{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;font-size:9px;color:var(--dim);
  position:relative;z-index:2}
.legend span{display:flex;align-items:center;gap:4px}
.legend i{width:9px;height:9px;border-radius:50%;background:var(--lc);display:inline-block}
.reach{font-size:9.5px;color:var(--dim);margin-top:5px;position:relative;z-index:2}
.reach b{color:var(--ochre)}

/* ---- side ---- */
.panel{border:1px solid var(--line);border-radius:4px;background:var(--panel);padding:9px;margin-bottom:10px}
.panel h2{margin:0 0 6px;font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim)}
#log{height:190px;overflow:auto;font-size:10px;line-height:1.55}
#log div{padding:1px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.log-you{color:var(--teal)}.log-foe{color:var(--blood)}.log-sys{color:var(--dim)}
.log-big{color:var(--ochre)}
.combo{position:absolute;left:50%;top:38%;transform:translateX(-50%);z-index:9;
  font-size:26px;font-weight:700;color:var(--ochre);text-shadow:0 3px 14px #000;opacity:0;pointer-events:none}
.combo.on{animation:cb 1s}
@keyframes cb{0%{opacity:0;transform:translate(-50%,10px) scale(.8)}
  20%{opacity:1;transform:translate(-50%,0) scale(1.1)}
  70%{opacity:1}100%{opacity:0;transform:translate(-50%,-14px)}}
.over{text-align:center;padding:14px}.over h2{font-size:15px;color:var(--ochre);margin:0 0 4px}
</style>
</head>
<body>
<div class="wrap">
  <h1>DHC Arena <span style="color:var(--ochre)">prototype v2 — puzzle battler</span></h1>
  <p class="sub">Each of your Fighters owns a gem. Match it to make them act. Match size is reach:
     <b>3</b> hits their front, <b>4</b> reaches mid, <b>5+</b> reaches back. Match 4+ and you go again.</p>
  <div class="top">
    <button class="btn go" id="reroll">New battle</button>
    <span class="turnflag" id="flag">—</span>
    <span class="sub" style="margin:0" id="round"></span>
  </div>

  <div class="game">
    <div>
      <div class="teamrow" id="foeTeam"></div>
      <div class="boardwrap">
        <div class="terrain" id="terrain"></div>
        <div class="combo" id="combo"></div>
        <div class="grid" id="grid"></div>
        <div class="legend" id="legend"></div>
        <div class="reach" id="reach"></div>
      </div>
      <div class="teamrow" id="myTeam" style="margin-top:8px"></div>
    </div>
    <div>
      <div class="panel"><h2>Battle log</h2><div id="log"></div></div>
      <div class="panel" id="resultPanel" style="display:none"></div>
    </div>
  </div>
</div>
<script>
/* =====================================================================
   DHC ARENA — PROTOTYPE v2, PUZZLE BATTLER (JavaScript, throwaway)
   Ported to dhcarena-engine.php once the feel is settled.
   ===================================================================== */
var RARITY = <?php echo json_encode($dhc_rarity); ?>;
var ART    = <?php echo json_encode($ART); ?>;
var HG_EXCL= <?php echo json_encode($excl); ?>;

var N = 7;                    // board is N x N
var GEMS = 5;                 // 0,1,2 = your three Fighters. 3 = guard. 4 = surge.
var SHAPE = ['','sq','di','tri','hex'];
var GEMNAME = ['front','mid','back','guard','surge'];

var TIER_MULT = {common:1.00, uncommon:1.08, epic:1.16, legendary:1.24, mythic:1.32};
function hash(s){var h=2166136261;for(var i=0;i<s.length;i++){h^=s.charCodeAt(i);h=Math.imul(h,16777619);}return h>>>0;}
function pick(a,n){return a[n%a.length];}
function cap(s){return s.charAt(0).toUpperCase()+s.slice(1);}
function slugsOf(c){return Object.keys(RARITY[c]||{});}
function tierOf(c,s){var r=(RARITY[c]||{})[s];return r?r[0]:'common';}
function artUrl(d,s,z){return ART+'/'+z+'/'+d+'/'+s+'.png';}

/* Weapon kits. What YOUR gem colour does when it matches -- which is the
   trait economy reaching into the puzzle. dhcarena.md §3. */
var KITS = [
  {id:'heavy',  name:'Heavy Swing',   dmg:1.55, note:'big single hit'},
  {id:'cleave', name:'Cleave',        dmg:0.80, cleave:true, note:'hits the whole rank reached'},
  {id:'drain',  name:'Siphon',        dmg:1.05, drain:.45,   note:'heals itself for 45%'},
  {id:'sunder', name:'Sunder',        dmg:0.95, sunder:true, note:'strips shields first'},
  {id:'precise',name:'Precision',     dmg:1.15, crit:.28,    note:'high crit'},
  {id:'volley', name:'Volley',        dmg:0.62, all:true,    note:'chips every enemy'},
  {id:'brutal', name:'Brutal Cut',    dmg:1.30, bleed:true,  note:'leaves a bleed'},
  {id:'quick',  name:'Quick Jab',     dmg:0.85, echo:true,   note:'strikes twice'}
];
var TERRAIN = [
  {id:'crit',  name:'Fractured Signal', note:'+12% crit'},
  {id:'dmg',   name:'Overclocked',      note:'+10% damage'},
  {id:'guard', name:'Dense Cover',      note:'guard gems give +50%'},
  {id:'surge', name:'Low Gravity',      note:'surge gems charge faster'},
  {id:'frail', name:'Corrosive Haze',   note:'-8% max HP for everyone'}
];

function buildFighter(t,name){
  var m=function(c,s){return TIER_MULT[tierOf(c,s)]||1;};
  var v=function(s,sp){return 1+((hash(s)%1000)/1000-.5)*sp;};
  var V=.6;   // §4: variance between traits must be wider than the gap between tiers
  var hp=Math.round(150*m('torso',t.torso)*v(t.torso,V));
  return {uid:'f'+(++UID), name:name, traits:t,
    kit:pick(KITS,hash(t.weapon)),
    maxHp:hp, hp:hp, shield:0, bleed:0,
    /* Tuned by simulating 140 full battles per setting rather than guessed.
       At power 15 a battle ran 17.6 of your moves -- a real tug of war but
       long enough to risk the tedium the v1 playtest already found. At 25 it
       collapsed to 9.6, too quick for the lead to change hands. 20 lands
       around 13, which is long enough to swing and short enough to stay
       sharp. Revisit against a 6-battle daily allowance. */
    power:Math.round(20*m('weapon',t.weapon)*v(t.weapon,V)),
    critC:0.06*m('headgear',t.headgear)*v(t.headgear,V),
    surge:0, rank:0, side:null, ko:false};
}
var UID=0;
function mulberry(s){return function(){s|=0;s=s+0x6D2B79F5|0;var t=Math.imul(s^s>>>15,1|s);
  t=t+Math.imul(t^t>>>7,61|t)^t;return((t^t>>>14)>>>0)/4294967296;};}
function randomTraits(rnd){
  var t={};['torso','head','headgear','arms','weapon','background','companion','effects'].forEach(function(c){
    var l=slugsOf(c);t[c]=l[Math.floor(rnd()*l.length)];});
  var b=HG_EXCL[t.head]||[],g=0;
  while(b.indexOf(t.headgear)!==-1&&g++<40){var h=slugsOf('headgear');t.headgear=h[Math.floor(rnd()*h.length)];}
  return t;
}
var FIRST=['Bone','Ash','Grim','Null','Vex','Rust','Pale','Iron','Hex','Dread','Cinder','Wraith'];
var LAST=['Harvester','Revenant','Conductor','Sentinel','Warden','Prowler','Herald','Butcher','Cipher','Widow'];
function fname(rnd){return FIRST[Math.floor(rnd()*FIRST.length)]+' '+LAST[Math.floor(rnd()*LAST.length)];}

/* ---------------- state ---------------- */
var S=null, sel=null, busy=false;

function newBattle(){
  var rnd=mulberry(Date.now()&0x7fffffff);
  var mine=[0,1,2].map(function(){return buildFighter(randomTraits(rnd),fname(rnd));});
  var foes=[0,1,2].map(function(){return buildFighter(randomTraits(rnd),fname(rnd));});
  mine.forEach(function(f,i){f.rank=i;f.side='mine';});
  foes.forEach(function(f,i){f.rank=i;f.side='foes';});
  S={mine:mine,foes:foes,board:[],turn:'mine',round:1,over:null,
     terrain:pick(TERRAIN,hash(foes[0].traits.background)),terrainBg:foes[0].traits.background};
  if(S.terrain.id==='frail') S.mine.concat(S.foes).forEach(function(f){f.maxHp=Math.round(f.maxHp*.92);f.hp=f.maxHp;});
  makeBoard();
  document.getElementById('log').innerHTML='';
  document.getElementById('resultPanel').style.display='none';
  logLine('sys','Terrain — '+S.terrain.name+': '+S.terrain.note+'. Set by their front rank.');
  renderAll();
}
function team(s){return s==='mine'?S.mine:S.foes;}
function alive(s){return team(s).filter(function(f){return !f.ko;});}
function fighterForGem(side,g){ return team(side).filter(function(f){return f.rank===g;})[0]; }

/* ---------------- board ---------------- */
function makeBoard(){
  do{
    S.board=[];
    for(var i=0;i<N*N;i++) S.board.push(Math.floor(Math.random()*GEMS));
  } while(findMatches().length || !hasMove());
}
function idx(r,c){return r*N+c;}
function inb(r,c){return r>=0&&r<N&&c>=0&&c<N;}
function findMatches(b){
  b=b||S.board; var out=[],r,c,i;
  for(r=0;r<N;r++){ c=0; while(c<N){ var run=1;
      while(c+run<N && b[idx(r,c+run)]===b[idx(r,c)] && b[idx(r,c)]!==-1) run++;
      if(run>=3){var g=[];for(i=0;i<run;i++)g.push(idx(r,c+i));out.push({cells:g,type:b[idx(r,c)],len:run});}
      c+=run; } }
  for(c=0;c<N;c++){ r=0; while(r<N){ var run2=1;
      while(r+run2<N && b[idx(r+run2,c)]===b[idx(r,c)] && b[idx(r,c)]!==-1) run2++;
      if(run2>=3){var g2=[];for(i=0;i<run2;i++)g2.push(idx(r+i,c));out.push({cells:g2,type:b[idx(r,c)],len:run2});}
      r+=run2; } }
  return out;
}
function swapped(b,a,bb){var n=b.slice();var t=n[a];n[a]=n[bb];n[bb]=t;return n;}
function hasMove(){
  for(var r=0;r<N;r++)for(var c=0;c<N;c++){
    if(c+1<N && findMatches(swapped(S.board,idx(r,c),idx(r,c+1))).length) return true;
    if(r+1<N && findMatches(swapped(S.board,idx(r,c),idx(r+1,c))).length) return true;
  }
  return false;
}
function collapse(){
  for(var c=0;c<N;c++){
    var col=[];
    for(var r=N-1;r>=0;r--){var v=S.board[idx(r,c)];if(v!==-1)col.push(v);}
    for(var r2=N-1,k=0;r2>=0;r2--,k++)
      S.board[idx(r2,c)] = k<col.length ? col[k] : Math.floor(Math.random()*GEMS);
  }
}

/* ---------------- combat ---------------- */
function reachFor(len){ return len>=5?2:len===4?1:0; }
function targetsAt(side,depth){
  var foes=alive(side); if(!foes.length) return [];
  var byRank=foes.slice().sort(function(a,b){return a.rank-b.rank;});
  // reach is a ceiling: you can always hit anything shallower
  var elig=byRank.filter(function(f){return f.rank<=depth;});
  return elig.length?elig:[byRank[0]];
}
function hurt(t,amt,tag){
  if(t.shield>0){var a=Math.min(t.shield,amt);t.shield-=a;amt-=a;if(a>0)pop(t,'-'+a+' shield','heal');}
  if(amt<=0)return 0;
  t.hp=Math.max(0,t.hp-amt); pop(t,'-'+amt,tag); shake(t);
  if(t.hp===0){t.ko=true;logLine(t.side==='mine'?'foe':'you',t.name+' is knocked out.');}
  return amt;
}
function healF(f,a){var b=f.hp;f.hp=Math.min(f.maxHp,f.hp+a);if(f.hp>b)pop(f,'+'+(f.hp-b),'heal');}

function resolveGroup(side,grp,chain){
  var foeSide=side==='mine'?'foes':'mine';
  var mult=1+(chain-1)*0.35;                        // cascades hit harder
  if(S.terrain.id==='dmg')mult*=1.10;
  if(grp.type===3){                                  // GUARD — shield your team
    var amt=Math.round((6+grp.len*4)*mult*(S.terrain.id==='guard'?1.5:1));
    alive(side).forEach(function(f){f.shield+=amt;});
    logLine(side==='mine'?'you':'foe','Guard x'+grp.len+' — +'+amt+' shield to the team.');
    return;
  }
  if(grp.type===4){                                  // SURGE — charge, then erupt
    var add=grp.len*(S.terrain.id==='surge'?2:1);
    var living=alive(side);
    if(!living.length) return;          // whole team down mid-cascade
    living.forEach(function(f){f.surge=Math.min(10,f.surge+add);});
    logLine(side==='mine'?'you':'foe','Surge x'+grp.len+' — team charge '+living[0].surge+'/10.');
    alive(side).forEach(function(f){
      if(f.surge>=10){ f.surge=0;
        var ts=alive(foeSide);
        ts.forEach(function(t){hurt(t,Math.round(f.power*0.9*mult),'big');});
        act(f); pop(f,'SURGE!','big');
        logLine(side==='mine'?'you':'foe',f.name+' erupts — hits everything.');
      }});
    return;
  }
  var f=fighterForGem(side,grp.type);
  if(!f||f.ko){ logLine('sys','Matched '+GEMNAME[grp.type]+' but that Fighter is down — wasted.'); return; }
  var depth=reachFor(grp.len);
  var ts=targetsAt(foeSide,depth);
  if(!ts.length)return;
  act(f);
  var k=f.kit;
  var hitList = k.all ? alive(foeSide) : (k.cleave ? ts : [ts[ts.length-1]]);
  var times = k.echo?2:1;
  for(var n=0;n<times;n++){
    hitList.forEach(function(t){
      if(t.ko)return;
      var crit=Math.random()<(f.critC+(k.crit||0)+(S.terrain.id==='crit'?.12:0));
      var base=f.power*k.dmg*mult*(crit?1.6:1)*(1+(grp.len-3)*0.30);
      if(k.sunder&&t.shield>0){t.shield=Math.max(0,t.shield-Math.round(base*0.5));}
      var dealt=hurt(t,Math.max(1,Math.round(base)),crit?'big':'');
      if(k.drain)healF(f,Math.round(dealt*k.drain));
      if(k.bleed&&!t.ko)t.bleed=3;
    });
  }
  logLine(side==='mine'?'you':'foe',
    f.name+' — '+k.name+' x'+grp.len+(chain>1?' (chain '+chain+')':'')+
    ' → '+['front','mid','back'][depth]+' rank');
}
function tickBleeds(side){
  alive(side).forEach(function(f){
    if(f.bleed>0){f.bleed--;var d=Math.round(f.maxHp*0.04);hurt(f,d,'');
      logLine('sys',f.name+' bleeds for '+d+'.');}});
}

/* ---------------- turn resolution ---------------- */
function applySwap(a,b,side,done){
  busy=true;
  var t=S.board[a];S.board[a]=S.board[b];S.board[b]=t;
  renderBoard();
  setTimeout(function(){ cascade(side,1,done); },130);
}
function cascade(side,chain,done){
  var ms=findMatches();
  if(!ms.length){
    if(!hasMove()){ makeBoard(); logLine('sys','No moves left — board reshuffled.'); renderBoard(); }
    busy=false; done&&done(chain-1); return;
  }
  if(chain>1){ var c=document.getElementById('combo'); c.textContent='CHAIN x'+chain;
    c.classList.remove('on'); void c.offsetWidth; c.classList.add('on'); }
  var cleared={};
  ms.forEach(function(g){ resolveGroup(side,g,chain); g.cells.forEach(function(i){cleared[i]=1;}); });
  Object.keys(cleared).forEach(function(i){ var el=cellEl(i); if(el) el.classList.add('clear'); });
  renderTeams();
  if(checkOver()){busy=false;return;}
  setTimeout(function(){
    Object.keys(cleared).forEach(function(i){S.board[i]=-1;});
    collapse(); renderBoard(true);
    setTimeout(function(){ cascade(side,chain+1,done); },170);
  },190);
}
function endTurn(best){
  if(S.over)return;
  var extra = best>=1 && S.lastLen>=4;
  if(extra){ logLine('big','Match of '+S.lastLen+' — you go again.'); renderAll(); return; }
  tickBleeds(S.turn==='mine'?'foes':'mine');
  if(checkOver())return;
  S.turn = S.turn==='mine'?'foes':'mine';
  if(S.turn==='mine')S.round++;
  renderAll();
  if(S.turn==='foes') setTimeout(aiMove,520);
}
function checkOver(){
  if(!alive('foes').length){S.over='win';renderAll();return true;}
  if(!alive('mine').length){S.over='lose';renderAll();return true;}
  return false;
}

/* ---------------- the AI ----------------
   Plays the same board by the same rules. dhcarena.md §3b: if a simple
   priority AI cannot play an ability well, the ability is not finished. */
function scoreMove(a,b,side){
  var nb=swapped(S.board,a,b); var ms=findMatches(nb);
  if(!ms.length)return -1;
  var sc=0;
  ms.forEach(function(g){
    var len=g.len;
    if(g.type===3) sc += len*4;
    else if(g.type===4) sc += len*5;
    else {
      var f=fighterForGem(side,g.type);
      if(!f||f.ko){ sc -= 2; return; }
      sc += len*10 + f.power*0.4;
      var depth=reachFor(len);
      var ts=targetsAt(side==='mine'?'foes':'mine',depth);
      // finishing something is worth a lot
      ts.forEach(function(t){ if(f.power*f.kit.dmg*(1+(len-3)*.3) >= t.hp) sc+=28; });
      if(len>=4) sc+=14;        // extra turn
    }
  });
  return sc;
}
function bestMove(side){
  var best=null;
  for(var r=0;r<N;r++)for(var c=0;c<N;c++){
    [[0,1],[1,0]].forEach(function(d){
      var r2=r+d[0],c2=c+d[1]; if(!inb(r2,c2))return;
      var a=idx(r,c),b=idx(r2,c2),s=scoreMove(a,b,side);
      if(s>0&&(!best||s>best.s))best={a:a,b:b,s:s};
    });
  }
  return best;
}
function aiMove(){
  if(S.over)return;
  var mv=bestMove('foes');
  if(!mv){ makeBoard(); renderBoard(); mv=bestMove('foes'); if(!mv){endTurn(0);return;} }
  var ms=findMatches(swapped(S.board,mv.a,mv.b));
  S.lastLen=ms.reduce(function(p,g){return Math.max(p,g.len);},0);
  applySwap(mv.a,mv.b,'foes',function(chains){
    if(S.over)return;
    if(S.lastLen>=4){ logLine('foe','They matched '+S.lastLen+' — they go again.');
      renderAll(); setTimeout(aiMove,560); return; }
    tickBleeds('mine'); if(checkOver())return;
    S.turn='mine'; S.round++; renderAll();
  });
}

/* ---------------- rendering ---------------- */
function cellEl(i){return document.querySelector('[data-i="'+i+'"]');}
function elFor(f){return document.querySelector('[data-id="'+f.uid+'"]');}
function shake(f){var e=elFor(f);if(!e)return;e.classList.remove('hit');void e.offsetWidth;e.classList.add('hit');
  var fl=e.querySelector('.flash');if(fl){fl.classList.remove('on');void fl.offsetWidth;fl.classList.add('on');}}
function act(f){var e=elFor(f);if(!e)return;e.classList.remove('act');void e.offsetWidth;e.classList.add('act');}
function pop(f,txt,kind){var e=elFor(f);if(!e)return;var p=document.createElement('div');
  p.className='pop on '+(kind||'');p.textContent=txt;e.appendChild(p);setTimeout(function(){p.remove();},950);}

function tokHtml(f,showGem){
  var pct=f.hp/f.maxHp, cls=pct<=.25?'crit':pct<=.55?'low':'';
  var t=f.traits;
  var layers=['torso','weapon','arms','effects','head','headgear','companion']
    .filter(function(k){return t[k];})
    .map(function(k){return '<img loading="lazy" alt="" src="'+artUrl(k,t[k],250)+'" onerror="this.remove()">';}).join('');
  return '<div class="tok'+(showGem?' mine':'')+(f.ko?' ko':'')+'" data-id="'+f.uid+'"'
    + (showGem?' style="--gem:var(--g'+f.rank+')"':'')+'>'
    + '<div class="flash"></div>'
    + '<div class="rk"><span>'+['front','mid','back'][f.rank]+'</span>'
    +   (showGem?'<span class="gemdot"></span>':'<span>'+(f.shield>0?'sh '+f.shield:'')+'</span>')+'</div>'
    + '<div class="art">'+layers+'</div>'
    + '<div class="nm">'+f.name+'</div>'
    + '<div class="kitn">'+f.kit.name+'</div>'
    + '<div class="hpwrap"><div class="hp '+cls+'" style="transform:scaleX('+pct+')"></div>'
    +   '<div class="sh" style="width:'+Math.min(100,(f.shield/f.maxHp)*100)+'%"></div></div>'
    + '<div class="hpn"><span>'+f.hp+'/'+f.maxHp+'</span><span>'
    +   (f.surge>0?'surge '+f.surge+'/10':'')+(f.bleed>0?' bleed':'')+'</span></div>'
    + '</div>';
}
function renderTeams(){
  document.getElementById('foeTeam').innerHTML =
    S.foes.slice().sort(function(a,b){return a.rank-b.rank;}).map(function(f){return tokHtml(f,false);}).join('');
  document.getElementById('myTeam').innerHTML =
    S.mine.slice().sort(function(a,b){return a.rank-b.rank;}).map(function(f){return tokHtml(f,true);}).join('');
}
function renderBoard(dropAnim){
  var g=document.getElementById('grid');
  g.style.gridTemplateColumns='repeat('+N+',1fr)';
  var h='';
  for(var i=0;i<N*N;i++){
    var v=S.board[i];
    h+='<div class="cell '+SHAPE[v]+(dropAnim?' drop':'')+'" data-i="'+i+'" style="--gc:var(--g'+v+')">'
      +'<div class="g"></div></div>';
  }
  g.innerHTML=h;
}
function renderAll(){
  renderTeams(); renderBoard();
  document.getElementById('terrain').style.backgroundImage='url("'+artUrl('background',S.terrainBg,1000)+'")';
  var lg=['front','mid','back'].map(function(n,i){
    var f=fighterForGem('mine',i);
    return '<span style="--lc:var(--g'+i+')"><i></i>'+(f?f.name.split(' ')[0]+' · '+f.kit.name:n)+'</span>';
  }).join('')
  + '<span style="--lc:var(--g3)"><i></i>Guard — team shield</span>'
  + '<span style="--lc:var(--g4)"><i></i>Surge — charge, erupts at 10</span>';
  document.getElementById('legend').innerHTML=lg;
  document.getElementById('reach').innerHTML='Reach — <b>3</b> their front · <b>4</b> their mid · <b>5+</b> their back. '
    + 'Cascades multiply. Terrain: '+S.terrain.name+'.';
  var fl=document.getElementById('flag');
  fl.className='turnflag '+(S.over?'':(S.turn==='mine'?'you':'foe'));
  fl.textContent=S.over?(S.over==='win'?'victory':'defeat'):(S.turn==='mine'?'your move':'their move');
  document.getElementById('round').textContent=S.over?'':'Round '+S.round;
  if(S.over){
    var p=document.getElementById('resultPanel');p.style.display='';
    p.innerHTML='<div class="over"><h2>'+(S.over==='win'?'Victory':'Defeat')+'</h2>'
      +'<p class="sub">'+(S.over==='win'?'In the real game this is where traits and ladder points land.'
                                        :'In the real game these Fighters would now be benched.')+'</p></div>';
  }
}
function logLine(kind,text){
  var l=document.getElementById('log');var d=document.createElement('div');
  d.className='log-'+kind;d.textContent=text;l.appendChild(d);l.scrollTop=l.scrollHeight;
}

/* ---------------- input ---------------- */
document.getElementById('grid').addEventListener('click',function(e){
  if(busy||S.over||S.turn!=='mine')return;
  var c=e.target.closest('.cell'); if(!c)return;
  var i=+c.getAttribute('data-i');
  if(sel===null){ sel=i; c.classList.add('sel'); return; }
  if(sel===i){ c.classList.remove('sel'); sel=null; return; }
  var r1=Math.floor(sel/N),c1=sel%N,r2=Math.floor(i/N),c2=i%N;
  var adj=Math.abs(r1-r2)+Math.abs(c1-c2)===1;
  var prev=cellEl(sel); if(prev)prev.classList.remove('sel');
  if(!adj){ sel=i; c.classList.add('sel'); return; }
  var a=sel; sel=null;
  var ms=findMatches(swapped(S.board,a,i));
  if(!ms.length){ logLine('sys','No match there.'); return; }
  S.lastLen=ms.reduce(function(p,g){return Math.max(p,g.len);},0);
  applySwap(a,i,'mine',function(chains){ endTurn(1); });
});
document.getElementById('reroll').onclick=newBattle;
newBattle();
</script>
</body>
</html>
