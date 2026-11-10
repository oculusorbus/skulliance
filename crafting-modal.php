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
	<?php /* THE ACTION IS THE HEADING, the pair is the detail. It read the
	         other way round -- a small grey "CONVERT" over a large teal
	         "CORE POINTS TO DIAMOND" -- which gave the loudest line on the
	         dialog to the two currencies and whispered the verb. The verb
	         is the thing being confirmed.

	         BOTH ARE THE NAME. aria-labelledby takes the two ids, so a
	         screen reader still announces "Convert, core points to
	         DIAMOND" rather than losing half of it to the restyle. */ ?>
	<div id="cft-box" role="dialog" aria-modal="true" aria-labelledby="cft-title cft-sub">
		<h4 id="cft-title">Crafting</h4>
		<div id="cft-sub"></div>

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
				<?php /* A UNIT VECTOR, NOT A POSITION. The direction belongs to
				         the markup -- six points, 60 degrees apart, generated so
				         convert and shatter cannot drift apart -- but the RADIUS
				         has to change with the breakpoint, and PHP cannot know
				         which breakpoint a phone will land on. The stage carries
				         --cft-r and .cft-orb multiplies, so one number per size
				         moves the whole ring and every keyframe that reads
				         --cx/--cy follows it unchanged. */ ?>
				<span class="cft-orb" style="--ux: <?php echo round(cos($ang), 4); ?>;
				      --uy: <?php echo round(sin($ang), 4); ?>;
				      --cd: <?php echo round($i * 0.055, 3); ?>s;">
					<img src="icons/<?php echo $slug; ?>.png" alt=""
					     onerror="this.style.display='none'">
				</span>
				<?php endforeach; ?>
			</div>
			<img id="cft-diamond" src="icons/diamond.png" alt="" onerror="this.style.display='none'">
			<img id="cft-carbon" src="icons/carbon.png" alt="" onerror="this.style.display='none'">
			<?php /* A CANVAS, not divs. Seven spans on a straight path read as
			         a row of dots; fire is a crowd of soft blobs blended
			         additively, which is a thing only a canvas can do. */ ?>
			<canvas id="cft-flame" width="232" height="232" aria-hidden="true"></canvas>
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
/*
 * DEPTH, THE SAME WAY THE CLAIM DIALOG GETS IT.
 *
 * The orbs, the diamond and the flame are all lit objects, and they
 * were sitting in a dialog made of flat rectangles. This borrows the
 * platform's own tokens -- --msp-lift, --msp-lift-soft, --msp-fill,
 * --msp-fill-in, --msp-ghost, declared in dist/polish.css -- rather
 * than inventing gradients for one dialog.
 *
 * AND THEY GO IN background-image, NOT THE `background` SHORTHAND.
 * polish.css is one <link> and ?polish=0 turns it off without a deploy
 * while this partial always loads; a shorthand whose custom property is
 * missing is invalid at computed-value time and falls back to the
 * initial value, which is TRANSPARENT. Split in two, a missing token
 * drops only the gradient and the colour underneath survives. Same note
 * as missions-claim-modal.php, same reason.
 */
#cft-veil { position: fixed; inset: 0; z-index: 99997; display: none;
  align-items: center; justify-content: center; padding: 20px;
  background-color: rgba(4,12,22,.9);
  background-image: radial-gradient(78% 58% at 50% 38%,
    rgba(13,36,55,.62) 0, rgba(4,12,22,0) 70%);
  backdrop-filter: blur(3px); }
#cft-veil.on { display: flex; }
#cft-box { width: min(392px, 100%); max-height: 94vh; overflow: auto;
  background-color: #0a1929; background-image: var(--msp-lift);
  border: 1px solid #1b3346; border-radius: 4px;
  box-shadow: 0 24px 70px rgba(0,0,0,.62), inset 0 1px 0 rgba(255,255,255,.07);
  padding: 18px 20px 20px;
  text-align: center; color: #e8eaed;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  animation: cftIn .3s cubic-bezier(.2,.9,.3,1.2); }
@keyframes cftIn { from { transform: scale(.9) translateY(14px); opacity: 0; }
  to { transform: none; opacity: 1; } }

#cft-title { margin: 0; font-size: 1.35rem; letter-spacing: .04em; color: #00c8a0; }
#cft-sub { margin: 3px 0 0; font-size: .72rem; letter-spacing: .14em;
  text-transform: uppercase; color: #6f8ba3; }

