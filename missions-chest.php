<?php
/*
 * missions-chest.php — the daily reward's state, as a picture.
 *
 * WHAT IT WAS AND WHY IT CHANGED. This used to do the whole performance
 * in the action column: a breathing closed chest, then a burst, a flash
 * and six sparks on claim. At 96px the burst was the only part that
 * read, and the things a player actually claimed sat beside it as a line
 * of 20px icons. The reveal moved to missions-claim-modal.php, where the
 * chest is 240px and the real reward icons fly out of its mouth.
 *
 * SO THIS IS NOW TWO STATES AND NOTHING ELSE. Closed when there is
 * something to take, open and lit once it has been taken. No idle
 * animation: it is a status light, and a status light that moves is
 * asking for attention it does not need.
 *
 * IT IS NOT A SECOND CONTROL. #claimRewardButton stays exactly where it
 * was and keeps doing the work: it is the thing that is focusable, the
 * thing a screen reader announces, and the thing skulliance.js knows
 * about. The chest is decorative (aria-hidden) and simply forwards a
 * click to it, so nothing here can strand a player who cannot use it.
 *
 * THE STATE SWITCH HANGS OFF A HOOK, not off the claim logic.
 * skulliance.js already calls window.DHC_SHOW_DROP when it exists;
 * window.SK_CHEST_OPEN follows the same pattern. If this file is not
 * included, dailyReward() behaves exactly as it always has.
 *
 * THREE LAYERS, because the light is its own picture. The art is a
 * closed chest, an open chest with a dark cavity, and the light by
 * itself, so the open state is lit rather than being a third rendering.
 */
if (!isset($ms_daily) || !$ms_daily) return;
$mc_open = empty($ms_daily['eligible']);     /* already claimed: show it open */
$mc_base = 'images/chest/';
?>
<div class="ms-chest<?php echo $mc_open ? ' is-open' : ''; ?>" id="ms-chest"
     aria-hidden="true" <?php echo $mc_open ? '' : 'role="presentation"'; ?>>
	<img class="ms-chest-l ms-chest-closed" src="<?php echo $mc_base; ?>closed.webp" alt=""
	     width="480" height="497" decoding="async">
	<img class="ms-chest-l ms-chest-open" src="<?php echo $mc_base; ?>open.webp" alt=""
	     width="480" height="497" decoding="async" <?php echo $mc_open ? '' : 'loading="lazy"'; ?>>
	<img class="ms-chest-l ms-chest-glow" src="<?php echo $mc_base; ?>glow.webp" alt=""
	     width="480" height="497" decoding="async" <?php echo $mc_open ? '' : 'loading="lazy"'; ?>>
</div>
<?php if (!defined('MS_CHEST_CSS')): define('MS_CHEST_CSS', 1); ?>
<style>
/* The chest sits in the action column, so the strip keeps its grid. */
.ms-chest { position: relative; flex: 0 0 auto; width: 96px; height: 99px; }
.ms-chest-l { position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: contain; pointer-events: none; }
.ms-chest-open, .ms-chest-glow { opacity: 0; }

/* Claimable: it is pressable, and says so on hover. A transition on a
   pointer event, not a loop -- see the header. */
.ms-chest:not(.is-open) { cursor: pointer; transition: transform .18s ease-out; }
.ms-chest:not(.is-open):hover { transform: scale(1.05); }

/* Taken. The crossfade is the state changing, and on a claim it happens
   behind the reveal modal's veil; on a reload it is simply the state. */
.ms-chest.is-open .ms-chest-closed { opacity: 0; transition: opacity .16s linear; }
.ms-chest.is-open .ms-chest-open { opacity: 1; transition: opacity .16s linear; }
.ms-chest.is-open .ms-chest-glow { opacity: 1; transition: opacity .3s ease-out .06s; }

@media (max-width: 760px) { .ms-chest { width: 76px; height: 79px; } }
@media (prefers-reduced-motion: reduce) {
  /* The open state still arrives -- it is the only thing on the strip
     that says the claim worked -- it just arrives without the fade. */
  .ms-chest:not(.is-open) { transition: none; }
  .ms-chest:not(.is-open):hover { transform: none; }
  .ms-chest.is-open .ms-chest-closed,
  .ms-chest.is-open .ms-chest-open,
  .ms-chest.is-open .ms-chest-glow { transition: none; }
}
</style>
<script>
(function () {
	var el = document.getElementById('ms-chest');
	if (!el) return;
	var btn = document.getElementById('claimRewardButton');

	/* Clicking the art is the same press as the button, including its
	   own guard against a second claim. The chest never claims by
	   itself: everything still goes through dailyReward(). */
	if (btn && !el.classList.contains('is-open')) {
		el.addEventListener('click', function () {
			if (btn.disabled) return;
			btn.click();
		});
		/* Fetch the other two frames while the page is idle, so the
		   switch is not waiting on the network when the claim lands. */
		var warm = function () {
			['open', 'glow'].forEach(function (n) {
				var i = new Image(); i.src = 'images/chest/' + n + '.webp';
			});
		};
		if (window.requestIdleCallback) requestIdleCallback(warm, { timeout: 2500 });
		else setTimeout(warm, 1200);
	}

	/*
	 * THE HOOK skulliance.js CALLS. Same shape as DHC_SHOW_DROP: it
	 * exists only when this partial rendered, and dailyReward() checks
	 * before calling, so the claim works with or without it.
	 *
	 * It sets state. The performance is missions-claim-modal.php's.
	 */
	window.SK_CHEST_OPEN = function () {
		if (el.classList.contains('is-open')) return;
		el.classList.add('is-open');
		el.style.cursor = 'default';
	};
})();
</script>
<?php endif; ?>
