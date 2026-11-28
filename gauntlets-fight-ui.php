<?php
/**
 * gauntlets-fight-ui.php -- the look, the noise and the music.
 *
 * Included ONCE by gauntlets.php, outside #gf-area. That placement is the
 * whole trick: the fragment swaps on every turn, and anything inside it
 * would be destroyed and rebuilt each time -- which for an <audio>
 * element means the soundtrack restarting on every swing.
 */
if (defined('GF_UI_DONE')) return;
define('GF_UI_DONE', 1);
?>
<style>
/* ── the fight ─────────────────────────────────────────────────────── */
.gf              { margin-bottom: 24px; position: relative; }
/*
 * THE OPPONENT'S WORLD. The encounter is staged in the theme of the
 * project their NFT comes from, reusing the Monstrocity theme art that
 * already exists per project. Heavily dimmed on purpose: it is a
 * backdrop, and a busy picture behind a health bar is just noise.
 */
.gf-has-bg::before { content: ''; position: absolute; inset: -14px -14px 0;
                     background-image: var(--gf-bg); background-size: cover;
                     background-position: center; opacity: .22;
                     pointer-events: none; z-index: 0;
                     mask-image: linear-gradient(180deg, #000 0%, #000 55%, transparent 100%);
                     -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 55%, transparent 100%); }
.gf-has-bg > *   { position: relative; z-index: 1; }
.gf-arena        { display: grid; grid-template-columns: 1fr auto 1fr; gap: 14px;
                   align-items: start; margin-bottom: 14px; }
/* Its own centred strip under the arena, NOT in the VS column: that
   column is `auto` in a 1fr/auto/1fr grid, so anything wide in there
   comes straight out of the two portraits on a phone. */
.gf-match        { display: flex; align-items: center; justify-content: center; gap: 8px;
                   margin: 0 0 12px; font-size: .68rem; letter-spacing: .1em;
                   text-transform: uppercase; color: rgba(255,255,255,.45); }
.gf-match i      { font-style: normal; font-weight: 700; letter-spacing: .04em;
                   padding: 2px 7px; border: 1px solid currentColor; }
