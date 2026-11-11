<?php
/*
 * dhcportal-reveal.php — the jettison, watched.
 *
 * THE RULES CARRIED FROM EVERY REVEAL BUILT BEFORE THIS ONE, because
 * each of them cost a round of "that looks wrong" to learn:
 *
 *   ONE CLOCK. Every beat is an offset from the moment the Portal takes
 *   the board, never an absolute timestamp. Three hardcoded timings is
 *   how a sequence drifts the first time any one of them moves.
 *
 *   ANTICIPATION IS NOT DEAD TIME. The chest needed 660ms of rattle and
 *   then 40ms of stillness before the lid; the stillness is what made
 *   the pop land. The same beat is held here before the Portal gives
 *   anything back.
 *
 *   ONLY transform AND opacity ANIMATE. Anything else is a layout
 *   thrash.
 *
 *   REDUCED MOTION KEEPS THE INFORMATION AND DROPS THE TRAVEL. The
 *   result is legible immediately; only the journey goes.
 *
 * AND THE ONE DECISION THAT IS THIS FEATURE'S OWN: a board can hold
 * sixteen pieces. Sixteen elements flying into a vortex is sixteen
 * animated DOM nodes, which is where a reveal becomes a slideshow on a
 * phone. So the pieces are DOM while you are touching them and become
 * PARTICLES the moment the jettison fires. Elements for the part you
 * interact with, canvas for the part that is spectacle.
 */
?>
<style>
#dhcpVeil { position: fixed; inset: 0; z-index: 99995; display: none;
  align-items: center; justify-content: center; padding: 20px;
  background-color: rgba(4,12,22,.93);
  background-image: radial-gradient(78% 58% at 50% 42%,
    rgba(40,8,20,.55) 0, rgba(4,12,22,0) 70%);
  backdrop-filter: blur(3px); }
#dhcpVeil.on { display: flex; }
#dhcpVeil canvas { position: absolute; inset: 0; width: 100%; height: 100%; }
#dhcpOut { position: relative; z-index: 2; width: min(360px, 100%); text-align: center;
  color: #e8eaed; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  opacity: 0; transform: scale(.8); transition: opacity .45s ease-out, transform .6s cubic-bezier(.16,1.1,.3,1); }
#dhcpOut.in { opacity: 1; transform: none; }
#dhcpOut, #dhcpOut * { text-align: center; }
#dhcpOut .kick { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase;
  color: #6f8ba3; }
#dhcpOut h4 { margin: 4px 0 14px; font-size: 1.3rem; letter-spacing: .03em;
  color: #00c8a0; text-transform: none; }
#dhcpOut .art { width: 190px; height: 190px; margin: 0 auto; border-radius: 4px;
  overflow: hidden; background: #0d1e30;
  box-shadow: 0 0 0 2px var(--c,#5a7888), 0 0 46px -6px var(--c,#5a7888); }
#dhcpOut .art img { width: 100%; height: 100%; object-fit: cover; display: block; }
#dhcpOut .nm { margin: 14px 0 0; font-size: 1rem; }
#dhcpOut .tr { margin: 4px 0 0; font-size: .76rem; letter-spacing: .1em;
  text-transform: uppercase; color: var(--c,#5a7888); }
#dhcpOut .sent { margin: 12px 0 0; font-size: .76rem; color: #8fa7bb; line-height: 1.45; }
#dhcpOut button { width: 100%; margin-top: 18px; padding: 11px 14px; font: inherit;
  font-size: .95rem; letter-spacing: .08em; text-transform: uppercase; color: #04121c;
  background-color: #00c8a0; background-image: var(--msp-fill); border: 0;
  border-radius: 3px; cursor: pointer; box-shadow: 0 2px 0 rgba(0,0,0,.3); }
#dhcpOut button:active { background-image: var(--msp-fill-in);
  transform: translateY(2px); box-shadow: 0 0 0 rgba(0,0,0,.3); }
#dhcpOut button:focus-visible { outline: 2px solid #04121c; outline-offset: -4px; }
@media (prefers-reduced-motion: reduce) {
  #dhcpOut { transition: none; }
}
</style>
<div id="dhcpVeil" aria-hidden="true">
  <canvas id="dhcpVeilCanvas"></canvas>
  <div id="dhcpOut" role="dialog" aria-modal="true" aria-labelledby="dhcpOutTitle">
    <div class="kick" id="dhcpOutKick"></div>
    <h4 id="dhcpOutTitle">Through the Portal</h4>
    <div class="art" id="dhcpOutArt"></div>
    <p class="nm" id="dhcpOutName"></p>
    <p class="tr" id="dhcpOutTier"></p>
    <p class="sent" id="dhcpOutSent"></p>
    <button type="button" id="dhcpOutGo">Take it</button>
  </div>
