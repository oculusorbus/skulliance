<?php
/*
 * crafting-modal.php — convert, shatter and burn, as things you watch.
 *
 * WHAT IT REPLACES. All three were a generic confirm box ("Convert 40 of
 * each core currency to 40 DIAMOND?"), a full-page form post, and then
 * openNotify() with a sentence after the reload. Three of the most
 * consequential presses on the platform, reported by the same grey box
 * that reports everything else.
 *
 * ONE MODAL, THREE SEQUENCES, because they are three readings of the
 * same object:
 *
 *   CONVERT  six core icons on a ring, spinning inward until they are on
 *            top of each other, and a DIAMOND where they met.
 *   SHATTER  the DIAMOND shakes, breaks, and throws the six back out to
 *            the ring they came from.
 *   BURN     CARBON catches, burns down, and a DIAMOND comes up through
 *            the flame.
 *
 * Convert and shatter are deliberately the same choreography in opposite
 * directions -- the ring is the same ring, the radius is the same radius
 * -- because the two operations are each other's undo and should look
 * like it.
 *
 * IT CONFIRMS, THEN IT ACTS, THEN IT REPORTS, all without leaving the
 * page. ajax/craft.php does the work and answers with what actually
 * moved; nothing here decides anything. A refusal shows the reason where
 * the result would have been, rather than animating a success and
 * reloading into an unchanged balance.
 *
 * THE RING IS SIX FIXED POINTS, not a random spray. Every orbit is a
 * core project, in the order the crafting panel lists them, so a player
 * who knows the six can follow their own points round.
 */
if (defined('CFT_MODAL')) return;
define('CFT_MODAL', 1);

/* The six core projects, in panel order. Ids 1-6; DIAMOND is 7 and
   CARBON is 15, neither of which belongs on the ring. */
$cft_core = array('star', 'dread', 'hype', 'sinder', 'cyber', 'crypt');
?>
<div id="cft-veil" aria-hidden="true">
	<div id="cft-box" role="dialog" aria-modal="true" aria-labelledby="cft-title">
		<div id="cft-kicker"></div>
		<h4 id="cft-title">Crafting</h4>

		<div id="cft-stage">
			<?php /* THE RING SPINS; THE ORBS TRAVEL. Two transforms rather than
			         one, because a single scale on the ring would shrink the
			         icons as they converged. The ring only ever rotates, and
			         each orb slides between its own point and the centre, so
			         the art keeps its size the whole way in. */ ?>
			<div id="cft-ring">
				<?php foreach ($cft_core as $i => $slug):
					/* Six points, 60 degrees apart, measured from the top.
					   Computed here so the markup carries the geometry and
					   nothing has to position anything at runtime. */
					$ang = deg2rad(-90 + $i * 60); ?>
				<span class="cft-orb" style="--cx: <?php echo round(cos($ang) * 92, 2); ?>px;
				      --cy: <?php echo round(sin($ang) * 92, 2); ?>px;
				      --cd: <?php echo round($i * 0.055, 3); ?>s;">
					<img src="icons/<?php echo $slug; ?>.png" alt=""
					     onerror="this.style.display='none'">
				</span>
				<?php endforeach; ?>
			</div>
			<img id="cft-diamond" src="icons/diamond.png" alt="" onerror="this.style.display='none'">
			<img id="cft-carbon" src="icons/carbon.png" alt="" onerror="this.style.display='none'">
			<div id="cft-fire">
				<?php for ($i = 1; $i <= 7; $i++): ?><span class="cft-flame f<?php echo $i; ?>"></span><?php endfor; ?>
			</div>
			<span id="cft-flash"></span>
		</div>

		<div id="cft-lede"></div>
		<div id="cft-rows"></div>
		<div id="cft-error" hidden></div>

		<div id="cft-actions">
			<button type="button" id="cft-go">Confirm</button>
			<button type="button" id="cft-cancel">Cancel</button>
		</div>
	</div>
</div>
<style>
#cft-veil { position: fixed; inset: 0; z-index: 99997; display: none;
  align-items: center; justify-content: center; padding: 20px;
  background: rgba(4,12,22,.9); backdrop-filter: blur(3px); }
