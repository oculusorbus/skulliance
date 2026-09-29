<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
require_once 'missions-lib.php';
include 'header.php';

/*
 * MISSIONS, rebuilt.
 *
 * WHAT THE OLD PAGE DID, and why each of these is gone:
 *
 *  - A first-time staker saw NOTHING. The page only called getMissions()
 *    `if(isset($_SESSION['userData']['project_id']))`, and the only thing
 *    that ever set that was clicking one of forty unlabelled icons. So the
 *    heading said "Available Missions" above an empty box until you
 *    guessed. This page picks a sensible project server-side and always
 *    renders a ladder. See $ms_project below.
 *
 *  - Choosing a mission was a FULL PAGE POST -- every card was its own
 *    <form action='missions.php#inventory'> and the card's onclick clicked
 *    a hidden submit. That re-ran verify.php, which is why the page shipped
 *    a nine-second fake progress bar to cover the wait. Maximise and
 *    Balance were two more POSTs, for arithmetic the browser has every
 *    input for. All of it is a drawer now, and the loader is gone.
 *
 *  - The load-out lived in $_SESSION, mutated by four ajax endpoints, and
 *    when it desynced the staker got told to hard-refresh. The drawer holds
 *    its own state and posts it in one request. See ajax/mission-launch.php.
 *
 *  - Locked missions were scrambled to "?????? ####" with a padlock, so to
 *    a newcomer most of the grid looked like noise instead of like a ladder
 *    with rungs above them.
 *
 * WHAT IS DELIBERATELY UNCHANGED: the three bulk launchers still come from
 * db.php's own renderers and still call their existing ajax; claiming still
 * goes through skulliance.js's completeMissions(), which reveals success and
 * failure in place by id -- so the active cards below keep the ids it
 * expects (mission-row-, mission-result-, mission-reward-, currency-,
 * consumable-, retreat-button-). Claiming is the money path and was working.
 */

$ms_user = mission_user_id();
?>
<?php /*
 * THE STYLESHEET GOES FIRST, before a single element of the page.
 *
 * It was at the bottom, after all the markup, and the result was a
 * full-blown flash of unstyled content: the browser painted every mission
 * card with only flexbox.css applied -- .nft at width:25%, .nfts holding
 * a 1000px min-height, and the art at its natural size -- so the page
 * showed metre-tall NFT images and stacked white boxes for as long as it
 * took the rest of the document to arrive, then reflowed. On a page this
 * size that is a second or more of garbage.
 *
 * Inline rather than a file on purpose, same as my-nfts and store: one
 * request, no second round trip, and nothing for another page to inherit.
 */ ?>

<style>
/* Scoped to missions.php, same approach as my-nfts and store: override what
   the platform imposes, change nothing in flexbox.css, so every other page
   using .row/.main/.content/.nft is untouched.

   NEUTRALISED HERE, checked before building rather than found in review:
   .main's text-align:center, .content's border-radius:10px, and
   .nfts' min-height:1000px -- which reserved a thousand pixels of empty
   page under the old mission grid whether or not anything was in it. */
.ms-head, .ms-head *, .ms-section, .ms-section *,
.ms-primer, .ms-primer *, .ms-deploy, .ms-deploy *, .ms-guest, .ms-guest *,
.ms-news, .ms-news *, .ms-drawer, .ms-drawer *,
.ms-daily, .ms-daily * { text-align: left; }
.main .content { border-radius: 0; }

