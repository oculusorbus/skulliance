<?php
/**
 * admin-chest.php — the daily reward chest, openable on demand.
 *
 * WHY IT EXISTS. The claim animation fires once a day, only for a
 * player with a claimable day, and once it has fired that account
 * cannot see it again until tomorrow. Checking a change to it meant
 * waiting for midnight or claiming on a test account, so in practice
 * nobody looked at it twice.
 *
 * IT CLAIMS NOTHING. There is no #claimRewardButton on this page, so
 * missions-chest.php's click binding never attaches and the art is
 * inert; the buttons below call window.SK_CHEST_OPEN() directly. No
 * streak is touched, no reward is paid, and the page is read-only.
 *
 * THE REAL PARTIAL, NOT A COPY. It includes missions-chest.php itself,
 * which is the only way a preview stays honest: a second copy of the
 * markup would drift from the one players see, which is the whole
 * failure this page exists to prevent.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

include 'header.php';
admin_chrome('chest');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Daily reward chest</h3>
	<p class="adm-note">The same partial the missions page renders. Nothing here claims
	   anything: there is no claim button on this page, so the chest is inert and these
	   buttons call the animation hook directly.</p>

	<div class="adm-card">
		<div id="adm-chest-stage" class="adm-chest-stage">
			<?php
			/* missions-chest.php wants $ms_daily and renders the claimable
			   state when 'eligible' is true. That is the state worth
			   looking at: the other one is this one after the burst. */
			$ms_daily = array('eligible' => true);
			include __DIR__ . '/missions-chest.php';
			?>
		</div>
		<div class="adm-chest-ctl">
			<button type="button" class="adm-btn" id="adm-chest-play">Open it</button>
			<button type="button" class="adm-btn" id="adm-chest-reset">Reset to closed</button>
			<span class="adm-note" id="adm-chest-say">Idle, waiting to be opened.</span>
		</div>
		<p class="adm-note" id="adm-chest-missing" hidden>
			The art did not load. The three files belong at
			<code>/staking/images/chest/</code> as <code>closed.webp</code>,
			<code>open.webp</code> and <code>glow.webp</code>, uploaded by FTP like
			every other image.
		</p>
	</div>

	<p class="adm-note">Reduced motion is honoured: the chest still opens and the light
	   still arrives, but the breathing, the burst and the sparks are dropped. To check
	   that, turn on Reduce Motion in your system settings and reload. It is deliberately
	   not simulated here, because a copy of those rules on this page could disagree with
	   the ones that ship.</p>
</div></div>

<style>
.adm-chest-stage { display: flex; align-items: flex-end; justify-content: center;
  min-height: 190px; padding: 26px 0 10px; background: #07111d;
  border: 1px solid rgba(0,200,160,.12); }
/* Bigger than the strip renders it, because the point of this page is
   seeing the thing rather than seeing it in situ. */
.adm-chest-stage .ms-chest { width: 150px; height: 155px; --mcrise: -81px; }
.adm-chest-ctl { display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-top: 12px; }
.adm-chest-ctl .adm-note { margin: 0; }
</style>
<script>
(function () {
	var stage = document.getElementById('adm-chest-stage');
	var el    = document.getElementById('ms-chest');
	var say   = document.getElementById('adm-chest-say');
	if (!el) return;

	document.getElementById('adm-chest-play').addEventListener('click', function () {
		if (el.classList.contains('is-open')) { say.textContent = 'Already open. Reset it first.'; return; }
		if (window.SK_CHEST_OPEN) SK_CHEST_OPEN();
		say.textContent = 'Opening. It settles into its resting pulse after about a second.';
	});

	/* Reset strips the classes the hook adds, which re-arms its own
	   open-once guard. Nothing else is stateful. */
	document.getElementById('adm-chest-reset').addEventListener('click', function () {
		el.classList.remove('is-open', 'is-bursting', 'is-settled');
		el.style.cursor = '';
		say.textContent = 'Closed, waiting to be opened.';
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
