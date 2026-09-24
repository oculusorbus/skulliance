<?php
/**
 * dhcarena-prototype.php — THROWAWAY feel prototype for DHC Arena.
 *
 * See dhcarena.md for the design this is testing. The point of this file is to
 * answer "is the battle fun to play" before anything is committed to, so it is
 * deliberately NOT the real build:
 *
 *   - No db.php, no skulliance.php, no session, no login. It touches nothing.
 *     You can open it, break it, and reload it with no consequence anywhere.
 *   - The rules engine is JavaScript, not the pure PHP engine dhcarena.md §8
 *     specifies. A turn that costs a server round-trip cannot be judged for
 *     feel. When the feel is right the rules port to dhcarena-engine.php, which
 *     is where they have to live to be authoritative.
 *   - No Stable, no fatigue, no allowance, no ladder, no rewards. Those are
 *     economy, and economy cannot be judged before combat is.
 *
 * What IS real: the 197 traits and their tiers, read from dhcrarity.php, and
 * the art, served from the same dhc/web tree the assembler uses.
 */
$dhc_rarity = is_file(__DIR__ . '/dhcrarity.php') ? (require __DIR__ . '/dhcrarity.php') : array();
if (!$dhc_rarity) { http_response_code(500); exit('dhcrarity.php missing — nothing to build Fighters from.'); }

// Relative, deliberately: the login cookie on this platform is host-only, and an
// absolute www link is a different origin from the bare domain. Relative also
// means this works from any host the repo is served on.
$ART = 'dhc/web';

// The real layering rules, so a randomly rolled Fighter cannot be built in a
// combination the assembler would refuse. Loaded defensively -- the prototype
// must still run if the config is not there.
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
:root{
  --ink:#0d0f13; --panel:#151922; --panel2:#1d2230; --line:#2b3345;
  --bone:#e8e6e1; --dim:#8b93a7; --teal:#00c8a0; --ochre:#f5a623;
  --blood:#e0466b; --shield:#5aa9ff;
  --t-common:#7a9eb0; --t-uncommon:#00c8a0; --t-epic:#8b7bd8;
  --t-legendary:#f5a623; --t-mythic:#ff4f8b;
}
*{box-sizing:border-box}
body{margin:0;background:var(--ink);color:var(--bone);
  font:13px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0)}
