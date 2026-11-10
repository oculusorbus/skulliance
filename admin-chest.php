<?php
/**
 * admin-chest.php — the daily reward reveal, playable on demand.
 *
 * WHY IT EXISTS. The claim fires once a day, only for a player with a
 * claimable day, and once it has fired that account cannot see it again
 * until tomorrow. Checking a change to it meant waiting for midnight or
 * claiming on a test account, so in practice nobody looked at it twice.
 *
 * IT CLAIMS NOTHING. There is no #claimRewardButton on this page, so
 * missions-chest.php's click binding never attaches and the strip art is
 * inert; the buttons below call window.SK_CLAIM_SHOW and
 * window.SK_CHEST_OPEN directly. No streak is touched, no reward is
 * paid, and nothing here writes.
 *
 * THE REAL PARTIALS, NOT COPIES. It includes missions-chest.php and
 * missions-claim-modal.php themselves, which is the only way a preview
 * stays honest: a second copy of the markup drifts from the one players
 * see, which is the whole failure this page exists to prevent.
 *
 * THE DAY MATTERS, so it is a choice. Each day of the cycle pays a
 * different consumable, and day seven also pays a DHC Fighters trait --
 * which is the one sequencing problem in the feature, because
 * dhc-dropmodal.php is on every page and its veil would otherwise open
 * on top of this one. Day seven here hands over a REAL trait payload,
 * built read-only from the registry, so the handoff can be watched with
 * art that actually loads.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

require_once __DIR__ . '/missions-lib.php';

/*
 * mission_daily() is NOT called here. It resets a lapsed streak as a side
 * effect, which is a write, and it would answer for the admin's own
 * account rather than for the day being previewed. The cycle's
 * consumables come from mission_daily_items(), which is where that map
 * lives, so this page cannot preview art the ladder does not use.
 */
$ac_days = array();
foreach (mission_daily_items() as $ac_d => $ac_it) {
	$ac_days[] = array('day' => $ac_d, 'item' => $ac_it['item'],
	                   'item_icon' => $ac_it['item_icon']);
}
$ms_daily = array('eligible' => true, 'days' => $ac_days);

/* A real trait, for the day-seven handoff. dhcf_drop_payload() only
   reads: the registry for the trait and one COUNT for is_new. */
$ac_drop = null;
if (is_file(__DIR__ . '/dhcfighters-lib.php')) {
	require_once __DIR__ . '/dhcfighters-lib.php';
	$ac_r = dhcf_rarity();
	foreach ($ac_r as $ac_cat => $ac_traits) {
		foreach ($ac_traits as $ac_slug => $ac_info) {
			$ac_drop = dhcf_drop_payload($conn, (int) $_SESSION['userData']['user_id'],
				$ac_cat, $ac_slug, $ac_info, 'dailystreak');
			break 2;
		}
	}
}

