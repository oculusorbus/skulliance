<?php
/*
 * missions-claim-modal.php — the daily claim, as a moment you watch.
 *
 * WHY IT IS HERE AND NOT IN THE STRIP. The chest used to do the whole
 * performance at 96px in the action column: burst, flash, six sparks.
 * At that size the burst is the only thing that reads, and the things a
 * player actually claimed -- the consumable, the currency, the amount --
 * were a line of 20px icons beside it. The reward is the single thing a
 * lot of players come for, so the reveal gets the room.
 *
 * THE STRIP ICON IS NOW STATE, NOT ANIMATION. missions-chest.php is a
 * closed chest when there is something to take and an open, lit one once
 * it has been taken. This file owns every moving part.
 *
 * THE REWARD ICONS FLY OUT OF THE CHEST. Not generic sparkles: the real
 * `icons/*.png` for the day's consumable and the currency that was drawn,
 * the same two pictures the reward line has always shown. They leave the
 * mouth of the chest and settle into their own row, which is what makes
 * the modal a reveal rather than a receipt. The sparks stay underneath
 * them as heat -- at this size there is room for both, and light alone
 * does not tell you what you got.
 *
 * IT IS ADDITIVE. skulliance.js still writes #reward, #claimed,
 * #progress_bar and #remaining exactly as before, and still opens the
 * strip chest through SK_CHEST_OPEN. If this partial is not included the
 * claim behaves as it always has.
 *
 * IT OWNS THE DAY-SEVEN HANDOFF. dhc-dropmodal.php is included on every
 * page, and a full streak pays a trait, so without sequencing the trait
 * modal would open on top of this one. SK_CLAIM_SHOW takes the drop and
 * reveals it after this modal closes.
 */
if (!isset($ms_daily) || !$ms_daily) return;
if (defined('MS_CLAIM_MODAL')) return;
define('MS_CLAIM_MODAL', 1);

/* The day -> consumable map comes from mission_daily(), which is where it
   is defined. skulliance.js builds the reward line from its own hardcoded
   copy of the same list; this does not add a third one. */
$mcm_days = array();
foreach ($ms_daily['days'] as $mcm_d) {
	$mcm_days[(int) $mcm_d['day']] = array(
		'name' => (string) $mcm_d['item'],
		'icon' => (string) $mcm_d['item_icon'],
	);
}
?>
<div id="mcm-veil" aria-hidden="true">
	<div id="mcm-box" role="dialog" aria-modal="true" aria-labelledby="mcm-title">
		<div id="mcm-kicker"></div>

		<div id="mcm-stage">
			<div id="mcm-chest" class="mcm-chest">
				<img class="mcm-l mcm-closed" src="images/chest/closed.webp" alt=""
				     width="480" height="497" decoding="async">
				<img class="mcm-l mcm-open" src="images/chest/open.webp" alt=""
				     width="480" height="497" decoding="async">
				<img class="mcm-l mcm-glow" src="images/chest/glow.webp" alt=""
				     width="480" height="497" decoding="async">
				<span class="mcm-flash"></span>
				<?php /* Fixed origins along the front rim, as in the strip version
				         this replaces: the chest is drawn in three-quarter view, so
				         the mouth runs from 49.5% down on the left to 45.0% on the
				         right. A deterministic burst looks the same to everyone and
				         needs no script to place it. */ ?>
				<?php for ($i = 1; $i <= 6; $i++): ?><span class="mcm-spark s<?php echo $i; ?>"></span><?php endfor; ?>
			</div>
		</div>

		<h4 id="mcm-title">Claimed</h4>

		<?php /* Filled by SK_CLAIM_SHOW from the claim's own reply, so what is
		         shown is what was actually paid rather than what was promised. */ ?>
		<div id="mcm-items"></div>

		<div id="mcm-streak"></div>
		<div id="mcm-remaining"></div>
		<?php /* Every claim pays a trait now, not just the seventh, and the
		         day sets the rarity table -- so this cannot say "full
		         streak" any more. Filled by the script below. */ ?>
		<div id="mcm-trait" hidden></div>

		<div id="mcm-actions">
			<button type="button" id="mcm-close">Collect</button>
		</div>
	</div>
</div>
<style>
#mcm-veil { position: fixed; inset: 0; z-index: 99998; display: none;
  align-items: center; justify-content: center; padding: 20px;
  background: rgba(4,12,22,.9); backdrop-filter: blur(3px); }
#mcm-veil.on { display: flex; }
#mcm-box { width: min(392px, 100%); max-height: 94vh; overflow: auto;
  background: #0a1929; border: 1px solid #1b3346; border-radius: 4px;
  box-shadow: 0 24px 70px rgba(0,0,0,.6); padding: 18px 20px 20px;
  text-align: center; color: #e8eaed;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  animation: mcmIn .3s cubic-bezier(.2,.9,.3,1.2); }