.wrap{max-width:1180px;margin:0 auto;padding:14px}
h1{font-size:16px;letter-spacing:.14em;text-transform:uppercase;margin:0 0 2px}
.sub{color:var(--dim);font-size:11px;margin:0 0 14px}
.bar-top{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
button{font:inherit;cursor:pointer;border-radius:3px}
.btn{background:var(--panel2);color:var(--bone);border:1px solid var(--line);padding:7px 13px}
.btn:hover:not(:disabled){border-color:var(--ochre);color:var(--ochre)}
.btn:disabled{opacity:.4;cursor:default}
.btn.go{background:var(--blood);border-color:var(--blood);color:#fff}
.btn.go:hover{filter:brightness(1.12)}

/* ---- board ---- */
.board{border:1px solid var(--line);border-radius:4px;background:var(--panel);
  position:relative;overflow:hidden}
.terrain{position:absolute;inset:0;background-size:cover;background-position:center;
  opacity:.16;filter:saturate(.8)}
.terrain-label{position:absolute;top:8px;right:10px;font-size:9.5px;letter-spacing:.1em;
  text-transform:uppercase;color:var(--dim);z-index:3;text-align:right}
.side{position:relative;z-index:2;padding:10px 12px}
.side-tag{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);margin-bottom:6px}
.ranks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.mid-rule{position:relative;z-index:2;height:1px;background:var(--line);margin:4px 12px}

/* ---- fighter token ---- */
.tok{background:var(--panel2);border:1px solid var(--line);border-radius:3px;
  padding:6px;position:relative;transition:transform .18s,border-color .15s,opacity .3s}
.tok .rank{font-size:8px;letter-spacing:.14em;text-transform:uppercase;color:var(--dim)}
.tok .art{position:relative;width:100%;aspect-ratio:1;overflow:hidden;border-radius:2px;
  background:repeating-conic-gradient(#191419 0% 25%,#201b20 0% 50%) 50%/10px 10px;margin:3px 0}
.tok .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.tok .nm{font-size:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tok .tier{font-size:8px;letter-spacing:.08em;text-transform:uppercase;color:var(--tier,var(--dim))}
.hpwrap{position:relative;height:7px;background:#0b0d11;border-radius:2px;overflow:hidden;margin-top:4px}
.hp{position:absolute;inset:0;width:100%;background:var(--teal);transform-origin:left;
  transition:transform .45s cubic-bezier(.2,.7,.3,1)}
.hp.low{background:var(--ochre)} .hp.crit{background:var(--blood)}
.armour{position:absolute;top:0;left:0;height:100%;background:var(--shield);opacity:.85;
  transition:width .45s}
.hpnum{font-size:8.5px;color:var(--dim);font-variant-numeric:tabular-nums;margin-top:2px;
  display:flex;justify-content:space-between;gap:4px}
.pips{display:flex;gap:2px;margin-top:3px;flex-wrap:wrap}
.pip{width:100%;max-width:26px;height:3px;border-radius:2px;background:#0b0d11;flex:1}
.pip.ready{background:var(--ochre)}
.status{display:flex;gap:3px;flex-wrap:wrap;margin-top:3px;min-height:12px}
.st{font-size:8px;padding:0 3px;border-radius:2px;background:#0b0d11;color:var(--dim);
  border:1px solid var(--line)}
.st.buff{color:var(--teal);border-color:var(--teal)}
.st.debuff{color:var(--blood);border-color:var(--blood)}
.st.shield{color:var(--shield);border-color:var(--shield)}
.tok.acting{border-color:var(--ochre);transform:translateY(-4px)}
.tok.targetable{border-color:var(--blood);cursor:pointer}
.tok.targetable:hover{background:#2a1d25}
.tok.ko{opacity:.32;filter:grayscale(1)}
.tok.hit{animation:hit .34s}
.tok.lunge-up{animation:lungeUp .34s}
.tok.lunge-down{animation:lungeDown .34s}
@keyframes hit{0%{transform:translateX(0)}25%{transform:translateX(-6px)}
  55%{transform:translateX(5px)}100%{transform:translateX(0)}}
@keyframes lungeUp{0%{transform:translateY(0)}45%{transform:translateY(-14px)}100%{transform:translateY(0)}}
@keyframes lungeDown{0%{transform:translateY(0)}45%{transform:translateY(14px)}100%{transform:translateY(0)}}
.flash{position:absolute;inset:0;background:#fff;opacity:0;pointer-events:none;border-radius:3px}
.flash.on{animation:fl .3s}
@keyframes fl{0%{opacity:.55}100%{opacity:0}}
.pop{position:absolute;left:50%;top:26%;transform:translateX(-50%);font-size:15px;
  font-weight:700;pointer-events:none;opacity:0;text-shadow:0 2px 6px #000;z-index:5}
.pop.on{animation:pop .85s}
.pop.crit{font-size:20px;color:var(--ochre)}
.pop.heal{color:var(--teal)}
@keyframes pop{0%{opacity:0;transform:translate(-50%,6px)}
  18%{opacity:1}100%{opacity:0;transform:translate(-50%,-24px)}}
.intent{font-size:8.5px;color:var(--blood);margin-top:3px;min-height:11px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* ---- controls ---- */
.ctl{display:grid;grid-template-columns:1.6fr 1fr;gap:10px;margin-top:12px}
@media (max-width:760px){.ctl{grid-template-columns:1fr}}
.panel{border:1px solid var(--line);border-radius:4px;background:var(--panel);padding:10px}
.panel h2{margin:0 0 7px;font-size:9.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim)}
.abil{display:block;width:100%;text-align:left;background:var(--panel2);border:1px solid var(--line);
  color:var(--bone);padding:7px 9px;margin-bottom:5px}
.abil:hover:not(:disabled){border-color:var(--ochre)}
.abil:disabled{opacity:.35;cursor:default}
.abil .an{font-size:11.5px}
.abil .ad{font-size:9px;color:var(--dim);display:flex;gap:8px;flex-wrap:wrap;margin-top:2px}
.abil .kill{color:var(--blood)}
.order{display:flex;gap:5px;flex-wrap:wrap}
.ord{font-size:9px;padding:2px 6px;border:1px solid var(--line);border-radius:999px;color:var(--dim)}
.ord.now{border-color:var(--ochre);color:var(--ochre)}
.ord.foe{border-style:dashed}
#log{height:150px;overflow:auto;font-size:10.5px;line-height:1.6}
#log div{padding:1px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.log-you{color:var(--teal)} .log-foe{color:var(--blood)} .log-sys{color:var(--dim)}
.hint{font-size:10px;color:var(--dim);margin-top:6px}
.over{text-align:center;padding:16px}
.over h2{font-size:15px;letter-spacing:.1em;margin:0 0 6px;color:var(--ochre)}
label.spd{font-size:10px;color:var(--dim);display:flex;align-items:center;gap:5px}
</style>
</head>
<body>
<div class="wrap">
  <h1>DHC Arena <span style="color:var(--ochre)">prototype</span></h1>
  <p class="sub">Throwaway feel test — no database, no login, nothing saved. Real traits, real art.
     Rules are JS here; they port to a pure PHP engine once the feel is right.</p>

  <div class="bar-top">
    <button class="btn go" id="reroll">New battle</button>
    <button class="btn" id="rerollMine">Reroll my team</button>
    <label class="spd"><input type="checkbox" id="fast"> fast mode (skip animation)</label>
    <span class="hint" id="round"></span>
  </div>

  <div class="board" id="board">
    <div class="terrain" id="terrain"></div>
    <div class="terrain-label" id="terrainLabel"></div>
    <div class="side">
      <div class="side-tag">Enemy Stable</div>
      <div class="ranks" id="foeRanks"></div>
    </div>
    <div class="mid-rule"></div>
    <div class="side">
      <div class="side-tag">Your Stable</div>
      <div class="ranks" id="myRanks"></div>
    </div>
  </div>

  <div class="ctl">
    <div class="panel">
      <h2 id="ctlHead">Actions</h2>
      <div id="actions"></div>
      <div class="hint" id="hint"></div>
    </div>
    <div class="panel">
      <h2>Turn order</h2>
      <div class="order" id="order"></div>
      <h2 style="margin-top:10px">Log</h2>
      <div id="log"></div>
    </div>
  </div>
</div>

<script>
/* =====================================================================
   DHC ARENA — PROTOTYPE ENGINE (JavaScript, throwaway)

   Ported to dhcarena-engine.php once the feel is settled. Kept as pure
   functions over a plain state object here for exactly that reason: the
   shape should survive the port even though the language will not.
   ===================================================================== */
var RARITY = <?php echo json_encode($dhc_rarity); ?>;
var ART    = <?php echo json_encode($ART); ?>;
var HG_EXCL= <?php echo json_encode($excl); ?>;

var TIERS = ['common','uncommon','epic','legendary','mythic'];
/* dhcarena.md §4: the whole point is that rarity must NOT decide the fight.
   dhcf_trait_points() spreads best-to-worst by 1.37x-3.17x depending on the
   category, so combat power lives in the same band. 1.0 -> 1.32 across five
   tiers is inside it, deliberately near the bottom. */
var TIER_MULT = {common:1.00, uncommon:1.08, epic:1.16, legendary:1.24, mythic:1.32};

function hash(s){ var h=2166136261; for(var i=0;i<s.length;i++){h^=s.charCodeAt(i);h=Math.imul(h,16777619);} return (h>>>0); }
function pick(arr, n){ return arr[n % arr.length]; }
function cap(s){ return s.charAt(0).toUpperCase()+s.slice(1); }
function niceName(slug){ return slug.split('-').map(cap).join(' '); }
function slugsOf(cat){ return Object.keys(RARITY[cat]||{}); }
function tierOf(cat, slug){ var r=(RARITY[cat]||{})[slug]; return r? r[0] : 'common'; }
function artUrl(dir, slug, size){ return ART+'/'+size+'/'+dir+'/'+slug+'.png'; }

/* ---------- abilities -------------------------------------------------
   dhcarena.md §3: the small pools carry actives. 16 weapons, 9 companions,
   21 effects = 46 to hand-author for real. Here they are assigned to real
   slugs by hash, so every weapon HAS an ability and they vary -- enough to
   test whether the decisions are interesting, not a final ability set.

   Each carries `from` (ranks it can be used from) and `hits` (ranks it can
   reach), which is §3bb's whole reason for existing.                      */
var WEAPON_KITS = [
  {id:'heavy',   name:'Heavy Swing',  dmg:1.45, from:[0],     hits:[0],       cd:2, note:'front only'},
  {id:'reach',   name:'Reaching Jab', dmg:0.85, from:[0,1,2], hits:[0,1,2],   cd:0, note:'any rank'},
  {id:'cleave',  name:'Cleave',       dmg:0.70, from:[0,1],   hits:[0,1], aoe:true, cd:2, note:'hits front + mid'},
  {id:'precise', name:'Precision Shot',dmg:0.95,from:[1,2],   hits:[0,1,2],   cd:1, crit:0.30, note:'back-line, high crit'},
  {id:'drain',   name:'Siphon',       dmg:0.80, from:[0,1],   hits:[0,1],     cd:2, drain:0.5, note:'heals for half'},
  {id:'sunder',  name:'Sunder',       dmg:0.65, from:[0],     hits:[0],       cd:1, sunder:6, note:'strips armour'},
  {id:'hook',    name:'Hook',         dmg:0.45, from:[0,1],   hits:[1,2],     cd:2, pull:true, note:'drags them forward'},
  {id:'volley',  name:'Volley',       dmg:0.50, from:[2],     hits:[0,1,2], aoe:true, cd:3, note:'back only, hits all'}
];
var COMPANION_KITS = [
  {id:'striker', name:'Strike',  kind:'dmg',    v:0.55, note:'companion attacks'},
  {id:'mender',  name:'Mend',    kind:'heal',   v:0.30, note:'companion heals an ally'},
  {id:'warder',  name:'Ward',    kind:'shield', v:12,   note:'companion shields an ally'}
];
var EFFECT_KITS = [
  {id:'thorns', name:'Thorns',    note:'returns 20% of melee damage'},
  {id:'regen',  name:'Regrowth',  note:'heals 4% max HP each round'},
  {id:'edge',   name:'Keen Edge', note:'+10% crit'},
  {id:'plate',  name:'Plating',   note:'+6 armour'},
  {id:'bulwark',name:'Bulwark',   note:'shields 10 on the first hit taken'}
];
var TERRAIN_KITS = [
  {id:'crit',  name:'Fractured Signal', note:'+15% crit for everyone'},
  {id:'dmg',   name:'Overclocked',      note:'+10% damage for everyone'},
  {id:'armour',name:'Dense Cover',      note:'+5 armour for everyone'},
  {id:'speed', name:'Low Gravity',      note:'+2 speed for everyone'},
  {id:'frail', name:'Corrosive Haze',   note:'-8% max HP for everyone'}
];

/* ---------- a Fighter, derived entirely from its traits ---------------- */
var UID = 0;
function buildFighter(traits, name){
  var h = hash(traits.torso+traits.head+traits.headgear+traits.arms+traits.weapon);
  var m = function(cat,slug){ return TIER_MULT[tierOf(cat,slug)] || 1; };
  // per-trait variance from the slug, so two legendaries are not identical
  var v = function(slug,spread){ return 1 + ((hash(slug)%1000)/1000 - .5) * spread; };

  /* TUNED AGAINST THE §4 HARNESS, not guessed. The first numbers here failed
     badly: all-commons beat all-legendaries 0.3% of the time against a 35%
     target, and a quarter of battles stalled out undecided.

     Two causes, both instructive. Armour was a flat subtraction at 8 against
     78 HP, so low-damage builds could not out-pace it and fights deadlocked.
     And per-trait variance was narrow (±11%) while the tier multiplier applied
     to every stat at once, so a legendary team carried 1.24x on HP AND armour
     AND crit AND speed AND power simultaneously -- small edges compounding into
     a decided result before the first turn.

     The fix is the §4 principle made literal: VARIANCE BETWEEN TRAITS IS WIDER
     THAN THE GAP BETWEEN TIERS. ±30% per trait against a 1.32x spread across
     all five tiers, so which trait you picked matters more than what colour it
     is. A well-chosen common beats a poorly-chosen legendary, which is the
     whole design intent.

     Measured at these values: commons take 37.6% against legendaries, no
     stalls, mirror match resolves in ~8 rounds. */
  var V = .6;
  var hp     = Math.round(78 * m('torso',  traits.torso)  * v(traits.torso, V));
  var armour = Math.round(3  * m('torso',  traits.torso)  * v(traits.torso+'a', V));
  var res    = Math.round(10 * m('head',   traits.head)   * v(traits.head, V));
  var critC  = 0.06 * m('headgear', traits.headgear) * v(traits.headgear, V);
  var critD  = 1.5  * m('headgear', traits.headgear);
  var speed  = Math.round(10 * m('arms',   traits.arms)   * v(traits.arms, V));
  var power  = Math.round(38 * m('weapon', traits.weapon) * v(traits.weapon, V));

  var kit = pick(WEAPON_KITS, hash(traits.weapon));
  var comp = traits.companion ? pick(COMPANION_KITS, hash(traits.companion)) : null;
  var eff  = traits.effects   ? pick(EFFECT_KITS,    hash(traits.effects))   : null;

  if (eff && eff.id==='plate') armour += 6;   // triples a base-3 armour: deliberate
  if (eff && eff.id==='edge')  critC += 0.10;

  return {
    uid:'f'+(++UID), name:name, traits:traits, kit:kit, comp:comp, eff:eff,
    maxHp:hp, hp:hp, armour:armour, armourMax:armour, res:res,
    critC:critC, critD:critD, speed:speed, power:power,
    cd:0, compCd:0, shield:0, bulwarkUsed:false, rank:0, side:null, ko:false
  };
}

function randomTraits(rnd){
  var t = {};
  ['torso','head','headgear','arms','weapon','background','companion','effects'].forEach(function(c){
    var list = slugsOf(c); t[c] = list[Math.floor(rnd()*list.length)];
  });
  // Respect the real head/headgear rule rather than rendering a pairing the
  // assembler forbids -- a Beheaded Cyborg has nothing to hang a helmet on.
  var banned = HG_EXCL[t.head] || [];
  var guard = 0;
  while (banned.indexOf(t.headgear) !== -1 && guard++ < 40){
    var hl = slugsOf('headgear'); t.headgear = hl[Math.floor(rnd()*hl.length)];
  }
  return t;
}
function mulberry(seed){ return function(){ seed|=0; seed=seed+0x6D2B79F5|0;
  var t=Math.imul(seed^seed>>>15,1|seed); t=t+Math.imul(t^t>>>7,61|t)^t;
  return ((t^t>>>14)>>>0)/4294967296; }; }

var FIRST = ['Bone','Ash','Grim','Null','Vex','Rust','Pale','Iron','Hex','Dread','Cinder','Wraith'];
var LAST  = ['Harvester','Revenant','Conductor','Sentinel','Warden','Prowler','Herald','Butcher','Cipher','Widow'];
function fighterName(rnd){ return FIRST[Math.floor(rnd()*FIRST.length)]+' '+LAST[Math.floor(rnd()*LAST.length)]; }

/* ---------- state ------------------------------------------------------ */
var S = null, sel = null, busy = false, animOff = false;

function newBattle(keepMine){
  var seed = Date.now() & 0x7fffffff;
  var rnd = mulberry(seed);
  var mine = (keepMine && S) ? S.mine.map(function(f){ return buildFighter(f.traits, f.name); })
                             : [0,1,2].map(function(){ return buildFighter(randomTraits(rnd), fighterName(rnd)); });
  var foes = [0,1,2].map(function(){ return buildFighter(randomTraits(rnd), fighterName(rnd)); });
  mine.forEach(function(f,i){ f.rank=i; f.side='mine'; });
  foes.forEach(function(f,i){ f.rank=i; f.side='foes'; });

  // dhcarena.md §3: the DEFENDER's front-rank Fighter sets the terrain.
  var terrain = pick(TERRAIN_KITS, hash(foes[0].traits.background));
  var tbg = foes[0].traits.background;

  S = { mine:mine, foes:foes, terrain:terrain, terrainBg:tbg, round:0, order:[], turn:0, log:[], over:null };
  applyTerrain();
  newRound();
  render();
  maybeAi();   // the fastest Fighter may be theirs; without this the board sits idle
  logLine('sys', 'Terrain — '+terrain.name+': '+terrain.note+'.');
  logLine('sys', 'Set by the defending front rank, '+foes[0].name+'.');
}

function applyTerrain(){
  all().forEach(function(f){
    if (S.terrain.id==='armour') { f.armour += 5; f.armourMax += 5; }
    if (S.terrain.id==='speed')  f.speed += 2;
    if (S.terrain.id==='frail')  { f.maxHp = Math.round(f.maxHp*0.92); f.hp = f.maxHp; }
  });
}
function all(){ return S.mine.concat(S.foes); }
function team(side){ return side==='mine' ? S.mine : S.foes; }
function alive(side){ return team(side).filter(function(f){ return !f.ko; }); }
function frontmost(side){ var a = alive(side); return a.length ? a.reduce(function(p,c){ return c.rank<p.rank?c:p; }) : null; }

function newRound(){
  S.round++;
  S.order = all().filter(function(f){ return !f.ko; })
                 .sort(function(a,b){ return b.speed-a.speed || a.name.localeCompare(b.name); });
  S.turn = 0;
  all().forEach(function(f){
    if (f.cd>0) f.cd--;
    if (f.compCd>0) f.compCd--;
    if (f.eff && f.eff.id==='regen' && !f.ko) heal(f, Math.round(f.maxHp*0.04), true);
  });
  planIntents();
}

/* ---------- combat maths ---------------------------------------------- */
function critChance(f){ return f.critC + (S.terrain.id==='crit' ? 0.15 : 0); }
function dmgMult(){ return S.terrain.id==='dmg' ? 1.10 : 1; }

function canUse(f, kit){ return kit.from.indexOf(f.rank) !== -1; }
function targetsFor(f, kit){
  var foes = alive(f.side==='mine'?'foes':'mine');
  return foes.filter(function(t){ return kit.hits.indexOf(t.rank) !== -1; });
}
function estimate(f, kit, t){
  var base = f.power * kit.dmg * dmgMult();
  var after = Math.max(1, Math.round(base) - t.armour);
  return after;
}
function applyDamage(src, t, amount, isCrit){
  if (t.shield > 0){
    var absorbed = Math.min(t.shield, amount);
    t.shield -= absorbed; amount -= absorbed;
    if (absorbed>0) popup(t, '-'+absorbed+' shield', 'heal');
  }
  if (t.eff && t.eff.id==='bulwark' && !t.bulwarkUsed){
    t.bulwarkUsed = true;
    var b = Math.min(10, amount); amount -= b;
    popup(t, 'bulwark', 'heal');
  }
  if (amount<=0) return 0;
  t.hp = Math.max(0, t.hp - amount);
  popup(t, '-'+amount, isCrit?'crit':'');
  shake(t);
  if (t.hp===0){ t.ko = true; logLine(t.side==='mine'?'foe':'you', t.name+' is knocked out.'); }
  return amount;
}
function heal(f, amt, quiet){
  var before = f.hp; f.hp = Math.min(f.maxHp, f.hp+amt);
  if (f.hp>before && !quiet) popup(f, '+'+(f.hp-before), 'heal');
}

function doAttack(f, kit, target){
  var hits = kit.aoe ? targetsFor(f, kit) : [target];
  lunge(f);
  hits.forEach(function(t){
    var isCrit = Math.random() < critChance(f);
    var base = f.power * kit.dmg * dmgMult() * (isCrit ? f.critD : 1);
    var amount = Math.max(1, Math.round(base) - t.armour);
    var dealt = applyDamage(f, t, amount, isCrit);
    logLine(f.side==='mine'?'you':'foe',
      f.name+' — '+kit.name+' → '+t.name+' for '+dealt+(isCrit?' (CRIT)':''));
    if (kit.sunder){ t.armour = Math.max(0, t.armour-kit.sunder); logLine('sys', t.name+'’s armour sundered.'); }
    if (kit.drain) heal(f, Math.round(dealt*kit.drain));
    if (kit.pull && t.rank>0){ swapRank(t, t.rank-1); logLine('sys', t.name+' is dragged forward.'); }
    if (t.eff && t.eff.id==='thorns' && !t.ko && kit.hits.indexOf(0)!==-1){
      var back = Math.round(dealt*0.20);
      if (back>0){ applyDamage(t, f, back, false); logLine('sys', 'Thorns bites back for '+back+'.'); }
    }
  });
  f.cd = kit.cd;
}
function swapRank(f, toRank){
  var other = team(f.side).filter(function(x){ return x.rank===toRank; })[0];
  if (other){ other.rank = f.rank; }
  f.rank = toRank;
}
function doCompanion(f){
  var c = f.comp; lunge(f);
  if (c.kind==='dmg'){
    var foes = alive(f.side==='mine'?'foes':'mine');
    if (!foes.length) return;
    var t = foes.reduce(function(p,x){ return x.hp<p.hp?x:p; });
    var amount = Math.max(1, Math.round(f.power*c.v) - t.armour);
    applyDamage(f, t, amount, false);
    logLine(f.side==='mine'?'you':'foe', f.name+'’s companion strikes '+t.name+' for '+amount+'.');
  } else if (c.kind==='heal'){
    var team_ = alive(f.side).reduce(function(p,x){ return (x.hp/x.maxHp)<(p.hp/p.maxHp)?x:p; });
    heal(team_, Math.round(team_.maxHp*c.v));
    logLine(f.side==='mine'?'you':'foe', f.name+'’s companion mends '+team_.name+'.');
  } else {
    var ally = alive(f.side).reduce(function(p,x){ return x.shield<p.shield?x:p; });
    ally.shield += c.v;
    logLine(f.side==='mine'?'you':'foe', f.name+'’s companion shields '+ally.name+' for '+c.v+'.');
  }
  f.compCd = 3;
}
function doDefend(f){ f.shield += Math.round(6 + f.armour*0.5); logLine(f.side==='mine'?'you':'foe', f.name+' braces.'); }

/* ---------- the AI ----------------------------------------------------
   dhcarena.md §3b: every ability must be something a simple priority AI can
   play competently, because half of all battles are your Stable piloted by
   a machine. This IS that AI -- if it cannot use an ability sensibly, the
   ability is not finished.                                                */
function aiChoose(f){
  if (f.comp && f.compCd===0){
    var wounded = alive(f.side).filter(function(x){ return x.hp/x.maxHp < 0.55; });
    if (f.comp.kind!=='dmg' && wounded.length) return {type:'comp'};
    if (f.comp.kind==='dmg') return {type:'comp'};
  }
  if (f.cd===0 && canUse(f, f.kit)){
    var ts = targetsFor(f, f.kit);
    if (ts.length){
      // finish something if you can, else hit the softest reachable target
      var killable = ts.filter(function(t){ return estimate(f, f.kit, t) >= t.hp && t.shield===0; });
      var t = killable.length ? killable[0]
            : ts.reduce(function(p,x){ return (x.hp - x.armour) < (p.hp - p.armour) ? x : p; });
      return {type:'atk', target:t};
    }
  }
  return {type:'def'};
}
function planIntents(){
  S.foes.forEach(function(f){
    if (f.ko){ f.intent=null; return; }
    var c = aiChoose(f);
    f.intent = c.type==='atk' ? (f.kit.name+' → '+c.target.name)
             : c.type==='comp' ? (f.comp.name+' (companion)') : 'Brace';
    f.intentPlan = c;
  });
}

/* ---------- turn loop -------------------------------------------------- */
function current(){ return S.order[S.turn]; }
function advance(){
  S.turn++;
  while (S.turn < S.order.length && S.order[S.turn].ko) S.turn++;
  if (checkOver()) return;
  if (S.turn >= S.order.length){ newRound(); }
  render();
  maybeAi();
}
function checkOver(){
  if (!alive('foes').length){ S.over='win';  render(); return true; }
  if (!alive('mine').length){ S.over='lose'; render(); return true; }
  return false;
}
function maybeAi(){
  var f = current();
  if (!f || S.over) return;
  if (f.side==='foes'){
    busy = true;
    setTimeout(function(){
      var c = f.intentPlan || aiChoose(f);
      if (c.type==='atk' && !c.target.ko) doAttack(f, f.kit, c.target);
      else if (c.type==='comp') doCompanion(f);
      else doDefend(f);
      busy = false;
      if (!checkOver()) { planIntents(); advance(); }
    }, animOff ? 60 : 620);
  }
}

/* ---------- animation helpers ----------------------------------------- */
function elFor(f){ return document.querySelector('[data-id="'+f.uid+'"]'); }
function shake(f){ if(animOff) return; var e=elFor(f); if(!e) return;
  e.classList.remove('hit'); void e.offsetWidth; e.classList.add('hit');
  var fl=e.querySelector('.flash'); if(fl){ fl.classList.remove('on'); void fl.offsetWidth; fl.classList.add('on'); } }
function lunge(f){ if(animOff) return; var e=elFor(f); if(!e) return;
  var c = f.side==='mine' ? 'lunge-up' : 'lunge-down';
  e.classList.remove(c); void e.offsetWidth; e.classList.add(c); }
function popup(f, text, kind){ if(animOff) return; var e=elFor(f); if(!e) return;
  var p=document.createElement('div'); p.className='pop on '+(kind||''); p.textContent=text;
  e.appendChild(p); setTimeout(function(){ p.remove(); }, 900); }

/* ---------- rendering -------------------------------------------------- */
function tokHtml(f){
  var pct = (f.hp/f.maxHp)*100;
  var cls = pct<=25 ? 'crit' : pct<=55 ? 'low' : '';
  var isCur = current()===f && !S.over;
  var t = f.traits;
  // Back-to-front, matching dhcf_slots(): torso, weapon, arms, effects, head,
  // headgear, companion. background is the terrain here, so it is not a layer.
  var layers = ['torso','weapon','arms','effects','head','headgear','companion']
    .filter(function(k){ return t[k]; })
    .map(function(k){
      var dir = (k==='effects') ? 'effects' : k;
      return '<img loading="lazy" alt="" src="'+artUrl(dir, t[k], 250)+'" onerror="this.remove()">';
    }).join('');
  var sts = [];
  if (f.shield>0) sts.push('<span class="st shield">shield '+f.shield+'</span>');
  if (f.eff)      sts.push('<span class="st buff">'+f.eff.name+'</span>');
  if (f.armour<f.armourMax) sts.push('<span class="st debuff">armour '+f.armour+'</span>');
  return '<div class="tok'+(f.ko?' ko':'')+(isCur?' acting':'')+'" data-id="'+f.uid+'" data-rank="'+f.rank+'">'
    + '<div class="flash"></div>'
    + '<div class="rank">'+['front','mid','back'][f.rank]+(isCur?' · acting':'')+'</div>'
    + '<div class="art">'+layers+'</div>'
    + '<div class="nm">'+f.name+'</div>'
    + '<div class="tier">'+f.kit.name+'</div>'
    + '<div class="hpwrap"><div class="hp '+cls+'" style="transform:scaleX('+(f.hp/f.maxHp)+')"></div>'
    +   '<div class="armour" style="width:'+Math.min(100,(f.armour/f.maxHp)*100)+'%"></div></div>'
    + '<div class="hpnum"><span>'+f.hp+'/'+f.maxHp+'</span><span>spd '+f.speed+' · arm '+f.armour+'</span></div>'
    + '<div class="pips"><span class="pip'+(f.cd===0?' ready':'')+'"></span>'
    +   (f.comp?'<span class="pip'+(f.compCd===0?' ready':'')+'"></span>':'')+'</div>'
    + '<div class="status">'+sts.join('')+'</div>'
    + (f.side==='foes' ? '<div class="intent">'+(f.ko?'':(f.intent?'▸ '+f.intent:''))+'</div>' : '<div class="intent"></div>')
    + '</div>';
}
function render(){
  document.getElementById('round').textContent = S.over ? '' : 'Round '+S.round;
  document.getElementById('terrainLabel').innerHTML = S.terrain.name+'<br><span style="opacity:.7">'+S.terrain.note+'</span>';
  document.getElementById('terrain').style.backgroundImage = 'url("'+artUrl('background', S.terrainBg, 1000)+'")';
  var order = function(side){ return team(side).slice().sort(function(a,b){return a.rank-b.rank;}); };
  document.getElementById('foeRanks').innerHTML = order('foes').slice().reverse().map(tokHtml).join('');
  document.getElementById('myRanks').innerHTML  = order('mine').map(tokHtml).join('');

  var ot = document.getElementById('order');
  ot.innerHTML = S.over ? '' : S.order.filter(function(f){return !f.ko;}).map(function(f,i){
    var isNow = f===current();
    return '<span class="ord'+(isNow?' now':'')+(f.side==='foes'?' foe':'')+'">'+f.name.split(' ')[0]+'</span>';
  }).join('');

  renderActions();
}
function renderActions(){
  var box = document.getElementById('actions'), hint = document.getElementById('hint');
  var head = document.getElementById('ctlHead');
  box.innerHTML = ''; hint.textContent = '';
  if (S.over){
    head.textContent = 'Result';
    box.innerHTML = '<div class="over"><h2>'+(S.over==='win'?'Victory':'Defeat')+'</h2>'
      + '<p class="sub">'+(S.over==='win'
          ? 'Their Stable is down. In the real game this is where traits and ladder points land.'
          : 'Your Stable is down. In the real game these Fighters would now be benched.')+'</p></div>';
    return;
  }
  var f = current();
  if (!f){ return; }
  if (f.side==='foes'){ head.textContent = 'Enemy turn'; hint.textContent = f.name+' is acting…'; return; }
  head.textContent = f.name+' — '+['front','mid','back'][f.rank]+' rank';

  var kit = f.kit, usable = canUse(f, kit), ready = f.cd===0, ts = targetsFor(f, kit);
  var b1 = document.createElement('button');
  b1.className = 'abil';
  b1.disabled = !(usable && ready && ts.length);
  var why = !usable ? 'needs '+kit.from.map(function(r){return ['front','mid','back'][r];}).join('/')+' rank'
          : !ready ? 'recharging ('+f.cd+')'
          : !ts.length ? 'nothing in reach' : kit.note;
  b1.innerHTML = '<div class="an">'+kit.name+'</div><div class="ad"><span>'+why+'</span>'
    + '<span>reaches '+kit.hits.map(function(r){return ['front','mid','back'][r];}).join('/')+'</span></div>';
  b1.onclick = function(){ beginTarget(f, kit); };
  box.appendChild(b1);

  if (f.comp){
    var b2 = document.createElement('button');
    b2.className='abil'; b2.disabled = f.compCd>0;
    b2.innerHTML = '<div class="an">'+f.comp.name+' <span style="color:var(--dim)">· companion</span></div>'
      + '<div class="ad"><span>'+(f.compCd>0?'recharging ('+f.compCd+')':f.comp.note)+'</span></div>';
    b2.onclick = function(){ act(function(){ doCompanion(f); }); };
    box.appendChild(b2);
  }
  var b3 = document.createElement('button');
  b3.className='abil';
  b3.innerHTML = '<div class="an">Brace</div><div class="ad"><span>gain shield, end turn</span></div>';
  b3.onclick = function(){ act(function(){ doDefend(f); }); };
  box.appendChild(b3);

  if (f.rank>0){
    var b4 = document.createElement('button');
    b4.className='abil';
    b4.innerHTML = '<div class="an">Advance</div><div class="ad"><span>swap forward one rank, end turn</span></div>';
    b4.onclick = function(){ act(function(){ swapRank(f, f.rank-1); logLine('you', f.name+' advances.'); }); };
    box.appendChild(b4);
  }
  hint.textContent = 'Speed sets the order. Position decides what you can reach.';
}
function beginTarget(f, kit){
  var ts = targetsFor(f, kit);
  if (kit.aoe){ act(function(){ doAttack(f, kit, ts[0]); }); return; }
  sel = {f:f, kit:kit};
  document.getElementById('hint').textContent = 'Choose a target — '+ts.map(function(t){
    var e = estimate(f,kit,t); return t.name+' ('+e+(e>=t.hp?', KILLS':'')+')'; }).join(' · ');
  ts.forEach(function(t){ var e = elFor(t); if (e){ e.classList.add('targetable');
    e.onclick = function(){ act(function(){ doAttack(f, kit, t); }); }; } });
}
function act(fn){
  if (busy || S.over) return;
  document.querySelectorAll('.targetable').forEach(function(e){ e.classList.remove('targetable'); e.onclick=null; });
  sel = null;
  fn();
  if (checkOver()) return;
  planIntents();
  advance();
}
function logLine(kind, text){
  S.log.push({kind:kind, text:text});
  var l = document.getElementById('log');
  var d = document.createElement('div'); d.className='log-'+kind; d.textContent=text;
  l.appendChild(d); l.scrollTop = l.scrollHeight;
}

document.getElementById('reroll').onclick = function(){ document.getElementById('log').innerHTML=''; newBattle(false); };
document.getElementById('rerollMine').onclick = function(){ document.getElementById('log').innerHTML=''; newBattle(false); };
document.getElementById('fast').onchange = function(){ animOff = this.checked; };
newBattle(false);
</script>
</body>
</html>