/*
 * THE STAGE CLIPS, AND IS SIZED SO IT DOES NOT HAVE TO. A transform that
 * escapes a scrollable ancestor adds scrollable overflow to it, and the
 * one thing that must not happen mid-craft is the dialog growing a
 * scrollbar. 276px square holds a 104px ring plus a 34px orb.
 */
/*
 * SQUARE, AND BIGGER THAN THE RING.
 *
 * It was round for a while, on the argument that what happens in it is
 * round. That was wrong in a way that is obvious the moment you look:
 * the orbs sit ON the ring, so at six points the circle's edge passes
 * straight through them -- a 232px circle clips at radius 116 and an
 * orb on a 92px ring reaches 109 plus its own corners. The six were
 * cut off at exactly their widest, and the animation had no room left
 * around it.
 *
 * 276 against a ring that reaches 121 leaves 17px of clearance all
 * round, and square means the corners are clearance too rather than
 * the tightest part of the frame.
 */
#cft-stage { position: relative; width: 276px; height: 276px; margin: 10px auto 2px;
  overflow: hidden; border-radius: 6px;
  --cft-r: 104px;
  background-color: #071320;
  background-image:
    radial-gradient(60% 60% at 50% 50%, rgba(0,200,160,.07) 0, rgba(0,200,160,0) 72%),
    linear-gradient(180deg, rgba(0,0,0,.26) 0, rgba(0,0,0,0) 64px);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.035),
              inset 0 10px 22px -12px rgba(0,0,0,.6); }
#cft-ring { position: absolute; inset: 0; }
/* The radius lands here. Every keyframe below reads --cx/--cy and none
   of them had to change. */
.cft-orb { position: absolute; left: 50%; top: 50%; width: 34px; height: 34px;
  margin: -17px 0 0 -17px; opacity: 0;
  --cx: calc(var(--ux) * var(--cft-r));
  --cy: calc(var(--uy) * var(--cft-r));
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
/* Covers the whole stage and sits over the lump, because fire wraps the
   thing that is burning rather than standing beside it. Nothing here is
   interactive, and the diamond is drawn after it in the markup so it
   comes up THROUGH the flame at the end. */
#cft-flame { position: absolute; inset: 0; width: 100%; height: 100%;
  opacity: 0; pointer-events: none; transition: opacity .35s linear; }
#cft-veil.go.k-burn #cft-flame { opacity: 1; }
#cft-veil.done.k-burn #cft-flame { opacity: 0; }
/* The lump itself: heats, chars, collapses. */
#cft-veil.go.k-burn #cft-carbon { animation: cftChar 1.65s ease-in forwards; }
/* IT HEATS, IT DOES NOT CHAR. The flame is the brand green, so a lump
   glowing orange underneath it would be the one thing on the stage
   arguing with the rest. It brightens into the same green and collapses. */
@keyframes cftChar {
  0%   { opacity: 1; filter: none; transform: scale(1); }
  35%  { opacity: 1; filter: brightness(1.5) drop-shadow(0 0 6px rgba(0,200,160,.6)); transform: scale(1.05); }
  70%  { opacity: .8; filter: brightness(2.1) drop-shadow(0 0 11px rgba(0,200,160,.8)); transform: scale(.9); }
  100% { opacity: 0; filter: brightness(3) drop-shadow(0 0 16px rgba(0,200,160,.9)); transform: scale(.5); }
}
#cft-veil.go.k-burn #cft-flash { animation: cftFlash .75s ease-out 1.45s forwards; }
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
/* A tinted panel, so the SOFT lift: the full one's black bottom edge
   over a red tint is brown, which is polish.css's own note. */
#cft-error { margin: 12px 0 0; padding: 9px 11px; font-size: .8rem; color: #ffd4d4;
  background-color: rgba(224,122,122,.1); background-image: var(--msp-lift-soft);
  border: 1px solid rgba(224,122,122,.35);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.05); }

#cft-actions { display: flex; gap: 10px; margin-top: 16px; }
/* flexbox.css styles bare buttons globally, so everything the dialog
   needs is re-stated here rather than inherited. */
#cft-actions button { flex: 1; padding: 11px 14px; font: inherit; font-size: .9rem;
  letter-spacing: .08em; text-transform: uppercase; text-align: center;
  border: 0; border-radius: 3px; cursor: pointer; }
/* The sheen inside --msp-fill IS the top edge; an inset white line on
   top of it reads as a second border drawn around the button. */