#cft-veil.on { display: flex; }
#cft-box { width: min(392px, 100%); max-height: 94vh; overflow: auto;
  background: #0a1929; border: 1px solid #1b3346; border-radius: 4px;
  box-shadow: 0 24px 70px rgba(0,0,0,.6); padding: 18px 20px 20px;
  text-align: center; color: #e8eaed;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  animation: cftIn .3s cubic-bezier(.2,.9,.3,1.2); }
@keyframes cftIn { from { transform: scale(.9) translateY(14px); opacity: 0; }
  to { transform: none; opacity: 1; } }

#cft-kicker { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase; color: #6f8ba3; }
#cft-title { margin: 4px 0 0; font-size: 1.35rem; letter-spacing: .04em; color: #00c8a0; }

/*
 * THE STAGE CLIPS, AND IS SIZED SO IT DOES NOT HAVE TO. A transform that
 * escapes a scrollable ancestor adds scrollable overflow to it, and the
 * one thing that must not happen mid-craft is the dialog growing a
 * scrollbar. 232px square holds a 92px ring plus a 34px orb.
 */
#cft-stage { position: relative; width: 232px; height: 232px; margin: 10px auto 2px;
  overflow: hidden; }
#cft-ring { position: absolute; inset: 0; }
.cft-orb { position: absolute; left: 50%; top: 50%; width: 34px; height: 34px;
  margin: -17px 0 0 -17px; opacity: 0;
  transform: translate(var(--cx), var(--cy)); }
.cft-orb img { width: 100%; height: 100%; object-fit: contain;
  filter: drop-shadow(0 0 7px rgba(0,200,160,.5)); }
#cft-diamond, #cft-carbon { position: absolute; left: 50%; top: 50%;
  width: 76px; height: 76px; margin: -38px 0 0 -38px; object-fit: contain;
  opacity: 0; pointer-events: none; }
#cft-flash { position: absolute; left: 50%; top: 50%; width: 12px; height: 12px;
  margin: -6px 0 0 -6px; border-radius: 50%; opacity: 0; pointer-events: none;
  background: radial-gradient(circle, #ffffff 0%, rgba(0,200,160,.8) 40%, rgba(0,200,160,0) 72%); }

/* ---- idle: the ring is simply there, waiting to be confirmed -------- */
#cft-veil.idle .cft-orb { opacity: 1; }
#cft-veil.idle.k-shatter .cft-orb,
#cft-veil.idle.k-burn .cft-orb { opacity: 0; }
#cft-veil.idle.k-shatter #cft-diamond { opacity: 1; }
#cft-veil.idle.k-burn #cft-carbon { opacity: 1; }

/* ---- CONVERT: spin inward, flash, diamond ---------------------------- */
#cft-veil.go.k-convert #cft-ring { animation: cftSpin 1.5s cubic-bezier(.4,0,.5,1) forwards; }
#cft-veil.go.k-convert .cft-orb {
  opacity: 1; animation: cftDrawIn 1.5s cubic-bezier(.5,0,.75,1) forwards; animation-delay: var(--cd); }
@keyframes cftSpin { to { transform: rotate(560deg); } }
@keyframes cftDrawIn {
  0%   { transform: translate(var(--cx), var(--cy)) scale(1); opacity: 1; }
  78%  { transform: translate(0,0) scale(.72); opacity: 1; }
  100% { transform: translate(0,0) scale(.3); opacity: 0; }
}
#cft-veil.go.k-convert #cft-flash { animation: cftFlash .7s ease-out 1.35s forwards; }
#cft-veil.go.k-convert #cft-diamond { animation: cftForm .8s cubic-bezier(.16,1.2,.3,1) 1.5s forwards; }
@keyframes cftFlash {
  0%   { opacity: 0;   transform: scale(1); }
  22%  { opacity: .95; transform: scale(9); }
  100% { opacity: 0;   transform: scale(15); }
}
@keyframes cftForm {
  0%   { opacity: 0; transform: scale(.2) rotate(-40deg); }
  55%  { opacity: 1; transform: scale(1.18) rotate(6deg); }
  100% { opacity: 1; transform: scale(1) rotate(0); }
}

