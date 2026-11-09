<?php
/*
 * missions-chest.php — the daily reward, as something you open.
 *
 * WHY. Claiming was a button that changed its own label. The reward is
 * the single thing a lot of players come for, and the moment it lands
 * is the one moment on this page worth making memorable.
 *
 * IT IS NOT A SECOND CONTROL. #claimRewardButton stays exactly where it
 * was and keeps doing the work: it is the thing that is focusable, the
 * thing a screen reader announces, and the thing skulliance.js knows
 * about. The chest is decorative (aria-hidden) and simply forwards a
 * click to it, so nothing here can strand a player who cannot use it.
 *
 * THE ANIMATION HANGS OFF A HOOK, not off the claim logic.
 * skulliance.js already calls window.DHC_SHOW_DROP when it exists;
 * window.SK_CHEST_OPEN follows the same pattern. If this file is not
 * included, dailyReward() behaves exactly as it always has.
 *
 * THREE LAYERS, because the glow has to move on its own. The art is a
 * closed chest, an open chest with a dark cavity, and the light by
 * itself. Animating the light's opacity and scale over a static chest
 * is what makes it read as something igniting rather than as two
 * pictures swapping.
 */
if (!isset($ms_daily) || !$ms_daily) return;
$mc_open = empty($ms_daily['eligible']);     /* already claimed: show it open */
$mc_base = 'images/chest/';
?>
<div class="ms-chest<?php echo $mc_open ? ' is-open is-settled' : ''; ?>" id="ms-chest"
     aria-hidden="true" <?php echo $mc_open ? '' : 'role="presentation"'; ?>>
	<img class="ms-chest-l ms-chest-closed" src="<?php echo $mc_base; ?>closed.webp" alt=""
	     width="480" height="497" decoding="async">
	<img class="ms-chest-l ms-chest-open" src="<?php echo $mc_base; ?>open.webp" alt=""
	     width="480" height="497" decoding="async" <?php echo $mc_open ? '' : 'loading="lazy"'; ?>>
	<img class="ms-chest-l ms-chest-glow" src="<?php echo $mc_base; ?>glow.webp" alt=""
	     width="480" height="497" decoding="async" <?php echo $mc_open ? '' : 'loading="lazy"'; ?>>
	<span class="ms-chest-flash"></span>
	<?php /* Fixed offsets rather than random ones: six sparks on staggered
	         delays read as a burst, and a deterministic burst is one that
	         looks the same to everyone and needs no script to place it. */ ?>
	<?php for ($i = 1; $i <= 6; $i++): ?><span class="ms-chest-spark s<?php echo $i; ?>"></span><?php endfor; ?>
</div>
<?php if (!defined('MS_CHEST_CSS')): define('MS_CHEST_CSS', 1); ?>
<style>
/* The chest sits in the action column, so the strip keeps its grid. The
   burst is allowed to overflow it: the moment reads bigger than the art
   without anything on the page moving. */
.ms-chest { position: relative; flex: 0 0 auto; width: 96px; height: 99px;
  overflow: visible; }
.ms-chest-l { position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: contain; pointer-events: none; }
.ms-chest-open, .ms-chest-glow { opacity: 0; }
.ms-chest-glow { transform: scale(.72); transform-origin: 50% 62%; }

/* Claimable: a slow breath, so it reads as waiting rather than as a
   picture. transform and opacity only, which the compositor can do
   without touching layout. */
.ms-chest:not(.is-open) { cursor: pointer; animation: mcBreath 3.4s ease-in-out infinite; }
.ms-chest:not(.is-open):hover { animation-play-state: paused; transform: scale(1.04); }
@keyframes mcBreath { 0%,100% { transform: scale(1); } 50% { transform: scale(1.025); } }

/* Opening. The lid swaps fast, the light takes its time arriving. */
.ms-chest.is-open .ms-chest-closed { opacity: 0; transition: opacity .16s linear; }
.ms-chest.is-open .ms-chest-open { opacity: 1; transition: opacity .16s linear; }
.ms-chest.is-open .ms-chest-glow { opacity: 1; transform: scale(1);
  transition: opacity .42s ease-out .06s, transform .52s cubic-bezier(.16,1.1,.3,1) .06s; }
/* Once the burst is over it keeps a slow pulse, so a claimed chest is
   still alive on the page without asking for attention. */
.ms-chest.is-settled .ms-chest-glow { animation: mcPulse 3.6s ease-in-out 1.2s infinite; }
@keyframes mcPulse { 0%,100% { opacity: .82; } 50% { opacity: 1; } }

.ms-chest-flash { position: absolute; left: 50%; top: 58%; width: 10px; height: 10px;
  margin: -5px 0 0 -5px; border-radius: 50%; opacity: 0; pointer-events: none;
  background: radial-gradient(circle, #ffffff 0%, rgba(0,200,160,.75) 42%, rgba(0,200,160,0) 72%); }
.ms-chest.is-bursting .ms-chest-flash { animation: mcFlash .62s ease-out forwards; }
@keyframes mcFlash {
  0%   { opacity: 0;   transform: scale(1); }
  18%  { opacity: .95; transform: scale(9); }
  100% { opacity: 0;   transform: scale(17); }
}

.ms-chest-spark { position: absolute; left: 50%; top: 56%; width: 5px; height: 5px;
  margin: -2px 0 0 -2px; border-radius: 50%; background: #9affdf; opacity: 0;
  pointer-events: none; box-shadow: 0 0 6px rgba(0,200,160,.9); }
.ms-chest.is-bursting .ms-chest-spark { animation: mcSpark 1.25s ease-out forwards; }
.ms-chest-spark.s1 { --mcx: -34px; animation-delay: .02s; }
.ms-chest-spark.s2 { --mcx: -19px; animation-delay: .11s; }
.ms-chest-spark.s3 { --mcx:  -6px; animation-delay: .05s; }
.ms-chest-spark.s4 { --mcx:   8px; animation-delay: .15s; }
.ms-chest-spark.s5 { --mcx:  21px; animation-delay: .08s; }
.ms-chest-spark.s6 { --mcx:  36px; animation-delay: .19s; }
@keyframes mcSpark {
  0%   { opacity: 0;  transform: translate(0,0) scale(.5); }
  15%  { opacity: 1; }
  100% { opacity: 0;  transform: translate(var(--mcx), -58px) scale(.9); }
}

@media (max-width: 760px) { .ms-chest { width: 76px; height: 79px; } }
/*
 * REDUCED MOTION TAKES THE MOVEMENT, NOT THE FEEDBACK. No breathing, no
 * burst, no sparks -- but the chest still opens and the light still
 * arrives, because that is the thing that says the claim worked.
 */
@media (prefers-reduced-motion: reduce) {
  .ms-chest, .ms-chest.is-settled .ms-chest-glow { animation: none; }
  .ms-chest:not(.is-open):hover { transform: none; }
  .ms-chest.is-open .ms-chest-glow { transition: opacity .2s linear; transform: scale(1); }
  .ms-chest-flash, .ms-chest-spark { display: none; }
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
		   burst is not waiting on the network at the moment it fires. */
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
	 */
	window.SK_CHEST_OPEN = function () {
		if (el.classList.contains('is-open')) return;
		el.classList.add('is-open', 'is-bursting');
		el.style.cursor = 'default';
		/* Settle into the resting pulse once the burst has finished.
		   Driven off the class rather than the animation event so a
		   browser that skipped the animation still settles. */
		setTimeout(function () {
			el.classList.remove('is-bursting');
			el.classList.add('is-settled');
		}, 1300);
	};
})();
</script>
<?php endif; ?>
