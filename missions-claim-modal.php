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

		<?php /* THE TITLE IS A HEADING, so it sits with the kicker at the
		         top rather than between the chest and the row of things
		         that came out of it. In the middle it broke the one
		         sentence the reveal is telling -- the chest opens, THESE
		         came out of it -- with a word about neither. */ ?>
		<h4 id="mcm-title">Claimed</h4>

		<?php /* THE PANE HOLDS THE WHOLE REVEAL, chest and rewards both.
		         The icons fly out of the chest, so landing them outside
		         the panel the chest stands in read as them leaving the
		         scene. #mcm-plinth is the chest's own floor inside it:
		         the contact shadow and the pool of light hang off that
		         rather than off the pane, so they stay under the chest
		         however tall the pane grows. */ ?>
		<div id="mcm-stage">
			<div id="mcm-plinth">
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

			<?php /* Filled by SK_CLAIM_SHOW from the claim's own reply, so what
			         is shown is what was actually paid rather than what was
			         promised. */ ?>
			<div id="mcm-items"></div>
		</div>

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
/*
 * DEPTH, BECAUSE THE CHEST HAS IT AND NOTHING ELSE DID.
 *
 * The art is a rendered three-quarter object with its own light on it,
 * and it was sitting in a dialog made of flat rectangles: a flat veil,
 * a flat panel, a flat teal slab, and 230px of nothing for the chest to
 * stand on. The object read as pasted onto the UI rather than held by
 * it.
 *
 * THE PANELS USE THE PLATFORM'S OWN TOKENS, not gradients invented
 * here. --msp-lift, --msp-lift-soft and --msp-fill are declared in
 * dist/polish.css and every panel and button on the platform already
 * wears them; a dialog with its own private idea of depth would be the
 * one lit thing that is lit differently.
 *
 * AND THEY GO IN background-image, NOT THE `background` SHORTHAND.
 * polish.css is one <link> and ?polish=0 turns it off without a deploy
 * -- but THIS file always loads. `background: var(--msp-lift), #0a1929`
 * is invalid at computed-value time when the token is missing, and an
 * invalid background shorthand falls back to the initial value, which
 * is transparent: the dialog would lose its surface entirely. Split in
 * two, a missing token drops only the gradient layer and the colour
 * underneath it survives. That is the whole reason this does not just
 * copy polish.css's own rules.
 *
 * The stage's own gradients ARE local, and deliberately: a pool of
 * light on a floor is about this chest, not about panels.
 */
#mcm-veil { position: fixed; inset: 0; z-index: 99998; display: none;
  align-items: center; justify-content: center; padding: 20px;
  background-color: rgba(4,12,22,.9);
  /* Lit where the dialog is, so the box sits in the brightest part of
     its own backdrop instead of being cut out of a flat sheet. */
  background-image: radial-gradient(78% 58% at 50% 38%,
    rgba(13,36,55,.62) 0, rgba(4,12,22,0) 70%);
  backdrop-filter: blur(3px); }
#mcm-veil.on { display: flex; }
#mcm-box { width: min(392px, 100%); max-height: 94vh; overflow: auto;
  background-color: #0a1929; background-image: var(--msp-lift);
  border: 1px solid #1b3346; border-radius: 4px;
  box-shadow: 0 24px 70px rgba(0,0,0,.62), inset 0 1px 0 rgba(255,255,255,.07);
  padding: 18px 20px 20px;
  text-align: center; color: #e8eaed;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  animation: mcmIn .3s cubic-bezier(.2,.9,.3,1.2); }
@keyframes mcmIn { from { transform: scale(.9) translateY(14px); opacity: 0; }
  to { transform: none; opacity: 1; } }

/*
 * CENTRED ON EVERY CHILD, not just on the box.
 *
 * This dialog renders INSIDE .ms-daily, and missions.php carries
 * `.ms-daily, .ms-daily * { text-align: left }` -- a universal
 * descendant selector, so it hits every element in here directly and a
 * direct rule always beats an inherited one. text-align:center on
 * #mcm-box alone therefore centred nothing: the kicker, the title, the
 * labels and the countdown all came out flush left on the real page
 * while admin-chest.php, which renders the same markup outside
 * .ms-daily, looked exactly as designed.
 *
 * `#mcm-box *` is (1,0,0) against that rule's (0,1,0), so it wins
 * wherever this dialog is mounted. Same trap as flexbox.css's global
 * `a` and `.main` -- a scoped component restates its own alignment.
 */
