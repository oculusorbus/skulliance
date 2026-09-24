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
.wrap{max-width:1500px;margin:0 auto;padding:12px}
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

.arena{display:grid;grid-template-columns:minmax(0,270px) minmax(0,1fr) minmax(0,270px);
  gap:14px;align-items:start}
/* The board must not simply eat the extra width -- a 1000px square does not fit
   a laptop viewport. Cap it against viewport HEIGHT and centre it, and the
   width freed up goes to the Fighters, which is the point. */
/* The cap belongs to the whole column, not just the board. Applied only to the
   boardwrap it left the reach line and the legend stretching the full column
   width, so they ran wider than the thing they describe. */
.boardcol > *{max-width:min(74vh,760px);margin-left:auto;margin-right:auto}
.teamcol{display:grid;gap:6px}
.boardcol{min-width:0}
@media (max-width:1000px){
  /* Stack, and put the enemies above the board where they read as the opposition */
  .arena{grid-template-columns:1fr}
  .teamcol{grid-template-columns:repeat(3,minmax(0,1fr))}
  .teamcol.foes{order:-1}
  .teamcol.mine{order:1}
}

/* ---- teams ---- */
.tok{background:var(--panel2);border:1px solid var(--line);border-radius:3px;padding:5px;
  position:relative;transition:transform .16s,opacity .3s,border-color .15s}
.tok{border-left:3px solid var(--gem)}
.tok .rk{font-size:7.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);
  display:flex;justify-content:space-between;gap:4px}
.tok .mygem{display:inline-flex;align-items:center;justify-content:center;
  width:19px;height:19px;border-radius:50%;background:var(--gem);font-size:11px;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12);flex:none}
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

/* In the side-column layout three stacked tokens would stand far taller than
   the board, so the art gets a fixed height there and letterboxes inside it --
   object-fit:contain already centres it. */
@media (min-width:1001px){
  /* A BUST CROP, not a letterboxed full body. The art is square, so fitting it
     into a wide column left big empty bars and a tiny Fighter. Cropping to the
     top of the frame fills the column and shows the head and torso -- the part
     you actually recognise a Fighter by -- several times larger than before.
     Every layer is the same size and gets the same object-position, so the
     composite stays aligned. */
  .teamcol .tok .art{aspect-ratio:auto;height:188px}
  .teamcol .tok .art img{object-fit:cover;object-position:top center}
  /* Enemies face the player. The art is all drawn facing one way, so the right
     column mirrors and the two Stables look at each other across the board
     instead of everyone staring the same direction. Only in the side-column
     layout -- stacked on a phone they are above you, not opposite you, and a
     mirrored row there just looks like different art. */
  .teamcol.foes .tok .art img{transform:scaleX(-1)}
  .teamcol .tok{padding:8px}
  .teamcol .tok .nm{font-size:12px}
  .teamcol .tok .kitn{font-size:10px}
  .teamcol .tok .rk{font-size:9px}
  .teamcol .tok .hpn{font-size:9.5px}
  .teamcol .tok .hpwrap{height:8px}
  .teamcol{gap:10px}
}

/* ---- board ---- */
.boardwrap{position:relative;border:1px solid var(--line);border-radius:4px;background:var(--panel);
  padding:8px;overflow:hidden}
.terrain{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.13}
.grid{position:relative;z-index:2;display:grid;gap:3px;touch-action:none}
.cell{position:relative;aspect-ratio:1;border-radius:4px;display:flex;align-items:center;
  justify-content:center;cursor:pointer;background:#10131a;border:1px solid transparent;
  transition:transform .12s,border-color .12s}
.cell:hover{border-color:var(--line)}
.cell.sel{border-color:var(--bone);transform:scale(.9)}
.cell .g{width:72%;height:72%;border-radius:50%;background:var(--gc);
  display:flex;align-items:center;justify-content:center;
  box-shadow:inset 0 -3px 6px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.08);
  transition:transform .18s,opacity .18s}