/* ---- SHATTER: shake, break, throw the six back out ------------------- */
#cft-veil.go.k-shatter #cft-diamond { animation: cftShake 1s ease-in-out forwards; }
@keyframes cftShake {
  0%, 100% { opacity: 1; transform: translate(0,0) rotate(0); }
  10% { transform: translate(-3px,1px) rotate(-4deg); }
  20% { transform: translate(3px,-2px) rotate(4deg); }
  30% { transform: translate(-4px,-1px) rotate(-6deg); }
  40% { transform: translate(4px,2px) rotate(6deg); }
  50% { transform: translate(-5px,1px) rotate(-8deg); }
  60% { transform: translate(5px,-2px) rotate(8deg); }
  70% { transform: translate(-6px,2px) rotate(-10deg) scale(1.06); }
  80% { transform: translate(6px,-1px) rotate(10deg) scale(1.1); }
  92% { opacity: 1; transform: scale(1.22); }
  100% { opacity: 0; transform: scale(.4); }
}
#cft-veil.go.k-shatter #cft-flash { animation: cftFlash .7s ease-out .92s forwards; }
#cft-veil.go.k-shatter #cft-ring { animation: cftSpinOut 1.4s cubic-bezier(.1,.7,.3,1) 1s forwards; }
@keyframes cftSpinOut { from { transform: rotate(-240deg); } to { transform: rotate(0); } }
#cft-veil.go.k-shatter .cft-orb {
  animation: cftBurstOut 1.4s cubic-bezier(.1,.75,.3,1) forwards;
  animation-delay: calc(1s + var(--cd)); }
@keyframes cftBurstOut {
  0%   { opacity: 0; transform: translate(0,0) scale(.3); }
  18%  { opacity: 1; }
  100% { opacity: 1; transform: translate(var(--cx), var(--cy)) scale(1); }
}