#mcm-box, #mcm-box * { text-align: center; }

#mcm-kicker { font-size: .72rem; letter-spacing: .14em; text-transform: uppercase;
  color: #6f8ba3; }
/* text-transform STATED, for the same reason the alignment is. It is an
   <h4>, and admin-css.php carries `.adm h4 { text-transform: uppercase }`
   -- so the preview rendered CLAIMED while the real page rendered
   Claimed. A preview that disagrees with the page is worse than no
   preview, and the component owns its own case either way. */
#mcm-title { margin: 4px 0 10px; font-size: 1.5rem; letter-spacing: .04em; color: #00c8a0;
  text-transform: none; }

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
/*
 * THE STAGE IS A NICHE NOW, not 230px of panel.
 *
 * Three layers, and each one answers something the flat version left
 * open. The fill is a step darker than the dialog, so the chest stands
 * IN something. The top shade is the recess. The faint teal at the
 * bottom is the floor picking up the chest's own light, which is the
 * one colour already in the art.
 */
/*
 * THE PANE IS THE WHOLE SCENE, chest and rewards together.
 *
 * The icons fly out of the chest's mouth, and they used to land on the
 * dialog OUTSIDE the panel the chest stands in -- which reads as them
 * leaving the picture rather than arriving in it. The pane holds both
 * now, so the flight starts and ends in the same room.
 *
 * Height is content-driven for the same reason: the row is one line for
 * most days and two when a name wraps, and a fixed pane would either
 * clip it or leave a gap under it.
 */
#mcm-stage { position: relative; display: flex; flex-direction: column;
  align-items: center; padding: 0 10px 14px;
  margin: 6px 0 2px; overflow: hidden;
  border-radius: 3px;
  background-color: #071320;
  /*
   * ONE LIGHT, NOT TWO. This used to carry a teal radial of its own --
   * always on, independent of the chest opening -- so the pool below
   * was always being read on top of a second glow with a different
   * centre. It made the pool impossible to judge and it was doing a
   * job the pool does better. The shade stays: that is the recess,
   * not a light.
   */
  background-image:
    linear-gradient(180deg, rgba(0,0,0,.30) 0, rgba(0,0,0,0) 70px);
  box-shadow: inset 0 0 0 1px rgba(255,255,255,.03); }
/*
 * THE PLINTH IS THE CHEST'S FLOOR, and it exists so the shadow and the
 * pool stay under the chest rather than at the bottom of a pane whose
 * height now depends on how long a reward is called.
 *
 * THE 28px IS NOT PADDING FOR LOOKS. The art fills its own frame -- the
 * alpha box of open.webp is 4px in from every edge of 480x497 -- so at
 * 210x217 the chest's base is under two pixels off the bottom of its
 * box. Flush to the floor there was nowhere for a contact shadow to
 * fall: it spread into the chest on one side and into overflow:hidden
 * on the other, cropped to a hard line exactly where it should have
 * been softest. height is the BORDER box (* { box-sizing: border-box }
 * is global), so 258 is 230 of chest room plus the 28 beneath it.
 */
#mcm-plinth { position: relative; width: 100%;
  display: flex; align-items: flex-end; justify-content: center;
  height: 248px; padding-bottom: 18px; }
/*
 * THE FLOOR LIGHTS UP WHEN THE LID DOES, and :has is what makes that a
 * stylesheet's job rather than another class for the script to toggle.
 * Where it is not supported the pool simply stays at the stage's own
 * level, which is the picture above -- nothing breaks, it just does not
 * brighten.
 */