</div>
<script>
(function () {
	var veil = document.getElementById('dhcpVeil');
	if (!veil) return;
	var cv   = document.getElementById('dhcpVeilCanvas');
	var out  = document.getElementById('dhcpOut');
	var CTX  = cv.getContext('2d');
	var calm = window.matchMedia
	        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* From dhcf_tier_colours(), the one place these live. Hand-picked
	   here once and they came out rotated. */
	var TIERCOL = <?php echo json_encode(dhcf_tier_colours()); ?>;
	var TIERNAME = { common:'Common', uncommon:'Uncommon', epic:'Epic',
	                 legendary:'Legendary', mythic:'Mythic' };

	/*
	 * THE RAMP, PRE-RENDERED ONCE.
	 *
	 * Tinting a sprite per particle per frame is what makes a particle
	 * system too slow to be worth having -- the crafting flame learned
	 * that and the fix is the same here: a handful of coloured discs
	 * drawn once into offscreen canvases, then blitted.
	 */
	var SPR = {}, SPRN = 14;
	function sprites(hex) {
		if (SPR[hex]) return SPR[hex];
		var list = [];
		for (var i = 0; i < SPRN; i++) {
			var c = document.createElement('canvas'), s = 26;
			c.width = c.height = s;
			var x = c.getContext('2d');
			var g = x.createRadialGradient(s/2, s/2, 0, s/2, s/2, s/2);
			var a = 1 - i / SPRN;
			g.addColorStop(0, 'rgba(255,255,255,' + (a * .95) + ')');
			g.addColorStop(.35, hex2rgba(hex, a * .8));
			g.addColorStop(1, hex2rgba(hex, 0));
			x.fillStyle = g; x.fillRect(0, 0, s, s);
			list.push(c);
		}
		SPR[hex] = list;
		return list;
	}
	function hex2rgba(h, a) {
		var n = parseInt(h.slice(1), 16);
		return 'rgba(' + ((n>>16)&255) + ',' + ((n>>8)&255) + ',' + (n&255) + ',' + a + ')';
	}

	var P = [], raf = 0, t0 = 0, phase = 'idle', spin = 0;

	function fit() {
		var r = veil.getBoundingClientRect();
		var d = Math.min(window.devicePixelRatio || 1, 2);
		cv.width = Math.round(r.width * d); cv.height = Math.round(r.height * d);
		CTX.setTransform(d, 0, 0, d, 0, 0);
	}

	/*
	 * ONE PARTICLE PER PIECE, WEIGHTED BY SIZE. A mythic takes sixteen
	 * slots and throws sixteen times the material, so what goes down the
	 * chute looks like what the board was holding rather than like a
	 * uniform handful.
	 */
	function feed(fed) {
		var r = veil.getBoundingClientRect();
		P = [];
		fed.forEach(function (p, i) {
			var n = Math.max(3, (p.size || 1) * 3);
			for (var k = 0; k < n; k++) {
				var ang = Math.random() * Math.PI * 2;
				var rad = (Math.min(r.width, r.height) * .42) * (.6 + Math.random() * .5);
				P.push({
					a: ang, r: rad, vr: -(1.6 + Math.random() * 1.4),
					va: .018 + Math.random() * .022,
					col: TIERCOL[p.tier] || '#5a7888',
					age: Math.random() * .3, born: i * .012, out: false
				});
			}
		});
	}

	/* The same particles, thrown back out. */
	function erupt(tier) {
		var col = TIERCOL[tier] || '#00c8a0';
		P.forEach(function (p) {
			p.r = 2 + Math.random() * 14; p.vr = 2.2 + Math.random() * 3.4;
			p.va = (Math.random() < .5 ? -1 : 1) * (.01 + Math.random() * .02);
			p.col = col; p.age = 0; p.born = Math.random() * .1; p.out = true;
		});
	}

	function frame(ts) {
		if (!t0) t0 = ts;
		var el = (ts - t0) / 1000;
		var r = veil.getBoundingClientRect();
		var cx = r.width / 2, cy = r.height / 2;

		CTX.clearRect(0, 0, r.width, r.height);
		CTX.globalCompositeOperation = 'lighter';

		/* The mouth: a disc that opens as it takes and flares as it gives. */
		spin += phase === 'out' ? .09 : .05;
		var open = phase === 'in' ? Math.min(1, el / 1.1)
		         : phase === 'out' ? Math.max(0, 1.6 - el / 1.1) : 0;
		if (open > 0) {
			var rad = 26 + open * 92;
			var g = CTX.createRadialGradient(cx, cy, 0, cx, cy, rad);
			g.addColorStop(0, 'rgba(255,255,255,' + (.22 * open) + ')');
			g.addColorStop(.45, 'rgba(226,24,74,' + (.34 * open) + ')');
			g.addColorStop(1, 'rgba(226,24,74,0)');
			CTX.fillStyle = g;
			CTX.beginPath(); CTX.arc(cx, cy, rad, 0, Math.PI * 2); CTX.fill();
		}

		var alive = 0;
		for (var i = 0; i < P.length; i++) {
			var p = P[i];
			if (el < p.born) { alive++; continue; }
			p.a += p.va * (p.out ? 1 : 1 + (240 / Math.max(40, p.r)) * .08);
			p.r += p.vr;
			p.age += .016;
			if (!p.out && p.r < 6) continue;            /* swallowed */
			if (p.out && p.r > Math.max(r.width, r.height)) continue;
			alive++;
			var k = Math.min(SPRN - 1, Math.floor(p.age * SPRN / 1.6));
			var sp = sprites(p.col)[k];
			var x = cx + Math.cos(p.a + spin * .15) * p.r;
			var y = cy + Math.sin(p.a + spin * .15) * p.r * .62;   /* ellipse: seen from above */
			CTX.drawImage(sp, x - 13, y - 13);
		}
		CTX.globalCompositeOperation = 'source-over';

		if (phase !== 'idle') raf = requestAnimationFrame(frame);
		else { CTX.clearRect(0, 0, r.width, r.height); }
	}

	function run(fn, ms) { return setTimeout(fn, ms); }
	var timers = [];
	function at(ms, fn) { timers.push(run(fn, ms)); }
	function clear() { timers.forEach(clearTimeout); timers = []; }

	window.DHCP_REVEAL = function (fed, res) {
		clear();
		veil.classList.add('on');
		veil.setAttribute('aria-hidden', 'false');
		out.classList.remove('in');
		fit();

		var won = res.won || {};
		var tier = won.tier || 'common';
		document.getElementById('dhcpOutKick').textContent =
			res.rows + ' row' + (res.rows === 1 ? '' : 's') + ' fed';
		document.getElementById('dhcpOutName').textContent = won.name || won.slug || 'A trait';
		document.getElementById('dhcpOutTier').textContent = TIERNAME[tier] || tier;
		out.style.setProperty('--c', TIERCOL[tier] || '#5a7888');
		/* BUILT, NOT READ. dhcf_drop_payload() carries category, slug, tier
		   and name but no image URL -- the drop modal composes the same
		   path from base + /250/cat/slug.png and so does the tray on this
		   page. Expecting a field that was never there would have shown an
		   empty frame on every win. */
		document.getElementById('dhcpOutArt').innerHTML = (won.category && won.slug)
			? '<img src="' + window.DHCP.art + '/250/' + won.category + '/' + won.slug
			  + '.png" alt="" onerror="this.style.display=\'none\'">'
			: '';
		document.getElementById('dhcpOutSent').textContent = res.sent > 0
			? (res.sent === 1
			   ? 'One trait went out to somebody who has been away.'
			   : res.sent + ' traits went out to players who have been away.')
			: '';

		/*
		 * REDUCED MOTION: the result, immediately. Everything the player
		 * needs to know is in the panel; only the journey goes.
		 */
		if (calm) {
			out.classList.add('in');
			document.getElementById('dhcpOutGo').focus();
			return;
		}

		/* ONE CLOCK. Every beat below is an offset from here. */
		var TAKE = 0, GIVE = 1500, SHOW = GIVE + 520;
		feed(fed);
		phase = 'in'; t0 = 0;
		cancelAnimationFrame(raf); raf = requestAnimationFrame(frame);

		at(GIVE, function () {
			/* The held beat. The chest taught this: stillness before the
			   payoff is what makes the payoff land. */
			phase = 'hold';
		});
		at(GIVE + 240, function () { phase = 'out'; t0 = 0; erupt(tier); });
		at(SHOW + 240, function () {
			out.classList.add('in');
			document.getElementById('dhcpOutGo').focus();
		});
		at(SHOW + 2600, function () { phase = 'idle'; });
	};

	document.getElementById('dhcpOutGo').addEventListener('click', function () {
		clear(); phase = 'idle';
		veil.classList.remove('on');
		veil.setAttribute('aria-hidden', 'true');
		location.reload();      /* the tray and the board both changed */
	});
	window.addEventListener('resize', function () { if (phase !== 'idle') fit(); });
})();
</script>