@keyframes mcmIn { from { transform: scale(.9) translateY(14px); opacity: 0; }
  to { transform: none; opacity: 1; } }

#mcm-kicker { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase;
  color: #6f8ba3; }
#mcm-title { margin: 10px 0 0; font-size: 1.5rem; letter-spacing: .04em; color: #00c8a0; }

/*
 * THE CHEST, AT A SIZE WORTH LOOKING AT: 210px against the strip's 96px.
 *
 * THE STAGE CLIPS THE BURST, and is sized so that it does not have to.
 * A flash scaling to 14x and six sparks rising 72px off a mouth 115px
 * down all finish inside these bounds -- but a transform that escapes a
 * scrollable ancestor adds scrollable overflow to it, and the one thing
 * that must not happen when a reward lands is the dialog growing a
 * scrollbar. The flying reward icons are siblings of the stage, not
 * children, so they are not clipped by this.
 */
#mcm-stage { position: relative; display: flex; align-items: flex-end;
  justify-content: center; height: 230px; margin: 6px 0 2px; overflow: hidden; }
.mcm-chest { position: relative; width: 210px; height: 217px; --mcrise: -72px; }
.mcm-l { position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: contain; pointer-events: none; }
.mcm-open, .mcm-glow { opacity: 0; }
.mcm-glow { transform: scale(.72); transform-origin: 50% 62%; }

/* Opening: the lid swaps fast, the light takes its time arriving. */
.mcm-chest.is-open .mcm-closed { opacity: 0; transition: opacity .16s linear; }
.mcm-chest.is-open .mcm-open { opacity: 1; transition: opacity .16s linear; }
.mcm-chest.is-open .mcm-glow { opacity: 1; transform: scale(1);
  transition: opacity .42s ease-out .06s, transform .52s cubic-bezier(.16,1.1,.3,1) .06s; }
.mcm-chest.is-settled .mcm-glow { animation: mcmPulse 3.6s ease-in-out 1.2s infinite; }
@keyframes mcmPulse { 0%,100% { opacity: .82; } 50% { opacity: 1; } }