/*
 * THE GLOW IS THE CONTACT. THERE IS NO DARK SHADOW, and that is the
 * whole answer to three rounds of "the chest is still floating".
 *
 * A contact shadow is how an object that BLOCKS light sits on a floor.
 * This chest is a light source -- it is open, lit from inside, throwing
 * green onto everything near it -- so black underneath it is not a
 * shadow, it is a hole. Measured down the centre column of the render:
 * with the shadow at .5 alpha there were nineteen rows of exactly zero
 * green straddling the chest's base, the chest's own glow above them
 * and the pool below them, and that dead band IS the gap. The sides
 * never showed it, because the shadow is darkest at its middle.
 *
 * So the floor lights up instead, brightest right where the chest
 * stands on it. A glowing object seats itself by lighting what it
 * stands on.
 *
 * AND IT LIES IN THE CHEST'S GROUND PLANE -- which is not level, not
 * where the chest's BOX ends, and not a line. All three numbers are
 * measured off open.webp's alpha channel rather than chosen: for each
 * column, the lowest opaque row is where the chest meets the floor.
 *
 *     ANGLE    least-squares slope -0.0826 = -4.7 degrees,
 *              rising to the right
 *     DEPTH    the footprint spans 80.1% to 98.4% of the art's
 *              height -- 18.3%, which is 40px on a 217px chest
 *     CENTRE   its midpoint is 89.2% down, so 23px ABOVE the
 *              bottom of the chest's own box
 *
 * THE SHIPPED NUMBERS ARE THE OWNER'S, NOT THESE, and the gap between
 * them is worth keeping. Dragged against the real art in the admin
 * tuner, what looks right is a glow centred 79.3% down the chest --
 * 22px HIGHER than the footprint's midpoint, 45px above the bottom of
 * the box -- 118px deep, and raked at only -1.8 degrees.
 *
 * Flatter and higher than the contour, and both make sense: the pool
 * is light on a floor seen from slightly above, so it foreshortens
 * toward level, and light under a lit object pushes back UNDER it
 * rather than pooling at the front edge. The measurement was the right
 * way to find the bug -- it is what proved the box bottom was 23px
 * below where the chest touches -- but it was never going to be the
 * last word on how it looks.
 *
 * THE ANGLE, because the chest is drawn in three-quarter view and a
 * level ellipse under it is a floor at a different angle from the one
 * it is standing on -- which reads as hovering over a puddle.
 *
 * THE CENTRE, because every earlier version was centred on the bottom
 * of the chest's BOX, which is 23px below where the chest actually
 * touches. That is why pushing the chest down onto the glow fixed it
 * by hand.
 *
 * THE DEPTH, because a 3/4 view has a footprint rather than a contact
 * line: front-left foot to back-right corner is 40px of floor, and a
 * glow thinner than that lights a stripe across the middle of it and
 * leaves both ends dark. The ellipse is 96px -- comfortably more than
 * the footprint, because a pool of light does not stop at the object
 * making it.
 *
 * ITS OWN BOX, NOT inset:0 ON THE PLINTH. A background is clipped to
 * its own element, so as a full-bleed layer centred near the bottom the
 * glow was cut off at the plinth's edge in a hard line. A sized box has
 * nothing to clip it: the only clip here is the pane's, far outside.
 */
#mcm-plinth::before { content: ""; position: absolute; z-index: 0;
  left: 50%; bottom: 4px; width: 320px; height: 118px;
  transform: translateX(-50%) rotate(-1.8deg);
  /*
   * HALF LIT BEFORE IT OPENS, FULL AFTER.
   *
   * The chest arrives shut and rattles for two thirds of a second
   * before the lid goes, and starting the floor at black meant the
   * light only existed after the payoff. At half it is already
   * glowing through a closed box -- something in there, before you
   * are shown what -- and the open doubles it.
   *
   * SAFE IN BOTH STATES because it is the same floor: closed.webp and
   * open.webp have identical footprints, 80.1% to 98.4% of the art,
   * midpoint 89.2%. The glow does not have to move when the lid does.
   *
   * The ramp is the transition below, not an animation, so it also
   * runs on a browser without :has -- that one just never reaches
   * full, which is a dimmer reveal rather than a broken one.
   */
  pointer-events: none; opacity: .5;
  /* Five stops, not three. Three gave a bright line with nothing
     either side of it; the middle pair are what put light across the
     whole footprint and then let it fall away instead of ending. */
  background: radial-gradient(50% 50% at 50% 50%,
    rgba(0,200,160,0.46) 0%,
    rgba(0,200,160,0.288) 27%,
    rgba(0,200,160,0.115) 51%,
    rgba(0,200,160,0.029) 69%,
    rgba(0,200,160,0.0) 84%);
  transition: opacity .55s ease-out .08s; }
