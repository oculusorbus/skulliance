<?php
/*
 * REALM GUARDIANS -- prototype.
 *
 * This build exists to answer ONE question: is keeping locations stocked under a
 * wave clock actually fun? Everything else in realm-guardians.md is cheap to add
 * afterwards and expensive to tune blind. Obscura was built the same way and it
 * was the right call.
 *
 * ---------------------------------------------------------------------------
 * IT CANNOT AFFECT REALMS OR RAIDS. THAT IS STRUCTURAL, NOT A PROMISE.
 * ---------------------------------------------------------------------------
 *   - NO WRITES ANYWHERE. Not one INSERT, UPDATE or DELETE, and no migration.
 *     Location levels and army size are hardcoded below.
 *   - The only query is a READ of public avatars from `users` (see the horde
 *     below). It touches none of realms, realms_locations, locations, soldiers,
 *     weapons or raids*.
 *   - db.php is not edited. Nothing in the realms/raids code path is touched.
 *   - Nothing links here. No nav entry, no hub card, no leaderboard.
 *   - No CARBON, no rewards, no writes of any kind anywhere.
 *
 * When this DOES read a real realm (build order step 6), it reads a SNAPSHOT and
 * plays with copies -- a soldier dying here must never kill the real one. See
 * realm-guardians.md.
 *
 * ---------------------------------------------------------------------------
 * WHY THE SIMULATION IS DETERMINISTIC EVEN THOUGH NOTHING IS SUBMITTED YET
 * ---------------------------------------------------------------------------
 * Fixed timestep, seeded PRNG, no Math.random anywhere. Nothing here needs that
 * today. But the real version has to accept a result on a board that pays
 * CARBON, and the only honest way to do that is to replay the player's inputs
 * server-side and derive the outcome rather than trusting a reported one. That
 * requires the same simulation to produce the same result from the same seed.
 * Building it non-deterministically now would mean throwing it away later --
 * this is the Skull Racer ghost-trace lesson, applied before it bites.
 */
include 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';

/*
 * THE HORDE IS OTHER STAKERS.
 *
 * Each attacker wears a real member's avatar. It costs nothing -- avatars are
 * already public on every leaderboard podium and profile, so this exposes
 * nothing new -- and it turns an anonymous wave into people you know. Being
 * overrun by names from your own Discord is a far better story than being
 * overrun by red squares, and raids already cast members as each other's
 * attackers, so the fiction is consistent with what Realms does today.
 *
 * READ ONLY, and only the `users` table. Same construction and same
 * icons/skull.png fallback as renderPodium() in leaderboards.php:302-304.
 *
 * The current player is excluded -- you are defending, you should not be in the
 * horde attacking yourself.
 *
 * Purely COSMETIC: avatars are assigned at render time by foe index and never
 * enter the simulation, so the seeded run stays reproducible regardless of who
 * happens to be drawn.
 */