.mcm-flash { position: absolute; left: 50%; top: 47%; width: 10px; height: 10px;
  margin: -5px 0 0 -5px; border-radius: 50%; opacity: 0; pointer-events: none;
  background: radial-gradient(circle, #ffffff 0%, rgba(0,200,160,.75) 42%, rgba(0,200,160,0) 72%); }
.mcm-chest.is-bursting .mcm-flash { animation: mcmFlash .62s ease-out forwards; }
@keyframes mcmFlash {
  0%   { opacity: 0;   transform: scale(1); }
  18%  { opacity: .95; transform: scale(9); }
  100% { opacity: 0;   transform: scale(14); }
}

/* The sparks leave along the mouth, which is not horizontal. */
.mcm-spark { position: absolute; width: 7px; height: 7px; margin: -3px 0 0 -3px;
  border-radius: 50%; background: #9affdf; opacity: 0; pointer-events: none;
  box-shadow: 0 0 9px rgba(0,200,160,.9); }
.mcm-chest.is-bursting .mcm-spark { animation: mcmSpark 1.25s ease-out forwards; }
.mcm-spark.s1 { left: 30%; top: 49.5%; --mcx: -26px; animation-delay: .02s; }
.mcm-spark.s2 { left: 38%; top: 48.6%; --mcx: -16px; animation-delay: .11s; }
.mcm-spark.s3 { left: 46%; top: 47.7%; --mcx:  -6px; animation-delay: .05s; }
.mcm-spark.s4 { left: 54%; top: 46.8%; --mcx:   8px; animation-delay: .15s; }
.mcm-spark.s5 { left: 62%; top: 45.9%; --mcx:  18px; animation-delay: .08s; }
.mcm-spark.s6 { left: 70%; top: 45.0%; --mcx:  28px; animation-delay: .19s; }
@keyframes mcmSpark {
  0%   { opacity: 0; transform: translate(0,0) scale(.5); }
  15%  { opacity: 1; }
  100% { opacity: 0; transform: translate(var(--mcx), var(--mcrise)) scale(.9); }
}

/*
 * THE REWARDS FLY FROM THE MOUTH TO THEIR ROW.
 *
 * NOT a FLIP of a cloned node: each item starts translated back to the
 * chest's mouth and transitions to transform:none, which is its own
 * resting layout position. The offset is measured once in JS and handed
 * over as two custom properties.
 *
 * The measurement is exact despite the scale() already being applied,
 * because transform-origin is the centre: scaling about the centre does
 * not move the centre, and the centre is the only thing read.
 */
#mcm-items { display: flex; flex-wrap: wrap; align-items: flex-start;
  justify-content: center; gap: 10px 18px; margin: 12px 0 4px; }
.mcm-item { display: flex; flex-direction: column; align-items: center; gap: 4px;
  width: 124px; opacity: 0;
  transform: translate(var(--mcmdx, 0px), var(--mcmdy, 0px)) scale(.35); }
.mcm-item.in { opacity: 1; transform: none;
  transition: transform .74s cubic-bezier(.2,.85,.25,1), opacity .3s linear; }
.mcm-item img { width: 44px; height: 44px; object-fit: contain;
  filter: drop-shadow(0 0 10px rgba(0,200,160,.55)); }
/* TWO LINES OF ROOM WHETHER OR NOT IT NEEDS THEM. "Fast Forward" wraps
   and "+2,250" does not, and a name that wraps pushes its own sub-label a
   line below its neighbour's -- two rewards whose labels sit at different
   heights read as a layout accident. A fixed box, centred, lines them up
   for every name in the cycle. */
.mcm-item b { display: flex; align-items: center; justify-content: center;
  min-height: 2.2em; font-size: .85rem; line-height: 1.1; color: #e8eaed; }
.mcm-item span { font-size: .72rem; color: #8fa7bb; line-height: 1.25; }

#mcm-streak { margin: 10px 0 0; }
#mcm-streak:empty { display: none; }
#mcm-remaining { margin: 8px 0 0; font-size: .78rem; color: #8fa7bb; }
#mcm-remaining:empty { display: none; }
#mcm-trait { margin: 10px 0 0; padding: 8px 10px; font-size: .78rem; color: #cfe3d9;
  background: rgba(0,200,160,.08); border: 1px solid rgba(0,200,160,.22); }

#mcm-actions { margin-top: 16px; }
/* flexbox.css styles bare buttons and anchors globally, so everything the
   dialog needs is re-stated here rather than inherited. */
#mcm-close { width: 100%; padding: 11px 14px; font: inherit; font-size: .95rem;
  letter-spacing: .08em; text-transform: uppercase; text-align: center;
  color: #04121c; background: #00c8a0; border: 0; border-radius: 3px;
  cursor: pointer; }
#mcm-close:hover { background: #2ae0bd; }
#mcm-close:focus-visible { outline: 2px solid #9affdf; outline-offset: 2px; }

/* The rise is in pixels, so it follows the rendered size or the sparks
   travel a third of a chest here. One variable, set wherever the size is. */
@media (max-width: 480px) {
  #mcm-stage { height: 186px; }
  .mcm-chest { width: 170px; height: 176px; --mcrise: -58px; }
}
/*
 * REDUCED MOTION KEEPS THE REVEAL AND DROPS THE TRAVEL. The chest is
 * open, the light is on and both rewards are legible the instant the
 * dialog appears -- that is the information. Only the flight, the burst
 * and the sparks go.
 */
@media (prefers-reduced-motion: reduce) {
  #mcm-box { animation: none; }
  .mcm-chest.is-settled .mcm-glow { animation: none; }
  .mcm-chest.is-open .mcm-glow { transition: opacity .2s linear; transform: scale(1); }
  .mcm-flash, .mcm-spark { display: none; }
  .mcm-item { transform: none; }
  .mcm-item.in { transition: opacity .2s linear; }
}
</style>
<script>
(function () {
	var veil = document.getElementById('mcm-veil');
	if (!veil) return;
	var box   = document.getElementById('mcm-box');
	var chest = document.getElementById('mcm-chest');
	var items = document.getElementById('mcm-items');
	var DAYS  = <?php echo json_encode($mcm_days, JSON_UNESCAPED_SLASHES); ?>;

	var timers = [];
	var pending = null;          /* the day-seven trait, revealed after close */
	var at = function (ms, fn) { timers.push(setTimeout(fn, ms)); };

	function esc(s) {
		return String(s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	/* An <img> whose file is missing must not leave a hole where a reward
	   was: the row is the proof the claim paid, so the label carries it on
	   its own. Same onerror guard the strip uses. */
	function item(icon, name, note) {
		var d = document.createElement('div');
		d.className = 'mcm-item';
		d.innerHTML = (icon ? '<img src="' + esc(icon) + '" alt="" onerror="this.style.display=\'none\'">' : '')
		            + '<b>' + esc(name) + '</b>'
		            + (note ? '<span>' + esc(note) + '</span>' : '');
		return d;
	}

	/*
	 * SK_CLAIM_SHOW(obj) -- obj is the claim's own JSON reply, untouched.
	 * Same optional-hook shape as DHC_SHOW_DROP and SK_CHEST_OPEN:
	 * dailyReward() checks for it, so a page without this partial claims
	 * exactly as it did before.
	 */
	window.SK_CLAIM_SHOW = function (obj) {
		if (!obj) return;
		reset();

		var day  = parseInt(obj.day, 10) || 0;
		var cur  = String(obj.currency || '').replace(/[0-9]+/g, '');
		var amt  = Number(obj.amount);
		var shown = isFinite(amt) ? amt.toLocaleString() : String(obj.amount || '');

		document.getElementById('mcm-kicker').textContent = day ? 'Day ' + day + ' of 7' : 'Daily reward';

		/* The consumable, then the currency: the same order, and the same
		   two pictures, as the reward line beneath the strip. */
		var info = DAYS[day];
		if (info) items.appendChild(item(info.icon, info.name, 'Consumable'));
		if (cur !== '') {
			items.appendChild(item('icons/' + String(obj.currency).toLowerCase() + '.png',
				'+' + shown, cur));
		} else if (shown !== '') {
			items.appendChild(item('', '+' + shown, 'Points'));
		}

		/* Server-rendered markup, written into the strip by dailyReward()
		   in the same breath. The countdown span inside #mcm-remaining is
		   static text: the ticker binds on page load and this arrives
		   after, so it shows the time the claim landed. */
		document.getElementById('mcm-streak').innerHTML    = obj.progress_bar || '';
		document.getElementById('mcm-remaining').innerHTML = obj.remaining || '';

		pending = (obj.dhc_drop && window.DHC_SHOW_DROP) ? obj.dhc_drop : null;
		var tr = document.getElementById('mcm-trait');
		tr.hidden = !pending;
		if (pending) {
			/*
			 * NAME THE LADDER, because the whole point of paying every day
			 * is that the player can see it climbing. Day seven says so
			 * outright; the others say what is coming.
			 */
			tr.textContent = day >= 7
				? 'Full streak. Your trait is drawn from the best table on the platform.'
				: 'A DHC Fighters trait is yours. Day ' + (day + 1) + ' draws from a better table.';
		}

		veil.classList.add('on');
		veil.setAttribute('aria-hidden', 'false');
		document.getElementById('mcm-close').focus();

		/* The chest is shut when the dialog arrives and opens a beat later,
		   so the opening is something you watch rather than something that
		   already happened behind the fade. */
		at(240, function () { chest.classList.add('is-open', 'is-bursting'); });
		at(1540, function () {
			chest.classList.remove('is-bursting');
			chest.classList.add('is-settled');
		});

		/* Measure, then fly. The offsets have to be set before the .in
		   class, and read after the row is laid out. */
		var kids = items.querySelectorAll('.mcm-item');
		var cr = chest.getBoundingClientRect();
		var mx = cr.left + cr.width * 0.5;
		var my = cr.top  + cr.height * 0.47;   /* the mouth, not the skull */
		Array.prototype.forEach.call(kids, function (k, i) {
			var r = k.getBoundingClientRect();
			k.style.setProperty('--mcmdx', (mx - (r.left + r.width / 2)).toFixed(1) + 'px');
			k.style.setProperty('--mcmdy', (my - (r.top + r.height / 2)).toFixed(1) + 'px');
			k.style.transitionDelay = (i * 0.11) + 's, 0s';
			at(430, function () { k.classList.add('in'); });
		});
	};

	function reset() {
		timers.forEach(clearTimeout);
		timers = [];
		chest.classList.remove('is-open', 'is-bursting', 'is-settled');
		items.innerHTML = '';
	}

	function close() {
		timers.forEach(clearTimeout);
		timers = [];
		veil.classList.remove('on');
		veil.setAttribute('aria-hidden', 'true');
		/*
		 * THE TRAIT GOES LAST, and only here.
		 *
		 * dhc-dropmodal.php is included on every page, so on the seventh
		 * day its veil would otherwise open on top of this one and the two
		 * reveals would collide. Handing it over on close makes the streak
		 * read as what it is: the reward, and then the bonus for finishing.
		 */
		if (pending) {
			var d = pending;
			pending = null;
			setTimeout(function () { DHC_SHOW_DROP(d); }, 260);
		}
	}
	window.SK_CLAIM_CLOSE = close;

	document.getElementById('mcm-close').addEventListener('click', close);
	veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && veil.classList.contains('on')) close();
	});
	/* Nothing behind the veil is reachable while it is up, so the dialog
	   keeps the focus rather than letting Tab walk off into the page. */
	box.addEventListener('keydown', function (e) {
		if (e.key === 'Tab') e.preventDefault();
	});

	/* The open and glow frames are fetched while the page is idle, so the
	   burst is not waiting on the network at the moment it fires. */
	var warm = function () {
		['open', 'glow'].forEach(function (n) {
			var i = new Image(); i.src = 'images/chest/' + n + '.webp';
		});
	};
	if (window.requestIdleCallback) requestIdleCallback(warm, { timeout: 2500 });
	else setTimeout(warm, 1200);
})();
</script>