#mcm-plinth:has(.mcm-chest.is-open)::before { opacity: 1; }
/* Reset puts the chest back to shut, so the floor goes back to half
   with it -- a second claim starts where the first one did. */
/* No ::after here on purpose. See the note on the pool above: a dark
   contact under a light source is a hole, not a shadow. */
/* Above both of them. The flash and the six sparks are its children, so
   they come up with it. */
.mcm-chest { position: relative; z-index: 1;
  width: 210px; height: 217px; --mcrise: -72px; }
.mcm-l { position: absolute; inset: 0; width: 100%; height: 100%;
  object-fit: contain; pointer-events: none; }
.mcm-open, .mcm-glow { opacity: 0; }
.mcm-glow { transform: scale(.72); transform-origin: 50% 62%; }

/*
 * THE RATTLE, WHICH IS THE WHOLE POINT OF WAITING.
 *
 * The dialog used to open the chest 240ms in and that was the entire
 * build-up: a lid swap you could miss. Something is trying to get out
 * first, and it gets louder -- half a degree and one pixel at the
 * start, two degrees and four by the end -- so the pop lands on a
 * pattern the eye has already started following.
 *
 * IT ENDS ON `none`, deliberately. The class comes off at the same
 * instant the lid swaps, and a keyframe that finished mid-throw would
 * snap the chest back four pixels under the burst. Settling to true
 * centre on the last beat also IS the anticipation: everything goes
 * still, and then it opens.
 *
 * MCM_SHAKE in the script below is this duration. The two have to agree
 * or the chest either opens mid-rattle or sits still waiting for it.
 */
/*
 * 2s, NOT .66s, AND THE NUMBER IS MEASURED -- the rattle builds across
 * the wreath's swell to land the lid on its first hit. See MCM_SHAKE in
 * the script, which must agree with this and which the harness compares.
 *
 * THE BEAT IS THE ORIGINAL'S; ONLY THE LENGTH AND THE FORCE CHANGED.
 * Keyframes are percentages, so simply stretching .66s to 2s slowed the
 * oscillation threefold and the chest went from rattling to swaying.
 * The original is eight half-swings in 660ms -- a 73ms beat -- so this
 * is twenty-seven of them across 2s, which is the same pace held three
 * times as long.
 *
 * The escalation moved into the amplitudes, growing on p^1.7 so most of
 * it arrives late: one pixel and half a degree at the start, six pixels
 * and over three degrees by the end. linear, not ease-in, because the
 * build is in how hard it shakes rather than in how fast.
 */
.mcm-chest.is-shaking { animation: mcmShake 2s linear both; }
@keyframes mcmShake {
  0%   { transform: none; }
  3.6% { transform: translate3d( -1.0px, -0.4px, 0) rotate(-0.51deg); }
  7.1% { transform: translate3d(  1.1px, -0.4px, 0) rotate( 0.53deg); }
  10.7%{ transform: translate3d( -1.1px, -0.5px, 0) rotate(-0.57deg); }
  14.3%{ transform: translate3d(  1.2px, -0.5px, 0) rotate( 0.61deg); }
  17.9%{ transform: translate3d( -1.3px, -0.6px, 0) rotate(-0.66deg); }
  21.4%{ transform: translate3d(  1.4px, -0.6px, 0) rotate( 0.72deg); }
  25.0%{ transform: translate3d( -1.5px, -0.7px, 0) rotate(-0.79deg); }
  28.6%{ transform: translate3d(  1.6px, -0.8px, 0) rotate( 0.87deg); }
  32.1%{ transform: translate3d( -1.8px, -0.9px, 0) rotate(-0.95deg); }
  35.7%{ transform: translate3d(  1.9px, -1.0px, 0) rotate( 1.04deg); }
  39.3%{ transform: translate3d( -2.1px, -1.1px, 0) rotate(-1.13deg); }
  42.9%{ transform: translate3d(  2.3px, -1.2px, 0) rotate( 1.23deg); }
  46.4%{ transform: translate3d( -2.4px, -1.3px, 0) rotate(-1.34deg); }
  50.0%{ transform: translate3d(  2.6px, -1.4px, 0) rotate( 1.45deg); }
  53.6%{ transform: translate3d( -2.8px, -1.5px, 0) rotate(-1.57deg); }
  57.1%{ transform: translate3d(  3.1px, -1.7px, 0) rotate( 1.69deg); }
  60.7%{ transform: translate3d( -3.3px, -1.8px, 0) rotate(-1.82deg); }
  64.3%{ transform: translate3d(  3.5px, -2.0px, 0) rotate( 1.96deg); }
  67.9%{ transform: translate3d( -3.8px, -2.1px, 0) rotate(-2.10deg); }
  71.4%{ transform: translate3d(  4.0px, -2.3px, 0) rotate( 2.24deg); }
  75.0%{ transform: translate3d( -4.3px, -2.4px, 0) rotate(-2.39deg); }
  78.6%{ transform: translate3d(  4.5px, -2.6px, 0) rotate( 2.55deg); }
  82.1%{ transform: translate3d( -4.8px, -2.8px, 0) rotate(-2.71deg); }
  85.7%{ transform: translate3d(  5.1px, -2.9px, 0) rotate( 2.87deg); }
  89.3%{ transform: translate3d( -5.4px, -3.1px, 0) rotate(-3.04deg); }
  92.9%{ transform: translate3d(  5.7px, -3.3px, 0) rotate( 3.22deg); }
  96.4%{ transform: translate3d( -6.0px, -3.5px, 0) rotate(-3.40deg); }
  100% { transform: none; }
}