.gf-match.is-good i { color: #00c8a0; }
.gf-match.is-even i { color: rgba(255,255,255,.55); }
.gf-match.is-bad  i { color: #ff5c5c; }
.gf-vs           { align-self: center; font-size: 1.1rem; font-weight: 800;
                   color: rgba(255,255,255,.28); letter-spacing: .08em; }
/* The cap lives on the whole side, not just the portrait, so the bar and
   the name line up with the picture instead of running wider than it. */
.gf-side         { min-width: 0; width: 100%; max-width: min(190px, 42vw);
                   margin: 0; justify-self: center; }
/* CAPPED, and that matters more than it sounds. Unconstrained these grew
   to fill half the grid column -- on a 900px page two 420px portraits and
   nothing else, with the bars, the log and the choices all below the
   fold. A fight you have to scroll to play is not a fight you read. */
.gf-portrait     { position: relative; border: 2px solid rgba(255,255,255,.1); overflow: hidden; background: #002f44;
                   margin: 0 auto; }
.gf-you .gf-portrait { border-color: #00c8a0; }
.gf-foe .gf-portrait { border-color: rgba(224,85,85,.55); }
/* --gf-flip is -1 on a side whose art faces the wrong way. A variable
   rather than a transform of its own, so the downed state below can
   compose with it instead of replacing it. */
.gf-portrait img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block;
                   transform: scaleX(var(--gf-flip, 1));
                   filter: drop-shadow(0 3px 8px rgba(0,0,0,.5)); }
/* The glow layer. Colour says WHAT happened, which is how a player reads
   an exchange without looking at the log. */
.gf-fx           { position: absolute; inset: 0; pointer-events: none; opacity: 0;
                   box-shadow: inset 0 0 26px 8px currentColor; color: transparent; }
/* Under the NFT's name, smaller: the card is the fighter, the person
   is who sent it. Kept to one line with the name clipped rather than
   wrapped, so adding it cannot push the arena down a phone screen. */
.gf-owner        { display: flex; align-items: center; justify-content: center; gap: 5px;
                   margin: -1px 0 6px; min-width: 0; }
.gf-owner img    { width: 15px; height: 15px; border-radius: 50%; object-fit: cover;
                   flex: 0 0 auto; }
.gf-owner span   { font-size: .66rem; color: rgba(255,255,255,.5);
                   white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gf-side.is-down .gf-owner { opacity: .45; }
.gf-name         { font-size: .8rem; font-weight: 600; color: #fff; margin: 7px 0 5px;
                   text-align: center; white-space: nowrap; overflow: hidden;
                   text-overflow: ellipsis; }

/* ── health, with the damage trail ─────────────────────────────────── */
.gf-bar          { position: relative; height: 20px; background: #0b1b24;
                   border: 1px solid rgba(0,0,0,.6); overflow: hidden;
                   background-image: repeating-linear-gradient(90deg,
                       rgba(0,0,0,.30) 0 1px, rgba(0,0,0,0) 1px 10%);
                   box-shadow: inset 0 1px 3px rgba(0,0,0,.55); }
/* The trail drains a beat behind the fill, so the strip between them IS
   the hit that just landed. */
.gf-trail        { position: absolute; inset: 0 auto 0 0;
                   background: linear-gradient(180deg,#ff8a8a 0%,#c0392b 100%);
                   transition: width .55s cubic-bezier(.33,1,.68,1) .18s; }
.gf-fill         { position: absolute; inset: 0 auto 0 0; transition: width .3s ease;
                   background-image: var(--gf-fill); }
.gf-bar.ok   .gf-fill { background-color: #00c8a0; }
.gf-bar.warn .gf-fill { background-color: #ffc800; }
.gf-bar.crit .gf-fill { background-color: #ff5c5c; }
/* The figure, drawn twice and clipped at the fill edge: dark over the
   colour, white over the empty rail. One ink cannot read against both. */
.gf-num          { position: absolute; inset: 0; pointer-events: none;
                   font: bold 11px/1 Arial, Helvetica, sans-serif;
                   font-variant-numeric: tabular-nums; letter-spacing: .03em; }
.gf-ink          { position: absolute; inset: 0; display: flex; align-items: center;
                   justify-content: center; transition: clip-path .3s ease; }
/* Near-black over the fill: it measures 6:1 or better on every band the
   bar uses, where white is 1.6:1 on the yellow. */
.gf-ink-on       { color: #07140f; text-shadow: 0 1px 0 rgba(255,255,255,.3);
                   clip-path: inset(0 calc(100% - var(--hp-pct, 100%)) 0 0); }
/* White over the empty rail and the damage trail, where black would be
   just as unreadable. */
.gf-ink-off      { color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.95), 0 0 3px rgba(0,0,0,.8);
                   clip-path: inset(0 0 0 var(--hp-pct, 100%)); }
.gf-armour       { font-size: .7rem; color: #9fd8ff; margin-top: 5px; text-align: center; }
.gf-shield       { color: #4fa3d9; }

/* ── the exchange log ──────────────────────────────────────────────── */
.gf-log          { list-style: none; margin: 0; padding: 10px 12px; background: #002f44;
                   border: 1px solid rgba(255,255,255,.07);
                   max-height: 118px; overflow-y: auto; }
.gf-li           { font-size: .78rem; color: rgba(255,255,255,.75); margin: 3px 0; }
.gf-who          { display: inline-block; min-width: 42px; font-weight: 700;
                   text-transform: uppercase; font-size: .66rem; letter-spacing: .07em; }
.gf-you  .gf-who, .gf-li.gf-you  .gf-who { color: #00c8a0; }
.gf-li.gf-foe  .gf-who { color: #e05555; }
.gf-k-heal strong { color: #5ce8b0; }
.gf-k-hit  strong { color: #ff8a8a; }
.gf-soak         { color: rgba(159,216,255,.7); font-size: .7rem; }

/* ── the choices ───────────────────────────────────────────────────── */
.gf-actions      { display: flex; flex-direction: column; gap: 10px; }
.gf-group-label  { text-align: center; font-size: .62rem; text-transform: uppercase; letter-spacing: .12em;
                   color: rgba(255,255,255,.35); margin-bottom: 5px; }
/* CENTRED. Left-aligned, a short row like Defend sat hard against the
   edge under a long Attack row and read as a ragged column rather than
   a set of choices. */
.gf-group-row    { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.gf-btn          { display: inline-flex; align-items: center; gap: 7px; cursor: pointer;
                   background: var(--gf-ghost), #123349; color: #e8eaed;
                   border: 1px solid rgba(0,200,160,.28); padding: 10px 13px; font: 600 .82rem/1 inherit;
                   /* a tap on the label must fire the button, not start a
                      selection -- the missions button taught us that */
                   user-select: none; -webkit-user-select: none; }
/* HOVER ONLY WHERE THERE IS A POINTER. A tap leaves :hover stuck on a
   phone, and this rule outranks .gf-go's own colours (0-3-0 against
   0-1-0), so after one tap Continue sat at the generic hover fill
   while keeping .gf-go's dark ink: black on navy, which reads as
   disabled. REPORTED exactly that way. */
@media (hover: hover) {
  .gf-btn:hover:not(:disabled) { background: var(--gf-ghost), #1a4663; border-color: #00c8a0; }
}
.gf-btn:focus-visible { background: var(--gf-ghost), #1a4663; border-color: #00c8a0; }
.gf-btn:active:not(:disabled) { background: var(--gf-press), #1a4663; }
.gf-btn:disabled { opacity: .35; cursor: default; }
.gf-btn img      { width: 18px; height: 18px; object-fit: contain; }
.gf-left         { font-style: normal; font-size: .68rem; color: #00c8a0;
                   background: rgba(0,0,0,.35); padding: 2px 5px; }
.gf-btn.is-spent .gf-left { color: rgba(255,255,255,.4); }
.gf-heal         { border-color: rgba(92,232,176,.4); }
.gf-stake        { border-color: rgba(255,200,0,.4); color: #ffe08a; }
@media (hover: hover) {
  .gf-stake:hover:not(:disabled) { border-color: #ffc800; background: #2a2a18; }
}
.gf-go           { background: var(--gf-fill), #00c8a0; color: #07111d; border-color: #2ee3bf; }
.gf-go:active    { background: var(--gf-press), #00c8a0; }
/* Matched to the generic hover's specificity so the primary button
   cannot be repainted by it. */
@media (hover: hover) {
  .gf-go:hover:not(:disabled) { background: var(--gf-fill), #00e6b8; color: #07111d; }
}
/* The result is ONE control, so it is not laid out like the stack of
   grouped choices -- as a column child it stretched full width with its
   label hard left, which reads as a broken row rather than a button. */
.gf-done         { display: flex; justify-content: center; margin-top: 4px; }
.gf-done .gf-btn { min-width: 180px; justify-content: center; }
.gf-qty          { font-style: normal; font-weight: 400; color: rgba(255,255,255,.45);
                   font-size: .74rem; }
.gf-loot         { font-style: normal; font-size: .6rem; text-transform: uppercase;
                   letter-spacing: .08em; color: #ffc800; background: rgba(255,200,0,.12);
                   border: 1px solid rgba(255,200,0,.35); padding: 1px 4px; }
.gf-log          { margin-top: 14px; }
/* The Fast Forward button sits directly under this line, and at zero
   bottom margin the two read as one block -- reported as the text being
   too tight against the button. */
.gf-hint         { font-size: .72rem; color: rgba(255,255,255,.4); margin: 10px 0 16px; }
.gf-result       { font-size: 1.1rem; font-weight: 700; text-align: center; padding: 12px; margin-bottom: 12px; }
.gf-result.win   { background: rgba(0,200,160,.14); border: 1px solid #00c8a0; color: #00c8a0; }
.gf-result.loss  { background: rgba(224,85,85,.14); border: 1px solid #e05555; color: #e05555; }
/* The replacement cards in the Fast Forward overlay. The bar is the
   reason the overlay exists, so it gets room; a card already at zero
   is out for the run and must not be selectable. */
.ff-hand-grid .gf-bar { height: 14px; margin-top: 6px; }
.ff-hand-grid .gf-num { font-size: 9px; }
.nft-card.ff-down { opacity: .45; cursor: not-allowed; pointer-events: none; }
.nft-card.ff-down .nft-img { filter: grayscale(1) brightness(.5); }
.ff-down-label   { font-size: .62rem; text-transform: uppercase; letter-spacing: .08em;
                   color: #e05555; margin-top: 4px; }
.gchest          { text-align: center; margin: 18px 0 8px; }
.gchest-btn      { background: none; border: 0; cursor: pointer; display: inline-flex;
                   flex-direction: column; align-items: center; gap: 8px; color: #ffc800;
                   font: 700 .85rem/1 inherit; text-transform: uppercase; letter-spacing: .08em;
                   user-select: none; -webkit-user-select: none; }
/* A block child is NOT centred by the text-align on .gchest, so the
   open chest sat hard left while its caption sat in the middle. The
   button case got away with it by being inline-flex. */
.gchest-img      { width: 150px; max-width: 46vw; height: auto; display: block;
                   margin-left: auto; margin-right: auto;
                   filter: drop-shadow(0 6px 18px rgba(0,0,0,.55)); }
@media (hover: hover) {
  .gchest-btn:hover .gchest-img { transform: translateY(-3px); }
}
.gchest-btn .gchest-img { transition: transform .18s ease; animation: gchestBreathe 2.6s ease-in-out infinite; }
@keyframes gchestBreathe { 0%,100% { filter: drop-shadow(0 6px 18px rgba(0,0,0,.55)); }
                           50% { filter: drop-shadow(0 6px 22px rgba(255,200,0,.45)); } }
/*
 * THE OPEN CHEST, SEATED. Three layers, as in missions-claim-modal.php:
 * the art, its own glow over it, and a pool of light on the floor under
 * it. The pool is what stops it floating -- a glowing object seats
 * itself by lighting what it stands on. Its box is sized and centred on
 * the chest's own footprint rather than stretched over the block, so
 * nothing clips it into a hard edge.
 */
.gchest-art      { position: relative; width: 150px; max-width: 46vw;
                   aspect-ratio: 480 / 497; margin: 0 auto; }
/*
 * THE MODAL'S OWN PROPORTIONS. missions-claim-modal.php puts a 320x118
 * pool under a 210x217 chest -- 152% of its width and 54% of its
 * height -- and those numbers were tuned by eye. I first wrote this at
 * 108% x 24%, which is barely wider than the chest itself, so the
 * gradient had nowhere to fade and its own box edge was visible as a
 * hard line to the left, right and below. Reported exactly that way.
 * The last stop reaches full transparency well inside the box now.
 */
.gchest-art::before { content: ""; position: absolute; z-index: 0;
                   left: 50%; bottom: 2%; width: 152%; height: 54%;
                   transform: translateX(-50%) rotate(-1.8deg);
                   pointer-events: none;
                   /*
                    * BLURRED, SO AN EDGE CANNOT EXIST. Widening the box
                    * and ending the gradient inside it was not enough --
                    * still reported as hard on the left and right. A
                    * blurred layer has no boundary to find, whatever the
                    * stops do, so this stops being a question of tuning
                    * numbers I cannot reproduce from here.
                    */
                   filter: blur(12px);
                   background: radial-gradient(ellipse at 50% 50%,
                       rgba(0,200,160,.58) 0%, rgba(0,200,160,.34) 24%,
                       rgba(0,200,160,.16) 42%, rgba(0,200,160,.05) 62%,
                       rgba(0,200,160,0) 80%); }
.gchest-l        { position: absolute; inset: 0; width: 100%; height: 100%;
                   object-fit: contain; pointer-events: none; z-index: 1;
                   filter: drop-shadow(0 6px 18px rgba(0,0,0,.55)); }
/* Scaled and anchored where the modal anchors it, so the light sits in
   the mouth rather than over the whole box. */
.gchest-glow     { transform-origin: 50% 62%; filter: none; z-index: 2;
                   animation: gchestPulse 3.6s ease-in-out infinite; }
@keyframes gchestPulse { 0%, 100% { opacity: 1; } 50% { opacity: .82; } }
.gchest-title    { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em;
                   color: rgba(255,255,255,.45); margin: 10px 0 8px; }
.gchest-items    { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.gchest-item     { display: inline-flex; align-items: center; gap: 7px;
                   background: rgba(255,200,0,.1); border: 1px solid rgba(255,200,0,.35); padding: 6px 10px; font-size: .8rem; color: #ffe08a;
                   animation: gchestPop .45s cubic-bezier(.2,.8,.3,1) backwards; }
.gchest-item img { width: 20px; height: 20px; object-fit: contain; }
.gchest-item:nth-child(2) { animation-delay: .09s; }
.gchest-item:nth-child(3) { animation-delay: .18s; }
.gchest-item:nth-child(4) { animation-delay: .27s; }
.gchest-item:nth-child(n+5) { animation-delay: .36s; }
@keyframes gchestPop { from { opacity: 0; transform: translateY(10px) scale(.9); }
                       to   { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) {
  .gchest-btn .gchest-img { animation: none; }
  .gchest-glow { animation: none; }
  .gchest-item { animation: none; }
}
.gf-err          { background: rgba(224,85,85,.12); border: 1px solid rgba(224,85,85,.5);
                   color: #ffb3b3; padding: 10px 12px; font-size: .82rem; }

/* ── impact ────────────────────────────────────────────────────────── */
/* Percentage-based, so they scale to any portrait size. Lifted from
   DHC Arena's @keyframes hit / hitBig for exactly that reason. */
@keyframes gfHit    { 0%{transform:translateX(0)} 30%{transform:translateX(-3%)}
                      60%{transform:translateX(2.4%)} 100%{transform:translateX(0)} }
@keyframes gfHitBig { 0%{transform:translateX(0)} 20%{transform:translateX(-5%)}
                      45%{transform:translateX(4%)} 70%{transform:translateX(-2%)}
                      100%{transform:translateX(0)} }
.gf-side.is-hit    .gf-portrait { animation: gfHit .3s ease-out; }
.gf-side.is-hitbig .gf-portrait { animation: gfHitBig .42s ease-out; }
/* The drop-shadow is restated in both frames: filter is ONE property, so
   animating it to `none` drops the portrait's shadow and snaps it back. */
@keyframes gfFlash { 0%   { filter: drop-shadow(0 3px 8px rgba(0,0,0,.5)) brightness(2.5) saturate(.25); }
                     100% { filter: drop-shadow(0 3px 8px rgba(0,0,0,.5)); } }
.gf-side.is-hit .gf-portrait img, .gf-side.is-hitbig .gf-portrait img { animation: gfFlash .24s ease-out; }
.gf-fx.show      { animation: gfGlow .5s ease-out; }
@keyframes gfGlow { 0% { opacity: .85; } 100% { opacity: 0; } }
.gf-fx.c-hit     { color: #ff5c5c; }
.gf-fx.c-heal    { color: #5ce8b0; }
.gf-fx.c-armor   { color: #4fa3d9; }
.gf-fx.c-down    { color: #ff2b2b; }
/*
 * THE FALLEN. Grey, dimmed and slumped -- a fighter that is out should
 * read as out at a glance, without the result text. The transition is
 * long enough to watch rather than a cut.
 */
.gf-side .gf-portrait img { transition: filter .7s ease, opacity .7s ease, transform .7s ease; }
.gf-side.is-down .gf-portrait img { filter: grayscale(1) brightness(.42)
                                            drop-shadow(0 3px 8px rgba(0,0,0,.5));
                                    opacity: .55;
                                    transform: scale(.96) rotate(-2deg) scaleX(var(--gf-flip, 1)); }
.gf-side.is-down .gf-portrait { border-color: rgba(255,255,255,.12) !important; }
.gf-side.is-down .gf-name { color: rgba(255,255,255,.4); }
.gf-side.is-down .gf-portrait::after {
  content: ''; position: absolute; inset: 0; background: rgba(7,17,29,.35); pointer-events: none; }
@keyframes gfSlump { 0%   { transform: scaleX(var(--gf-flip, 1)); }
                     100% { transform: scale(.96) rotate(-2deg) scaleX(var(--gf-flip, 1)); } }
.gf-k-down strong { color: #ff2b2b; }
/* The number, out of flow so it cannot push the card around. */
.gf-float        { position: absolute; left: 50%; top: 36%; transform: translate(-50%,0);
                   font: 900 26px/1 Arial, Helvetica, sans-serif; font-variant-numeric: tabular-nums;
                   pointer-events: none; z-index: 5; color: #ff5f5f;
                   text-shadow: 0 2px 0 #4a0d0d, 0 -1px 0 #4a0d0d, 1px 0 0 #4a0d0d,
                                -1px 0 0 #4a0d0d, 0 0 14px rgba(255,80,80,.6);
                   animation: gfFloat 1s cubic-bezier(.2,.7,.3,1) forwards; }
.gf-float.heal   { color: #5ce8b0; text-shadow: 0 2px 0 #06402c, 0 0 12px rgba(90,230,170,.5); }
.gf-float.big    { font-size: 38px; color: #ff4040; }
@keyframes gfFloat { 0%{opacity:0;transform:translate(-50%,8px) scale(.7)}
                     18%{opacity:1;transform:translate(-50%,-4px) scale(1.12)}
                     30%{transform:translate(-50%,-8px) scale(1)}
                     100%{opacity:0;transform:translate(-50%,-48px) scale(1)} }
@media (prefers-reduced-motion: reduce) {
  .gf-side.is-hit .gf-portrait, .gf-side.is-hitbig .gf-portrait,
  .gf-side.is-hit .gf-portrait img, .gf-side.is-hitbig .gf-portrait img { animation: none; }
  .gf-float { animation-duration: .6s; }
  .gf-trail, .gf-fill { transition: none; }
}
/* Under the game, so the margin is above it, not below. */
.gf-audio        { --ska-accent: #00c8a0; margin: 18px 0 0; }
@media (max-width: 600px) {
  .gf-arena { gap: 8px; }
  .gf-vs { font-size: .85rem; }
  .gf-btn { padding: 9px 11px; font-size: .78rem; }
  .gf-float { font-size: 22px; } .gf-float.big { font-size: 30px; }
}
</style>

<!-- OUTSIDE #gf-area on purpose: the fragment is replaced every turn, and
     an <audio> element inside it would be torn down and rebuilt each time,
     restarting the soundtrack on every swing. -->
<audio id="gf-music" loop preload="none"></audio>

<?php /* The shared helper, because iOS ignores element.volume outright and
         a slider wired the obvious way does nothing on a phone. */
   include __DIR__ . '/sk-audio.php'; ?>
<!-- The STRIP itself is not here: it renders under the game, after
     #g-area, in gauntlets.php. Only the CSS, the helper and the <audio>
     load up here, so nothing flashes unstyled on the way in. -->

<script>
(function () {
  var AREA = 'gf-area';
  /* audio/sounds/, NOT sounds/. Everything 404'd silently on the first
     build because the path was read off the tail of a grep hit. */
  var SND  = '/staking/audio/sounds/';
  var TRK  = '/staking/audio/tracks/';
  /*
   * THREE TRACKS, AND ONLY ONE OF THEM PLAYS DURING A RUN.
   *
   * There were five: the theme swapped for 'frantic' under half health
   * and 'doom' under a quarter, and a won fight cut to 'Triumph'. A
   * round lasts a handful of turns, so none of that read as scoring --
   * it read as the song restarting every time anything happened. The
   * theme now runs from the moment the page opens, through every round,
   * every win and every swap, and is interrupted exactly once: when the
   * RUN ends. Triumph for a swept gauntlet, Death for a wipe.
   */
  var MUSIC = { theme: 'Crypt Crawl Theme.mp3',
                win:   'Crypt Crawl Triumph.mp3',
                lose:  'Crypt Crawl Death.mp3' };
  var cur = null, muted = false, playing = false, paused = false;
  var VOL_DEFAULT = 0.32, vol = VOL_DEFAULT;

  try { muted = localStorage.getItem('gfMute') === '1'; } catch (e) {}
  try {
    var sv = localStorage.getItem('gfVol');
    if (sv !== null && sv !== '' && isFinite(+sv)) vol = Math.max(0, Math.min(1, +sv / 100));
  } catch (e) {}

  /* THROUGH SkAudio, NEVER el.volume -- iOS ignores element volume
     outright, so the slider would move and nothing would happen. */
  function applyVol() {
    var el = document.getElementById('gf-music');
    if (el && window.SkAudio) SkAudio.set(el, muted ? 0 : vol);
    else if (el) el.volume = muted ? 0 : vol;
  }

  /*
   * TWO SOUND LIBRARIES, and they are not interchangeable. The .mp3 set
   * Crypt Crawl uses lives under audio/sounds/; the .ogg set DHC Arena
   * and Monstrocity use lives under sounds/. skullcoinlose is in the
   * second -- it is the cue DHC plays when a fighter is knocked out, and
   * the one a player already associates with something falling.
   */
  var OGG = { ko: 'skullcoinlose' };
  function play(name) {
    if (!name || muted) return;
    var url = OGG[name] ? '/staking/sounds/' + OGG[name] + '.ogg' : SND + name + '.mp3';
    /* SCALED BY THE SLIDER, AND THROUGH SkAudio. These were a flat 0.55
       / 0.5 straight onto a.volume, so the slider moved the music and
       left every punch at full -- and on iOS element volume does
       nothing at all, so they were at full there regardless. The per-cue
       level is kept as a multiplier so the mix survives. */
    try {
      var a = new Audio(url), lvl = (OGG[name] ? 0.55 : 0.5) * vol;
      if (window.SkAudio) { SkAudio.unlock(); SkAudio.set(a, lvl); } else { a.volume = lvl; }
      a.play().catch(function(){});
    } catch (e) {}
  }
  /*
   * THE FIRST play() IS ALWAYS REFUSED. Nothing has been tapped when the
   * page loads, so autoplay is blocked -- and `cur = key` was committed
   * BEFORE that refusal, so every later call for the same mood hit the
   * `cur === key` guard and returned. The track never started for the
   * whole fight unless the health band happened to change. REPORTED as
   * no music on battle.
   *
   * `cur` now only records which track is loaded; whether it is audible
   * is `playing`, and a call that finds it silent retries.
   */
  function music(key) {
    var el = document.getElementById('gf-music');
    if (!el || muted || paused) return;
    if (cur === key && playing && !el.paused) return;
    if (cur !== key) {
      cur = key;
      /* Only the theme loops. The other two mark the end of a run and
         are meant to finish. */
      el.loop = (key === 'theme');
      el.src  = TRK + encodeURIComponent(MUSIC[key]);
    }
    /* Re-applied every call: in the fallback path a new src does not
       carry the old volume with it. */
    applyVol();
    var pr = el.play();
    /*
     * REPAINT WHEN IT SETTLES, not when it is asked. `playing` is only
     * true once play() RESOLVES, which is after the gesture that
     * unlocks audio -- so without this the button sat on the play icon
     * while the music was audibly running. Reported as "why is there
     * only a play button, no pause icon".
     */
    if (pr && pr.then) pr.then(function () { playing = true;  icons(); },
                               function () { playing = false; icons(); });
    else { playing = true; icons(); }
  }
  /*
   * And the retry has to happen ON the gesture. animate() calls music()
   * from inside the fetch .then(), which is a task of its own: iOS has
   * already spent the tap's gesture token by then and refuses there too.
   * This fires synchronously on the first touch anywhere on the page.
   */
  function unlock() {
    if (playing || muted || paused) return;
    /*
     * NO ARENA TEST. This used to require #gf-area, the FIGHT fragment,
     * which only exists during an encounter -- so on the deck screen and
     * the run-over screen the first tap unlocked nothing and the music
     * never started, which also left the button stuck on the play icon.
     * boot() lost the same guard when the theme became page-wide; this
     * is the copy that was missed. moodFrom() reads #g-area, the page.
     */
    music(moodFrom());
  }
  document.addEventListener('pointerdown', unlock, true);
  document.addEventListener('touchstart',  unlock, true);
  /*
   * The PAGE decides, not the fight. #g-area carries the mood the
   * server worked out in gaunt_page_mood(), which is 'theme' for the
   * whole of a run and only changes when the run is over. A fight
   * ending does not touch it, which is what stops the track restarting
   * between rounds.
   */
  function moodFrom() {
    var page = document.getElementById('g-area');
    var m = page ? page.getAttribute('data-mood') : '';
    return MUSIC[m] ? m : 'theme';
  }

  /* Animate exactly what the server said happened, and nothing else. */
  function animate(area) {
    var tl;
    try { tl = JSON.parse(area.getAttribute('data-tl') || '[]'); } catch (e) { tl = []; }
    tl.forEach(function (e, i) {
      setTimeout(function () {
        var side = area.querySelector(e.who === 'you' ? '.gf-foe' : '.gf-you');
        if (e.kind === 'heal' || e.kind === 'armor') side = area.querySelector(e.who === 'you' ? '.gf-you' : '.gf-foe');
        if (!side) return;
        var fx = side.querySelector('.gf-fx');
        /* A FALL NAMES ITS OWN SIDE. Every other entry names the ACTOR,
           so the target is the other one -- `down` is the exception and
           has to short-circuit, or the slump and the red glow land on
           whoever is still standing. */
        if (e.kind === 'down') {
          var dead = area.querySelector(e.who === 'you' ? '.gf-you' : '.gf-foe');
          if (dead) {
            dead.classList.add('is-down');
            var dfx = dead.querySelector('.gf-fx');
            if (dfx) { dfx.className = 'gf-fx c-down'; void dfx.offsetWidth; dfx.classList.add('show'); }
          }
          play(e.sfx);
          return;
        }
        if (e.kind === 'hit') {
          side.classList.remove('is-hit', 'is-hitbig');
          void side.offsetWidth;                /* re-adding a class does not restart a CSS animation */
          side.classList.add(e.big ? 'is-hitbig' : 'is-hit');
          setTimeout(function () { side.classList.remove('is-hit', 'is-hitbig'); }, 500);
        }
        if (fx) {
          fx.className = 'gf-fx c-' + (e.kind === 'hit' ? 'hit' : e.kind);
          void fx.offsetWidth; fx.classList.add('show');
        }
        if (e.amount) {
          var p = side.querySelector('.gf-portrait');
          if (p) {
            var n = document.createElement('div');
            n.className = 'gf-float' + (e.kind === 'heal' ? ' heal' : '') + (e.big ? ' big' : '');
            n.textContent = (e.kind === 'heal' ? '+' : '-') + e.amount;
            n.style.left = (42 + Math.random() * 16) + '%';
            p.appendChild(n);
            n.addEventListener('animationend', function () { n.remove(); });
          }
        }
        play(e.sfx);
      }, i * 420);
    });
    music(moodFrom());
  }

  function wire(area) {
    var form = area.querySelector('#gf-form');
    if (!form) return;
    form.addEventListener('submit', function (ev) {
      var btn = ev.submitter;
      if (!btn || !btn.name) return;            /* no submitter: let it post normally */
      ev.preventDefault();
      Array.prototype.forEach.call(form.querySelectorAll('button'), function (b) { b.disabled = true; });
      var body = new URLSearchParams();
      body.append('encounter_id', form.querySelector('[name=encounter_id]').value);
      body.append('turn', btn.value);
      fetch('ajax/gauntlet-fight.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      }).then(function (r) { return r.text(); })
        .then(function (html) {
          var holder = document.createElement('div');
          holder.innerHTML = html;
          var next = holder.querySelector('#' + AREA);
          var old  = document.getElementById(AREA);
          if (!next || !old) { form.submit(); return; }   /* fall back to a real post */
          old.replaceWith(next);
          animate(next); wire(next);
        })
        .catch(function () { form.submit(); });
    });
  }

  /*
   * Called on load and again after the page body is swapped. The music
   * is started whatever state the page is in -- there may be no fight
   * on screen at all -- so the theme is running from the moment the
   * Gauntlet is opened, not from the first swing.
   */
  function boot() {
    var area = document.getElementById(AREA);
    if (area) wire(area);
    wireAudio();            /* the strip is in the shell, so this is once */
    music(moodFrom());
    icons();
  }
  window.GF_BOOT = boot;

  /*
   * THE CONTROLS. Play/pause, mute and a volume slider, the set Crypt
   * Crawl and Crypt Conquest already carry -- and the slider is the half
   * that only works because SkAudio routes through a GainNode.
   *
   * No prev/next here on purpose: Gauntlets is one track for a whole run
   * and only changes it when the run ends, so skip buttons would have
   * nothing to skip to.
   */
  var TRACK_NAME = { theme: 'Gauntlet Theme', win: 'Triumph', lose: 'Death' };
  function label() {
    var el = document.getElementById('gf-a-name');
    if (!el) return;
    el.textContent = muted ? 'Muted'
                   : (paused ? 'Paused' : (TRACK_NAME[cur] || 'Music'));
  }
  function icons() {
    var pb = document.getElementById('gf-a-play'),
        mb = document.getElementById('gf-a-mute');
    if (pb) { pb.innerHTML = (paused || !playing) ? '&#9654;' : '&#10073;&#10073;';
              pb.classList.toggle('off', paused); }
    if (mb) { mb.innerHTML = muted ? '&#128263;' : '&#128266;';
              mb.classList.toggle('off', muted); }
    label();
  }
  function remember(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

  function wireAudio() {
    var pb = document.getElementById('gf-a-play'),
        mb = document.getElementById('gf-a-mute'),
        vb = document.getElementById('gf-a-vol'),
        el = document.getElementById('gf-music');
    if (!pb || pb._wired) return;
    pb._wired = true;
    if (vb) vb.value = Math.round(vol * 100);

    pb.addEventListener('click', function () {
      if (window.SkAudio) SkAudio.unlock();
      paused = !paused;
      if (paused) { if (el) el.pause(); playing = false; }
      else { muted = false; remember('gfMute', '0'); music(moodFrom()); }
      icons();
    });
    mb.addEventListener('click', function () {
      if (window.SkAudio) SkAudio.unlock();
      muted = !muted;
      remember('gfMute', muted ? '1' : '0');
      if (muted) { if (el) el.pause(); playing = false; }
      else { paused = false; music(moodFrom()); }
      applyVol();
      icons();
    });
    /* The win and death tracks do not loop, so the element ends on its
       own and the button has to come back to play. */
    if (el) el.addEventListener('ended', function () { playing = false; icons(); });
    if (vb) vb.addEventListener('input', function () {
      if (window.SkAudio) SkAudio.unlock();
      vol = Math.max(0, Math.min(1, (parseInt(vb.value, 10) || 0) / 100));
      remember('gfVol', String(Math.round(vol * 100)));
      /* Dragging off zero is the same intent as unmuting, and leaving it
         muted there would read as a broken slider. */
      if (vol > 0 && muted) { muted = false; remember('gfMute', '0'); music(moodFrom()); }
      applyVol();
      icons();
    });
    icons();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