$rg_me = intval($_SESSION['userData']['user_id'] ?? 0);
$rg_horde = array();
$rg_r = $conn->query("SELECT username, discord_id, avatar FROM users
                      WHERE discord_id != '' AND avatar != '' AND id != " . $rg_me . "
                      ORDER BY RAND() LIMIT 40");
if ($rg_r) {
	while ($rg_u = $rg_r->fetch_assoc()) {
		$rg_horde[] = array(
			'name' => $rg_u['username'],
			'img'  => 'https://cdn.discordapp.com/avatars/' . $rg_u['discord_id'] . '/' . $rg_u['avatar'] . '.png',
		);
	}
}
?>

<div class="row" id="row1">
  <div class="col1of3" style="max-width:760px;margin:0 auto;flex:1 1 100%;">

	<h2 class="rg-intro">Realm Guardians <span class="rg-tag">prototype</span></h2>
	<div class="rg-blurb rg-intro">Hold the wall. Keep the Tower manned, the Armory
		stocked and the dead moving back out of the Crypt &mdash; the horde does not wait.
		<br><em>Hardcoded levels, nothing saved, nothing rewarded. This is here to find out if it's fun.</em></div>

	<div id="rg-game">

		<div class="rg-hud">
			<span>Wave <strong id="rg-wave">0</strong></span>
			<span>Tower <strong id="rg-hp">100</strong></span>
			<span>CARBON <strong id="rg-carbon">0</strong></span>
			<span id="rg-status">Press Begin</span>
		</div>

		<!-- The approach. Enemies march right to left toward the wall. -->
		<div id="rg-field">
			<div id="rg-wall"></div>
			<div id="rg-enemies"></div>
		</div>

		<div id="rg-locations">
			<div class="rg-loc" data-loc="tower">
				<div class="rg-loc-name">Tower <span class="rg-lvl" id="rg-lvl-tower">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-garrison">0</strong> / <span id="rg-garrison-cap">4</span> garrison</div>
				<button type="button" class="rg-act" data-act="deploy">Deploy</button>
				<button type="button" class="rg-act rg-up" data-act="up-tower">Upgrade</button>
			</div>
			<div class="rg-loc" data-loc="barracks">
				<div class="rg-loc-name">Barracks <span class="rg-lvl" id="rg-lvl-barracks">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-reserve">3</strong> in reserve</div>
				<div class="rg-bar"><i id="rg-bar-barracks"></i></div>
				<button type="button" class="rg-act rg-up" data-act="up-barracks">Upgrade</button>
			</div>
			<div class="rg-loc" data-loc="armory">
				<div class="rg-loc-name">Armory <span class="rg-lvl" id="rg-lvl-armory">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-weapons">2</strong> weapons</div>
				<div class="rg-bar"><i id="rg-bar-armory"></i></div>
				<button type="button" class="rg-act rg-up" data-act="up-armory">Upgrade</button>
			</div>
			<div class="rg-loc" data-loc="crypt">
				<div class="rg-loc-name">Crypt <span class="rg-lvl" id="rg-lvl-crypt">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-dead">0</strong> dead</div>
				<button type="button" class="rg-act" data-act="raise">Raise</button>
				<button type="button" class="rg-act rg-up" data-act="up-crypt">Upgrade</button>
			</div>
		</div>

		<div id="rg-log"></div>
		<button type="button" id="rg-begin">Begin the siege</button>
	</div>

  </div>
</div>

<style>
.rg-blurb { font-size:.82rem; color:rgba(255,255,255,.5); margin:-6px 0 16px; line-height:1.5; }
.rg-tag { font-size:.6rem; text-transform:uppercase; letter-spacing:.12em; color:#ffcc44; border:1px solid rgba(255,204,68,.4); border-radius:10px; padding:2px 8px; vertical-align:middle; }
.rg-hud { display:flex; gap:16px; font-size:.78rem; color:rgba(255,255,255,.55); margin-bottom:10px; flex-wrap:wrap; align-items:center; }
.rg-hud strong { color:#00c8a0; font-size:1rem; }
#rg-status { margin-left:auto; color:#ffcc44; }

/* The approach. Enemies are absolutely positioned by percentage of the run, so
   the field scales to any width without the simulation knowing about pixels. */
#rg-field { position:relative; height:74px; background:#0a1929; border:1px solid rgba(255,255,255,.08); border-radius:8px; overflow:hidden; margin-bottom:12px; }
#rg-wall { position:absolute; left:0; top:0; bottom:0; width:10px; background:linear-gradient(180deg,#00c8a0,#007a61); }
#rg-enemies { position:absolute; inset:0; }
.rg-foe { position:absolute; top:50%; transform:translateY(-50%); width:22px; height:22px; border-radius:50%; background:#c0392b; border:2px solid #c0392b; transition:left .1s linear; }
.rg-foe img { width:100%; height:100%; border-radius:50%; display:block; object-fit:cover; }
/* Tougher attackers are bigger and ringed, so a dangerous one reads at a glance
   without needing a label. */
.rg-foe.rg-tough { width:30px; height:30px; border-color:#c39bd3; box-shadow:0 0 8px rgba(195,155,211,.6); }
.rg-foe i { position:absolute; left:0; bottom:-6px; height:2px; background:#ff6b6b; }

#rg-locations { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
.rg-loc { background:#0d1e30; border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:9px 10px; }
.rg-loc-name { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.5); }
.rg-lvl { color:#ffcc44; }
.rg-loc-stat { font-size:.82rem; margin:3px 0 7px; }
.rg-loc-stat strong { color:#fff; font-size:1rem; }
.rg-bar { height:3px; background:rgba(255,255,255,.08); border-radius:2px; overflow:hidden; margin-bottom:7px; }
.rg-bar i { display:block; height:100%; width:0; background:#00c8a0; }
.rg-act { background:#00c8a0; color:#04121d; font-weight:bold; border:0; border-radius:5px; padding:7px 10px; font-size:.76rem; cursor:pointer; margin-right:4px; }
.rg-act.rg-up { background:rgba(255,255,255,.12); color:rgba(255,255,255,.75); }
.rg-act:disabled { opacity:.32; cursor:default; }
#rg-log { margin-top:12px; font-size:.78rem; color:rgba(255,255,255,.45); min-height:3.2em; line-height:1.5; }
#rg-log b { color:#ff6b6b; }
#rg-begin { display:block; margin:14px auto 0; background:#00c8a0; color:#04121d; font-weight:bold; border:0; border-radius:6px; padding:11px 26px; font-size:.9rem; cursor:pointer; }
#rg-begin[hidden] { display:none; }

/* Same lesson Obscura learned the hard way: the board has to fit a phone with
   no scrolling, and nothing may sit under a fixed bottom strip. */
@media (max-width:560px) {
  .rg-intro { display:none; }
  #rg-field { height:60px; }
  #rg-locations { gap:6px; }
  .rg-loc { padding:7px 8px; }
  .rg-act { padding:8px 9px; font-size:.74rem; }
  body::after { content:none !important; display:none !important; }
  #quick-menu { display:none !important; }
  #back-to-top-button { display:none !important; }
  #rg-game { padding-bottom:calc(env(safe-area-inset-bottom, 0px) + 12px); }
}
</style>

<script>
(function () {
  var game = document.getElementById('rg-game');
  if (!game) return;

  /* ------------------------------------------------------------------
   * Deterministic core. Seeded PRNG + fixed timestep, no Math.random.
   * Same seed + same actions at the same ticks == same outcome, which is
   * what lets a server replay a run later instead of trusting a score.
   * ------------------------------------------------------------------ */
  /* The horde, from PHP. Cosmetic only -- never read by the simulation. */
  var HORDE = <?php echo json_encode($rg_horde); ?>;

  var SEED = 20260909;
  function mulberry32(a) {
    return function () {
      a |= 0; a = a + 0x6D2B79F5 | 0;
      var t = Math.imul(a ^ a >>> 15, 1 | a);
      t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    };
  }
  var rand = mulberry32(SEED);

  var TICK = 100;                 // ms per simulation step, fixed
  var actionLog = [];             // what a server would replay

  /* Hardcoded stand-ins for realm state. Step 6 replaces these with a
     read-only snapshot of the player's actual locations. */
  var S = {
    running:false, over:false, tick:0, wave:0,
    hp:100, carbon:0, reserve:3, weapons:2, dead:0, garrison:0, armed:0,
    lvl:{ tower:1, barracks:1, armory:1, crypt:1 },
    prod:{ barracks:0, armory:0 },
    foes:[], spawnQueue:[], nextAttack:0, betweenWaves:0
  };

  /* Levels are RATES AND CAPS, not a power number -- the whole point of
     mapping realm locations onto a siege. */
  function garrisonCap() { return 3 + S.lvl.tower; }
  function reserveCap()  { return 4 + S.lvl.barracks * 2; }
  function weaponCap()   { return 3 + S.lvl.armory * 2; }
  function barracksRate(){ return Math.max(14, 60 - S.lvl.barracks * 6); }  // ticks per soldier
  function armoryRate()  { return Math.max(18, 70 - S.lvl.armory * 6); }
  function raiseCost()   { return Math.max(4, 12 - S.lvl.crypt * 2); }
  function upgradeCost(k){ return 20 * S.lvl[k]; }
  function towerDamage() { return S.armed * 3 + (S.garrison - S.armed) * 1; }

  var el = {};
  ['wave','hp','carbon','reserve','weapons','dead','garrison','garrison-cap','status',
   'lvl-tower','lvl-barracks','lvl-armory','lvl-crypt','bar-barracks','bar-armory']
    .forEach(function (k) { el[k] = document.getElementById('rg-' + k); });
  var foesEl = document.getElementById('rg-enemies');
  var logEl  = document.getElementById('rg-log');
  var beginBtn = document.getElementById('rg-begin');

  /* Each foe carries a stable id, so the same attacker keeps the same face for
     its whole life on the field rather than flickering between members every
     render. Falls back to a nameless raider when nobody could be loaded. */
  var foeSeq = 0;
  function foeIdentity(f) {
    if (!HORDE.length) return { name:'A raider', img:'' };
    return HORDE[f.id % HORDE.length];
  }
  function escAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function log(msg, bad) {
    logEl.innerHTML = (bad ? '<b>' + msg + '</b>' : msg) + '<br>' +
      logEl.innerHTML.split('<br>').slice(0, 2).join('<br>');
  }

  /* Waves escalate in count and toughness. Drawn from the seeded PRNG so the
     whole siege is reproducible from SEED alone. */
  function buildWave(n) {
    var q = [], count = 3 + Math.floor(n * 1.6);
    for (var i = 0; i < count; i++) {
      var tough = n >= 3 && rand() < 0.18 + n * 0.02;
      q.push({
        id: foeSeq++,
        hp: (tough ? 26 : 10) + n * 3,
        max:(tough ? 26 : 10) + n * 3,
        speed:(tough ? 0.22 : 0.34) + n * 0.006,
        pos:100 + i * (7 + rand() * 6),
        tough:tough
      });
    }
    return q;
  }

  function startWave() {
    S.wave++;
    S.spawnQueue = buildWave(S.wave);
    S.foes = S.spawnQueue;
    S.spawnQueue = [];
    el.status.textContent = 'Wave ' + S.wave + ' incoming';
    log('Wave ' + S.wave + ' approaches &mdash; ' + S.foes.length + ' of them.');
  }

  function step() {
    S.tick++;

    // Production. Barracks trains soldiers, Armory forges weapons, both capped.
    S.prod.barracks++;
    if (S.prod.barracks >= barracksRate()) {
      S.prod.barracks = 0;
      if (S.reserve < reserveCap()) S.reserve++;
    }
    S.prod.armory++;
    if (S.prod.armory >= armoryRate()) {
      S.prod.armory = 0;
      if (S.weapons < weaponCap()) S.weapons++;
    }

    // The Tower fires on the closest foe. Armed soldiers hit far harder, which
    // is what makes keeping the Armory stocked matter.
    S.nextAttack--;
    if (S.nextAttack <= 0 && S.foes.length && S.garrison > 0) {
      S.nextAttack = 6;
      var target = S.foes[0];
      for (var i = 1; i < S.foes.length; i++) if (S.foes[i].pos < target.pos) target = S.foes[i];
      target.hp -= towerDamage();
      if (target.hp <= 0) {
        S.foes.splice(S.foes.indexOf(target), 1);
        S.carbon += target.tough ? 6 : 2;   // economy comes from killing, not a mine
      }
    }

    // Advance, and resolve anything that reaches the wall.
    for (var j = S.foes.length - 1; j >= 0; j--) {
      var f = S.foes[j];
      f.pos -= f.speed;
      if (f.pos <= 0) {
        S.foes.splice(j, 1);
        S.hp -= f.tough ? 12 : 5;
        // A breach kills a defender. The dead go to the Crypt, not away.
        if (S.garrison > 0) {
          S.garrison--; if (S.armed > 0) S.armed--;
          S.dead++;
          // Naming the attacker is most of the point of the avatar horde -- being
          // breached by someone from your own Discord is a story, "a defender
          // fell" is not.
          log(escAttr(foeIdentity(f).name) + ' breaches the wall. A guardian falls.', true);
        }
        if (S.hp <= 0) return end(false);
      }
    }

    // Wave cleared -- brief respite, then the next one.
    if (!S.foes.length) {
      if (S.betweenWaves <= 0) { S.betweenWaves = 45; el.status.textContent = 'Wave cleared &mdash; regroup'; }
      S.betweenWaves--;
      if (S.betweenWaves <= 0) startWave();
    }
    render();
  }

  function render() {
    el.wave.textContent = S.wave;
    el.hp.textContent = Math.max(0, S.hp);
    el.carbon.textContent = S.carbon;
    el.reserve.textContent = S.reserve;
    el.weapons.textContent = S.weapons;
    el.dead.textContent = S.dead;
    el.garrison.textContent = S.garrison;
    el['garrison-cap'].textContent = garrisonCap();
    ['tower','barracks','armory','crypt'].forEach(function (k) { el['lvl-' + k].textContent = S.lvl[k]; });
    el['bar-barracks'].style.width = Math.round(S.prod.barracks / barracksRate() * 100) + '%';
    el['bar-armory'].style.width   = Math.round(S.prod.armory / armoryRate() * 100) + '%';

    // Foes are rendered by percentage, so the field scales with the screen.
    // Avatars are attached HERE, at render time, and never touch S -- keeping
    // who happens to be drawn out of the deterministic simulation.
    var html = '';
    for (var i = 0; i < S.foes.length; i++) {
      var f = S.foes[i];
      if (f.pos > 100) continue;
      var who = foeIdentity(f);
      html += '<div class="rg-foe' + (f.tough ? ' rg-tough' : '') + '" style="left:' + f.pos + '%"'
            + ' title="' + escAttr(who.name) + '">'
            + (who.img ? '<img src="' + escAttr(who.img) + '" alt="" onerror="this.onerror=null;this.src=\'icons/skull.png\'">' : '')
            + '<i style="width:' + Math.max(0, Math.round(f.hp / f.max * 20)) + 'px"></i></div>';
    }
    foesEl.innerHTML = html;

    document.querySelectorAll('.rg-act').forEach(function (b) {
      var a = b.dataset.act;
      if (a === 'deploy')     b.disabled = !(S.reserve > 0 && S.garrison < garrisonCap());
      else if (a === 'raise') b.disabled = !(S.dead > 0 && S.carbon >= raiseCost());
      else {
        var k = a.slice(3);
        b.disabled = S.carbon < upgradeCost(k);
        b.textContent = 'Upgrade (' + upgradeCost(k) + ')';
      }
    });
  }

  function act(a) {
    if (!S.running || S.over) return;
    actionLog.push([S.tick, a]);   // what a server would replay
    if (a === 'deploy' && S.reserve > 0 && S.garrison < garrisonCap()) {
      S.reserve--; S.garrison++;
      if (S.weapons > 0) { S.weapons--; S.armed++; }   // armed if the Armory can supply
    } else if (a === 'raise' && S.dead > 0 && S.carbon >= raiseCost()) {
      S.carbon -= raiseCost(); S.dead--; S.reserve++;
      log('The Crypt gives one back.');
    } else if (a.indexOf('up-') === 0) {
      var k = a.slice(3);
      if (S.carbon >= upgradeCost(k)) { S.carbon -= upgradeCost(k); S.lvl[k]++; log(k + ' raised to ' + S.lvl[k] + '.'); }
    }
    render();
  }

  function end(won) {
    S.over = true; S.running = false;
    clearInterval(S.timer);
    el.status.textContent = 'The wall is breached';
    log('The realm falls at wave ' + S.wave + '. Guardians lost: ' + S.dead + '.', true);
    beginBtn.textContent = 'Try again';
    beginBtn.hidden = false;
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('.rg-act');
    if (b && !b.disabled) act(b.dataset.act);
  });

  beginBtn.addEventListener('click', function () {
    rand = mulberry32(SEED);
    actionLog = [];
    S.running = true; S.over = false; S.tick = 0; S.wave = 0;
    S.hp = 100; S.carbon = 0; S.reserve = 3; S.weapons = 2; S.dead = 0;
    S.garrison = 0; S.armed = 0; S.betweenWaves = 0; S.nextAttack = 0;
    S.lvl = { tower:1, barracks:1, armory:1, crypt:1 };
    S.prod = { barracks:0, armory:0 };
    S.foes = [];
    logEl.innerHTML = '';
    beginBtn.hidden = true;
    startWave();
    S.timer = setInterval(step, TICK);
  });

  render();
})();
</script>

</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
// No $conn->close() dance beyond the platform default -- this page runs no
// queries of its own. See the header comment.
$conn->close();
?>
</html>