/* Opening: the lid swaps fast, the light takes its time arriving. */
.mcm-chest.is-open .mcm-closed { opacity: 0; transition: opacity .16s linear; }
.mcm-chest.is-open .mcm-open { opacity: 1; transition: opacity .16s linear; }
.mcm-chest.is-open .mcm-glow { opacity: 1; transform: scale(1);
  transition: opacity .42s ease-out .06s, transform .52s cubic-bezier(.16,1.1,.3,1) .06s; }
.mcm-chest.is-settled .mcm-glow { animation: mcmPulse 3.6s ease-in-out 1.2s infinite; }
/*
 * THE LOOP STARTS WHERE THE OPENING LEFT OFF, and that is the whole
 * reason these numbers are this way round.
 *
 * The glow arrives at opacity 1 and the pulse then waits 1.2s before it
 * runs. There is no fill-mode, so through that delay the light stays at
 * 1 -- and the moment the animation started, 0% snapped it to .82. A
 * visible stutter, a second and a half after the chest opens, from the
 * brightest thing on screen to a dimmer one in a single frame.
 *
 * So the loop brightest-first: 1 at both ends, .82 in the middle. Its
 * first frame is now identical to the state it takes over from, the
 * join is invisible, and the light breathes down and back instead of
 * dropping and climbing.
 */
@keyframes mcmPulse { 0%,100% { opacity: 1; } 50% { opacity: .82; } }

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
  justify-content: center; gap: 10px 18px; margin: 0;
  position: relative; z-index: 1; }
.mcm-item { display: flex; flex-direction: column; align-items: center; gap: 4px;
  width: 124px; opacity: 0;
  transform: translate(var(--mcmdx, 0px), var(--mcmdy, 0px)) scale(.35); }
.mcm-item.in { opacity: 1; transform: none;
  transition: transform .74s cubic-bezier(.2,.85,.25,1), opacity .3s linear; }
.mcm-item img { width: 44px; height: 44px; object-fit: contain;
  filter: drop-shadow(0 0 10px rgba(0,200,160,.55));
  /* A socket behind the glyph. These fly out of a lit chest and then sat
     on flat panel; the backing is what they carry out with them. */
  background-image: radial-gradient(50% 50% at 50% 54%,
    rgba(0,200,160,.15) 0, rgba(0,200,160,0) 70%); }
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
/* A tinted panel, so the SOFT lift: a black bottom edge over a teal
   tint is brown, which is the note in polish.css's own token. */
#mcm-trait { margin: 10px 0 0; padding: 8px 10px; font-size: .78rem; color: #cfe3d9;
  background-color: rgba(0,200,160,.08); background-image: var(--msp-lift-soft);
  border: 1px solid rgba(0,200,160,.22);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.05); }

