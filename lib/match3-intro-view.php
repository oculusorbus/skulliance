<?php
/*
 * lib/match3-intro-view.php — the overlay itself. Included by
 * match3_intro(); $game and $force are already set by the caller.
 *
 * NO IMAGES. Images deploy by FTP outside this repo, and a teaching
 * overlay that 404s teaches nothing. Every tile here is a CSS shape, so
 * the demo works the moment the code lands.
 */
?>
<div id="m3i" aria-hidden="true">
	<div id="m3i-box" role="dialog" aria-modal="true" aria-labelledby="m3i-title">
		<div id="m3i-kick">Before your first run</div>
		<h4 id="m3i-title">Tiles travel the whole row</h4>

		<?php /* THE DEMO. Five columns, three rows, and the move happens on
		         the middle one. Built here rather than in script so it is
		         in the markup a screen reader and a no-JS browser meet;
		         the script only moves what is already on the page. */ ?>
		<div id="m3i-stage">
			<div id="m3i-grid" aria-hidden="true">
				<?php
				/*
				 * THE ROW THAT MAKES THE POINT: a circle at the left, two
				 * strangers, then two more circles. Dragging the circle two
				 * cells right leaves three circles in a line -- a match a
				 * neighbour swap cannot reach from here, which is the whole
				 * lesson in one move.
				 */
				/* c circle, q square, r triangle. NOT 't' for triangle: the
				   base class on every tile is .m3i-t, so a triangle came out
				   as class="m3i-t m3i-t" and its own rule never matched. */
				$m3i_rows = array(
					array('r', 'c', 'q', 'r', 'c'),
					array('c', 'r', 'q', 'c', 'c'),   /* the live row */
					array('q', 'c', 'r', 'q', 'r'),
				);
				foreach ($m3i_rows as $ry => $m3i_row):
					foreach ($m3i_row as $rx => $kind): ?>
					<i class="m3i-t m3i-k-<?php echo $kind; ?>"
					   data-x="<?php echo $rx; ?>" data-y="<?php echo $ry; ?>"></i>
				<?php endforeach; endforeach; ?>
				<?php /* The hand. A CSS ring rather than a cursor glyph, which
				         is a different shape on every platform. */ ?>
				<i id="m3i-hand" aria-hidden="true"></i>
			</div>
		</div>

		<p id="m3i-say">
			Drag a tile as far as you like along its row or column. Everything it
			passes shifts back one place to make room.
		</p>
		<p id="m3i-say2">
			That is the whole trick: a match three cells away is one move, not three.
			<?php echo htmlspecialchars($game); ?> is won by the players who use it.
		</p>

		<button type="button" id="m3i-go">Got it</button>
	</div>
</div>
<style>
#m3i { position: fixed; inset: 0; z-index: 99996; display: flex;
  align-items: center; justify-content: center; padding: 18px;
  background-color: rgba(4,12,22,.92);
  background-image: radial-gradient(78% 58% at 50% 38%,
    rgba(13,36,55,.62) 0, rgba(4,12,22,0) 70%);
  backdrop-filter: blur(3px); }
#m3i.off { display: none; }
#m3i-box { width: min(380px, 100%); max-height: 94vh; overflow: auto;
  background-color: #0a1929; background-image: var(--msp-lift);
  border: 1px solid #1b3346; border-radius: 4px;
  box-shadow: 0 24px 70px rgba(0,0,0,.62), inset 0 1px 0 rgba(255,255,255,.07);
  padding: 18px 20px 20px; text-align: center; color: #e8eaed;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
#m3i-box, #m3i-box * { text-align: center; }
#m3i-kick { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase;
  color: #6f8ba3; }
#m3i-title { margin: 4px 0 12px; font-size: 1.22rem; letter-spacing: .03em;
  color: #00c8a0; text-transform: none; }