/* ---- BURN: carbon catches, then a diamond comes up through it -------- */
#cft-fire { position: absolute; left: 50%; bottom: 58px; width: 0; height: 0; opacity: 0; }
#cft-veil.go.k-burn #cft-fire { opacity: 1; }
.cft-flame { position: absolute; width: 13px; height: 19px; left: 0; bottom: 0;
  margin-left: -6px; border-radius: 50% 50% 50% 50% / 62% 62% 38% 38%;
  background: linear-gradient(0deg, #ffd166 0%, #ff8c2b 42%, rgba(255,70,20,0) 100%);
  opacity: 0; transform-origin: 50% 100%; }
#cft-veil.go.k-burn .cft-flame { animation: cftFlame 1.5s ease-out infinite; }
@keyframes cftFlame {
  0%   { opacity: 0;  transform: translate(var(--fx), 0) scale(.5); }
  25%  { opacity: .95; }
  100% { opacity: 0;  transform: translate(var(--fx), -78px) scale(.18); }
}
.cft-flame.f1 { --fx: -34px; animation-delay: 0s;    }
.cft-flame.f2 { --fx: -22px; animation-delay: .19s;  }
.cft-flame.f3 { --fx: -11px; animation-delay: .07s;  }
.cft-flame.f4 { --fx:   0px; animation-delay: .26s;  }
.cft-flame.f5 { --fx:  11px; animation-delay: .12s;  }
.cft-flame.f6 { --fx:  22px; animation-delay: .32s;  }
.cft-flame.f7 { --fx:  34px; animation-delay: .22s;  }
/* The lump itself: heats, chars, collapses. */
#cft-veil.go.k-burn #cft-carbon { animation: cftChar 1.9s ease-in forwards; }
@keyframes cftChar {
  0%   { opacity: 1; filter: none; transform: scale(1); }
  35%  { opacity: 1; filter: brightness(1.7) saturate(1.6) hue-rotate(-18deg); transform: scale(1.05); }
  70%  { opacity: .85; filter: brightness(2.4) saturate(2) hue-rotate(-30deg); transform: scale(.92); }
  100% { opacity: 0; filter: brightness(3); transform: scale(.55); }
}
#cft-veil.go.k-burn #cft-flash { animation: cftFlash .8s ease-out 1.6s forwards; }
#cft-veil.go.k-burn #cft-diamond { animation: cftForm .9s cubic-bezier(.16,1.2,.3,1) 1.75s forwards; }

/* ---- the settled state, once a sequence has finished ---------------- */
#cft-veil.done #cft-diamond { opacity: 1; animation: cftGlow 3.4s ease-in-out infinite; }
#cft-veil.done.k-shatter #cft-diamond { opacity: 0; animation: none; }
#cft-veil.done.k-shatter .cft-orb { opacity: 1; transform: translate(var(--cx), var(--cy)); }
@keyframes cftGlow { 0%,100% { filter: drop-shadow(0 0 6px rgba(0,200,160,.5)); }
  50% { filter: drop-shadow(0 0 16px rgba(0,200,160,.9)); } }

/* ---- the panel below the stage -------------------------------------- */
#cft-lede { font-size: .82rem; color: #b9c7d4; line-height: 1.4; margin: 10px 0 0; }
#cft-rows { display: flex; flex-direction: column; gap: 7px; margin: 12px 0 0; }
#cft-rows:empty { display: none; }
.cft-row { display: flex; align-items: center; justify-content: center; gap: 9px;
  font-size: .9rem; }
.cft-row img { width: 24px; height: 24px; object-fit: contain; }
.cft-row .cft-six { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; width: 40px; }
.cft-row .cft-six img { width: 12px; height: 12px; }
.cft-row b { color: #e8eaed; }
.cft-row.out b { color: #e07a7a; }
.cft-row.in  b { color: #00c8a0; }
.cft-row span { font-size: .72rem; color: #8fa7bb; }
#cft-error { margin: 12px 0 0; padding: 9px 11px; font-size: .8rem; color: #ffd4d4;
  background: rgba(224,122,122,.1); border: 1px solid rgba(224,122,122,.35); }

#cft-actions { display: flex; gap: 10px; margin-top: 16px; }
/* flexbox.css styles bare buttons globally, so everything the dialog
   needs is re-stated here rather than inherited. */
#cft-actions button { flex: 1; padding: 11px 14px; font: inherit; font-size: .9rem;
  letter-spacing: .08em; text-transform: uppercase; text-align: center;
  border: 0; border-radius: 3px; cursor: pointer; }
#cft-go { color: #04121c; background: #00c8a0; }
#cft-go:hover { background: #2ae0bd; }
#cft-go:disabled { opacity: .45; cursor: default; }
#cft-cancel { color: #9fb6c4; background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.12); }
#cft-cancel:hover { color: #e8eaed; background: rgba(255,255,255,.12); }
#cft-actions button:focus-visible { outline: 2px solid #9affdf; outline-offset: 2px; }

@media (max-width: 480px) {
  #cft-stage { width: 196px; height: 196px; }
  .cft-orb { width: 28px; height: 28px; margin: -14px 0 0 -14px; }
  #cft-diamond, #cft-carbon { width: 64px; height: 64px; margin: -32px 0 0 -32px; }
}
/*
 * REDUCED MOTION KEEPS THE OUTCOME AND DROPS THE JOURNEY. What a player
 * needs from this dialog is what they are about to spend and what they
 * got; the spin, the shake and the flame are the part that is optional.
 */
@media (prefers-reduced-motion: reduce) {
  #cft-box { animation: none; }
  #cft-ring, .cft-orb, #cft-diamond, #cft-carbon, #cft-flash, .cft-flame { animation: none !important; }
  #cft-veil.go.k-convert .cft-orb, #cft-veil.go.k-burn .cft-orb { opacity: 0; }
  #cft-veil.go.k-convert #cft-diamond, #cft-veil.go.k-burn #cft-diamond { opacity: 1; }
  #cft-veil.go.k-shatter #cft-diamond { opacity: 0; }
  #cft-veil.go.k-shatter .cft-orb { opacity: 1; transform: translate(var(--cx), var(--cy)); }
}
</style>
<script>
(function () {
	var veil = document.getElementById('cft-veil');
	if (!veil) return;
	var box    = document.getElementById('cft-box');
	var go     = document.getElementById('cft-go');
	var cancel = document.getElementById('cft-cancel');
	var rows   = document.getElementById('cft-rows');
	var lede   = document.getElementById('cft-lede');
	var err    = document.getElementById('cft-error');

	var CORE = <?php echo json_encode($cft_core, JSON_UNESCAPED_SLASHES); ?>;
	/* How long each sequence runs before the result is readable. Kept
	   beside the CSS it has to agree with, which is the one thing that
	   drifts: the delays below are the last keyframe plus its delay. */
	var RUNTIME = { convert: 2300, shatter: 2400, burn: 2650 };

	var KIND = {
		convert: { kicker: 'Convert', title: 'Core points to DIAMOND',
		           lede: function (n) { return 'Spending ' + n + ' of each of the six core points.'; } },
		shatter: { kicker: 'Shatter', title: 'DIAMOND to core points',
		           lede: function (n) { return 'Breaking ' + n + ' DIAMOND back into core points.'; } },
		burn:    { kicker: 'Burn',    title: 'CARBON to DIAMOND',
		           lede: function (n) { return 'Burning ' + n + ' CARBON. 100 CARBON forms 1 DIAMOND.'; } }
	};

	var timers = [];
	var state  = null;          /* { kind, amount, done } */
	var at = function (ms, fn) { timers.push(setTimeout(fn, ms)); };
	var clear = function () { timers.forEach(clearTimeout); timers = []; };
	var n = function (v) { return Number(v).toLocaleString(); };

	function esc(s) {
		return String(s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	function six() {
		return '<span class="cft-six">' + CORE.map(function (c) {
			return '<img src="icons/' + c + '.png" alt="" onerror="this.style.display=\'none\'">';
		}).join('') + '</span>';
	}

	/* A row is one movement: what left, or what arrived. */
	function row(dir, art, amount, label) {
		var d = document.createElement('div');
		d.className = 'cft-row ' + dir;
		d.innerHTML = art + '<b>' + (dir === 'out' ? '-' : '+') + n(amount) + '</b>'
		            + '<span>' + esc(label) + '</span>';
		rows.appendChild(d);
	}

	/*
	 * SK_CRAFT_OPEN(kind, amount) -- offer it. Nothing has happened yet:
	 * this is the confirmation, with the ring already assembled so the
	 * player can see what is about to move.
	 */
	window.SK_CRAFT_OPEN = function (kind, amount) {
		if (!KIND[kind]) return;
		amount = parseInt(amount, 10) || 0;
		if (amount <= 0) return;
		clear();
		state = { kind: kind, amount: amount, done: false, preview: false };

		veil.className = 'on idle k-' + kind;
		veil.setAttribute('aria-hidden', 'false');
		document.getElementById('cft-kicker').textContent = KIND[kind].kicker;
		document.getElementById('cft-title').textContent  = KIND[kind].title;
		lede.textContent = KIND[kind].lede(n(kind === 'burn' ? amount : amount));
		rows.innerHTML = '';
		err.hidden = true;
		go.disabled = false;
		go.textContent = 'Confirm';
		cancel.textContent = 'Cancel';
		cancel.hidden = false;
		go.focus();
	};

	/*
	 * THE PRESS. The request goes first and the animation starts with it,
	 * so the two run together rather than one after the other -- but the
	 * RESULT is not written until both have finished, because a reply
	 * that beats the animation would print the outcome over the top of it.
	 */
	go.addEventListener('click', function () {
		if (!state) return;
		/*
		 * A PREVIEW CAN NEVER REACH THE REQUEST. admin-crafting.php plays a
		 * sequence with a made-up result and leaves this button reading
		 * "Done" -- and the first version cleared state.done to stop the
		 * close from reloading, which left exactly one press between the
		 * admin preview and a real craft. The flag is checked first and is
		 * the only thing that can be true on that page.
		 */
		if (state.preview) { close(); return; }
		if (state.done) { close(); return; }

		go.disabled = true;
		go.textContent = 'Working…';
		cancel.hidden = true;
		veil.className = 'on go k-' + state.kind;

		var settled = false;
		var reply   = null;
		var ran     = false;

		var finish = function () {
			if (settled || !ran || reply === null) return;
			settled = true;
			if (reply.success) {
				veil.className = 'on done k-' + state.kind;
				show(reply);
			} else {
				veil.className = 'on idle k-' + state.kind;
				err.textContent = reply.error || 'That did not go through.';
				err.hidden = false;
				go.disabled = false;
				go.textContent = 'Confirm';
				cancel.hidden = false;
			}
		};

		at(RUNTIME[state.kind], function () { ran = true; finish(); });

		var x = new XMLHttpRequest();
		x.open('POST', 'ajax/craft.php', true);
		x.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		x.timeout = 20000;
		var fail = function (msg) {
			reply = { success: false, error: msg };
			/* A failure does not wait for the animation to finish; there is
			   nothing to reveal. */
			ran = true; finish();
		};
		x.ontimeout = function () { fail('Timed out. Nothing was crafted.'); };
		x.onerror   = function () { fail('Network error. Nothing was crafted.'); };
		x.onload = function () {
			if (x.status !== 200) { fail('Could not craft. Try again.'); return; }
			try { reply = JSON.parse(x.responseText); }
			catch (e) { fail('Could not craft. Try again.'); return; }
			if (!reply.success) { ran = true; }
			finish();
		};
		x.send('action=' + encodeURIComponent(state.kind) + '&amount=' + encodeURIComponent(state.amount));
	});

	/* What actually moved, from the endpoint's own reply. */
	function show(r) {
		state.done = true;
		rows.innerHTML = '';
		if (r.kind === 'convert') {
			lede.textContent = 'Converted.';
			row('out', six(), r.spent, 'of each core point');
			row('in', '<img src="icons/diamond.png" alt="" onerror="this.style.display=\'none\'">', r.gained, 'DIAMOND');
		} else if (r.kind === 'shatter') {
			lede.textContent = 'Shattered.';
			row('out', '<img src="icons/diamond.png" alt="" onerror="this.style.display=\'none\'">', r.spent, 'DIAMOND');
			row('in', six(), r.gained, 'of each core point');
		} else {
			lede.textContent = 'Burned.';
			row('out', '<img src="icons/carbon.png" alt="" onerror="this.style.display=\'none\'">', r.spent, 'CARBON');
			row('in', '<img src="icons/diamond.png" alt="" onerror="this.style.display=\'none\'">', r.gained, 'DIAMOND');
		}
		go.disabled = false;
		go.textContent = 'Done';
		cancel.hidden = true;
		go.focus();
	}

	function close() {
		clear();
		/* A preview moved nothing, so there is nothing for a reload to pick up. */
		var moved = state && state.done && !state.preview;
		veil.className = '';
		veil.setAttribute('aria-hidden', 'true');
		state = null;
		/*
		 * A SUCCESSFUL CRAFT RELOADS, because the panel behind this dialog
		 * is six server-rendered balances and three sliders whose maximums
		 * are all now wrong. Redrawing them from here would be a second
		 * copy of renderCrafting() in JavaScript, and the first thing to
		 * drift. The reload happens after the result has been read, which
		 * is the only moment it costs nothing.
		 */
		if (moved) window.location.reload();
	}
	/*
	 * SK_CRAFT_PREVIEW(kind, amount) -- play a sequence and print a
	 * made-up result WITHOUT touching ajax/craft.php.
	 *
	 * admin-crafting.php is the only caller. It exists because each
	 * sequence only runs when somebody has the balance to run it, and
	 * running it spends that balance -- so without this, checking a
	 * change to any of the three meant finding an account in the right
	 * state and destroying that state to look once.
	 *
	 * It cannot craft by construction: there is no request in it, and
	 * the numbers it reports are the ones it was handed.
	 */
	window.SK_CRAFT_PREVIEW = function (kind, amount) {
		if (!KIND[kind]) return;
		amount = parseInt(amount, 10) || 0;
		if (amount <= 0) return;
		SK_CRAFT_OPEN(kind, amount);
		state.preview = true;        /* read by the Confirm handler and by close() */
		go.disabled = true;
		go.textContent = 'Working…';
		cancel.hidden = true;
		veil.className = 'on go k-' + kind;
		at(RUNTIME[kind], function () {
			veil.className = 'on done k-' + kind;
			show({ kind: kind, spent: amount,
			       gained: kind === 'burn' ? Math.floor(amount / 100) : amount });
			go.textContent = 'Done';
		});
	};

	cancel.addEventListener('click', close);
	veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && veil.classList.contains('on')) close();
	});
	/* Nothing behind the veil is reachable while it is up. */
	box.addEventListener('keydown', function (e) { if (e.key === 'Tab') e.preventDefault(); });
})();
</script>