#mcm-actions { margin-top: 16px; }
/* flexbox.css styles bare buttons and anchors globally, so everything the
   dialog needs is re-stated here rather than inherited. */
#mcm-close { width: 100%; padding: 11px 14px; font: inherit; font-size: .95rem;
  letter-spacing: .08em; text-transform: uppercase; text-align: center;
  color: #04121c; background-color: #00c8a0; background-image: var(--msp-fill);
  border: 0; border-radius: 3px; cursor: pointer;
  /* The sheen in --msp-fill IS the top edge. An inset white line on top
     of it reads as a second border drawn around the button rather than
     as the light on it. */
  box-shadow: 0 2px 0 rgba(0,0,0,.3); }
#mcm-close:hover { background-color: #2ae0bd; }
/* Pressed is the ramp upside down and the slab dropping onto its own
   shadow -- the same two moves the platform's buttons make. */
#mcm-close:active { background-image: var(--msp-fill-in);
  transform: translateY(2px); box-shadow: 0 0 0 rgba(0,0,0,.3); }
/*
 * THE FOCUS RING SITS INSIDE THE BUTTON, not around it.
 *
 * The dialog moves focus here as it opens, so this ring is on screen
 * for most of the reveal -- and offset outward in pale mint it read as
 * a second border drawn around the slab rather than as part of it. It
 * cannot just go: it is the only thing telling a keyboard user where
 * they are. Inset and in the button's own ink it is still unmistakable
 * against the teal and it belongs to the button.
 */
#mcm-close:focus-visible { outline: 2px solid #04121c; outline-offset: -4px; }

/* The rise is in pixels, so it follows the rendered size or the sparks
   travel a third of a chest here. One variable, set wherever the size is. */