include 'header.php';
admin_chrome('chest');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Daily reward reveal</h3>
	<p class="adm-note">The same two partials the missions page renders. Nothing here claims
	   anything: there is no claim button on this page, so the strip chest is inert and
	   these buttons call the hooks directly.</p>

	<div class="adm-card">
		<h4>The reveal</h4>
		<p class="adm-note">What a player sees the moment a claim lands. The chest opens,
		   and the day's consumable and the currency that was drawn fly out of its mouth
		   into their own row. Day seven also pays a DHC Fighters trait, which is revealed
		   after this closes rather than on top of it.</p>
		<div class="adm-chest-ctl">
			<label class="adm-note" for="adm-chest-day">Day</label>
			<select id="adm-chest-day">
				<?php foreach ($ac_days as $ac_row): ?>
				<option value="<?php echo (int) $ac_row['day']; ?>"<?php echo (int) $ac_row['day'] === 3 ? ' selected' : ''; ?>>
					<?php echo (int) $ac_row['day']; ?> &middot; <?php echo htmlspecialchars($ac_row['item'], ENT_QUOTES, 'UTF-8'); ?>
				</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="adm-btn" id="adm-chest-play">Play the reveal</button>
			<span class="adm-note" id="adm-chest-say">Pick a day and play it.</span>
		</div>
		<?php if (!$ac_drop): ?>
		<p class="adm-note">The day-seven trait handoff cannot be previewed: the DHC Fighters
		   registry did not load, so there is no trait payload to hand over.</p>
		<?php endif; ?>
	</div>

	<div class="adm-card">
		<h4>The strip icon</h4>
		<p class="adm-note">Two states and nothing else, in the missions action column:
		   closed while there is something to take, open and lit once it has been. It no
		   longer animates. A claim switches it through the same hook these buttons use.</p>
		<div id="adm-chest-stage" class="adm-chest-stage">
			<?php include __DIR__ . '/missions-chest.php'; ?>
		</div>
		<div class="adm-chest-ctl">
			<button type="button" class="adm-btn" id="adm-chest-open">Switch to open</button>
			<button type="button" class="adm-btn" id="adm-chest-reset">Reset to closed</button>
		</div>
		<p class="adm-note" id="adm-chest-missing" hidden>
			The art did not load. The three files belong at
			<code>/staking/images/chest/</code> as <code>closed.webp</code>,
			<code>open.webp</code> and <code>glow.webp</code>, uploaded by FTP like
			every other image.
		</p>
	</div>

	<p class="adm-note">Reduced motion is honoured: the chest is open, the light is on and
	   both rewards are legible the instant the dialog appears, but the flight, the burst
	   and the sparks are dropped. To check that, turn on Reduce Motion in your system
	   settings and reload. It is deliberately not simulated here, because a copy of those
	   rules on this page could disagree with the ones that ship.</p>
</div></div>

<?php /* The reveal itself, the same partial, rendered once. */ ?>
<?php include __DIR__ . '/missions-claim-modal.php'; ?>

<style>
.adm-chest-stage { display: flex; align-items: flex-end; justify-content: center;
  min-height: 150px; padding: 26px 0 10px; background: #07111d;
  border: 1px solid rgba(0,200,160,.12); }
.adm-chest-ctl { display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-top: 12px; }
.adm-chest-ctl .adm-note { margin: 0; }
</style>
<script>
(function () {
	var stage = document.getElementById('adm-chest-stage');
	var el    = document.getElementById('ms-chest');
	var say   = document.getElementById('adm-chest-say');
	var drop  = <?php echo json_encode($ac_drop, JSON_UNESCAPED_SLASHES); ?>;

	/*
	 * A SAMPLE REPLY, shaped exactly like ajax/daily-reward.php's. The
	 * progress bar and the countdown are markup in the real one too --
	 * getRewardProgressBar() and getRewardTimeRemaining() build them --
	 * so the preview hands over the same kind of string rather than
	 * something the modal would render differently.
	 */
	document.getElementById('adm-chest-play').addEventListener('click', function () {
		if (!window.SK_CLAIM_SHOW) { say.textContent = 'The reveal partial did not load.'; return; }
		var day = parseInt(document.getElementById('adm-chest-day').value, 10) || 1;
		SK_CLAIM_SHOW({
			day: day,
			amount: 1500 + day * 250,
			currency: 'CARBON',
			progress_bar: '<div class="w3-border-rewards"><div class="w3-grey-rewards" style="width:' + (day * 14) + '%"></div></div>',
			remaining: 'Next Reward in&nbsp;11h 24m 9s',
			/* Only the seventh day pays one, same as the claim. */
			dhc_drop: (day === 7 ? drop : null)
		});
		say.textContent = day === 7
			? 'Day 7. Close it and the trait reveal follows.'
			: 'Day ' + day + '. Close it with Collect, the veil or Escape.';
	});

	if (!el) return;
	document.getElementById('adm-chest-open').addEventListener('click', function () {
		if (window.SK_CHEST_OPEN) SK_CHEST_OPEN();
	});

	/* Reset strips the class the hook adds, which re-arms its open-once
	   guard. Nothing else about the strip icon is stateful. */
	document.getElementById('adm-chest-reset').addEventListener('click', function () {
		el.classList.remove('is-open');
		el.style.cursor = '';
	});

	/* FTP deploys the art, so a fresh install has the markup and no
	   pictures. Say which files and where rather than showing three
	   broken frames. */
	var note = document.getElementById('adm-chest-missing');
	stage.querySelectorAll('img').forEach(function (img) {
		img.addEventListener('error', function () { note.hidden = false; });
	});
})();
</script>