/* ---- the board ------------------------------------------------------- */
#m3i-stage { display: flex; justify-content: center;
  background-color: #071320; border-radius: 3px; padding: 14px 0;
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.035); }
/* --m3i-c is the pitch: one tile plus one gap. Every offset below is a
   multiple of it, so the board resizes by changing one number. */
#m3i-grid { --m3i-s: 38px; --m3i-g: 5px; --m3i-c: calc(var(--m3i-s) + var(--m3i-g));
  position: relative;
  width: calc(var(--m3i-c) * 5 - var(--m3i-g));
  height: calc(var(--m3i-c) * 3 - var(--m3i-g)); }
.m3i-t { position: absolute; width: var(--m3i-s); height: var(--m3i-s);
  left: 0; top: 0; border-radius: 4px;
  transform: translate(calc(var(--m3i-c) * var(--dx, 0)), calc(var(--m3i-c) * var(--dy, 0)));
  transition: transform .42s cubic-bezier(.3,.8,.3,1), opacity .22s linear,
              box-shadow .2s linear;
  display: flex; align-items: center; justify-content: center; }
/* Three shapes, not three colours alone: colour-blind players get the
   same information, and the platform's own three accents carry it. */
.m3i-t::before { content: ""; display: block; }
.m3i-k-c { background: rgba(0,200,160,.16); }
.m3i-k-c::before { width: 56%; height: 56%; border-radius: 50%; background: #00c8a0; }
.m3i-k-q { background: rgba(230,164,73,.14); }
.m3i-k-q::before { width: 50%; height: 50%; border-radius: 2px; background: #e6a449; }
.m3i-k-r { background: rgba(138,73,230,.16); }
.m3i-k-r::before { border-left: 11px solid transparent;
  border-right: 11px solid transparent; border-bottom: 18px solid #8a49e6; }
/* The grabbed tile and the match it lands in. */
.m3i-t.lift { box-shadow: 0 0 0 2px #9affdf, 0 6px 16px rgba(0,0,0,.5); z-index: 2; }
.m3i-t.pop  { animation: m3iPop .5s ease-out; }
@keyframes m3iPop {
  0%   { box-shadow: 0 0 0 0 rgba(154,255,223,0); }
  35%  { box-shadow: 0 0 0 3px rgba(154,255,223,.85); }
  100% { box-shadow: 0 0 0 10px rgba(154,255,223,0); }
}
/* The hand: a ring that rides the grabbed tile. */
#m3i-hand { position: absolute; left: 0; top: 0;
  width: var(--m3i-s); height: var(--m3i-s); border-radius: 50%;
  border: 2px solid rgba(255,255,255,.9); box-shadow: 0 0 10px rgba(0,0,0,.6);
  opacity: 0; pointer-events: none; z-index: 3;
  transform: translate(calc(var(--m3i-c) * var(--dx, 0)), calc(var(--m3i-c) * var(--dy, 0))) scale(.72);
  transition: transform .42s cubic-bezier(.3,.8,.3,1), opacity .18s linear; }
#m3i-hand.on { opacity: 1; }

#m3i-say  { margin: 13px 0 0; font-size: .82rem; line-height: 1.45; color: #cfe3d9; }
#m3i-say2 { margin: 7px 0 0; font-size: .76rem; line-height: 1.45; color: #8fa7bb; }
#m3i-go { width: 100%; margin-top: 16px; padding: 11px 14px; font: inherit;
  font-size: .95rem; letter-spacing: .08em; text-transform: uppercase;
  color: #04121c; background-color: #00c8a0; background-image: var(--msp-fill);
  border: 0; border-radius: 3px; cursor: pointer;
  box-shadow: 0 2px 0 rgba(0,0,0,.3); }
#m3i-go:hover { background-color: #2ae0bd; }
#m3i-go:active { background-image: var(--msp-fill-in);
  transform: translateY(2px); box-shadow: 0 0 0 rgba(0,0,0,.3); }
#m3i-go:focus-visible { outline: 2px solid #04121c; outline-offset: -4px; }

@media (max-width: 420px) { #m3i-grid { --m3i-s: 32px; --m3i-g: 4px; } }
/*
 * REDUCED MOTION KEEPS THE LESSON AND DROPS THE LOOP. The board is
 * still the point, so it is left in its STARTING state with the hand
 * showing where the drag begins, and the caption carries the rest. An
 * animation nobody asked to watch running forever is exactly what the
 * preference is about.
 */
@media (prefers-reduced-motion: reduce) {
  .m3i-t, #m3i-hand { transition: none; }
  .m3i-t.pop { animation: none; }
}
</style>
<script>
(function () {
	var veil = document.getElementById('m3i');
	if (!veil) return;
	var grid = document.getElementById('m3i-grid');
	var hand = document.getElementById('m3i-hand');
	var tiles = {};
	/*
	 * The tiles are addressed by where they START, not by where they are.
	 * The whole demo is one move and a reset, so a fixed handle on each
	 * tile is simpler than tracking a board, and the reset is just
	 * putting every tile back on its own coordinates.
	 */
	Array.prototype.forEach.call(grid.querySelectorAll('.m3i-t'), function (t) {
		tiles[t.getAttribute('data-x') + ',' + t.getAttribute('data-y')] = t;
		place(t, +t.getAttribute('data-x'), +t.getAttribute('data-y'));
	});
	function place(el, x, y) {
		el.style.setProperty('--dx', x);
		el.style.setProperty('--dy', y);
	}

	/* The live row is y=1: c t s c c. Dragging the circle at x=0 to x=2
	   leaves t s c c c -- three circles from x=2. */
	var ROW = 1, FROM = 0, TO = 2;
	var calm = window.matchMedia
	        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	var timers = [];
	function at(ms, fn) { timers.push(setTimeout(fn, ms)); }
	function clear() { timers.forEach(clearTimeout); timers = []; }

	function reset() {
		Array.prototype.forEach.call(grid.querySelectorAll('.m3i-t'), function (t) {
			t.classList.remove('lift', 'pop');
			t.style.opacity = '';
			place(t, +t.getAttribute('data-x'), +t.getAttribute('data-y'));
		});
		hand.classList.remove('on');
		place(hand, FROM, ROW);
	}

	function run() {
		clear();
		reset();
		if (calm) { hand.classList.add('on'); return; }   /* the still frame */

		var moved = tiles[FROM + ',' + ROW];
		/* Grab. */
		at(520, function () {
			hand.classList.add('on');
			moved.classList.add('lift');
		});
		/* Drag. The grabbed tile goes to TO; everything it passes steps
		   back one, which is exactly what slideTiles() does to the board. */
		at(980, function () {
			place(moved, TO, ROW);
			place(hand, TO, ROW);
			for (var x = FROM + 1; x <= TO; x++) place(tiles[x + ',' + ROW], x - 1, ROW);
		});
		/* Let go, and the three that are now in a line go off. */
		at(1520, function () {
			moved.classList.remove('lift');
			hand.classList.remove('on');
		});
		at(1760, function () {
			[TO, TO + 1, TO + 2].forEach(function (x) {
				var t = tiles[(x === TO ? FROM : x) + ',' + ROW];
				if (t) t.classList.add('pop');
			});
		});
		at(2320, function () {
			[TO, TO + 1, TO + 2].forEach(function (x) {
				var t = tiles[(x === TO ? FROM : x) + ',' + ROW];
				if (t) t.style.opacity = '.15';
			});
		});
		at(3400, run);
	}
	run();

	function close() {
		clear();
		veil.classList.add('off');
		veil.setAttribute('aria-hidden', 'true');
	}
	document.getElementById('m3i-go').addEventListener('click', close);
	/* Escape closes it, like any dialog. Nothing else does: a stray tap
	   on the veil dismissing the one thing that explains the game is how
	   this fails to do its job. */
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !veil.classList.contains('off')) close();
	});
	veil.setAttribute('aria-hidden', 'false');
	document.getElementById('m3i-go').focus();
})();
</script>