#cft-go { color: #04121c; background-color: #00c8a0; background-image: var(--msp-fill);
  box-shadow: 0 2px 0 rgba(0,0,0,.3); }
#cft-go:hover { background-color: #2ae0bd; }
/* Pressed is the ramp upside down and the slab dropping onto its own
   shadow -- the two moves the platform's buttons make. */
#cft-go:active:not(:disabled) { background-image: var(--msp-fill-in);
  transform: translateY(2px); box-shadow: 0 0 0 rgba(0,0,0,.3); }
#cft-go:disabled { opacity: .45; cursor: default; box-shadow: none; }
/* The outline control stays quieter than the solid beside it: a ghost
   button that shouts is worse than a flat one. */
#cft-cancel { color: #9fb6c4; background-color: rgba(255,255,255,.06);
  background-image: var(--msp-ghost); border: 1px solid rgba(255,255,255,.12); }
#cft-cancel:hover { color: #e8eaed; background-color: rgba(255,255,255,.12); }
/* Inset, in the button's own ink. Offset outward in pale mint it reads
   as a second border around the slab rather than as part of it -- and it
   cannot simply go, it is the only thing telling a keyboard user where
   they are. */
#cft-actions button:focus-visible { outline: 2px solid #04121c; outline-offset: -4px; }
#cft-cancel:focus-visible { outline-color: #9affdf; }