.cell.sq .g{border-radius:4px}
.cell.di .g{border-radius:3px;transform:rotate(45deg) scale(.82)}
/* Flat shoulders, straight flanks, tapering to a point at the bottom -- the
   same silhouette as the emoji sitting on it. */
.cell.shield .g{border-radius:3px 3px 0 0;
  clip-path:polygon(0% 0%,100% 0%,100% 48%,86% 78%,50% 100%,14% 78%,0% 48%)}
/* the point steals height from the bottom, so the emoji rides a little high */
.cell.shield .g .em{transform:translateY(-8%)}
.cell.hex .g{clip-path:polygon(25% 5%,75% 5%,100% 50%,75% 95%,25% 95%,0 50%)}
.cell .em{font-size:clamp(11px,2.4vw,19px);line-height:1;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6));
  pointer-events:none}
/* the rotated diamond must not rotate its emoji with it */
.cell.di .g .em{transform:rotate(-45deg)}
.cell.clear .g{transform:scale(0);opacity:0}
.cell.drop{animation:drp .22s}
.cell.settle{animation:stl .16s}
@keyframes stl{0%{transform:scale(1.06)}100%{transform:scale(1)}}
@keyframes drp{0%{transform:translateY(-16px);opacity:.4}100%{transform:translateY(0);opacity:1}}
.legend{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.lchip{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--bone);
  padding:4px 9px 4px 4px;border-radius:999px;background:#10131a;
  border:1px solid var(--line);border-left:3px solid var(--lc);white-space:nowrap}
.lchip b{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;
  border-radius:50%;background:var(--lc);font-size:12px;font-weight:400;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12)}
.lchip.gone{opacity:.32;text-decoration:line-through}
.reach{font-size:10px;color:var(--dim);margin-top:7px;display:flex;flex-wrap:wrap;gap:4px 14px;
  align-items:baseline}
.reach b{color:var(--ochre)}
.reach .terr{color:var(--teal);margin-left:auto}
.reach b{color:var(--ochre)}

/* ---- side ---- */
.panel{border:1px solid var(--line);border-radius:4px;background:var(--panel);padding:9px;margin-bottom:10px}
.panel h2{margin:0 0 6px;font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim)}
.logbox{border:1px solid var(--line);border-radius:4px;background:var(--panel);
  margin-top:10px;padding:8px 10px}
.logbox summary{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);
  cursor:pointer;list-style:none}
.logbox summary::-webkit-details-marker{display:none}
.logbox summary:before{content:'▸ ';}
.logbox[open] summary:before{content:'▾ ';}
#log{height:150px;overflow:auto;font-size:10px;line-height:1.55;margin-top:6px}
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
  <p class="sub"><b>Drag a gem along its row or column, any distance</b> — the gems it passes shift back one.
     <b>There are always five gems.</b> Three of them are <b>ranks</b> — red front, amber mid, violet back —
     and matching one makes that rank's Fighter act, for whichever side matched it. The gem on the board
     shows <i>your</i> weapon in that rank; their token shows what the same gem does for <i>them</i>.
     The other two are 🛡️ Shield and ⚡ Charge. Match size is reach:
     <b>3</b> hits their front, <b>4</b> reaches mid, <b>5+</b> reaches back. Match 4+ and you go again.
     A slide with no match costs nothing.</p>
  <div class="top">
    <button class="btn go" id="reroll">New battle</button>
    <span class="turnflag" id="flag">—</span>
    <span class="sub" style="margin:0" id="round"></span>
  </div>

  <!-- Wide: your Stable down the left, the board in the middle, theirs down the
       right, so both teams hug the board. Narrow: they stack, enemies on top.
       The legend sits under the board and the log under that -- it is a
       reference for a curious player, not something read mid-turn. -->
  <div class="arena">
    <div class="teamcol mine" id="myTeam"></div>
    <div class="boardcol">
      <div class="boardwrap">
        <div class="terrain" id="terrain"></div>
        <div class="combo" id="combo"></div>
        <div class="grid" id="grid"></div>
      </div>
      <div class="reach" id="reach"></div>
      <div class="legend" id="legend"></div>
    </div>
    <div class="teamcol foes" id="foeTeam"></div>
  </div>
  <div class="panel" id="resultPanel" style="display:none;margin-top:10px"></div>
  <details class="logbox" open>
    <summary>Battle log</summary>
    <div id="log"></div>
  </details>
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
/* Shape reinforces meaning where meaning is fixed. Gems 0-2 belong to whichever
   Fighters you brought, so their shapes are just distinguishers -- circle,
   square, diamond. Gem 3 is always Shield and gem 4 is always Charge, so those
   two get silhouettes that say so. */