<?php /*
 * ===== THE GLOW TUNER, AND IT IS TEMPORARY =====================
 *
 * Behind ?tune=1 so it never renders for anybody who did not ask for
 * it, and it is not wired into the admin tabs. The six numbers under
 * the chest -- where its pool of light sits, how deep it is, how far it
 * spreads, the angle of the floor it lies in, and the two ends of its
 * falloff -- are the kind of thing nobody gets right by arithmetic.
 * This makes them draggable against the real art, writes the finished
 * rule out, and keeps it in localStorage so it survives the reload the
 * day buttons do.
 *
 * It writes NOTHING to the stylesheet. The values here have to be
 * copied into missions-claim-modal.php by hand, and the harness checks
 * the geometry there -- the glow's centre has to land on the chest's
 * footprint midpoint (89.2% of the chest, measured off open.webp) and
 * its depth has to be at least 1.8x the footprint. The readout prints
 * both, so an over-dragged setting says so before it is pasted.
 *
 * DELETE THIS BLOCK once the numbers are settled.
 */ ?>
<?php if (isset($_GET['tune'])): ?>
<style>
#tune { position: fixed; right: 14px; top: 14px; z-index: 100000; width: 300px;
  max-height: 92vh; overflow: auto; background: #0a1929; color: #e8eaed;
  border: 1px solid #1b3346; border-radius: 4px; padding: 12px 14px;
  font: 12px/1.4 ui-monospace, Menlo, Consolas, monospace; }
#tune h4 { margin: 0 0 8px; font-size: .78rem; letter-spacing: .12em;
  text-transform: uppercase; color: #00c8a0; }
#tune label { display: block; margin: 9px 0 0; color: #8fa7bb; }
#tune label b { color: #e8eaed; font-weight: normal; float: right; }
#tune input[type=range] { width: 100%; margin: 3px 0 0; accent-color: #00c8a0; }
#tune textarea { width: 100%; height: 150px; margin-top: 10px; resize: vertical;
  background: #071320; color: #cfe3d9; border: 1px solid #1b3346; border-radius: 3px;
  font: 11px/1.35 ui-monospace, Menlo, Consolas, monospace; padding: 7px; }
#tune .tune-row { display: flex; gap: 7px; margin-top: 9px; }
#tune button { flex: 1; padding: 7px; font: inherit; border: 0; border-radius: 3px;
  cursor: pointer; color: #04121c; background: #00c8a0; }
#tune button.ghost { color: #9fb6c4; background: rgba(255,255,255,.08); }
#tune .tune-check { margin-top: 8px; padding: 6px 8px; border-radius: 3px;
  background: rgba(0,200,160,.08); color: #cfe3d9; }
#tune .tune-check.bad { background: rgba(224,122,122,.12); color: #ffd4d4; }
</style>
<div id="tune">
	<h4>Glow tuner</h4>
	<div id="tune-fields"></div>
	<div class="tune-check" id="tune-check"></div>
	<div class="tune-row">
		<button type="button" id="tune-copy">Copy CSS</button>
		<button type="button" class="ghost" id="tune-reset">Reset</button>
	</div>
	<textarea id="tune-out" readonly></textarea>
