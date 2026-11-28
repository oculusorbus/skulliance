<?php
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
require_once __DIR__ . '/gauntlets-fight-lib.php';     // the turn-based fight
require_once __DIR__ . '/gauntlets-fight-render.php';  // shared with ajax/gauntlet-fight.php

$user_id = intval($_SESSION['userData']['user_id']);

define('GAUNTLET_ACTIONS_OK', 1);
require __DIR__ . '/gauntlets-actions.php';

// PRG, exactly as before: the no-JS path posts and is redirected. The
// AJAX path never reaches here -- it posts to ajax/gauntlet-page.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	header('Location: gauntlets.php');
	exit;
}

// Tell nginx not to buffer this response so the loader flushes to the browser immediately
header('X-Accel-Buffering: no');
include 'header.php';
?>
<style>
#gauntlet-loader {
	position: fixed; inset: 0;
	background: #07111d;
	z-index: 9999;
	display: flex; flex-direction: column;
	align-items: center; justify-content: center; gap: 20px;
	transition: opacity 0.5s ease;
}
#gauntlet-loader.fade-out { opacity: 0; pointer-events: none; }
@keyframes lb { to { width:90%; } }
.loader-bar-wrap { width:200px;height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden; }
.loader-bar      { height:100%;background:#00c8a0;width:0%;animation:lb 9s ease-out forwards; }
.loader-text     { font-size:.78rem;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase; }
</style>
<div id="gauntlet-loader">
	<div style="animation:lp 1.2s ease-in-out infinite;"><img src="/staking/pwa/skulliance-logo-icon.png" alt="" width="35" height="48"></div>
	<div class="loader-bar-wrap"><div class="loader-bar"></div></div>
	<div class="loader-text">Loading Gauntlet</div>
</div>
<?php
if (ob_get_level() > 0) { ob_flush(); flush(); }

define('GAUNTLET_STATE_OK', 1);
require __DIR__ . '/gauntlets-state.php';
?>
<style>
/* ── Gauntlet page ── */
.gauntlet-wrap        { max-width: 900px; margin: 0 auto; padding: 20px 16px 60px; }
.gauntlet-title       { font-size: 2rem; font-weight: 700; color: #00c8a0; letter-spacing: .05em; margin-bottom: 4px; text-transform: uppercase; }
.gauntlet-subtitle    { color: rgba(255,255,255,.45); font-size: .85rem; margin-bottom: 24px; }

/* Run progress bar */
.run-progress         { display: flex; gap: 10px; margin-bottom: 24px; }
.run-pip              { flex: 1; height: 6px; border-radius: 3px; background: rgba(255,255,255,.1); }
.run-pip.win          { background: #00c8a0; }
.run-pip.loss         { background: #e05555; }

/* Flash messages */
.flash                { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: .9rem; }
.flash.win            { background: rgba(0,200,160,.15); border: 1px solid #00c8a0; color: #00c8a0; }
.flash.loss           { background: rgba(224,85,85,.15);  border: 1px solid #e05555; color: #e05555; }
.flash.error          { background: rgba(224,85,85,.1);   border: 1px solid rgba(224,85,85,.4); color: #e05555; }
.flash.info           { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.15); color: rgba(255,255,255,.7); }

/* NFT cards */
.hand-grid            { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
@media (max-width: 600px) { .hand-grid { grid-template-columns: repeat(2, 1fr); } }

.nft-card             { background: #002f44; border: 2px solid rgba(255,255,255,.08); border-radius: 10px; overflow: hidden; cursor: pointer; transition: border-color .2s, transform .15s; position: relative; }
.nft-card:hover:not(.played) { border-color: #00c8a0; transform: translateY(-3px); }
.nft-card.played      { opacity: .35; cursor: default; pointer-events: none; }
.nft-card.selected    { border-color: #00c8a0; box-shadow: 0 0 12px rgba(0,200,160,.35); }
.nft-card img         { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.nft-img              { background: url('icons/skull.png') center/40% no-repeat rgba(0,0,0,.15); }
.nft-card-info        { padding: 8px 10px; }
.nft-card-name        { font-size: .78rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nft-card-project     { font-size: .7rem; color: rgba(255,255,255,.4); margin-top: 2px; }
.played-label         { position: absolute; top: 6px; right: 6px; background: rgba(0,0,0,.7); color: rgba(255,255,255,.5); font-size: .65rem; padding: 2px 6px; border-radius: 3px; }

/* Encounter arena */
.arena                { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: start; margin-bottom: 24px; }
.arena-vs             { display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; color: rgba(255,255,255,.3); padding-top: 40px; }
@media (max-width: 600px) {
	.hide-in-fight { display: none; }
	.arena { gap: 8px; }
	.arena-vs { font-size: 1rem; padding-top: 30px; }
	.arena-card-label { font-size: .6rem; padding: 6px 6px 2px; }
	.arena-card-name  { font-size: .72rem; padding: 0 6px 3px; }
	.arena-card-project, .arena-card-user { font-size: .65rem; padding: 0 6px 6px; }
}
.arena-card           { background: #002f44; border: 2px solid rgba(255,255,255,.1); border-radius: 10px; overflow: hidden; }
.arena-card.player    { border-color: #00c8a0; }
.arena-card img       { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.arena-card-label     { font-size: .65rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.4); padding: 8px 10px 2px; text-align: center; }
.arena-card-name      { font-size: .82rem; font-weight: 600; color: #fff; padding: 0 10px 4px; text-align: center; }
.arena-card-project   { font-size: .72rem; color: rgba(255,255,255,.45); padding: 0 10px 4px; text-align: center; }
.arena-card-user      { display: flex; align-items: center; justify-content: center; gap: 5px; padding: 0 10px 10px; }
.arena-card-user img  { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.arena-card-user a    { font-size: .72rem; color: #00c8a0; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.arena-card-user a:hover { text-decoration: underline; }

/* Odds display */

/* Resource panel */

/* Action buttons */
.btn-fight            { width: 100%; padding: 14px; font-size: 1rem; font-weight: 700; background: #00c8a0; color: #000; border: none; border-radius: 8px; cursor: pointer; letter-spacing: .05em; transition: opacity .15s; margin-bottom: 16px; }
.btn-fight:hover      { opacity: .85; }
/* Auto width and centred, like every other control in the fight. It
   was full-bleed, which made a run-level action look like a page
   section rather than a button. */
.btn-ff               { display: block; margin: 0 auto 10px; width: auto; min-width: 260px; max-width: 100%; padding: 10px; font-size: .82rem; background: rgba(255,255,255,.07); color: rgba(255,255,255,.65); border: 1px solid rgba(255,255,255,.15); border-radius: 8px; cursor: pointer; transition: all .15s; }
.btn-ff:hover         { background: rgba(255,255,255,.12); }
.btn-ff:disabled      { opacity: .3; cursor: default; }
.btn-start            { padding: 14px 40px; font-size: 1rem; font-weight: 700; background: #00c8a0; color: #000; border: none; border-radius: 8px; cursor: pointer; letter-spacing: .05em; transition: opacity .15s; }
.btn-start:hover      { opacity: .85; }

/* FF card picker overlay */
.ff-overlay           { display: none; background: rgba(0,0,0,.85); border: 1px solid rgba(255,255,255,.12); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
.ff-overlay.open      { display: block; }
.ff-overlay-title     { font-size: .8rem; color: rgba(255,255,255,.5); margin-bottom: 12px; }
.ff-hand-grid         { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }

/* Run results */
.run-results          { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 20px; margin-bottom: 24px; text-align: center; }
.run-result-icon      { font-size: 3rem; margin-bottom: 8px; }
.run-result-title     { font-size: 1.4rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
.run-result-sub       { font-size: .85rem; color: rgba(255,255,255,.45); }

/* Encounter history */
.history-list         { display: flex; flex-direction: column; gap: 8px; }
.history-row          { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.06); border-radius: 8px; padding: 10px 14px; }
.history-badge        { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 700; flex-shrink: 0; }
.history-badge.win    { background: rgba(0,200,160,.2); color: #00c8a0; }
.history-badge.loss   { background: rgba(224,85,85,.2); color: #e05555; }
.history-text         { font-size: .82rem; color: rgba(255,255,255,.7); flex: 1; }
.history-avatar       { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin: 0 3px 0 6px; }
.history-text a       { color: #00c8a0; text-decoration: none; font-size: .78rem; }
.history-text a:hover { text-decoration: underline; }
.history-meta         { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; flex-shrink: 0; }
.history-reward       { display: flex; align-items: center; gap: 4px; font-size: .78rem; font-weight: 600; white-space: nowrap; }
.history-reward.win   { color: #00c8a0; }
.history-reward.loss  { color: rgba(224,85,85,.75); }
.history-reward img   { width: 14px; height: 14px; object-fit: contain; vertical-align: middle; }
.history-reward .reward-random { font-size: .65rem; color: rgba(255,255,255,.3); }
.history-odds         { font-size: .72rem; color: rgba(255,255,255,.25); text-align: right; }

.section-heading      { font-size: .75rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.3); margin: 24px 0 10px; }

/* Win reward banner (pick_card state) */
.win-reward-banner    { display: flex; align-items: center; justify-content: center; gap: 10px; background: rgba(0,200,160,.1); border: 1px solid rgba(0,200,160,.35); border-radius: 8px; padding: 14px 20px; margin-bottom: 20px; }
.win-reward-amount    { font-size: 1.5rem; font-weight: 700; color: #00c8a0; line-height: 1; }
.win-reward-banner img { width: 30px; height: 30px; object-fit: contain; }
.win-reward-currency  { font-size: .8rem; color: rgba(255,255,255,.4); }

/* Fight animation overlay (pre-submit suspense) */

/* Result reveal overlay (post-fight modal) */
#result-reveal { position: fixed; inset: 0; z-index: 9998; background: rgba(7,17,29,.97); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; cursor: pointer; animation: rr-in .3s ease forwards; }
#result-reveal.hiding { animation: rr-out .4s ease forwards; }
.rr-accent  { position: absolute; inset: 0; pointer-events: none; }
#result-reveal.win  .rr-accent { box-shadow: inset 0 0 120px rgba(0,200,160,.18); }
#result-reveal.loss .rr-accent { box-shadow: inset 0 0 120px rgba(224,85,85,.18); }
.rr-drop    { margin-top: 10px; font-size: .95rem; font-weight: 700; color: #ffc800;
              letter-spacing: .04em; display: flex; flex-wrap: wrap;
              align-items: center; justify-content: center; gap: 8px; }
.rr-took    { display: inline-flex; align-items: center; gap: 7px; }
.rr-drop img { width: 24px; height: 24px; object-fit: contain; }
/* Its own full-width row under the icon and the words, which is what
   the block display did before this line became a flex row. */
.rr-drop .rr-drop-note { flex: 0 0 100%; }
.rr-drop-note { display: block; font-size: .65rem; font-weight: 400; letter-spacing: .1em;
                text-transform: uppercase; color: rgba(255,200,0,.55); margin-top: 3px; }
.rr-icon    { font-size: 5rem; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .15s both; }
.rr-title   { font-size: 2.8rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .25s both; }
#result-reveal.win  .rr-title { color: #00c8a0; }
#result-reveal.loss .rr-title { color: #e05555; }
.rr-reward  { display: flex; align-items: center; gap: 10px; font-size: 1.6rem; font-weight: 700; color: #00c8a0; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .35s both; }
.rr-reward img { width: 32px; height: 32px; object-fit: contain; }
.rr-win-num { font-size: .78rem; color: rgba(255,255,255,.35); letter-spacing: .12em; text-transform: uppercase; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .45s both; }
.rr-dismiss { font-size: .72rem; color: rgba(255,255,255,.2); letter-spacing: .1em; text-transform: uppercase; margin-top: 8px; animation: rr-pop .45s ease .8s both; }
@keyframes rr-in  { from { opacity: 0; } to { opacity: 1; } }
@keyframes rr-out { from { opacity: 1; } to { opacity: 0; pointer-events: none; } }
@keyframes rr-pop { from { opacity: 0; transform: scale(.55); } to { opacity: 1; transform: scale(1); } }
</style>

<div class="row">
<div class="main">
<div class="gauntlet-wrap">

	<?php /* Swapped whole between rounds -- see gauntlets-body.php. The
	         <audio> and the fight's script live OUTSIDE it, in the shell,
	         so neither is torn down when a round ends. */
	   define('GAUNTLET_BODY_OK', 1); ?>
	<?php /* ONCE, IN THE SHELL. The <audio>, the fight's stylesheet and
	         its script are loaded here and never again: #g-area below is
	         replaced wholesale between rounds, and anything in there is
	         torn down with it. */
	   include __DIR__ . '/gauntlets-fight-ui.php';

	   /* The chest reveal, for the same reason: a <script> arriving in a
	      swapped fragment never runs, so the performance is loaded once
	      here and the body only leaves it a payload. */
	   $sk_reveal_only = true;
	   include __DIR__ . '/missions-claim-modal.php'; ?>

	<div id="g-area" data-mood="<?php echo htmlspecialchars(gaunt_page_mood($state, $run_stats)); ?>">
	<?php include __DIR__ . '/gauntlets-body.php'; ?>
	</div><!-- #g-area -->

	<?php /* UNDER THE GAME, not over it. It is the least urgent control on
	         the page and it was pushing the fight down a phone screen.
	         Still OUTSIDE #g-area, which is the part that matters: the
	         body is swapped between rounds and anything inside it would be
	         rebuilt mid-track. */ ?>
	<div class="ska-player gf-audio" id="gf-audio">
		<button type="button" class="ska-btn" id="gf-a-play" title="Play or pause the music">&#9654;</button>
		<span class="ska-name" id="gf-a-name">Music off</span>
		<button type="button" class="ska-btn" id="gf-a-mute" title="Mute">&#128266;</button>
		<input type="range" class="ska-vol" id="gf-a-vol" min="0" max="100" value="32"
		       title="Volume" aria-label="Music volume">
	</div>

</div><!-- .gauntlet-wrap -->
</div><!-- .main -->
</div><!-- .row -->

<div class="footer">
	<p>Skulliance<br>Copyright &copy; <span id="year"></span></p>
</div>
</div>
</div>
</body>
<?php $conn->close(); ?>
<script>
document.getElementById('year') && (document.getElementById('year').textContent = new Date().getFullYear());

// Show loader overlay (used before form submissions for immediate feedback)
/*
 * A FULL-SCREEN OVERLAY GOES TO THE TOP OF THE PAGE AND PINS IT THERE.
 *
 * REPORTED: picking a card lower down the list, or opening the items
 * panel, made the fight overlay and the loader "load off centre towards
 * the top". The CSS is not the problem -- both are position:fixed inset:0
 * with justify-content:center, and rendered against the real stylesheets
 * while scrolled 900px down they measure full-viewport with their content
 * dead centre. It is the scroll position.
 *
 * Picking a card POSTs and navigates. You had to scroll down to reach that
 * card, so the browser restores that scroll on the next page -- and this
 * page STREAMS, flushing the loader before the rest of the document
 * exists. The overlay is laid out against a viewport that is still being
 * established and then the page jumps underneath it, which on iOS leaves
 * the fixed layer anchored where it started rather than where the viewport
 * ended up. The further down the list you picked, the further off it sat.
 *
 * So: put the window back at the top, and hold it there while the overlay
 * is up. Both halves are needed. Scrolling alone still lets a stray touch
 * move the page behind a modal; locking alone leaves it locked at whatever
 * offset it inherited.
 */
function lockForOverlay() {
	window.scrollTo(0, 0);
	document.body.style.overflow = 'hidden';
}
function unlockAfterOverlay() {
	document.body.style.overflow = '';
}

function showLoader(msg) {
	var loader = document.getElementById('gauntlet-loader');
	if (!loader) return;
	lockForOverlay();
	var txt = loader.querySelector('.loader-text');
	if (txt && msg) txt.textContent = msg;
	// Reset bar animation by replacing the element
	var bar = loader.querySelector('.loader-bar');
	if (bar) { var nb = bar.cloneNode(true); bar.parentNode.replaceChild(nb, bar); }
	loader.classList.remove('fade-out');
	loader.style.display = 'flex';
	loader.style.opacity = '1';
}

/*
 * THIS PAGE ALWAYS STARTS AT THE TOP, and that is the other half of the
 * fix above. Every move here is a POST that reloads the page, so the
 * browser restores wherever you were scrolled -- and the first thing this
 * page shows is a full-screen loader, which has nothing to do with the
 * card you happened to be looking at. The restore is what put the overlay
 * and the viewport out of step in the first place.
 *
 * scrollRestoration is set as early as the script runs rather than on
 * unload, so it covers a back-navigation into the page too.
 */
if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

/* The loader is in the markup and visible before any of this runs, and a
   result reveal is printed server-side after a fight -- so whatever the
   browser did with the scroll, start level with the overlay. */
(function(){
	if (document.getElementById('gauntlet-loader') || document.getElementById('result-reveal')) {
		lockForOverlay();
	}
})();

// Fade out page loader now that DOM is fully rendered
(function(){
	var loader = document.getElementById('gauntlet-loader');
	if (loader) {
		loader.classList.add('fade-out');
		setTimeout(function(){
			loader.style.display = 'none';
			/* Only give the page back if nothing else is covering it: the
			   result reveal is printed server-side and outlives the loader
			   by up to four seconds. */
			if (!document.getElementById('result-reveal')
			    || document.getElementById('result-reveal').style.display === 'none') {
				unlockAfterOverlay();
			}
		}, 500);
	}
})();

/* The loader's message. Chosen in GAUNT_POST now rather than bound to
   each form at load: the forms are replaced on every swap, and a
   listener attached to the old ones would be gone with them. */
function gauntletLoaderMsg(form) {
	var a = (form.querySelector('[name="action"]') || {}).value || '';
	return a === 'fast_forward' ? 'Swapping Card…' : 'Loading Gauntlet';
}

/*
 * Result reveal -- auto-dismiss after 4s or on tap.
 *
 * THIS HAS TO BE RE-ARMED AFTER A SWAP. It used to be a one-shot IIFE,
 * which was correct while every round was a fresh page load. A reveal
 * that arrives in a swapped body would otherwise never time out and
 * would sit over the page until it was tapped.
 */
function armResultReveal() {
	var rr = document.getElementById('result-reveal');
	if (!rr || rr._armed) return;
	rr._armed = true;
	lockForOverlay();
	rr._autoTimer = setTimeout(dismissResult, 4000);
}
armResultReveal();

/*
 * The chest, fired from the payload the body leaves behind. Runs on a
 * fresh load and again after every swap; the id is dropped once it has
 * played so a later swap cannot replay the same haul.
 */
function fireChestReveal() {
	var n = document.getElementById('g-chest-payload');
	if (!n || !window.SK_REVEAL_SHOW) return;
	var p;
	try { p = JSON.parse(n.getAttribute('data-reveal') || 'null'); } catch (e) { return; }
	n.removeAttribute('id');
	if (p && p.items && p.items.length) SK_REVEAL_SHOW(p);
}
fireChestReveal();
function dismissResult() {
	var rr = document.getElementById('result-reveal');
	if (!rr || rr.classList.contains('hiding')) return;
	clearTimeout(rr._autoTimer);
	rr.classList.add('hiding');
	setTimeout(function(){ rr.style.display = 'none'; unlockAfterOverlay(); }, 400);
}

// Fight animation — show suspense overlay, then submit

// Pick card from hand
function pickCard(nftId) {
	document.querySelectorAll('.nft-card').forEach(c => c.classList.remove('selected'));
	event.currentTarget.classList.add('selected');
	document.getElementById('pick-nft-id').value = nftId;
	showLoader('Loading Gauntlet');
	/* Through the swap, not a navigation -- and NOT via .submit(), which
	   fires no submit event and would sail straight past the handler
	   below. Falls back to a real post if the swap is unavailable. */
	setTimeout(function () {
		var f = document.getElementById('pick-form');
		if (!window.GAUNT_POST || !GAUNT_POST(f)) f.submit();
	}, 120);
}

/*
 * THE ROUND CHANGE, WITHOUT A PAGE LOAD.
 *
 * Every action here was a POST and a redirect, so finishing a fight and
 * picking the next card reloaded the document twice -- and each reload
 * destroyed the <audio> element and started the soundtrack over. The
 * shell (this file) is now loaded once and #g-area is swapped in place,
 * so the player, the track and its position all survive a round.
 *
 * ajax/gauntlet-page.php runs the SAME gauntlets-actions.php this page
 * runs, so the two cannot drift. Any failure falls back to the ordinary
 * post, which still works on its own.
 */
(function () {
	var AREA = 'g-area';
	function area() { return document.getElementById(AREA); }

	window.GAUNT_POST = function (form) {
		var el = area();
		if (!el || !form || !window.fetch || !window.FormData) return false;
		var body = new URLSearchParams(new FormData(form));
		showLoader(gauntletLoaderMsg(form));
		fetch('ajax/gauntlet-page.php', {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		}).then(function (r) {
			if (!r.ok) throw new Error('http');
			return r.text();
		}).then(function (html) {
			var holder = document.createElement('div');
			holder.innerHTML = html;
			var next = holder.querySelector('#' + AREA);
			var cur  = area();
			if (!next || !cur) throw new Error('fragment');
			cur.replaceWith(next);
			hideGauntletLoader();
			/* The fight's script is in the shell and outlived the swap,
			   so the new arena has to be handed to it. It also re-reads
			   the mood off the new #g-area. */
			if (window.GF_BOOT) GF_BOOT();
			/* Both of these were bound once at load, which was fine when
			   a round was a page load. */
			armResultReveal();
			fireChestReveal();
			window.scrollTo(0, 0);
		}).catch(function () { form.submit(); });
		return true;
	};

	/* Capture phase, so this runs before anything a fragment binds. The
	   fight's own form is not ours: it posts single turns to its own
	   endpoint and swaps only the arena. */
	document.addEventListener('submit', function (ev) {
		var f = ev.target;
		if (!f || f.id === 'gf-form') return;
		var el = area();
		if (!el || !el.contains(f)) return;
		ev.preventDefault();
		if (!GAUNT_POST(f)) f.submit();
	}, true);
})();

function hideGauntletLoader() {
	var loader = document.getElementById('gauntlet-loader');
	if (!loader) return;
	loader.classList.add('fade-out');
	setTimeout(function () {
		loader.style.display = 'none';
		if (!document.getElementById('result-reveal')) unlockAfterOverlay();
	}, 260);
}


// Resource selection — consumables are multi-select (toggle); weapon/armor are single-select


// Resource tab switching

// Fast Forward overlay
function toggleFF() {
	const ov = document.getElementById('ff-overlay');
	if (ov) ov.classList.toggle('open');
}

function submitFF(encId, newNftId) {
	document.getElementById('ff-new-nft-id').value = newNftId;
	/* Same reason as pickCard: .submit() fires no submit event, so it
	   would walk straight past the delegated handler and reload the
	   page -- which is the one thing that restarts the soundtrack. */
	var f = document.getElementById('ff-form');
	if (!window.GAUNT_POST || !GAUNT_POST(f)) { showLoader('Swapping Card…'); f.submit(); }
}
</script>

<?php include 'dhc-dropmodal.php'; ?>
<?php if (!empty($_SESSION['dhcf_gauntlet_win'])): unset($_SESSION['dhcf_gauntlet_win']); ?>
<script>
/* Gauntlets pays Effects for SWEEPING a gauntlet -- all three encounters, no
   loss. Claimed once: the flag is cleared as it is read, so refreshing the
   victory page cannot farm it. The server enforces the daily cap regardless. */
if (window.DHC_DROP) DHC_DROP({ game: 'gauntlets', value: 1, delay: 900 });
</script>
<?php endif; ?>
</html>