var SHAPE = ['','sq','di','shield','hex'];

var TIER_MULT = {common:1.00, uncommon:1.08, epic:1.16, legendary:1.24, mythic:1.32};
function hash(s){var h=2166136261;for(var i=0;i<s.length;i++){h^=s.charCodeAt(i);h=Math.imul(h,16777619);}return h>>>0;}
function pick(a,n){return a[n%a.length];}
function cap(s){return s.charAt(0).toUpperCase()+s.slice(1);}
function slugsOf(c){return Object.keys(RARITY[c]||{});}
function tierOf(c,s){var r=(RARITY[c]||{})[s];return r?r[0]:'common';}
function artUrl(d,s,z){return ART+'/'+z+'/'+d+'/'+s+'.png';}

/* Weapon kits. What YOUR gem colour does when it matches -- which is the
   trait economy reaching into the puzzle. dhcarena.md §3. */
/* Every kit carries an emoji and a name that says what the match DOES, because
   colour alone made the board unreadable -- you cannot play a puzzle whose
   pieces need a legend lookup each turn. The emoji is the same on the gem, on
   the Fighter's token and in the legend, so the three are one glance apart.

   Names are plain verbs on purpose. "Heavy Swing" tells you nothing at a
   glance; "Smash" does. */
var KITS = [
  {id:'heavy',  emoji:'🔨', name:'Smash',  dmg:1.55, note:'one big hit'},
  {id:'cleave', emoji:'🪓', name:'Cleave', dmg:0.80, cleave:true, note:'hits the whole rank'},
  {id:'drain',  emoji:'🩸', name:'Drain',  dmg:1.05, drain:.45,   note:'heals itself'},
  {id:'sunder', emoji:'⛏️', name:'Break',  dmg:0.95, sunder:true, note:'smashes shields'},
  {id:'precise',emoji:'🎯', name:'Snipe',  dmg:1.15, crit:.28,    note:'crits often'},
  {id:'volley', emoji:'🏹', name:'Volley', dmg:0.62, all:true,    note:'chips everyone'},
  {id:'brutal', emoji:'🗡️', name:'Bleed',  dmg:1.30, bleed:true,  note:'leaves a bleed'},
  {id:'quick',  emoji:'⚔️', name:'Double', dmg:0.85, echo:true,   note:'strikes twice'}
];
/* The two shared gems. Named for the effect, not the mechanic. */
var SHARED = {
  3:{emoji:'🛡️', name:'Shield', note:'shields your whole team'},
  4:{emoji:'⚡', name:'Charge',  note:'charges up — erupts at 10'}
};
/** What a gem shows and means, for a given side. */
function gemInfo(side,g){
  if(SHARED[g]) return SHARED[g];
  var f=fighterForGem(side,g);
  return f ? {emoji:f.kit.emoji, name:f.kit.name, note:f.kit.note, fighter:f} : {emoji:'·',name:'—',note:''};
}
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
/* Distinct first names per battle: the log and the legend both refer to a
   Fighter by its first name, and a battle with two Ashes in it is unreadable. */
function fname(rnd,used){
  var g=0,n;
  do{ n=FIRST[Math.floor(rnd()*FIRST.length)]; }while(used&&used[n]&&g++<60);
  if(used)used[n]=1;
  return n+' '+LAST[Math.floor(rnd()*LAST.length)];
}