@media (max-width: 480px) {
  /* Same floor, scaled: 186 of chest room plus 22 underneath. Without
     the padding here the phone gets back exactly the crop this fixed. */
  #mcm-plinth { height: 192px; padding-bottom: 16px; }
  /* The owner's setting scaled by the chest: his glow sits 79.3% down
     a 217px chest, so on a 176px one that is 139.5px from the chest's
     top -- which with a 96px ellipse lands on the same bottom: 4px.
     The angle is a camera angle, not a size, so it does not change. */
  #mcm-plinth::before { bottom: 4px; width: 260px; height: 96px; }
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
  /* The script drops the rattle's delay too, so the chest does not sit
     still for two thirds of a second waiting for an animation that was
     never going to run. */
  .mcm-chest.is-shaking { animation: none; }
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

	/*
	 * THE RATTLE'S LENGTH LIVES IN TWO PLACES AND MUST MATCH: this
	 * number and the .66s on .mcm-chest.is-shaking above. Too short and
	 * the chest opens mid-throw; too long and it sits still waiting.
	 *
	 * REDUCED MOTION TAKES THE DELAY, NOT JUST THE ANIMATION. Setting
	 * the CSS animation to none on its own would leave the dialog
	 * staring at a shut chest for two thirds of a second for exactly
	 * the players who asked for less of this.
	 */
	var MCM_CALM     = window.matchMedia
	                 && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/*
	 * THE PICTURES ARE CUT TO THE SOUND, AND THESE THREE NUMBERS ARE
	 * MEASURED OFF IT RATHER THAN CHOSEN.
	 *
	 * quest_award_wreath.ogg is not a hit: it is a two-second swell that
	 * lands, and then a second smaller hit just after. Read off the file
	 * at 10ms resolution -- the swell begins at 0.30s, peaks hard at
	 * 2.34s (the loudest frame in the whole file), and the second hit is
	 * at 2.93s.
	 *
	 * So the rattle starts when the swell does and runs until the lid,
	 * the lid goes on the peak, and the icons land on the second hit.
	 * Nothing here is a feel number any more; if the sound is ever
	 * replaced these have to be re-measured.
	 */
	var MCM_SFX_HIT1 = 2340;   /* the wreath's peak -- measured */
	var MCM_SFX_HIT2 = 2930;   /* its second hit -- measured */

	var MCM_SHAKE_AT = 300;    /* where the swell becomes audible */
	/* 40ms of stillness before the lid, which is what makes the pop land
	   -- so the rattle has to stop that much before the peak. */
	var MCM_SHAKE    = MCM_CALM ? 0 : (MCM_SFX_HIT1 - MCM_SHAKE_AT - 40);
	var MCM_OPEN     = MCM_SHAKE ? MCM_SHAKE_AT + MCM_SHAKE + 40 : 240;
	/* The icons fly on the second hit. Expressed as a distance from the
	   lid, because everything after the open already is -- and under
	   reduced motion, where there is no sound to hit, it keeps the
	   original 190ms. */
	var MCM_KIDS_AT  = MCM_CALM ? 190 : (MCM_SFX_HIT2 - MCM_SFX_HIT1);

	/*
	 * TWO SOUNDS, ON THE TWO BEATS THE ANIMATION ALREADY HAS.
	 *
	 * The wreath lands as the modal opens -- the reward is announced
	 * before the chest does anything -- and the diamonds land ON TOP of
	 * it when the lid goes, which is why they are layered rather than
	 * sequenced. The second does not wait for the first to finish.
	 *
	 * OFF THE SAME CLOCK AS THE PICTURES. MCM_OPEN is the lid, and it
	 * moves whenever the rattle does; a hardcoded delay here would drift
	 * away from the thing it is supposed to be hitting.
	 *
	 * PLAIN <audio>, NOT THE ARENA'S MIXER. That one decodes into an
	 * AudioContext so it can reverse and pitch-shift a stack of layers.
	 * Two files played once at a fixed volume need none of it, and the
	 * mixer would be two hundred lines on a partial that header.php
	 * includes on every page.
	 *
	 * ALLOWED TO FAIL, EVERY TIME. Autoplay policy, a missing file, a
	 * codec a browser will not take: none of those may stop a claim
	 * being shown. The chest is the reveal; the sound is a bonus on top
	 * of it. (This one is safe in practice -- the modal only opens from
	 * a tap on Claim, which is the user gesture autoplay asks for.)
	 */
	var MCM_SFX = {
		open: ['sounds/quest_award_wreath.ogg', 0.55],
		lid:  ['sounds/diamond_mine_treasurefind_diamonds.ogg', 0.75],
		/* Collect. A press wants an answer, and this one closes the
		   modal -- so it is the last thing heard rather than something
		   layered over the reveal. */
		tick: ['sounds/tick.ogg', 0.6]
	};
	var mcmAudio = {};
	function mcmSfx(key) {
		if (MCM_CALM) return;          /* reduced motion takes the noise with it */
		var def = MCM_SFX[key];
		if (!def) return;
		try {
			var a = mcmAudio[key];
			if (!a) { a = mcmAudio[key] = new Audio(def[0]); a.volume = def[1]; }
			a.currentTime = 0;
			var pr = a.play();
			if (pr && pr.catch) pr.catch(function () {});
		} catch (e) {}
	}

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

		/*
		 * ONE CLOCK, HUNG OFF THE OPEN.
		 *
		 * The chest is shut when the dialog arrives, rattles, and then
		 * opens -- so the opening is something you watch rather than
		 * something that already happened behind the fade. Everything
		 * after it is written as an offset FROM the open rather than as
		 * an absolute, because the rattle moves that moment and three
		 * hardcoded timestamps would have had to move with it.
		 *
		 * The 40ms between the rattle finishing and the lid going is not
		 * slack: it is the beat of stillness that makes the pop land.
		 */
		mcmSfx('open');
		at(MCM_SHAKE_AT, function () { chest.classList.add('is-shaking'); });
		at(MCM_OPEN, function () {
			chest.classList.remove('is-shaking');
			chest.classList.add('is-open', 'is-bursting');
			/* On the lid, over the wreath rather than after it. */
			mcmSfx('lid');
		});
		at(MCM_OPEN + 1300, function () {
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
			at(MCM_OPEN + MCM_KIDS_AT, function () { k.classList.add('in'); });
		});
	};

	function reset() {
		timers.forEach(clearTimeout);
		timers = [];
		chest.classList.remove('is-shaking', 'is-open', 'is-bursting', 'is-settled');
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

	document.getElementById('mcm-close').addEventListener('click', function () {
		mcmSfx('tick');
		close();
	});
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