</div>
<script>
(function () {
	var KEY = 'mcmGlowTune';
	/* The shipped values, which are also what Reset returns to. */
	var DEF = { bottom: -7, height: 96, width: 334, rot: -4.7, core: 0.48, spread: 1.00 };
	/* Measured off open.webp: the footprint's midpoint is 89.2% down the
	   chest and it is 18.3% of the chest deep. Same numbers the harness
	   uses, so the readout agrees with what will pass. */
	var MID = 0.892, DEPTH = 0.183;

	var FIELDS = [
		['bottom', 'bottom (up / down)', -80, 80, 1],
		['height', 'height (depth of pool)', 20, 220, 1],
		['width',  'width (spread)',       120, 500, 1],
		['rot',    'rotate (floor angle)', -20, 8, 0.1],
		['core',   'core alpha',           0, 1, 0.01],
		['spread', 'falloff scale',        0.5, 1.8, 0.01]
	];

	var val = Object.assign({}, DEF);
	try { var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
	      if (saved) val = Object.assign(val, saved); } catch (e) {}

	var wrap = document.getElementById('tune-fields');
	FIELDS.forEach(function (f) {
		var id = 'tune-' + f[0];
		var l = document.createElement('label');
		l.innerHTML = f[1] + ' <b id="' + id + '-v"></b>'
		            + '<input type="range" id="' + id + '" min="' + f[2] + '" max="' + f[3]
		            + '" step="' + f[4] + '">';
		wrap.appendChild(l);
	});

	var css = document.getElementById('tune-out');
	var check = document.getElementById('tune-check');
	var style = document.createElement('style');
	document.head.appendChild(style);

	function rule() {
		var s = val.spread;
		/* The five stops, scaled together so one slider moves the whole
		   falloff instead of six. The alphas keep their shipped ratios
		   to the core. */
		var stops = [[0, 1], [26, .625], [50, .25], [68, .0625], [82, 0]];
		var body = stops.map(function (p) {
			var pos = Math.min(100, Math.round(p[0] * s));
			var a = (val.core * p[1]).toFixed(3).replace(/0+$/, '').replace(/\.$/, '.0');
			return '    rgba(0,200,160,' + a + ') ' + pos + '%';
		}).join(',\n');
		return '#mcm-plinth::before {\n'
		     + '  left: 50%; bottom: ' + val.bottom + 'px; width: ' + val.width
		     + 'px; height: ' + val.height + 'px;\n'
		     + '  transform: translateX(-50%) rotate(' + val.rot + 'deg);\n'
		     + '  background: radial-gradient(50% 50% at 50% 50%,\n' + body + ');\n}';
	}

	/* The same arithmetic the harness does, so an over-dragged setting
	   says so here rather than failing after it is pasted. */
	function verdict() {
		var pl = document.getElementById('mcm-plinth');
		var ch = document.getElementById('mcm-chest');
		if (!pl || !ch) return 'open a day first, so there is a chest to measure against';
		var plH = pl.getBoundingClientRect().height;
		var chH = ch.getBoundingClientRect().height;
		var pad = parseFloat(getComputedStyle(pl).paddingBottom) || 0;
		var footMid = (plH - pad - chH) + chH * MID;
		var glowMid = plH - val.bottom - val.height / 2;
		var off = Math.round(glowMid - footMid);
		var minDepth = Math.round(chH * DEPTH * 1.8);
		var okCentre = Math.abs(off) <= 8, okDepth = val.height >= minDepth;
		check.className = 'tune-check' + (okCentre && okDepth ? '' : ' bad');
		return 'centre ' + (off === 0 ? 'on' : (off > 0 ? off + 'px below' : (-off) + 'px above'))
		     + ' the footprint line' + (okCentre ? ' (ok)' : ' (over 8px)')
		     + ' \u00b7 depth ' + val.height + ' of ' + minDepth + ' min'
		     + (okDepth ? ' (ok)' : ' (too shallow)');
	}

	function paint() {
		FIELDS.forEach(function (f) {
			var el = document.getElementById('tune-' + f[0]);
			el.value = val[f[0]];
			document.getElementById('tune-' + f[0] + '-v').textContent = val[f[0]];
		});
		var r = rule();
		style.textContent = r;
		css.value = r;
		check.textContent = verdict();
		try { localStorage.setItem(KEY, JSON.stringify(val)); } catch (e) {}
	}

	FIELDS.forEach(function (f) {
		document.getElementById('tune-' + f[0]).addEventListener('input', function () {
			val[f[0]] = parseFloat(this.value);
			paint();
		});
	});
	document.getElementById('tune-reset').addEventListener('click', function () {
		val = Object.assign({}, DEF); paint();
	});
	document.getElementById('tune-copy').addEventListener('click', function () {
		css.select(); css.setSelectionRange(0, 99999);
		try { document.execCommand('copy'); } catch (e) {}
	});

	paint();
	/* The reveal re-renders the chest, so re-measure once it has. */
	setInterval(function () { check.textContent = verdict(); }, 1200);
})();
</script>
<?php endif; ?>
</html>