/* ---------------- state ---------------- */
var S=null, sel=null, busy=false;

function newBattle(){
  var rnd=mulberry(Date.now()&0x7fffffff);
  /* Your three get DISTINCT kits, so no two of your gems wear the same emoji.
     An ambiguous icon is worse than no icon -- the whole point is that a glance
     resolves it. Their side may repeat; their gems are not on your board. */
  var mine=[], usedKits={}, usedNames={}, guard=0;
  while(mine.length<3 && guard++<300){
    var f=buildFighter(randomTraits(rnd),fname(rnd,usedNames));
    if(usedKits[f.kit.id]) continue;
    usedKits[f.kit.id]=1; mine.push(f);
  }
  while(mine.length<3) mine.push(buildFighter(randomTraits(rnd),fname(rnd,usedNames)));
  var foeNames={};
  var foes=[0,1,2].map(function(){return buildFighter(randomTraits(rnd),fname(rnd,foeNames));});
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
/* THE MOVE IS A SLIDE, NOT A SWAP.
   Matched to Monstrocity and Skull Swap, which is the feel players already
   have: pick a gem and drag it any distance along its row or column, and every
   gem between shifts one step back toward where it came from. Read out of
   monstrocity.php slideTiles() rather than approximated -- same rotation, same
   free revert when a move produces no match.

   It also makes the board far richer than adjacent swapping. On a 7x7 every
   gem has twelve destinations instead of four neighbours, so a move is a real
   search rather than a scan, and reaching for a 5-match is often possible
   somewhere if you can see it. */
function slid(b,a,z){
  if(a===z) return null;
  var ra=Math.floor(a/N), ca=a%N, rz=Math.floor(z/N), cz=z%N, x, y;
  if(ra!==rz && ca!==cz) return null;      // must share a row or a column
  var n=b.slice(), t=b[a];
  if(ra===rz){
    if(ca<cz){ for(x=ca;x<cz;x++) n[idx(ra,x)]=b[idx(ra,x+1)]; }
    else     { for(x=ca;x>cz;x--) n[idx(ra,x)]=b[idx(ra,x-1)]; }
    n[idx(ra,cz)]=t;
  }else{
    if(ra<rz){ for(y=ra;y<rz;y++) n[idx(y,ca)]=b[idx(y+1,ca)]; }
    else     { for(y=ra;y>rz;y--) n[idx(y,ca)]=b[idx(y-1,ca)]; }
    n[idx(rz,ca)]=t;
  }
  return n;
}
/** Every legal slide from every cell. The move space the AI and hasMove share. */
function eachSlide(fn){
  for(var r=0;r<N;r++) for(var c=0;c<N;c++){
    var a=idx(r,c), k;
    for(k=0;k<N;k++){ if(k!==c) fn(a, idx(r,k)); }
    for(k=0;k<N;k++){ if(k!==r) fn(a, idx(k,c)); }
  }
}
function hasMove(){
  var found=false;
  eachSlide(function(a,z){
    if(found) return;
    var nb=slid(S.board,a,z);
    if(nb && findMatches(nb).length) found=true;
  });
  return found;
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
    logLine(side==='mine'?'you':'foe','🛡️ Shield x'+grp.len+' — +'+amt+' to the whole team.');
    return;
  }
  if(grp.type===4){                                  // SURGE — charge, then erupt
    var add=grp.len*(S.terrain.id==='surge'?2:1);
    var living=alive(side);
    if(!living.length) return;          // whole team down mid-cascade
    living.forEach(function(f){f.surge=Math.min(10,f.surge+add);});
    logLine(side==='mine'?'you':'foe','⚡ Charge x'+grp.len+' — now '+living[0].surge+'/10.');
    alive(side).forEach(function(f){
      if(f.surge>=10){ f.surge=0;
        var ts=alive(foeSide);
        ts.forEach(function(t){hurt(t,Math.round(f.power*0.9*mult),'big');});
        act(f); pop(f,'SURGE!','big');
        logLine(side==='mine'?'you':'foe','⚡ '+f.name+' ERUPTS — hits everything.');
      }});
    return;
  }
  var f=fighterForGem(side,grp.type);
  if(!f||f.ko){ logLine('sys','Matched '+gemInfo(side,grp.type).emoji+' — that Fighter is down, the match is wasted.'); return; }
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
function applySlide(a,z,side,done){
  busy=true;
  var nb=slid(S.board,a,z);
  if(!nb){ busy=false; return; }
  S.board=nb;
  renderBoard(false,true);
  setTimeout(function(){ cascade(side,1,done); },150);
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
  /* Guarded because a throw in here is unrecoverable, not cosmetic: the
     exception escapes cascade(), so busy stays true and done() is never called,
     and the board sits disabled with the turn never handed back. That is
     exactly what a dangling GEMNAME reference did -- and only when a gem
     belonging to a knocked-out Fighter was matched, so it survived every test
     until a Fighter actually went down mid-battle. One bad group should cost
     one group's effect, never the game. */
  ms.forEach(function(g){
    try { resolveGroup(side,g,chain); }
    catch(err){ logLine('sys','(effect error — skipped)');
                if(window.console) console.error('resolveGroup', err); }
    g.cells.forEach(function(i){cleared[i]=1;});
  });
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
  var nb=slid(S.board,a,b); if(!nb) return -1;
  var ms=findMatches(nb);
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
/* THE AI IS DELIBERATELY NOT OPTIMAL.
   Slides give 545 legal moves on a typical board against 78 for adjacent
   swapping, and about 32 of them reach a 5-match. A machine that evaluates all
   545 and always takes the best one is not a worthy opponent, it is a wall --
   it would out-see a human every single turn, and the skill the slide mechanic
   exists to reward would never pay.

   So it picks from its shortlist rather than the top of it. Roughly half the
   time it plays its best move; otherwise it takes one of the next few. That
   reads as an opponent who missed something, which is what a human opponent
   does, and it leaves room for a player who spots the 5-match to win because
   they spotted it.

   This is a prototype knob. The real AI (dhcarena.md §3b) defends someone's
   Stable for real stakes and its strength is a design decision, not a
   convenience -- §14 of the locked calls says one fixed difficulty. */
function bestMove(side){
  var cand=[];
  eachSlide(function(a,z){
    var sc=scoreMove(a,z,side);
    if(sc>0) cand.push({a:a,b:z,s:sc});
  });
  if(!cand.length) return null;
  cand.sort(function(x,y){return y.s-x.s;});
  if(Math.random()<0.5) return cand[0];
  return cand[Math.min(cand.length-1, 1+Math.floor(Math.random()*4))];
}
function aiMove(){
  if(S.over)return;
  var mv=bestMove('foes');
  if(!mv){ makeBoard(); renderBoard(); mv=bestMove('foes'); if(!mv){endTurn(0);return;} }
  var ms=findMatches(slid(S.board,mv.a,mv.b));
  S.lastLen=ms.reduce(function(p,g){return Math.max(p,g.len);},0);
  applySlide(mv.a,mv.b,'foes',function(chains){
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
  return '<div class="tok'+(showGem?' mine':' foe')+(f.ko?' ko':'')+'" data-id="'+f.uid+'"'
    + ' style="--gem:var(--g'+f.rank+')">'
    + '<div class="flash"></div>'
    /* BOTH sides wear their gem. The colour is the RANK -- red is front, amber
       is mid, violet is back -- and it drives whichever side matched it. So the
       same gem shows your weapon on the board and theirs on their token, which
       is the information that was missing: leave violet matches lying around
       and their back-rank Fighter is the one who gets to use them. */
    + '<div class="rk"><span>'+['front','mid','back'][f.rank]+'</span>'
    +   '<span class="mygem" title="'+(showGem?'your':'their')+' '
    +     ['front','mid','back'][f.rank]+' — '+f.kit.name+', '+f.kit.note+'">'
    +     f.kit.emoji+'</span></div>'
    + '<div class="art">'+layers+'</div>'
    + '<div class="nm">'+f.name+'</div>'
    + '<div class="kitn" title="'+f.kit.name+' — '+f.kit.note+'">'
    +   (showGem?f.kit.emoji+' ':'')+f.kit.note+'</div>'
    + '<div class="hpwrap"><div class="hp '+cls+'" style="transform:scaleX('+pct+')"></div>'
    +   '<div class="sh" style="width:'+Math.min(100,(f.shield/f.maxHp)*100)+'%"></div></div>'
    + '<div class="hpn"><span>'+f.hp+'/'+f.maxHp+'</span><span>'
    +   (f.shield>0?'sh '+f.shield+'  ':'')
    +   (f.surge>0?'surge '+f.surge+'/10':'')+(f.bleed>0?' bleed':'')+'</span></div>'
    + '</div>';
}
function renderTeams(){
  document.getElementById('foeTeam').innerHTML =
    S.foes.slice().sort(function(a,b){return a.rank-b.rank;}).map(function(f){return tokHtml(f,false);}).join('');
  document.getElementById('myTeam').innerHTML =
    S.mine.slice().sort(function(a,b){return a.rank-b.rank;}).map(function(f){return tokHtml(f,true);}).join('');
}
function renderBoard(dropAnim,settle){
  var g=document.getElementById('grid');
  g.style.gridTemplateColumns='repeat('+N+',1fr)';
  var h='';
  for(var i=0;i<N*N;i++){
    var v=S.board[i];
    var gi=gemInfo('mine',v);
    h+='<div class="cell '+SHAPE[v]+(dropAnim?' drop':'')+(settle?' settle':'')+'" data-i="'+i+'"'
      +' style="--gc:var(--g'+v+')" title="'+gi.name+' — '+gi.note+'">'
      +'<div class="g"><span class="em">'+gi.emoji+'</span></div></div>';
  }
  g.innerHTML=h;
}
function renderAll(){
  renderTeams(); renderBoard();
  document.getElementById('terrain').style.backgroundImage='url("'+artUrl('background',S.terrainBg,1000)+'")';
  /* The description is the point. An earlier version read "Cleave — Bone",
     which pairs an action with a Fighter's name and never says what it does,
     so the legend answered the wrong question. Effect first, always. */
  /* ONE STRIP, NOT FIVE BANDS. The row version stranded the description on the
     left, threw the kit name at the right edge and orphaned the Fighter's name
     on a second line -- five of those cost ~290px under the board for five
     short phrases. The kit name is gone entirely: it was never the answer to
     "what does this do", which is the only question a legend is for. The
     Fighter's name is gone too, because its token already wears the gem. */
  function chip(colour, emoji, does, dead){
    return '<span class="lchip'+(dead?' gone':'')+'" style="--lc:'+colour+'">'
      + '<b>'+emoji+'</b>'+does+'</span>';
  }
  var lg=[0,1,2].map(function(i){
    var f=fighterForGem('mine',i);
    return f ? chip('var(--g'+i+')', f.kit.emoji, f.kit.note, f.ko) : '';
  }).join('')
  + chip('var(--g3)','🛡️','shields your team')
  + chip('var(--g4)','⚡','erupts at 10');
  document.getElementById('legend').innerHTML = lg;
  // Everything else about reach is in the header; this is the at-a-glance
  // reminder plus the one thing that changes per battle, the terrain.
  document.getElementById('reach').innerHTML=
      '<b>3</b> front · <b>4</b> mid · <b>5+</b> back · cascades multiply'
    + '<span class="terr">'+S.terrain.name+' — '+S.terrain.note+'</span>';
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

/* ---------------- input: drag to slide ----------------
   Live preview while dragging, because that IS the feel: the gem follows your
   finger along one axis and the gems it displaces shift the other way, so you
   can see the move before you commit it. Pointer events so mouse and touch are
   one code path. A slide that produces no match reverts for free and does not
   cost the turn, matching Monstrocity. */
var drag=null;
function cellSize(){
  var c=document.querySelector('.cell');
  if(!c) return 40;
  var r=c.getBoundingClientRect();
  var g=parseFloat(getComputedStyle(document.getElementById('grid')).gap)||3;
  return r.width+g;
}
function clearOffsets(){
  document.querySelectorAll('.cell').forEach(function(e){
    e.style.transition=''; e.style.transform=''; e.style.zIndex='';
  });
}
function previewDrag(){
  if(!drag||!drag.axis) return;
  var sz=drag.size, a=drag.from, ra=Math.floor(a/N), ca=a%N;
  var raw = drag.axis==='row' ? drag.dx : drag.dy;
  var maxBack = (drag.axis==='row' ? ca : ra) * sz;
  var maxFwd  = ((N-1) - (drag.axis==='row' ? ca : ra)) * sz;
  var off = Math.max(-maxBack, Math.min(maxFwd, raw));
  var steps = Math.round(off/sz);
  drag.to = drag.axis==='row' ? idx(ra, ca+steps) : idx(ra+steps, ca);

  clearOffsets();
  var lead = cellEl(a);
  if(lead){ lead.style.zIndex='6';
    lead.style.transform = drag.axis==='row' ? 'translateX('+off+'px) scale(1.06)'
                                             : 'translateY('+off+'px) scale(1.06)'; }
  // everything between shifts one cell the other way, which is what a slide is
  if(steps!==0){
    var dir = steps>0?1:-1;
    for(var k=1;k<=Math.abs(steps);k++){
      var i = drag.axis==='row' ? idx(ra, ca+dir*k) : idx(ra+dir*k, ca);
      var e = cellEl(i); if(!e) continue;
      e.style.transition='transform .08s';
      e.style.transform = drag.axis==='row' ? 'translateX('+(-dir*sz)+'px)'
                                            : 'translateY('+(-dir*sz)+'px)';
    }
  }
}
function endDrag(commit){
  if(!drag) return;
  var from=drag.from, to=drag.to;
  clearOffsets(); drag=null;
  if(!commit || to===undefined || to===from) return;
  var nb=slid(S.board,from,to);
  if(!nb) return;
  var ms=findMatches(nb);
  if(!ms.length){ logLine('sys','No match on that slide — free, try another.'); return; }
  S.lastLen=ms.reduce(function(p,g){return Math.max(p,g.len);},0);
  applySlide(from,to,'mine',function(){ endTurn(1); });
}
var gridEl=document.getElementById('grid');
gridEl.addEventListener('pointerdown',function(e){
  if(busy||S.over||S.turn!=='mine')return;
  var c=e.target.closest('.cell'); if(!c)return;
  e.preventDefault();
  gridEl.setPointerCapture&&gridEl.setPointerCapture(e.pointerId);
  drag={from:+c.getAttribute('data-i'),x0:e.clientX,y0:e.clientY,dx:0,dy:0,
        axis:null,size:cellSize(),to:undefined};
  c.classList.add('sel');
});
gridEl.addEventListener('pointermove',function(e){
  if(!drag)return;
  drag.dx=e.clientX-drag.x0; drag.dy=e.clientY-drag.y0;
  if(!drag.axis){
    if(Math.abs(drag.dx)>6&&Math.abs(drag.dx)>Math.abs(drag.dy)) drag.axis='row';
    else if(Math.abs(drag.dy)>6&&Math.abs(drag.dy)>Math.abs(drag.dx)) drag.axis='col';
  }
  if(drag.axis) previewDrag();
});
function release(e){ if(!drag)return;
  document.querySelectorAll('.cell.sel').forEach(function(x){x.classList.remove('sel');});
  endDrag(true); }
gridEl.addEventListener('pointerup',release);
gridEl.addEventListener('pointercancel',function(){ if(drag){clearOffsets();drag=null;} });
window.addEventListener('pointerup',function(e){ if(drag) release(e); });

document.getElementById('reroll').onclick=newBattle;
newBattle();
</script>
</body>
</html>
