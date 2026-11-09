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
</html>
