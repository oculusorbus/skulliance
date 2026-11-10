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
		<?php /* It names BOTH axes and takes two lines to do it. The one
		         thing this overlay exists to remove is an assumption, so a
		         shorter title that leaves one of them to be inferred would
		         be the bug in the headline. */ ?>
		<h4 id="m3i-title">Tiles travel the whole row or column</h4>

		<?php /* THE DEMO. Five by five, and it performs TWO moves: a row
		         slide from one edge to the other, then a column slide the
		         same way. Two, because one move along one axis leaves the
		         other axis to be guessed at, and the whole problem being
		         fixed is players not guessing. Edge to edge, because the
		         distance IS the lesson -- a short hop still looks like a
		         swap with extra steps.

		         Built here rather than in script so it is in the markup a
		         screen reader and a no-JS browser meet; the script only
		         moves what is already on the page. */ ?>
		<div id="m3i-stage">
			<div id="m3i-grid" aria-hidden="true">
				<?php
				/*
				 * THIS BOARD IS SOLVED, NOT SKETCHED. It was searched for
				 * under three conditions, and all three are load-bearing:
				 *
				 *   1. no three-in-a-row anywhere to begin with, or the
				 *      demo opens on a board the game would already have
				 *      cleared;
				 *   2. row 1 dragged x0 -> x4 lands EXACTLY ONE match,
				 *      horizontal at x2..x4;
				 *   3. column 1 dragged y0 -> y4 lands EXACTLY ONE match,
				 *      vertical at y2..y4.
				 *
				 * Exactly one each way, because a second accidental match
				 * would fire somewhere the demo never highlights and read
				 * as the board doing something unexplained.
				 *
				 * Both lines are c q r c c, which is what makes the rotation
				 * work: the tile that travels the full length meets the pair
				 * that was already sitting at the far end. A neighbour swap
				 * cannot reach either match from the corner, which is the
				 * argument the whole overlay exists to make.
				 *
				 * c circle, q square, r triangle. NOT 't' for triangle: the
				 * base class on every tile is .m3i-t, so a triangle came out
				 * as class="m3i-t m3i-t" and its own rule never matched.
				 */
				$m3i_rows = array(
					array('r', 'c', 'r', 'q', 'q'),
					array('c', 'q', 'r', 'c', 'c'),   /* the row that slides */
					array('r', 'r', 'q', 'r', 'c'),
					array('q', 'c', 'q', 'c', 'q'),
					array('q', 'c', 'c', 'r', 'c'),
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

		<div id="m3i-leg" aria-hidden="true"><span id="m3i-axis">Sliding a row</span></div>

		<p id="m3i-say">
			Drag a tile as far as you like along its row <em>or its column</em> -
			the whole way to the far edge if you want. Everything it passes shifts
			back one place to make room.
		</p>
		<p id="m3i-say2">
			That is the whole trick: a match four cells away is one move, not four.
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
#m3i-title { margin: 4px 0 12px; font-size: 1.1rem; line-height: 1.3; letter-spacing: .03em;
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
  height: calc(var(--m3i-c) * 5 - var(--m3i-g)); }
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

/* The axis, named. The two moves show it, and this leaves nothing to
   infer from them. */
#m3i-leg { margin: 10px 0 0; font-size: .68rem; letter-spacing: .14em;
  text-transform: uppercase; color: #00c8a0; min-height: 1em; }
#m3i-say em { font-style: normal; color: #00c8a0; }
#m3i-say  { margin: 9px 0 0; font-size: .82rem; line-height: 1.45; color: #cfe3d9; }
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

@media (max-width: 420px) { #m3i-grid { --m3i-s: 30px; --m3i-g: 4px; } }
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

	/*
	 * TWO MOVES, BOTH EDGE TO EDGE.
	 *
	 * One move along one axis leaves the other axis to be guessed at,
	 * and players not guessing is the entire problem. And the DISTANCE
	 * is the lesson: a two-cell hop still reads as a swap with extra
	 * steps, where a tile crossing the whole board cannot be mistaken
	 * for one.
	 *
	 * The board is solved for exactly these two (see the note on the
	 * markup): each lands one match and only one.
	 */
	var LAST = 4;
	var MOVES = [
		{ axis: 'row', line: 1, from: 0, to: LAST, label: 'Sliding a row' },
		{ axis: 'col', line: 1, from: 0, to: LAST, label: 'Sliding a column' }
	];
	var calm = window.matchMedia
	        && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	var timers = [];
	function at(ms, fn) { timers.push(setTimeout(fn, ms)); }
	function clear() { timers.forEach(clearTimeout); timers = []; }

	/* Where cell i on this move's line lives, as [x, y]. One function so
	   the whole sequence below is written once for both axes. */
	function cellOf(mv, i) { return mv.axis === 'row' ? [i, mv.line] : [mv.line, i]; }
	function tileOf(mv, i) { var c = cellOf(mv, i); return tiles[c[0] + ',' + c[1]]; }
	function put(el, cell) { place(el, cell[0], cell[1]); }

	function reset() {
		Array.prototype.forEach.call(grid.querySelectorAll('.m3i-t'), function (t) {
			t.classList.remove('lift', 'pop');
			t.style.opacity = '';
			place(t, +t.getAttribute('data-x'), +t.getAttribute('data-y'));
		});
		hand.classList.remove('on');
	}

	var axisEl = document.getElementById('m3i-axis');
	var at_ = 0;

	function scene(n) {
		var mv = MOVES[n % MOVES.length];
		clear();
		reset();
		put(hand, cellOf(mv, mv.from));
		axisEl.textContent = mv.label;
		if (calm) {                       /* the still frame, no loop */
			hand.classList.add('on');
			tileOf(mv, mv.from).classList.add('lift');
			return;
		}

		var moved = tileOf(mv, mv.from);
		/* Grab. */
		at(460, function () {
			hand.classList.add('on');
			moved.classList.add('lift');
		});
		/*
		 * Drag the whole way. The grabbed tile goes to the far edge and
		 * every tile it passes steps one place back toward where it
		 * started -- which is exactly what the board does: the move
		 * rotates the span, it does not swap two cells.
		 */
		at(900, function () {
			put(moved, cellOf(mv, mv.to));
			put(hand, cellOf(mv, mv.to));
			for (var i = mv.from + 1; i <= mv.to; i++) put(tileOf(mv, i), cellOf(mv, i - 1));
		});
		/* Let go. */
		at(1600, function () {
			moved.classList.remove('lift');
			hand.classList.remove('on');
		});
		/*
		 * The three that are now in a line. After the rotation the far
		 * end holds the travelling tile and the two that were already
		 * sitting there, so the match is always {from, to-1, to} in
		 * STARTING coordinates -- which is how they are addressed.
		 */
		function trio(fn) {
			[mv.from, mv.to - 1, mv.to].forEach(function (i) {
				var t = tileOf(mv, i); if (t) fn(t);
			});
		}
		at(1860, function () { trio(function (t) { t.classList.add('pop'); }); });
		at(2420, function () { trio(function (t) { t.style.opacity = '.15'; }); });
		at(3500, function () { scene(n + 1); });
	}
	scene(0);

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