.ms-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; flex-wrap: wrap; margin: 0 0 20px;
  border-bottom: 1px solid rgba(0,200,160,.14); padding-bottom: 16px;
}
.ms-kick { display: block; font-size: .7rem; letter-spacing: .16em; text-transform: uppercase; color: #00c8a0; }
.ms-title { margin: 2px 0 0; line-height: 1.1; font-size: clamp(1.5rem, 3.2vw, 2.2rem);
  text-transform: none; color: #e8eaed; letter-spacing: -.01em; }
.ms-ctx { margin: 8px 0 0; font-size: .86rem; color: #7a9eb0; max-width: 56ch; }
.ms-figures { display: flex; gap: 8px; flex-wrap: wrap; }
.ms-fig { background: #0a1929; border: 1px solid rgba(0,200,160,.14); padding: 9px 15px; min-width: 96px; }
.ms-fig b { display: block; font-size: 1.3rem; color: #e8eaed; line-height: 1.1; }
.ms-fig b i { font-style: normal; font-size: .8rem; color: #4f7488; }
.ms-fig span { font-size: .64rem; color: #7a9eb0; letter-spacing: .05em; text-transform: uppercase; }
/* The one number that means "there is something to do right now". */
.ms-fig-go { border-color: rgba(0,200,160,.5); }
.ms-fig-go b { color: #00c8a0; }

/* ---- primer: shown only to someone who has never run a mission -------- */
/* THREE STEPS, THREE COLUMNS. auto-fit with a 230px minimum makes ten
   tracks on a 2560px monitor and leaves seven of them empty beside the
   content -- the steps are a fixed set of three, not a collection. */
.ms-primer {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 1px; background: rgba(0,200,160,.14); border: 1px solid rgba(0,200,160,.14);
  margin: 0 0 20px; max-width: 1080px;
}
@media (max-width: 760px) { .ms-primer { grid-template-columns: 1fr; } }
.ms-step { background: #0a1929; padding: 16px 18px; }
.ms-step b { display: inline-block; font-size: .68rem; letter-spacing: .1em; color: #07111d;
  background: #00c8a0; padding: 2px 7px; margin-bottom: 8px; }
.ms-step h3 { margin: 0 0 6px; font-size: .96rem; color: #e8eaed; text-transform: none; }
.ms-step p { margin: 0; font-size: .82rem; color: #8fa8b8; line-height: 1.5; }
.ms-primer-more { grid-column: 1 / -1; background: #0a1929; padding: 12px 18px;
  font-size: .82rem; color: #00c8a0; text-decoration: none; }
.ms-primer-more:hover { text-decoration: underline; }

/* ---- section nav ------------------------------------------------------
   Sticky at top: 0. The platform navbar is position:relative on desktop so
   it scrolls away and leaves this the topmost thing; under 700px it becomes
   a burger fixed at the top RIGHT, which is what the padding dodges. */
.ms-nav {
  position: sticky; top: 0; z-index: 90; display: flex; gap: 2px; flex-wrap: wrap;
  background: #07111d; border-bottom: 1px solid rgba(0,200,160,.18);
  margin: 0 0 16px; padding: 4px 0;
}
.ms-nav a {
  display: flex; align-items: center; gap: 6px; padding: 7px 13px; border-radius: 0;
  font-size: .74rem; color: #7a9eb0; text-decoration: none; white-space: nowrap;
  border-bottom: 2px solid transparent;
}
.ms-nav a:hover { color: #e8eaed; background: rgba(0,200,160,.06); }
.ms-nav a.on { color: #00c8a0; border-bottom-color: #00c8a0; }
.ms-nav a i { font-style: normal; font-size: .62rem; background: rgba(255,255,255,.08);
  color: #b9c7d4; padding: 1px 6px; }
.ms-nav a i.go { background: #f5a623; color: #07111d; font-weight: bold; }
/* Jumping must not drop the heading under the sticky bar. */
#ms-sec-daily, #ms-news, #ms-sec-deploy, #ms-field-section, #ms-sec-launch, #ms-sec-record
  { scroll-margin-top: 54px; }
@media (max-width: 700px) {
  .ms-nav { padding-right: 56px; overflow-x: auto; flex-wrap: nowrap; }
  .ms-nav a { padding: 7px 10px; }
}

/* ---- daily reward -----------------------------------------------------
   Seven rows became one strip. Claimable is a teal card you cannot miss
   from the top of the page; already claimed collapses to a quiet line,
   because then it is just a fact rather than a thing to do. */
/* auto + 1fr, not 1fr + auto: the track is content-width and the action
   column takes the slack, so on a wide monitor the two halves do not end up
   pinned to opposite edges with a metre of nothing between them. */
.ms-daily { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px 28px;
  align-items: center; background: #0a1929; border: 1px solid rgba(0,200,160,.14);
  padding: 12px 16px; margin: 0 0 16px; }
.ms-daily.go { border-color: rgba(0,200,160,.5); background: rgba(0,200,160,.06); }
.ms-daily-kick { display: block; font-size: .64rem; letter-spacing: .12em;
  text-transform: uppercase; color: #7a9eb0; margin-bottom: 7px; }
.ms-daily.go .ms-daily-kick { color: #00c8a0; }
.ms-daily-track { display: flex; gap: 5px; flex-wrap: wrap; }
.ms-day { position: relative; display: flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; background: #07111d; border: 1px solid rgba(255,255,255,.08); }
.ms-day img { width: 19px; height: 19px; object-fit: contain; opacity: .3; }
.ms-day i { position: absolute; bottom: -1px; right: 1px; font-style: normal;
  font-size: .5rem; color: #4f7488; }
.ms-day.done { border-color: rgba(0,200,160,.45); background: rgba(0,200,160,.08); }
.ms-day.done img { opacity: 1; }
.ms-day.done i { color: #00c8a0; }
/* The rung you are on, whether or not it can be taken yet. */
.ms-day.now { border-color: #f5a623; }
.ms-day.now img { opacity: .85; }
.ms-day.now i { color: #f5a623; }
.ms-daily-right { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; justify-content: flex-start; }
/* dailyReward() sets display:flex on #claimed, #progress_bar and #remaining,
   so these have to read correctly as flex boxes, not just as blocks. */
.ms-daily-line { display: flex; align-items: center; gap: 7px; font-size: .84rem; color: #b9c7d4; }
.ms-daily-line strong { color: #e8eaed; }
.ms-daily-line .icon { height: 18px; width: auto; margin: 0; }
.ms-daily-done strong { color: #00c8a0; }
.ms-daily-next { color: #7a9eb0; font-size: .78rem; }
.ms-daily-bar { width: 140px; }
.ms-daily .button { border-radius: 0; margin: 0; }
.ms-daily-foot { grid-column: 1 / -1; display: flex; align-items: center; gap: 10px;
  border-top: 1px solid rgba(255,255,255,.06); padding-top: 9px; font-size: .74rem; color: #7a9eb0; }
.ms-daily-foot form { margin: 0; }
.ms-daily-lb { background: transparent; border: 1px solid rgba(0,200,160,.3); color: #00c8a0;
  font-size: .64rem; letter-spacing: .06em; text-transform: uppercase; padding: 3px 9px;
  cursor: pointer; border-radius: 0; }
.ms-daily-lb:hover { background: rgba(0,200,160,.12); }
/* The progress bar markup comes from getRewardProgressBar() and carries the
   platform's own w3-*-rewards classes; only the width needs constraining. */
.ms-daily-bar .w3-border-rewards { width: 100%; }
@media (max-width: 760px) {
  .ms-daily { grid-template-columns: 1fr; }
  .ms-daily-right { justify-content: flex-start; }
}

/* ---- newly unlocked ---------------------------------------------------
   Amber, not teal. Teal on this page means "good to go"; this is "you are
   about to miss something", which is a different thing and has to read as
   one at a glance from the top of the page. */
.ms-news { background: rgba(245,166,35,.06); border: 1px solid rgba(245,166,35,.35);
  border-left: 3px solid #f5a623; padding: 13px 16px; margin: 0 0 16px; }
.ms-news-head { margin-bottom: 10px; }
.ms-news-head b { display: block; color: #f5a623; font-size: .98rem; }
.ms-news-head span { font-size: .8rem; color: #8fa8b8; }
.ms-news-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 8px; }
.ms-new { position: relative; display: flex; gap: 10px; align-items: stretch; text-align: left;
  background: #0a1929; border: 1px solid rgba(245,166,35,.22); border-radius: 0;
  padding: 0; cursor: pointer; overflow: hidden; transition: border-color .12s; }
.ms-new:hover { border-color: #f5a623; }
.ms-new img { width: 56px; flex: 0 0 56px; object-fit: cover; align-self: stretch; }
.ms-new-body { display: flex; flex-direction: column; gap: 1px; padding: 8px 10px 8px 0; min-width: 0; }
.ms-new-title { color: #e8eaed; font-size: .84rem; font-weight: bold;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ms-new-sub { font-size: .68rem; color: #7a9eb0; }
.ms-new-act { font-size: .68rem; color: #00c8a0; margin-top: auto; }
.ms-new-act.warn { color: #f5a623; }
.ms-new-flag { position: absolute; top: 0; right: 0; font-style: normal; font-size: .56rem;
  letter-spacing: .1em; text-transform: uppercase; background: #f5a623; color: #07111d;
  padding: 2px 6px; font-weight: bold; }
.ms-news-more { margin: 9px 0 0; font-size: .76rem; color: #7a9eb0; }
.ms-fig-new { border-color: rgba(245,166,35,.5); }
.ms-fig-new b { color: #f5a623; }
/* Two badges, because they mean two different things. Solid amber = the
   rung that just opened. Outline = one you passed over on the way up;
   still worth catching, but it is not news and must not shout like it. */
.ms-quest-new { position: absolute; top: 4px; right: 4px; font-size: .56rem; letter-spacing: .08em;
  text-transform: uppercase; background: #f5a623; color: #07111d; padding: 2px 5px; font-weight: bold; }
/* The label on the action line, where there is room for the long one. */
.ms-quest-go i, .ms-quest-block i { font-style: normal; color: #f5a623; font-weight: bold;
  text-transform: uppercase; letter-spacing: .06em; font-size: .62rem; }

/* ---- daily deployment ------------------------------------------------- */
.ms-deploy {
  display: flex; align-items: center; justify-content: space-between; gap: 18px;
  flex-wrap: wrap; background: #0a1929; border: 1px solid rgba(0,200,160,.22);
  border-left: 3px solid #00c8a0; padding: 14px 18px; margin: 0 0 22px;
}
.ms-deploy-label b { display: block; color: #e8eaed; font-size: .98rem; }
.ms-deploy-label span { font-size: .8rem; color: #7a9eb0; }
.ms-deploy-buttons { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
/* db.php's three renderers each emit <span ...><button></span><br>. The
   break is theirs and would stack the buttons vertically in here. */
.ms-deploy-buttons br { display: none; }
.ms-deploy-buttons .button { border-radius: 0; margin: 0; }

/* ---- sections --------------------------------------------------------- */
.ms-section { margin: 0 0 26px; }
.ms-section-head { display: flex; align-items: baseline; justify-content: space-between;
  gap: 12px; border-bottom: 1px solid rgba(0,200,160,.14); padding-bottom: 8px; margin-bottom: 14px; }
.ms-section-head h3 { margin: 0; font-size: .78rem; letter-spacing: .14em; text-transform: uppercase;
  color: #7a9eb0; font-weight: normal; }
.ms-section-note { font-size: .78rem; color: #00c8a0; }
.ms-more { display: flex; align-items: center; gap: 12px; margin-top: 10px; }
.ms-more span { font-size: .78rem; color: #7a9eb0; }
.ms-more-btn { background: transparent; border: 1px solid rgba(0,200,160,.3); color: #00c8a0;
  font-size: .66rem; letter-spacing: .06em; text-transform: uppercase; padding: 6px 12px;
  cursor: pointer; border-radius: 0; }
.ms-more-btn:hover { background: rgba(0,200,160,.12); }
.ms-empty { background: #0a1929; border: 1px solid rgba(0,200,160,.12); padding: 28px 22px; }
.ms-empty h4 { margin: 0 0 6px; color: #e8eaed; font-size: 1rem; text-transform: none; }
.ms-empty p { margin: 0; color: #8fa8b8; font-size: .86rem; max-width: 60ch; }

/* ---- in the field ----------------------------------------------------- */
.ms-claimbar { display: flex; align-items: center; justify-content: space-between; gap: 16px;
  flex-wrap: wrap; background: rgba(0,200,160,.09); border: 1px solid rgba(0,200,160,.45);
  padding: 12px 16px; margin-bottom: 12px; }
.ms-claimbar b { color: #00c8a0; display: block; }
.ms-claimbar span { font-size: .8rem; color: #8fa8b8; }
.ms-claimbar .button { border-radius: 0; margin: 0; }

.ms-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 12px; }
.ms-mcard { display: flex; background: #0a1929; border: 1px solid rgba(0,200,160,.14);
  overflow: hidden; transition: border-color .15s; }
.ms-mcard.ready { border-color: rgba(0,200,160,.5); }
/* skulliance.js adds these on claim -- same colours the old .mc-card used. */
.ms-mcard.success { border-color: rgba(140,90,255,.6); }
.ms-mcard.failure { border-color: rgba(255,92,92,.5); }
.ms-mcard.success .ms-mcard-bar i { background: rgba(140,90,255,.75); }
.ms-mcard.failure .ms-mcard-bar i { background: rgba(255,92,92,.6); }
.ms-mcard-art { width: 92px; flex: 0 0 92px; background: #07111d; }
.ms-mcard-art img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ms-mcard-body { flex: 1 1 auto; min-width: 0; padding: 10px 12px 0;
  display: flex; flex-direction: column; gap: 8px; }
.ms-mcard-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.ms-mcard-title { display: block; color: #e8eaed; font-weight: bold; font-size: .92rem;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ms-mcard-sub { display: block; font-size: .72rem; color: #7a9eb0; }
.ms-mcard-time { text-align: right; flex: 0 0 auto; }
.ms-mcard-time-label { display: block; font-size: .6rem; letter-spacing: .08em;
  text-transform: uppercase; color: #4f7488; }
.ms-mcard-clock { font-size: .82rem; color: #b9c7d4; font-variant-numeric: tabular-nums; }
.ms-mcard-clock.go { color: #00c8a0; font-weight: bold; }
.ms-mcard-stats { display: flex; gap: 14px; flex-wrap: wrap; }
/* DIRECT CHILDREN ONLY. A bare `.ms-mcard-stats span` also caught the
   nested currency span -- the one skulliance.js rewrites on claim -- turned
   it into a flex box and pushed "STAR" onto its own line under the number. */
.ms-mcard-stats > span { display: flex; flex-direction: column; }
.ms-mcard-stats i { font-style: normal; font-size: .58rem; letter-spacing: .08em;
  text-transform: uppercase; color: #4f7488; }
/* nowrap: "3,600 SKULL" was breaking after the number, which made one card
   in a row taller than the rest and knocked the progress bars out of line. */
.ms-mcard-stats b { font-size: .82rem; color: #b9c7d4; font-weight: normal; white-space: nowrap; }
.ms-mcard-stats b.ms-sure { color: #00c8a0; }
.ms-mcard-stats b em { font-style: normal; color: #ffcc44; font-size: .7rem; }
.ms-mcard-foot { display: flex; align-items: center; justify-content: space-between;
  gap: 10px; margin-top: auto; padding-bottom: 10px; }
.ms-mcard-items { display: flex; gap: 4px; flex-wrap: wrap; min-height: 18px; }
.ms-mcard-items img { height: 18px; width: auto; }
.ms-retreat, .ms-collect { border-radius: 0; cursor: pointer; font-size: .68rem;
  letter-spacing: .08em; text-transform: uppercase; padding: 6px 12px; }
.ms-retreat { background: transparent; color: #7a9eb0; border: 1px solid rgba(122,158,176,.35); }
.ms-retreat:hover { color: #ff5c5c; border-color: rgba(255,92,92,.5); }
.ms-collect { background: #00c8a0; color: #07111d; border: 0; font-weight: bold; }
.ms-mcard-bar { height: 3px; background: rgba(255,255,255,.07); margin: 0 -12px; }
.ms-mcard-bar i { display: block; height: 100%; background: #00c8a0; }

/* ---- launch: projects + ladder ---------------------------------------- */
.ms-launch { display: grid; grid-template-columns: 250px 1fr; gap: 14px; align-items: start; }
.ms-projects { display: flex; flex-direction: column; gap: 4px; max-height: 620px;
  overflow-y: auto; padding-right: 4px; }
/* THE OLD PICKER WAS FORTY UNLABELLED ICONS with a hover tooltip, which is
   nothing at all on a touch screen. A name, your progress and whether you
   have anybody idle -- the three things that decide where you click. */
.ms-proj { display: grid; grid-template-columns: 26px 1fr; grid-template-rows: auto auto auto;
  gap: 0 10px; align-items: center; text-align: left; width: 100%; cursor: pointer;
  background: #0a1929; border: 1px solid rgba(0,200,160,.1); border-radius: 0;
  padding: 8px 10px; transition: border-color .12s, background .12s; }
.ms-proj:hover { border-color: rgba(0,200,160,.4); }
.ms-proj.sel { border-color: #00c8a0; background: rgba(0,200,160,.07); }
.ms-proj img { grid-row: 1 / 3; width: 26px; height: 26px; object-fit: contain; }
.ms-proj-name { color: #c9d6e0; font-size: .82rem; overflow: hidden;
  text-overflow: ellipsis; white-space: nowrap; }
.ms-proj.sel .ms-proj-name { color: #e8eaed; font-weight: bold; }
.ms-proj-sub { font-size: .66rem; color: #4f7488; }
/* Teal ring on the icon = you have idle NFTs for this one right now. */
.ms-proj.can img { outline: 1px solid rgba(0,200,160,.45); outline-offset: 1px; }
.ms-proj-bar { grid-column: 1 / -1; height: 2px; background: rgba(255,255,255,.08); margin-top: 7px; }
.ms-proj-bar i { display: block; height: 100%; background: #ffcc44; }
.ms-proj-bar.full i { background: #00c8a0; }

.ms-ladder { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
.ms-quest { display: flex; background: #0a1929; border: 1px solid rgba(0,200,160,.12);
  overflow: hidden; transition: border-color .12s, transform .12s; }
.ms-quest.can { cursor: pointer; }
.ms-quest.can:hover, .ms-quest.can:focus-visible { border-color: #00c8a0; outline: none; }
.ms-quest.locked { opacity: .62; }
.ms-quest-art { position: relative; width: 84px; flex: 0 0 84px; background: #07111d; }
.ms-quest-art img { width: 100%; height: 100%; object-fit: cover; display: block; }
/* A locked rung is dimmed and desaturated, not scrambled. The old renderer
   replaced the title with "?????? ####" and the art with a padlock. */
.ms-quest.locked .ms-quest-art img { filter: grayscale(1) brightness(.6); }
.ms-quest-lvl { position: absolute; top: 4px; left: 4px; font-size: .58rem; letter-spacing: .06em;
  text-transform: uppercase; background: rgba(7,17,29,.85); color: #7a9eb0; padding: 2px 5px; }
.ms-quest-run { position: absolute; bottom: 4px; left: 4px; font-size: .58rem;
  background: rgba(0,200,160,.85); color: #07111d; padding: 2px 5px; font-weight: bold; }
.ms-quest-body { flex: 1 1 auto; min-width: 0; padding: 9px 11px; display: flex;
  flex-direction: column; gap: 3px; }
.ms-quest-title { color: #e8eaed; font-size: .86rem; font-weight: bold;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ms-quest-pay { font-size: .8rem; color: #b9c7d4; }
.ms-quest-pay em { font-style: normal; color: #7a9eb0; }
.ms-quest-pay b { color: #00c8a0; }
.ms-quest-meta { font-size: .68rem; color: #4f7488; }
.ms-quest-block { margin-top: auto; font-size: .68rem; color: #f5a623; }
.ms-quest-go { margin-top: auto; font-size: .68rem; color: #00c8a0; }

/* ---- the launch drawer ------------------------------------------------ */
.ms-drawer { position: fixed; inset: 0; z-index: 1001; background: rgba(0,0,0,.86);
  display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 32px 16px; }
.ms-drawer[hidden] { display: none; }
/* A DIALOG, not a tall block. Left to grow, the crew grid pushed Launch
   below the fold -- and the page behind it scrolls too, so you could not
   tell which scrollbar you were meant to use. Header and footer are fixed;
   only the middle scrolls. */
.ms-drawer-box { position: relative; background: #0a1929; border: 1px solid rgba(0,200,160,.32);
  width: 100%; max-width: 640px; max-height: calc(100vh - 64px);
  display: flex; flex-direction: column; overflow: hidden; }
#ms-drawer-body { display: contents; }
.ms-d-scroll { overflow-y: auto; flex: 1 1 auto; min-height: 0; }
.ms-d-foot { flex: 0 0 auto; border-top: 1px solid rgba(0,200,160,.18);
  background: #0a1929; padding: 12px 18px; text-align: left; }
.ms-drawer-x { position: absolute; top: 6px; right: 8px; z-index: 2; background: transparent;
  border: 0; color: #7a9eb0; font-size: 1.6rem; line-height: 1; cursor: pointer; padding: 2px 8px; }
.ms-drawer-x:hover { color: #e8eaed; }
.ms-d-art { height: 170px; flex: 0 0 auto; background: #07111d; }
.ms-d-art img, .ms-d-art video { width: 100%; height: 100%; object-fit: cover; display: block; }
.ms-d-main { padding: 16px 18px; text-align: left; }
.ms-d-main h3 { margin: 0; color: #e8eaed; font-size: 1.15rem; text-transform: none; }
.ms-d-sub { font-size: .74rem; color: #7a9eb0; margin: 2px 0 10px; }
.ms-d-desc { font-size: .84rem; color: #8fa8b8; line-height: 1.55; margin: 0 0 14px; }
.ms-d-facts { display: flex; gap: 16px; flex-wrap: wrap; border-top: 1px solid rgba(0,200,160,.12);
  border-bottom: 1px solid rgba(0,200,160,.12); padding: 10px 0; margin-bottom: 14px; }
.ms-d-facts > span { display: flex; flex-direction: column; }
.ms-d-facts i { font-style: normal; font-size: .58rem; letter-spacing: .08em;
  text-transform: uppercase; color: #4f7488; }
.ms-d-facts b { font-size: .88rem; color: #b9c7d4; font-weight: normal; }

/* THE SUCCESS METER is the whole point of the drawer: it is the number the
   old page made you read off a list while a server round trip recomputed
   it. Here it moves as you select. */
.ms-d-rate { margin-bottom: 14px; }
.ms-d-rate-top { display: flex; align-items: baseline; justify-content: space-between; }
.ms-d-rate-top b { font-size: 1.5rem; color: #00c8a0; line-height: 1; }
.ms-d-rate-top span { font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: #4f7488; }
.ms-d-meter { height: 6px; background: rgba(255,255,255,.08); margin: 6px 0 4px; }
.ms-d-meter i { display: block; height: 100%; background: #00c8a0; transition: width .18s; }
.ms-d-meter.sure i { background: #b47fff; }
.ms-d-rate-note { font-size: .72rem; color: #7a9eb0; }

.ms-d-group { margin-bottom: 14px; }
.ms-d-group-head { display: flex; align-items: baseline; justify-content: space-between;
  gap: 10px; margin-bottom: 7px; flex-wrap: wrap; }
.ms-d-group-head h4 { margin: 0; font-size: .68rem; letter-spacing: .12em; text-transform: uppercase;
  color: #7a9eb0; font-weight: normal; }
.ms-d-tools { display: flex; gap: 6px; }
.ms-d-tool { background: transparent; border: 1px solid rgba(0,200,160,.3); color: #00c8a0;
  font-size: .64rem; letter-spacing: .06em; text-transform: uppercase; padding: 4px 9px;
  cursor: pointer; border-radius: 0; }
.ms-d-tool:hover { background: rgba(0,200,160,.12); }
/* Still capped, because a whale has hundreds -- but the dialog scrolls now,
   so this is a lid on one section rather than the only way to reach Launch. */
.ms-d-crew { display: grid; grid-template-columns: repeat(auto-fill, minmax(62px, 1fr));
  gap: 6px; max-height: 210px; overflow-y: auto; }
.ms-d-nft { position: relative; background: #07111d; border: 1px solid rgba(255,255,255,.07);
  padding: 0; cursor: pointer; border-radius: 0; overflow: hidden; }
.ms-d-nft img { width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block; opacity: .45;
  transition: opacity .12s; }
.ms-d-nft.on { border-color: #00c8a0; }
.ms-d-nft.on img { opacity: 1; }
.ms-d-nft span { display: block; font-size: .58rem; color: #7a9eb0; padding: 2px 0;
  background: rgba(7,17,29,.9); }
.ms-d-nft.on span { color: #00c8a0; }
.ms-d-items { display: flex; gap: 6px; flex-wrap: wrap; }
.ms-d-item { display: flex; align-items: center; gap: 6px; background: #07111d;
  border: 1px solid rgba(255,255,255,.07); color: #8fa8b8; font-size: .72rem;
  padding: 6px 10px; cursor: pointer; border-radius: 0; }
.ms-d-item img { height: 16px; width: auto; }
.ms-d-item.on { border-color: #00c8a0; color: #e8eaed; }
.ms-d-item em { font-style: normal; color: #4f7488; }
.ms-d-msg { font-size: .78rem; color: #f5a623; min-height: 1.1em; margin: 0 0 8px; }
.ms-d-msg.ok { color: #00c8a0; }
.ms-d-launch { display: flex; align-items: center; justify-content: space-between;
  gap: 12px; flex-wrap: wrap; }
.ms-d-launch .button { border-radius: 0; margin: 0; }
.ms-d-cost { font-size: .78rem; color: #7a9eb0; }

@media (max-width: 900px) {
  .ms-launch { grid-template-columns: 1fr; }
  /* The picker becomes a horizontal rail rather than a 40-item column you
     would have to scroll past to reach the missions. */
  .ms-projects { flex-direction: row; overflow-x: auto; max-height: none; padding-bottom: 6px; }
  .ms-proj { flex: 0 0 168px; }
}
@media (max-width: 620px) {
  .ms-drawer { padding: 0; }
  .ms-drawer-box { max-width: none; max-height: 100vh; height: 100vh; border: 0; }
  .ms-head { align-items: flex-start; }
  .ms-cards { grid-template-columns: 1fr; }
  .ms-deploy { flex-direction: column; align-items: flex-start; }
  .ms-d-art { height: 130px; }
}
</style>

<?php if ($ms_user <= 0): ?>
<?php
/* ---------------------------------------------------------------- guest -- */
/* The old guest view was a bare "Please connect a Cardano wallet to view
   missions." next to a wallet widget -- no answer to why anyone would. */
?>
	<div class="row" id="row1">
		<div class="main">
			<div class="ms-head">
				<span class="ms-kick">Missions</span>
				<h2 class="ms-title">Send your NFTs out to work.</h2>
				<p class="ms-ctx">Missions are the idle game built on top of staking. You pick a job,
				   send the NFTs you already own, and they come back days later with points -- or
				   empty-handed. Nothing is ever spent from your wallet and nothing leaves it.</p>
			</div>
			<div class="ms-primer">
				<div class="ms-step"><b>1</b><h3>Pick a job</h3>
					<p>Each project has a ladder of missions. Clearing one opens the next, so
					   the bigger rewards are earned rather than bought.</p></div>
				<div class="ms-step"><b>2</b><h3>Send a crew</h3>
					<p>The NFTs you send decide your odds. More of them, and rarer ones, raise
					   the success rate. Items in your pack can push it to a certainty.</p></div>
				<div class="ms-step"><b>3</b><h3>Come back</h3>
					<p>Missions run for days, not minutes. Collect when they land. Fail four in
					   a row on one job and the fifth is guaranteed.</p></div>
			</div>
			<div class="ms-guest">
				<h3>Connect a wallet to begin</h3>
				<p>Staking is free and your NFTs stay in your own wallet the entire time.</p>
				<div class="ms-guest-wallet"><?php renderWalletConnection("missions"); ?></div>
				<p class="ms-fine">Claiming from the store needs membership; missions do not.
				   <a href="info.php">How membership works &rarr;</a></p>
			</div>
		</div>
	</div>
<?php else: ?>
<?php
/* --------------------------------------------------------------- staker -- */
$ms_over     = mission_overview($conn);
$ms_active   = mission_active($conn);
$ms_projects = mission_projects($conn);

$ms_ready = 0;
foreach ($ms_active as $a) if (!empty($a['ready'])) $ms_ready++;

/*
 * THE DEFAULT PROJECT -- the fix for the empty page.
 *
 * Preference order, and each step is a real answer to "what did they come
 * here to do": the project they were last looking at, then one they have
 * idle NFTs for AND an unlocked mission they can afford, then any with
 * idle NFTs, then simply the first. There is no branch that shows nothing.
 */
$ms_project = 0;
$ms_byid    = array();
foreach ($ms_projects as $p) $ms_byid[$p['project_id']] = $p;

if (isset($_SESSION['userData']['project_id'])
    && isset($ms_byid[(int)$_SESSION['userData']['project_id']]))
	$ms_project = (int)$_SESSION['userData']['project_id'];

if (!$ms_project) foreach ($ms_projects as $p)
	if ($p['eligible'] && $p['levels_open'] > 0) { $ms_project = $p['project_id']; break; }
if (!$ms_project) foreach ($ms_projects as $p)
	if ($p['eligible']) { $ms_project = $p['project_id']; break; }
if (!$ms_project && $ms_projects) $ms_project = $ms_projects[0]['project_id'];

$ms_quests = $ms_project ? mission_quests($conn, $ms_project) : array();
$ms_pname  = isset($ms_byid[$ms_project]) ? $ms_byid[$ms_project]['name'] : '';

/* The daily reward. Computed up here with everything else because it is
   the most time-sensitive thing on the page -- it expires -- so it renders
   directly under the masthead rather than at the bottom. */
$ms_daily = mission_daily($conn);

/* Rungs that opened while you were not looking. See mission_frontier(). */
$ms_new_rungs = mission_frontier($conn);
$ms_new_ids   = array();
foreach ($ms_new_rungs as $r) $ms_new_ids[(int)$r['quest_id']] = !empty($r['frontier']);

/* A staker who has never run one gets the primer. Once they have, the page
   is a control panel and the primer would be in the way every day. */
$ms_new = empty($ms_over['ever']);

function ms_n($v) { return number_format((float)$v); }
function ms_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
	<div class="row" id="row1">
		<div class="main">

			<div class="ms-head">
				<div class="ms-head-left">
					<span class="ms-kick">Missions</span>
					<h2 class="ms-title"><?php
						echo $ms_ready  ? ms_n($ms_ready) . ' ready to collect'
						   : ($ms_active ? ms_n(count($ms_active)) . ' in the field'
						   : 'Send your NFTs out to work.'); ?></h2>
					<p class="ms-ctx"><?php
						echo $ms_new
						  ? 'Pick a job, send the NFTs you already own, collect points when they land.'
						  : 'Your NFTs earn while they are away. Nothing leaves your wallet.'; ?></p>
				</div>
				<div class="ms-figures">
					<div class="ms-fig"><b><?php echo ms_n($ms_over['active']); ?></b><span>In the field</span></div>
					<?php if ($ms_ready): ?>
					<div class="ms-fig ms-fig-go"><b><?php echo ms_n($ms_ready); ?></b><span>Ready</span></div>
					<?php endif; ?>
					<div class="ms-fig"><b><?php echo ms_n($ms_over['success']); ?></b><span>Completed</span></div>
					<?php if ($ms_new_rungs): ?>
					<div class="ms-fig ms-fig-new"><b><?php echo ms_n(count($ms_new_rungs)); ?></b><span>Never run</span></div>
					<?php endif; ?>
					<div class="ms-fig"><b><?php echo ms_n($ms_over['levels_open']); ?><i>/<?php
						echo ms_n($ms_over['levels_top']); ?></i></b><span>Levels open</span></div>
				</div>
			</div>

			<?php
			/*
			 * SECTION NAV -- what the old quick-menu was for.
			 *
			 * That menu existed because a staker with a hundred missions in
			 * flight could not reach anything without a long scroll. It only
			 * appeared under 700px, and it worked by hiding the other panels
			 * with inline style.display, which is why the page had four
			 * sections fighting over who was visible.
			 *
			 * This is the same job done differently: sticky at every width,
			 * nothing hidden, and it carries the counts so you can see where
			 * the work is before you jump. Items whose section is not on the
			 * page are not rendered at all.
			 */
			?>
			<nav class="ms-nav" id="ms-nav">
				<a href="#ms-sec-daily" data-sec="ms-sec-daily">Daily<?php
					echo (!empty($ms_daily['eligible'])) ? ' <i class="go">!</i>' : ''; ?></a>
				<?php if ($ms_new_rungs): ?>
				<a href="#ms-news" data-sec="ms-news">Never run <i id="ms-nav-new"><?php
					echo count($ms_new_rungs); ?></i></a>
				<?php endif; ?>
				<a href="#ms-sec-deploy" data-sec="ms-sec-deploy">Deploy</a>
				<a href="#ms-field-section" data-sec="ms-field-section">In the field <i id="ms-nav-field"><?php
					echo ms_n($ms_total); ?></i></a>
				<a href="#ms-sec-launch" data-sec="ms-sec-launch">Launch</a>
				<a href="#ms-sec-record" data-sec="ms-sec-record">Record</a>
			</nav>

			<div id="ms-sec-daily"><div id="ms-daily-slot"><?php include 'missions-daily.php'; ?></div></div>

			<?php if ($ms_new): ?>
			<div class="ms-primer">
				<div class="ms-step"><b>1</b><h3>Pick a job</h3>
					<p>Each project has a ladder. Clearing one mission opens the next, so the
					   bigger rewards are earned rather than bought.</p></div>
				<div class="ms-step"><b>2</b><h3>Send a crew</h3>
					<p>The NFTs you send decide your odds -- more of them, and rarer ones, raise
					   the success rate. Items in your pack can push it to a certainty.</p></div>
				<div class="ms-step"><b>3</b><h3>Come back</h3>
					<p>Missions run for days. Collect when they land. Fail four in a row on one
					   job and the fifth is guaranteed.</p></div>
				<a class="ms-primer-more" href="skullpaper.php?page=missions">Read the full rules &rarr;</a>
			</div>
			<?php endif; ?>

			<div id="ms-news"><?php include 'missions-news.php'; ?></div>

			<?php /* DAILY DEPLOYMENT -- promoted out of the Current Missions panel,
			         where it used to sit below the fold. For a lot of stakers
			         pressing these IS the visit. The three renderers are db.php's
			         own and still call their existing ajax; they hide themselves
			         when there is nothing to launch, which is why the bar checks
			         for a visible child before showing a heading. */ ?>
			<div class="ms-deploy" id="ms-sec-deploy">
				<div class="ms-deploy-label">
					<b>Daily deployment</b>
					<span>Send everything idle in one press.</span>
				</div>
				<div class="ms-deploy-buttons">
					<?php $ms_bulk_projects = renderStartAllFreeEligibleMissionsButton($conn); ?>
					<?php renderStartAutoMissionsButton($conn); ?>
					<?php renderMaxMaxiMissionsButton($conn); ?>
				</div>
			</div>

			<?php /* ---- in the field ---- */ ?>
			<div class="ms-section" id="ms-field-section">
				<div class="ms-section-head">
					<h3>In the field</h3>
					<span id="ms-claim-slot"></span>
				</div>
				<div id="ms-field"><?php include 'missions-field.php'; ?></div>
			</div>

			<?php /* ---- launch ---- */ ?>
			<div class="ms-section" id="ms-sec-launch">
				<div class="ms-section-head">
					<h3>Launch a mission</h3>
					<span class="ms-section-note" id="ms-project-note"><?php echo ms_e($ms_pname); ?></span>
				</div>
				<div class="ms-launch">
					<div class="ms-projects" id="ms-projects">
						<?php foreach ($ms_projects as $p):
							$sel = ($p['project_id'] === $ms_project); ?>
						<button type="button" class="ms-proj<?php
								echo $sel ? ' sel' : ''; echo $p['eligible'] ? ' can' : ''; ?>"
							data-project="<?php echo (int)$p['project_id']; ?>"
							data-name="<?php echo ms_e($p['name']); ?>"
							onclick="msPickProject(<?php echo (int)$p['project_id']; ?>, this)">
							<img src="<?php echo ms_e($p['icon']); ?>" alt="" loading="lazy"
								onerror="this.style.visibility='hidden'">
							<span class="ms-proj-name"><?php echo ms_e($p['name']); ?></span>
							<span class="ms-proj-sub"><?php
								if ($p['levels_top'] > 0) {
									echo (int)$p['levels_open'] . '/' . (int)$p['levels_top'] . ' open';
									if ($p['idle_nfts']) echo ' &middot; ' . (int)$p['idle_nfts'] . ' idle';
								} else { echo 'No missions yet'; } ?></span>
							<span class="ms-proj-bar<?php echo $p['complete'] ? ' full' : ''; ?>"><i style="width:<?php
								echo $p['levels_top'] > 0
									? (int)round($p['levels_open'] / $p['levels_top'] * 100) : 0; ?>%"></i></span>
						</button>
						<?php endforeach; ?>
					</div>
					<div class="ms-ladder" id="ms-ladder"><?php include 'missions-ladder.php'; ?></div>
				</div>
			</div>

			<?php /* The detailed month/all-time breakdown keeps db.php's renderer
			         and its lazy load; it is the one part of the old page that
			         was already legible. */ ?>
			<div class="ms-section" id="ms-sec-record">
				<div class="ms-section-head"><h3>Your record</h3></div>
				<div class="content missions" id="total-missions-container"></div>
			</div>

			<?php /* The daily reward moved to the top -- it is a once-a-day
			         action on a timer and it was landing below everything.
			         What is left here is the wallet panel, which is a utility
			         you go looking for rather than something that expires. */ ?>
			<div class="ms-section">
				<div class="ms-section-head"><h3>Wallets</h3></div>
				<div class="content" id="player-stats">
					<?php renderWalletConnection("missions"); ?>
				</div>
			</div>

		</div>
	</div>

	<?php /* ---- the launch drawer ---- */ ?>
	<div id="ms-drawer" class="ms-drawer" hidden>
		<div class="ms-drawer-box" role="dialog" aria-modal="true" aria-labelledby="ms-d-title">
			<button type="button" class="ms-drawer-x" onclick="msCloseDrawer()" aria-label="Close">&times;</button>
			<div id="ms-drawer-body"></div>
		</div>
	</div>
<?php endif; ?>

	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
</div>
</body>
<?php $conn->close(); ?>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php if ($ms_user > 0): ?>
<script type="text/javascript">
/*
 * Missions page script. Loaded AFTER skulliance.js on purpose: the bulk
 * launchers and retreat() both call loadCurrentMissions() when they are
 * done, and that function repaints a container this page no longer has.
 * Reassigning it here points those existing, working paths at the new
 * field list without editing skulliance.js, which nine other pages share.
 */
(function () {
	var drawer   = document.getElementById('ms-drawer');
	var drawerBody = document.getElementById('ms-drawer-body');
	var LO = null;               // the open mission's load-out
	var picked = {};             // nft_id -> rate
	var items  = {};             // consumable_id -> boost

	function n(v) { return Number(v || 0).toLocaleString(); }
	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
		});
	}

	/* ---------- the field list ---------------------------------------- */
	function navCount(id, v) {
		var el = document.getElementById(id);
		if (el && typeof v === 'number') el.textContent = n(v);
	}
	function paintField(html, ready) {
		document.getElementById('ms-field').innerHTML = html;
		var fig = document.querySelector('.ms-fig-go');
		if (fig && typeof ready === 'number') {
			if (ready > 0) fig.querySelector('b').textContent = n(ready);
			else fig.remove();
		}
	}
	function refreshField() {
		return fetch('ajax/mission-data.php?what=field', {credentials: 'same-origin'})
			.then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.ok) { paintField(j.html, j.ready); navCount('ms-nav-field', j.total); } })
			.then(refreshNews);
	}
	/* Launching a rung is precisely what stops it being "never run", and the
	   bulk launchers can take one too -- Start All Auto picks the
	   highest-reward mission it can afford, which may be the new one. So the
	   notice is re-rendered after anything that launches, not just after the
	   drawer. */
	function refreshNews() {
		return fetch('ajax/mission-data.php?what=news', {credentials: 'same-origin'})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (!j || !j.ok) return;
				document.getElementById('ms-news').innerHTML = j.html;
				var fig = document.querySelector('.ms-fig-new');
				if (fig) { if (j.count > 0) fig.querySelector('b').textContent = n(j.count); else fig.remove(); }
				var nv = document.querySelector('.ms-nav a[data-sec="ms-news"]');
				if (nv) { if (j.count > 0) navCount('ms-nav-new', j.count); else nv.remove(); }
			})
			.catch(function () {});
	}

	/* The rest of the field, on request. Fetching rather than rendering all
	   of them up front is the whole point of the cap. */
	window.msShowAllField = function (btn) {
		btn.disabled = true;
		btn.textContent = 'Loading\u2026';
		fetch('ajax/mission-data.php?what=field&all=1', {credentials: 'same-origin'})
			.then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.ok) paintField(j.html, j.ready); })
			.catch(function () { btn.disabled = false; btn.textContent = 'Show all'; });
	};

	/* A rung you cannot launch yet still deserves a way through to it. */
	window.msJumpToProject = function (pid) {
		var b = document.querySelector('.ms-proj[data-project="' + pid + '"]');
		if (!b) return;
		msPickProject(pid, b);
		b.scrollIntoView({behavior: 'smooth', block: 'nearest'});
		document.getElementById('ms-ladder').scrollIntoView({behavior: 'smooth', block: 'center'});
	};
	/* THE OVERRIDE. skulliance.js defines loadCurrentMissions() against the
	   old #current-missions-container; the bulk launchers and retreat() both
	   call it on completion. Repointing it keeps those working untouched. */
	window.loadCurrentMissions = refreshField;
	window.currentMissionsLoaded = true;

	/* Claiming still goes through skulliance.js's completeMissions(), which
	   reveals each outcome in place by id. It does not repaint, deliberately
	   -- the reveal is the point -- so the list is refreshed a moment later,
	   once the player has seen which ones made it back. */
	window.msClaimAll = function (btn, missionIds, questIds) {
		btn.disabled = true;
		btn.textContent = 'Collecting…';
		completeMissions(missionIds, questIds);
		setTimeout(refreshField, 6000);
	};

	/* ---------- the project picker ------------------------------------ */
	window.msPickProject = function (pid, el) {
		document.querySelectorAll('.ms-proj.sel').forEach(function (b) { b.classList.remove('sel'); });
		el.classList.add('sel');
		var note = document.getElementById('ms-project-note');
		if (note) note.textContent = el.getAttribute('data-name') || '';
		var lad = document.getElementById('ms-ladder');
		lad.setAttribute('aria-busy', 'true');
		fetch('ajax/mission-data.php?what=ladder&project_id=' + encodeURIComponent(pid),
			{credentials: 'same-origin'})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				lad.removeAttribute('aria-busy');
				if (j && j.ok) lad.innerHTML = j.html;
			})
			.catch(function () { lad.removeAttribute('aria-busy'); });
	};

	/* ---------- the launch drawer ------------------------------------- */
	window.msOpenDrawer = function (questId) {
		drawerBody.innerHTML = '<div class="ms-d-scroll"><div class="ms-d-main"><p class="ms-d-desc">Loading…</p></div></div>';
		drawer.hidden = false;
		fetch('ajax/mission-data.php?what=loadout&quest_id=' + encodeURIComponent(questId),
			{credentials: 'same-origin'})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (!j || !j.ok) { drawerBody.innerHTML = '<div class="ms-d-scroll"><div class="ms-d-main"><p class="ms-d-desc">'
					+ esc((j && j.message) || 'Could not load that mission.') + '</p></div></div>'; return; }
				LO = j.loadout;
				renderDrawer();
				applyThreshold(LO.threshold);   // the default the old page pre-selected
			})
			.catch(function () {
				drawerBody.innerHTML = '<div class="ms-d-scroll"><div class="ms-d-main"><p class="ms-d-desc">'
					+ 'Could not reach the server.</p></div></div>';
			});
	};
	window.msCloseDrawer = function () { drawer.hidden = true; LO = null; picked = {}; items = {}; };
	drawer.addEventListener('click', function (e) { if (e.target === drawer) msCloseDrawer(); });
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !drawer.hidden) msCloseDrawer();
	});

	function renderDrawer() {
		var art = LO.video
			? '<video src="' + esc(LO.video) + '" poster="' + esc(LO.image) + '" autoplay muted loop playsinline></video>'
			: '<img src="' + esc(LO.image) + '" alt="" onerror="this.src=\'/staking/icons/skull.png\'">';

		var crew = LO.squad.length
			? LO.squad.map(function (s) {
				return '<button type="button" class="ms-d-nft" data-nft="' + s.nft_id + '" data-rate="' + s.rate + '">'
					+ '<img src="' + esc(s.image) + '" alt="" loading="lazy" onerror="this.src=\'/staking/icons/skull.png\'">'
					+ '<span>+' + s.rate + '%</span></button>';
			}).join('')
			: '<p class="ms-d-desc">Every NFT you own for this project is already out on a mission.'
			  + ' A success item can still send this one on its own.</p>';

		var pack = LO.items.length
			? LO.items.map(function (it) {
				return '<button type="button" class="ms-d-item" data-item="' + it.consumable_id
					+ '" data-boost="' + it.boost + '">'
					+ '<img src="' + esc(it.icon) + '" alt="" onerror="this.style.display=\'none\'">'
					+ esc(it.name) + ' <em>&times;' + it.amount + '</em></button>';
			}).join('')
			: '<p class="ms-d-desc">No items in your pack yet. They drop from completed missions.</p>';

		drawerBody.innerHTML =
			'<div class="ms-d-art">' + art + '</div>'
			+ '<div class="ms-d-scroll"><div class="ms-d-main">'
			+ '<h3 id="ms-d-title">' + esc(LO.title) + '</h3>'
			+ '<p class="ms-d-sub">' + esc(LO.project) + ' &middot; Level ' + LO.level + '</p>'
			+ (LO.description ? '<p class="ms-d-desc">' + esc(LO.description) + '</p>' : '')
			+ '<div class="ms-d-facts">'
			+   '<span><i>Cost</i><b>' + (LO.cost > 0 ? n(LO.cost) + ' ' + esc(LO.currency) : 'Free') + '</b></span>'
			+   '<span><i>Reward</i><b>' + n(LO.reward) + ' ' + esc(LO.currency) + '</b></span>'
			+   '<span><i>Runs for</i><b>' + LO.duration + (LO.duration === 1 ? ' day' : ' days') + '</b></span>'
			+   '<span><i>Net per day</i><b>' + n(Math.round(LO.net_per_day)) + '</b></span>'
			+   '<span><i>Your balance</i><b>' + n(LO.balance) + ' ' + esc(LO.currency) + '</b></span>'
			+ '</div>'
			+ '<div class="ms-d-rate">'
			+   '<div class="ms-d-rate-top"><b id="ms-d-pct">0%</b><span>Chance of success</span></div>'
			+   '<div class="ms-d-meter" id="ms-d-meter"><i style="width:0%"></i></div>'
			+   '<div class="ms-d-rate-note" id="ms-d-note"></div>'
			+ '</div>'
			+ '<div class="ms-d-group"><div class="ms-d-group-head"><h4>Crew</h4>'
			+   '<div class="ms-d-tools">'
			+     '<button type="button" class="ms-d-tool" data-act="max">Maximise</button>'
			+     '<button type="button" class="ms-d-tool" data-act="balance">Balance</button>'
			+     '<button type="button" class="ms-d-tool" data-act="none">Clear</button>'
			+   '</div></div>'
			+   '<div class="ms-d-crew">' + crew + '</div></div>'
			+ '<div class="ms-d-group"><div class="ms-d-group-head"><h4>Pack</h4></div>'
			+   '<div class="ms-d-items">' + pack + '</div></div>'
			+ '</div></div>'
			+ '<div class="ms-d-foot">'
			+   '<p class="ms-d-msg" id="ms-d-msg"></p>'
			+   '<div class="ms-d-launch">'
			+     '<span class="ms-d-cost" id="ms-d-summary"></span>'
			+     '<button type="button" class="button" id="ms-d-go">Launch mission</button>'
			+   '</div>'
			+ '</div>';

		drawerBody.querySelectorAll('.ms-d-nft').forEach(function (b) {
			b.addEventListener('click', function () { toggleNft(b); });
		});
		drawerBody.querySelectorAll('.ms-d-item').forEach(function (b) {
			b.addEventListener('click', function () { toggleItem(b); });
		});
		drawerBody.querySelectorAll('.ms-d-tool').forEach(function (b) {
			b.addEventListener('click', function () {
				var a = b.getAttribute('data-act');
				applyThreshold(a === 'max' ? 100 : (a === 'balance' ? LO.threshold : 0));
			});
		});
		document.getElementById('ms-d-go').addEventListener('click', launch);
	}

	function toggleNft(b) {
		var id = b.getAttribute('data-nft');
		if (picked[id] !== undefined) { delete picked[id]; b.classList.remove('on'); }
		else { picked[id] = parseFloat(b.getAttribute('data-rate')); b.classList.add('on'); }
		paintRate();
	}
	function toggleItem(b) {
		var id = b.getAttribute('data-item');
		if (items[id] !== undefined) { delete items[id]; b.classList.remove('on'); }
		else { items[id] = parseFloat(b.getAttribute('data-boost')); b.classList.add('on'); }
		paintRate();
	}

	/*
	 * MAXIMISE AND BALANCE, in the browser.
	 *
	 * Both were full page POSTs back to missions.php (renderInventoryButton),
	 * for what is: walk the roster in the order the server sent it and take
	 * NFTs while the running total stays under a threshold. 100 is Maximise;
	 * LO.threshold is the whale-balancing share the server worked out, which
	 * is what the old page pre-selected by default.
	 */
	function applyThreshold(limit) {
		picked = {};
		var total = 0;
		drawerBody.querySelectorAll('.ms-d-nft').forEach(function (b) {
			var rate = parseFloat(b.getAttribute('data-rate'));
			if (limit > 0 && total + rate <= limit) {
				total += rate; picked[b.getAttribute('data-nft')] = rate; b.classList.add('on');
			} else {
				b.classList.remove('on');
			}
		});
		paintRate();
	}

	function rateParts() {
		var crew = 0, boost = 0;
		for (var k in picked) crew += picked[k];
		for (var i in items) boost += items[i];
		return {crew: crew, boost: boost, total: Math.min(100, crew + boost)};
	}
	function paintRate() {
		var p = rateParts(), c = 0;
		for (var k in picked) c++;
		document.getElementById('ms-d-pct').textContent = Math.round(p.total) + '%';
		var m = document.getElementById('ms-d-meter');
		m.querySelector('i').style.width = p.total + '%';
		m.classList.toggle('sure', p.total >= 100);
		var bits = [c + (c === 1 ? ' NFT' : ' NFTs') + ' (' + Math.round(p.crew) + '%)'];
		if (p.boost > 0) bits.push('items (+' + Math.round(p.boost) + '%)');
		/* Say it plainly rather than making them work it out: over 100 is
		   waste, and the old page only told you by refusing at the alert. */
		if (p.crew + p.boost > 100) bits.push('capped at 100% — ' + Math.round(p.crew + p.boost - 100) + '% is going spare');
		document.getElementById('ms-d-note').textContent = bits.join(' + ').replace(' + capped', ' — capped');
		document.getElementById('ms-d-summary').textContent =
			(LO.cost > 0 ? 'Costs ' + n(LO.cost) + ' ' + LO.currency : 'Free to run')
			+ ' · back in ' + LO.duration + (LO.duration === 1 ? ' day' : ' days');
	}

	function launch() {
		var go = document.getElementById('ms-d-go');
		var msg = document.getElementById('ms-d-msg');
		var p = rateParts();
		var ids = Object.keys(picked);
		if (!ids.length && p.boost <= 0) {
			msg.className = 'ms-d-msg';
			msg.textContent = 'Pick at least one NFT, or a success item, before launching.';
			return;
		}
		go.disabled = true; go.textContent = 'Launching…';
		msg.className = 'ms-d-msg ok'; msg.textContent = '';

		var body = new URLSearchParams();
		body.set('quest_id', LO.quest_id);
		body.set('nfts', ids.join(','));
		body.set('items', Object.keys(items).join(','));

		fetch('ajax/mission-launch.php', {method: 'POST', body: body, credentials: 'same-origin'})
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (!j || !j.ok) {
					go.disabled = false; go.textContent = 'Launch mission';
					msg.className = 'ms-d-msg';
					msg.textContent = (j && j.message) || 'Could not launch.';
					return;
				}
				paintField(j.field, j.ready);
				navCount('ms-nav-field', j.total);
				refreshNews();
				msCloseDrawer();
				/* The ladder now shows one more "out" badge, and the crew that
				   went is no longer idle anywhere else on the page. */
				var sel = document.querySelector('.ms-proj.sel');
				if (sel) msPickProject(sel.getAttribute('data-project'), sel);
				document.getElementById('ms-field-section').scrollIntoView({behavior: 'smooth', block: 'start'});
			})
			.catch(function () {
				go.disabled = false; go.textContent = 'Launch mission';
				msg.className = 'ms-d-msg';
				msg.textContent = 'Could not reach the server.';
			});
	}

	/*
	 * WHICH SECTION YOU ARE IN. An observer rather than a scroll handler:
	 * this page can be very tall, and a listener firing on every pixel of a
	 * scroll through two hundred cards is exactly the sort of thing that
	 * makes a long page feel worse than it is.
	 */
	(function () {
		var nav = document.getElementById('ms-nav');
		if (!nav || !('IntersectionObserver' in window)) return;
		var links = {};
		nav.querySelectorAll('a[data-sec]').forEach(function (a) { links[a.getAttribute('data-sec')] = a; });
		var seen = {};
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (e) { seen[e.target.id] = e.isIntersecting; });
			var first = null;
			Object.keys(links).forEach(function (id) { if (!first && seen[id]) first = id; });
			Object.keys(links).forEach(function (id) { links[id].classList.toggle('on', id === first); });
		}, {rootMargin: '-56px 0px -60% 0px'});
		Object.keys(links).forEach(function (id) {
			var el = document.getElementById(id);
			if (el) io.observe(el);
		});
	}());

	/* The record panel keeps db.php's renderer and its lazy load. */
	if (typeof loadTotalMissions === 'function') loadTotalMissions();

	/* The three bulk launchers hide themselves when there is nothing to
	   deploy. If all three are hidden the bar is an empty box with a
	   heading, so say what is actually going on instead. */
	(function () {
		var bar = document.getElementById('ms-sec-deploy');
		if (!bar) return;
		var any = Array.prototype.some.call(bar.querySelectorAll('span[id]'), function (s) {
			return s.style.display !== 'none' && s.querySelector('button');
		});
		if (!any) {
			bar.querySelector('.ms-deploy-buttons').innerHTML =
				'<span class="ms-d-cost">Everything you own is already out on a mission.</span>';
		}
	}());
}());
</script>
<?php endif; ?>
</html>