@media (max-width: 480px) {
  /* THE RING SHRINKS WITH THE STAGE NOW, which it never did: the
     radius was baked into the markup at 92px, so a 196px stage clipped
     at 98 while the orbs still reached 106. One number moves it. */
  #cft-stage { width: 212px; height: 212px; --cft-r: 78px; }
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
  #cft-ring, .cft-orb, #cft-diamond, #cft-carbon, #cft-flash { animation: none !important; }
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

	/* action is the heading, detail is the line under it. Named for what
	   they ARE rather than where they sit, because they swapped places. */
	var KIND = {
		convert: { action: 'Convert', detail: 'Core points to DIAMOND',
		           lede: function (n) { return 'Spending ' + n + ' of each of the six core points.'; } },
		shatter: { action: 'Shatter', detail: 'DIAMOND to core points',
		           lede: function (n) { return 'Breaking ' + n + ' DIAMOND back into core points.'; } },
		burn:    { action: 'Burn',    detail: 'CARBON to DIAMOND',
		           lede: function (n) { return 'Burning ' + n + ' CARBON. 100 CARBON forms 1 DIAMOND.'; } }
	};

	/*
	 * ===== THE FLAME ========================================================
	 *
	 * The first version was seven <span>s with a linear-gradient, rising on a
	 * straight line. Reported, correctly, as "a row of orange dots". Divs
	 * cannot be fire: there are too few of them, they do not overlap into a
	 * mass, and a straight path has no lick in it.
	 *
	 * THREE THINGS MAKE IT READ AS FLAME, and all three were missing.
	 *
	 * 1. ADDITIVE BLENDING. globalCompositeOperation = 'lighter' is the whole
	 *    trick: where particles overlap, their light SUMS, so a crowd of
	 *    faint blobs becomes one bright body with a hot core instead of a
	 *    handful of discrete dots. Without it no amount of particles helps.
	 *
	 * 2. A HOT CORE AND A COOL TIP. Colour is a function of AGE, not a fixed
	 *    fill: almost white where it is born, through the brand green, to a
	 *    dark green that fades out at the top. Fire is a temperature
	 *    gradient; one colour is confetti. The ramp is pre-rendered into 24
	 *    tinted sprites once, because tinting per particle per frame is the
	 *    thing that makes this too slow to run.
	 *
	 * 3. TURBULENCE. Every reference implementation I checked gives particles
	 *    an initial velocity and lets them fly straight, which is why they
	 *    spray rather than lick. Each particle here carries its own sine
	 *    phase and frequency, and the amplitude GROWS as it rises, so the
	 *    column is tight at the base and whips at the tip.
	 *
	 * It is green because a green flame is read as magic rather than as bad
	 * fire -- the motion has to be convincing, the colour does not have to be
	 * plausible.
	 */
	var FIRE = (function () {
		var cv = document.getElementById('cft-flame');
		if (!cv || !cv.getContext) return { start: function () {}, stop: function () {} };
		var g = cv.getContext('2d');

		/* THE RAMP, birth to death. Near-white at the base because that is
		   where a flame is hottest, saturating through the brand green, then
		   darkening out. The alpha is low on purpose: with additive blending
		   the brightness comes from the crowd, not from any one blob. */
		var RAMP = [
			[235, 255, 250], [170, 255, 238],                     /* the core, and it is BRIEF */
			[ 96, 242, 212], [ 42, 224, 189], [  0, 200, 160],    /* the brand green, the body */
			[  0, 186, 150], [  0, 168, 135], [  0, 146, 117],
			[  0, 120,  97], [  2,  94,  76], [  3,  66,  54], [  2,  40,  33]
		];
		var STEPS = 24, sprites = [];
		function mix(a, b, t) { return [
			Math.round(a[0] + (b[0] - a[0]) * t),
			Math.round(a[1] + (b[1] - a[1]) * t),
			Math.round(a[2] + (b[2] - a[2]) * t)
		]; }
		(function build() {
			for (var i = 0; i < STEPS; i++) {
				var f = i / (STEPS - 1) * (RAMP.length - 1);
				var lo = Math.floor(f), hi = Math.min(RAMP.length - 1, lo + 1);
				var c = mix(RAMP[lo], RAMP[hi], f - lo);
				var s = document.createElement('canvas');
				s.width = s.height = 48;
				var sg = s.getContext('2d');
				/* A SOFT RADIAL BLOB, not a filled circle. A hard edge reads
				   as a dot however many of them there are; the falloff is what
				   lets neighbours melt into one another. */
				var rg = sg.createRadialGradient(24, 24, 0, 24, 24, 24);
				var rgb = c[0] + ',' + c[1] + ',' + c[2];
				/* The near-white steps are drawn fainter than the green ones.
				   They are also the densest part of the flame, so at equal
				   alpha they add up to a flat white lozenge instead of a
				   glowing core. */
				var k = i < 4 ? 0.62 + i * 0.095 : 1;
				rg.addColorStop(0,   'rgba(' + rgb + ',' + (0.60 * k).toFixed(3) + ')');
				rg.addColorStop(0.3, 'rgba(' + rgb + ',' + (0.22 * k).toFixed(3) + ')');
				rg.addColorStop(0.7, 'rgba(' + rgb + ',' + (0.05 * k).toFixed(3) + ')');
				rg.addColorStop(1,   'rgba(' + rgb + ',0)');
				sg.fillStyle = rg;
				sg.fillRect(0, 0, 48, 48);
				sprites.push(s);
			}
		})();

		/*
		 * WHEN THE FIRE GOES OUT, and it has to be before the DIAMOND
		 * arrives rather than after. It used to burn at full strength until
		 * the whole sequence settled, so the diamond formed THROUGH a fire
		 * still going, and then everything cut at once.
		 *
		 * BURNOUT_AT stops the spawner; the particles already in the air
		 * live out their lives and rise away while FADE_FRAMES dims what is
		 * left. Fire starves, it does not switch off, and the difference is
		 * the whole reason not to simply call stop().
		 *
		 * These two numbers have to land on or before the diamond's own
		 * delay in the CSS above. The harness checks that they do.
		 */
		var BURNOUT_AT = 1250;      /* ms after start: stop feeding it */
		var FADE_FRAMES = 30;       /* ~500ms at 60fps for the rest to die */

		var W = 0, H = 0, dpr = 1, parts = [], raf = 0, tick = 0, running = false;
		var fadeAt = 0, startedAt = 0;

		function size() {
			dpr = Math.min(2, window.devicePixelRatio || 1);
			var r = cv.getBoundingClientRect();
			W = Math.round(r.width); H = Math.round(r.height);
			cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
			g.setTransform(dpr, 0, 0, dpr, 0, 0);
		}

		function spawn(n) {
			for (var i = 0; i < n; i++) {
				/* Triangular across the mouth, so the body is dense in the
				   middle and frays at the edges rather than being a slab. */
				var j = (Math.random() + Math.random() - 1);
				var ember = Math.random() < 0.04;
				parts.push({
					/* A 30px mouth, not a 46px one: the first pass was wider
					   than it was tall, which is a bonfire seen from above
					   rather than a flame. */
					ox:   W / 2 + j * 15,
					y:    H * 0.66 + Math.random() * 5,
					vy:   -(0.35 + Math.random() * 0.5) * (ember ? 1.8 : 1),
					life: 0,
					/* Long enough to actually climb. At this buoyancy and drag
					   a particle settles near 2.5px a frame, so ~60 frames is
					   the ~130px of travel the stage has room for. */
					max:  (ember ? 85 : 52) + Math.random() * (ember ? 30 : 26),
					sz:   (ember ? 3.5 : 10 + Math.random() * 9) * (1 - Math.abs(j) * 0.3),
					ph:   Math.random() * 6.283,
					fr:   0.07 + Math.random() * 0.1,
					amp:  (ember ? 15 : 7) + Math.random() * 9,
					drift: j * 0.18,
					ember: ember
				});
			}
		}

		function frame() {
			if (!running) return;
			tick++;
			g.clearRect(0, 0, W, H);
			g.globalCompositeOperation = 'lighter';

			/* Starving, not stopping: no new fuel past BURNOUT_AT, and what
			   is still in the air dims as it rises. */
			var starving = (Date.now() - startedAt) >= BURNOUT_AT;
			var fade = 1;
			if (starving) {
				if (!fadeAt) fadeAt = tick;
				fade = 1 - (tick - fadeAt) / FADE_FRAMES;
				if (fade <= 0) { out(); return; }
			} else {
				/* FLICKER. Two sines that do not share a period, so the rate
				   never settles into a pulse you can count. */
				var rate = 17 + Math.sin(tick * 0.21) * 4 + Math.sin(tick * 0.077) * 3;
				spawn(Math.max(2, Math.round(rate)));
			}

			for (var i = parts.length - 1; i >= 0; i--) {
				var p = parts[i];
				p.life++;
				var t = p.life / p.max;
				if (t >= 1) { parts.splice(i, 1); continue; }

				p.vy -= 0.05;           /* buoyancy */
				p.vy *= 0.981;          /* drag */
				p.y  += p.vy;
				/* The curl widens as it climbs: tight at the mouth, whipping
				   at the tip. This is the part that separates fire from a
				   fountain. */
				var cx = W / 2;
				var x = cx + (p.ox - cx) * (1 - 0.72 * t)   /* taper to a tip */
				      + p.drift * p.life
				      + Math.sin(p.ph + p.life * p.fr) * p.amp * t * t;

				var s = sprites[Math.min(STEPS - 1, (t * STEPS) | 0)];
				/* THE SILHOUETTE: pinched at the mouth, widest about a third
				   of the way up, tapering to a tip. A blob that only ever
				   shrinks has no shape to it. */
				var d = p.ember ? p.sz * (1 - t * 0.5)
				                : p.sz * (0.35 + 0.65 * Math.sin(Math.PI * Math.min(1, t * 1.15)));
				if (d <= 0.5) continue;
				/* Fade in fast, out slowly: a particle that appears at full
				   strength pops, and fire has no pops in it. */
				g.globalAlpha = Math.min(1, t * 9) * Math.pow(1 - t, 1.3) * (p.ember ? 1 : 0.9) * fade;
				g.drawImage(s, x - d / 2, p.y - d / 2, d, d);
			}
			g.globalAlpha = 1;
			g.globalCompositeOperation = 'source-over';
			raf = requestAnimationFrame(frame);
		}

		function out() {
			running = false;
			if (raf) cancelAnimationFrame(raf);
			raf = 0; parts = []; fadeAt = 0;
			if (W) g.clearRect(0, 0, W, H);
		}

		return {
			start: function () {
				if (running) return;
				/* Reduced motion gets no loop at all; the CSS already puts
				   the stage in its finished state. */
				if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
				size();
				parts = []; tick = 0; fadeAt = 0; running = true;
				startedAt = Date.now();
				spawn(40);                      /* catch, rather than ramp up */
				raf = requestAnimationFrame(frame);
			},
			/* The hard stop stays, for close() and for a refusal: those are
			   not a fire going out, they are a dialog leaving. */
			stop: out
		};
	})();

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
		document.getElementById('cft-title').textContent = KIND[kind].action;
		document.getElementById('cft-sub').textContent   = KIND[kind].detail;
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
		if (state.kind === 'burn') FIRE.start();

		var settled = false;
		var reply   = null;
		var ran     = false;

		var finish = function () {
			if (settled || !ran || reply === null) return;
			settled = true;
			FIRE.stop();
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
		/* The loop is a requestAnimationFrame chain; without this it keeps
		   running against a hidden canvas for as long as the tab is open. */
		FIRE.stop();
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
		if (kind === 'burn') FIRE.start();
		at(RUNTIME[kind], function () {
			FIRE.stop();
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
