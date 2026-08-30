<?php
// CRYPT CONQUEST — Regicide-inspired solo card game. Vertical-slice
// prototype (2026-08-30) built directly off Crypt Crawl's own architecture,
// including its two hardest-won fixes: the merge-not-replace SessionCookie
// restore (see skulliance.php's own comment for why a raw replace is
// dangerous), and the hidden-overlay pattern for a reliable win/loss
// screen (see cryptconquest.md and cryptcrawl.php's own long comment on
// #cc-result-overlay for the saga that pattern came out of). Not linked in
// nav yet -- see cryptconquest.md §6 for what's still outstanding before
// this is a real, announced feature. Public/guest-playable, same as
// cryptcrawl.php, only persisted to the DB for a real account.
include_once 'db.php';
include 'message.php';
include 'verify.php';
include_once 'cryptconquest-actions.php';
include_once 'cryptconquest-render.php';

// Same forced-session-start as cryptcrawl.php -- a brand-new anonymous
// visitor still needs somewhere for flash messages/guest run state to live
// between requests, and db.php only starts a session when a cookie already exists.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Restore from the 6-month SessionCookie when PHPSESSID has lapsed --
// same merge-not-replace pattern as every other page here (see
// skulliance.php's own fix and MAINTENANCE.md for why a raw replace is
// dangerous: it can silently wipe this exact session's own
// cryptconquest_flash/cryptconquest_guest_run, not just restore login).
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
    $cookieData = json_decode($_COOKIE['SessionCookie'], true);
    if (is_array($cookieData)) {
        $_SESSION = array_merge((array)$_SESSION, $cookieData);
    }
}
$user_id = isset($_SESSION['userData']['user_id']) ? intval($_SESSION['userData']['user_id']) : 0;

if (!isset($_SESSION['cryptconquest_flash'])) $_SESSION['cryptconquest_flash'] = [];

// No-JS fallback only -- with JS, forms inside #cq-game-area post to
// ajax/cryptconquest-action.php instead (see the script block below).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	cryptconquestHandleAction($conn, $user_id, $_POST);
	header('Location: cryptconquest.php');
	exit;
}

// header.php's nav is gated on isset($name) (plus $avatar_url) -- normally
// supplied by skulliance.php's extract(). This page skips that hard-gated
// include the same way cryptcrawl.php does, so a logged-in visitor needs
// the same two values computed here or the whole nav renders empty.
if ($user_id > 0 && isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	extract($_SESSION['userData']);
	if (isset($discord_id) && isset($avatar)) {
		$avatar_url = "https://cdn.discordapp.com/avatars/$discord_id/$avatar.jpg";
	}
}

include 'header.php';
?>
<style>
@keyframes cqResultPop { from { opacity: 0; transform: scale(.6); } to { opacity: 1; transform: scale(1); } }
@keyframes cqFlashIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes cqFlashBackdropIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes cqFlashModalIn { from { opacity: 0; transform: scale(.85) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes cqBtnSheen { from { transform: translateX(-120%) skewX(-20deg); } to { transform: translateX(220%) skewX(-20deg); } }
@keyframes cqKenBurns {
	from { transform: scale(var(--kb-scale-from, 1)) translate(var(--kb-x-from, 0%), var(--kb-y-from, 0%)); }
	to   { transform: scale(var(--kb-scale-to, 1.12)) translate(var(--kb-x-to, 2%), var(--kb-y-to, -2%)); }
}

.cq-wrap { padding: 20px 16px 60px; }
/* position:relative + a real z-index here is load-bearing, not decorative --
   confirmed live (both desktop and reported on mobile): #cq-theme-bg::before
   runs a continuous Ken Burns animation with will-change:transform, which
   Chrome/WebKit promotes to its own compositor layer. Once that layer
   exists, a plain static, non-positioned sibling can end up painted BEHIND
   it despite coming later in DOM order -- that's exactly what was
   happening to .cq-suffer-banner (the "Incoming: N damage" prompt): fully
   correct markup/data (verified via outerHTML), valid layout, just
   invisible, because it was the one piece of UI here with no transform/
   position of its own to earn a competing stacking context. Every other
   visible element (cards, buttons, the HUD box) happened to already have
   position:relative or an animated transform for unrelated reasons (sheen
   effects, corner badges, its own intro animation), which is why only this
   one prompt vanished. Promoting the whole content wrapper once here is
   the general fix -- any future plain child inherits a safe stacking
   context instead of only patching the one element that happened to get
   caught by it this time. */
.cq-inner { max-width: 720px; width: 100%; margin: 0 auto; position: relative; z-index: 1; }
#cq-game-area, #cq-result-overlay { width: 100%; }
/* #cq-theme-bg is a PERMANENT element (see the markup below) -- present in
   every state, "bare" (this rule only) when no themed backdrop applies.
   Ported directly from cryptcrawl.php's own #cc-theme-bg -- see that
   file's comment for why it has to be permanent (an AJAX-swapped copy
   would restart the Ken Burns drift on every single action instead of
   only when the scene actually changes). */
.cq-theme-bg { position: relative; }
.cq-theme-bg.cq-theme-active {
	overflow: hidden; border-radius: 14px; padding: 18px; margin: 0 -16px;
	display: flex; align-items: center; justify-content: center; box-sizing: border-box; min-height: 200px;
}
.cq-theme-bg::before {
	content: ''; position: absolute; inset: -5%; background-image: var(--theme-img);
	background-size: cover; background-position: center; transition: background-image .6s ease;
	will-change: transform; display: none;
}
.cq-theme-bg.cq-theme-active::before { display: block; }
.cq-theme-bg.cq-zoom::before {
	animation: cqKenBurns var(--kb-duration, 26s) ease-in-out infinite alternate;
}

.cq-flash-backdrop {
	position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.65);
	display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
	padding: 24px; box-sizing: border-box; cursor: pointer;
	animation: cqFlashBackdropIn .25s ease both;
}
.cq-flash-modal {
	background: rgba(10,16,24,.97); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
	border-radius: 16px; padding: 26px 24px; max-width: 380px; width: 100%; box-sizing: border-box;
	text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.6);
	animation: cqFlashModalIn .35s cubic-bezier(.18,.89,.32,1.28) both;
}
.cq-flash-icon { font-size: 2rem; margin-bottom: 8px; line-height: 1; }
.cq-flash-text { font-size: 1rem; font-weight: 700; line-height: 1.4; }
.cq-flash-modal.win { border: 1px solid rgba(0,200,160,.5); }
.cq-flash-modal.win .cq-flash-text { color: #00c8a0; }
.cq-flash-modal.error { border: 1px solid rgba(255,68,68,.5); }
.cq-flash-modal.error .cq-flash-text { color: #ff7070; }
.cq-flash-modal.info { border: 1px solid rgba(255,255,255,.25); }
.cq-flash-modal.info .cq-flash-text { color: #c8dce8; }

.cq-instructions-backdrop { display: none; position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.65); align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; }
.cq-instructions-backdrop.show { display: flex; animation: cqFlashBackdropIn .2s ease both; }
.cq-instructions-modal {
	background: rgba(10,16,24,.97); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
	border-radius: 16px; padding: 22px 20px; max-width: 460px; width: 100%; box-sizing: border-box;
	box-shadow: 0 20px 60px rgba(0,0,0,.6); animation: cqFlashModalIn .3s cubic-bezier(.18,.89,.32,1.28) both;
	max-height: 85vh; overflow-y: auto;
}
.cq-instructions-modal h3 { margin: 0 0 10px; font-size: 1.05rem; }
.cq-instructions-close { margin-top: 16px; width: 100%; }

.cq-rules { font-size: 0.85rem; line-height: 1.6; opacity: 0.75; margin-bottom: 20px; }
.cq-note { font-size: 0.68rem; opacity: 0.5; margin: 8px 0 16px; }
.cq-rally { color: #00c8a0; font-weight: 700; white-space: nowrap; }
.cq-rally.used { color: rgba(255,255,255,.4); }

.cq-hud {
	display: flex; flex-direction: column; gap: 12px; margin-bottom: 18px; animation: cqFlashIn .5s ease .1s both;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border-radius: 12px; padding: 14px; box-sizing: border-box;
}
.cq-enemy { display: flex; align-items: center; gap: 14px; }
.cq-enemy-badge {
	flex: none; width: 64px; height: 88px; border-radius: 8px; background: #000;
	border: 2px solid var(--cq-suit-color, rgba(255,255,255,.2)); box-sizing: border-box;
	position: relative; overflow: hidden;
}
/* Real Crypties art fills the badge/card edge-to-edge (enemy pool for
   court cards, player pool -- Season 1, a deliberately different
   collection -- for numbers/Companions in hand, see
   cryptconquestGetEnemyCardArt()/GetPlayerCardArt() in db.php), with
   rank/suit as a text-shadowed corner overlay instead of centered text --
   same top-left + bottom-right corner-index treatment as Crypt Crawl's
   own .cc-card-corner tl/br, shown whether real art loaded or not (a
   404'd <img> just leaves the plain black background under the corners,
   see the onerror on each <img> itself). */
.cq-enemy-art-img, .cq-card-art-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
.cq-card-corner {
	position: absolute; display: flex; flex-direction: column; align-items: center; line-height: 1;
	text-shadow:
		-1.5px -1.5px 2px rgba(0,0,0,.95), 1.5px -1.5px 2px rgba(0,0,0,.95),
		-1.5px  1.5px 2px rgba(0,0,0,.95), 1.5px  1.5px 2px rgba(0,0,0,.95),
		0 0 8px rgba(0,0,0,.85), 0 0 3px rgba(0,0,0,.9);
}
.cq-card-corner.tl { top: 6%; left: 10%; }
.cq-card-corner.br { bottom: 6%; right: 10%; transform: rotate(180deg); }
.cq-corner-rank { font-size: 1rem; font-weight: 800; color: #fff; }
.cq-corner-suit { font-size: 1.2rem; color: #fff; margin-top: 1px; }
.cq-enemy-info { flex: 1; min-width: 0; }
.cq-enemy-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.cq-enemy-immune { font-size: 0.65rem; opacity: 0.6; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; padding: 2px 8px; text-transform: uppercase; letter-spacing: .03em; }
.cq-hp-bar-bg {
	background: linear-gradient(90deg,#ff4444,#ff9900,#00c8a0); border-radius: 6px; height: 12px;
	overflow: hidden; position: relative; margin-bottom: 6px;
}
.cq-hp-bar-fill {
	background: rgba(5,12,20,.88); height: 100%; position: absolute; top: 0; right: 0;
	transition: width .6s cubic-bezier(.22,1,.36,1);
}
.cq-enemy-stats { display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.75rem; opacity: 0.85; }
.cq-shield { color: #c8dce8; }
.cq-attack { color: #ff7070; }
.cq-hud-meta { display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.72rem; opacity: 0.8; align-items: center; border-top: 1px solid rgba(255,255,255,.08); padding-top: 10px; }
.cq-hud-carbon { display: inline-flex; align-items: center; gap: 3px; color: #ffcc4d; font-weight: 700; white-space: nowrap; margin-left: auto; }
.cq-hud-carbon img { width: 14px; height: 14px; object-fit: contain; }

.cq-suffer-banner {
	background: rgba(255,68,68,.12); border: 1px solid rgba(255,68,68,.4); border-radius: 10px;
	padding: 10px 14px; font-size: 0.85rem; margin-bottom: 14px; text-align: center;
}

/* Fixed 4 columns (not auto-fill) -- an 8-card hand used to split 6-then-2
   on a normal desktop width, which read as lopsided; this keeps it an
   even 4-and-4 regardless of hand size. */
.cq-hand { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 4px; }
.cq-card { display: block; cursor: pointer; }
.cq-card-check { position: absolute; opacity: 0; width: 0; height: 0; }
.cq-card-face {
	position: relative; aspect-ratio: 5 / 7; border-radius: 10px; background: #000;
	border: 2px solid rgba(255,255,255,.12); box-sizing: border-box; overflow: hidden;
	transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
}
.cq-card-companion-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
.cq-card-footer { font-size: 0.62rem; opacity: 0.6; text-align: center; margin-top: 4px; text-transform: uppercase; letter-spacing: .02em; }
.cq-card:has(.cq-card-check:checked) .cq-card-face {
	border-color: #ffcc4d; box-shadow: 0 0 0 2px rgba(255,204,77,.3), 0 6px 16px rgba(255,204,77,.25);
	transform: translateY(-4px);
}
@media (hover: hover) and (pointer: fine) {
	.cq-card:hover .cq-card-face { border-color: rgba(255,255,255,.35); }
}
@media (max-width: 480px) {
	/* 4 columns is snug under ~480px -- shrink the gap rather than drop to
	   fewer columns, so the "4 and 4" layout stays even at every width. */
	.cq-hand { gap: 6px; }
	.cq-corner-rank { font-size: 0.8rem; }
	.cq-corner-suit { font-size: 0.95rem; }
}

.cq-hand-controls { display: flex; gap: 8px; margin: 12px 0 4px; flex-wrap: wrap; }
.cq-hand-controls .cq-btn { flex: 1; min-width: 140px; }
.cq-controls-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
.cq-controls-row .cq-btn { flex: 1; min-width: 140px; }

.cq-btn {
	position: relative; overflow: hidden; background: #00c8a0; color: #012; border: 1px solid transparent; border-radius: 6px;
	padding: 10px 14px; font-size: 0.82rem; font-weight: 600; cursor: pointer; box-sizing: border-box;
	width: 100%; min-height: 46px; display: flex; align-items: center; justify-content: center; text-align: center;
	transition: transform .12s ease, filter .12s ease, box-shadow .2s ease;
}
.cq-btn:hover:not(:disabled) { filter: brightness(1.12); box-shadow: 0 6px 16px rgba(0,200,160,.35); }
.cq-btn:active:not(:disabled) { transform: scale(.96); }
.cq-btn.secondary { background: rgba(255,255,255,.08); color: #e8f2f8; border-color: rgba(255,255,255,.3); }
.cq-btn.secondary:hover:not(:disabled) { background: rgba(255,255,255,.15); box-shadow: 0 6px 16px rgba(255,255,255,.12); }
.cq-btn.attack { background: #ff4444; color: #012; }
.cq-btn.attack:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(255,68,68,.4); }
.cq-btn:disabled { opacity: 0.35; cursor: default; }
@media (hover: hover) and (pointer: fine) {
	.cq-btn:not(:disabled)::after {
		content: ''; position: absolute; top: 0; left: 0; width: 40%; height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
		transform: translateX(-120%) skewX(-20deg); pointer-events: none;
	}
	.cq-btn:not(:disabled):hover::after { animation: cqBtnSheen .6s ease; }
}

.cq-result {
	text-align: center; border-radius: 12px; padding: 30px 20px; margin-bottom: 20px; box-sizing: border-box;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
}
.cq-result.lost { box-shadow: 0 0 40px rgba(224,85,85,.15) inset, 0 0 1px rgba(224,85,85,.4); }
.cq-result.won { box-shadow: 0 0 40px rgba(0,200,160,.15) inset, 0 0 1px rgba(0,200,160,.4); }
.cq-result-icon { font-size: 3.2rem; margin-bottom: 10px; animation: cqResultPop .5s cubic-bezier(.18,.89,.32,1.28) .1s both; }
.cq-result-title { font-size: 1.5rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 8px; animation: cqResultPop .5s cubic-bezier(.18,.89,.32,1.28) .2s both; }
.cq-result.lost .cq-result-title { color: #ff7070; }
.cq-result.won .cq-result-title { color: #00c8a0; }
.cq-result-sub { font-size: .85rem; color: rgba(255,255,255,.5); animation: cqResultPop .5s cubic-bezier(.18,.89,.32,1.28) .3s both; }
.cq-result-carbon {
	margin-top: 10px; font-size: 1rem; font-weight: 700; color: #ffcc4d;
	display: flex; align-items: center; justify-content: center; gap: 6px;
	animation: cqResultPop .5s cubic-bezier(.18,.89,.32,1.28) .4s both;
}
.cq-result-carbon img { width: 20px; height: 20px; object-fit: contain; }

/* Persistent control bar -- same pill shape/position as Crypt Crawl's own
   #cc-audio-player (sits below the game content, in normal flow, so it's
   in the same spot regardless of no_run/active/game_over). */
.cq-player {
	max-width: 720px; margin: 14px auto 0; display: flex; align-items: center; justify-content: center; gap: 8px;
	background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); border-radius: 999px;
	padding: 8px 14px; box-sizing: border-box; font-size: 0.78rem; color: #c8dce8;
}
.cq-player-btn {
	background: none; border: none; color: #c8dce8; cursor: pointer;
	font-size: 0.78rem; font-weight: 600; padding: 4px 10px; border-radius: 999px;
	display: flex; align-items: center; gap: 6px; transition: background .12s ease, opacity .12s ease;
}
.cq-player-btn:hover { background: rgba(255,255,255,.1); }
.cq-player-btn.off { opacity: 0.5; }

@media (prefers-reduced-motion: reduce) {
	.cq-flash-backdrop, .cq-flash-modal, .cq-instructions-backdrop.show, .cq-instructions-modal,
	.cq-hud, .cq-btn::after, .cq-result-icon, .cq-result-title, .cq-result-sub, .cq-result-carbon,
	.cq-theme-bg::before { animation: none !important; }
	.cq-btn, .cq-hp-bar-fill, .cq-card-face, .cq-theme-bg::before { transition: none !important; }
}
</style>
<div class="cq-wrap">
<!-- Permanent wrapper -- unlike #cq-game-area inside it, this element is
     NEVER destroyed/recreated by an AJAX swap, specifically so its Ken
     Burns CSS animation (.cq-zoom, above) keeps running continuously
     across actions instead of restarting on every single one. Starts
     "bare" (no cq-theme-active class, no background) since PHP no longer
     decides whether to emit it at all -- that's now a class JS toggles
     based on #cq-theme-state's data-theme-* attributes (emitted fresh
     inside #cq-game-area on every render), applied by applyThemeState()
     below. Ported directly from cryptcrawl.php's #cc-theme-bg. -->
<div class="cq-theme-bg" id="cq-theme-bg">
<div id="cq-game-area"><?php cryptconquestRenderGameArea($conn, $user_id); ?></div>
<!-- Permanent, hidden sibling of #cq-game-area -- a win/loss result gets
     dropped in here and revealed with a synchronous style.display flip
     instead of an innerHTML swap into #cq-game-area itself. Ported
     directly from cryptcrawl.php's #cc-result-overlay -- see that file's
     own long comment (and cryptconquest.md) for the saga this design
     replaced: an in-place swap and a forced navigation both turned out to
     have real failure modes across browser/PWA/mobile that this doesn't. -->
<div id="cq-result-overlay" style="display:none;"></div>
</div>
<!-- Persistent control bar -- OUTSIDE #cq-game-area/#cq-theme-bg on
     purpose, so it's visible in every state (no_run/active/game_over),
     not just tucked into the active-play controls row where it used to
     live and was easy to miss. Same pill-bar position/style as Crypt
     Crawl's own #cc-audio-player; just the zoom toggle for now -- if
     ambient audio comes later, its controls belong in this same bar. -->
<div class="cq-player" id="cq-player">
	<button type="button" class="cq-player-btn" id="cq-zoom-toggle" title="Background zoom: on">🎥 Background Animation</button>
</div>
</div>
<script>
(function() {
	var gameArea = document.getElementById('cq-game-area');
	var resultOverlay = document.getElementById('cq-result-overlay');

	// Background zoom (Ken Burns drift) on/off -- persists for the rest of
	// the browser session, same sessionStorage-backed on/off convention
	// Crypt Crawl's own player controls use. On by default.
	function getZoomEnabled() {
		var v = sessionStorage.getItem('cq_zoom_enabled');
		return v === null ? true : v === '1';
	}
	function setZoomEnabled(v) { try { sessionStorage.setItem('cq_zoom_enabled', v ? '1' : '0'); } catch (e) {} }

	// Sizing only -- #cq-theme-bg's active/inactive state and image are
	// applyThemeState()'s job below. One resize listener, attached once
	// (not re-added per swap, which would stack up a fresh listener per
	// action) -- same shape as cryptcrawl.php's own sizeTheme().
	function sizeTheme() {
		var el = document.getElementById('cq-theme-bg');
		if (!el) return;
		if (!el.classList.contains('cq-theme-active')) {
			el.style.height = ''; // bare mode -- size naturally
			return;
		}
		var top = el.getBoundingClientRect().top;
		var bottomPad = 60; // matches .cq-wrap's bottom padding
		var available = window.innerHeight - top - bottomPad;
		el.style.height = 'auto';
		var natural = el.scrollHeight;
		el.style.height = Math.max(200, available, natural) + 'px';
	}
	window.addEventListener('resize', sizeTheme);

	// Randomizes the Ken Burns pan/zoom direction -- same approach as
	// cryptcrawl.php's own randomizeKenBurns(), opposite-corner drift so it
	// never jerks toward one edge, random angle/pace so every theme change
	// looks different.
	function randomizeKenBurns(el) {
		var scaleFrom = 1 + Math.random() * 0.04;
		var scaleTo = 1.08 + Math.random() * 0.08;
		var angle = Math.random() * Math.PI * 2;
		var dist = 1.5 + Math.random() * 2;
		var xFrom = (Math.cos(angle) * dist).toFixed(2) + '%';
		var yFrom = (Math.sin(angle) * dist).toFixed(2) + '%';
		var xTo = (Math.cos(angle + Math.PI) * dist).toFixed(2) + '%';
		var yTo = (Math.sin(angle + Math.PI) * dist).toFixed(2) + '%';
		var duration = (20 + Math.random() * 14).toFixed(1) + 's';
		el.style.setProperty('--kb-scale-from', scaleFrom.toFixed(3));
		el.style.setProperty('--kb-scale-to', scaleTo.toFixed(3));
		el.style.setProperty('--kb-x-from', xFrom);
		el.style.setProperty('--kb-y-from', yFrom);
		el.style.setProperty('--kb-x-to', xTo);
		el.style.setProperty('--kb-y-to', yTo);
		el.style.setProperty('--kb-duration', duration);
	}

	// Reconciles the PERMANENT #cq-theme-bg element against whatever
	// #cq-theme-state (emitted fresh inside #cq-game-area on every render)
	// says this render's theme state is. Comparing the incoming image
	// against what's already applied (dataset.currentImg) is what keeps
	// the same background from restarting its drift on every action --
	// only a genuinely different image gets a fresh random direction.
	function applyThemeState() {
		var themeBg = document.getElementById('cq-theme-bg');
		var stateEl = document.getElementById('cq-theme-state');
		if (!themeBg || !stateEl) return;
		var active = stateEl.getAttribute('data-theme-active') === '1';
		var img = stateEl.getAttribute('data-theme-img') || '';
		themeBg.classList.toggle('cq-theme-active', active);
		if (active) {
			if (themeBg.dataset.currentImg !== img) {
				themeBg.style.setProperty('--theme-img', img);
				themeBg.dataset.currentImg = img;
				randomizeKenBurns(themeBg);
			}
			themeBg.classList.toggle('cq-zoom', getZoomEnabled());
		}
		sizeTheme();
	}

	function initGameArea() {
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var flashBackdrop = document.getElementById('cq-flash-backdrop');
		if (flashBackdrop) {
			var dismissFlash = function() {
				if (!flashBackdrop.isConnected) return;
				if (reduceMotion) { flashBackdrop.remove(); return; }
				flashBackdrop.style.transition = 'opacity .3s ease';
				flashBackdrop.style.opacity = '0';
				setTimeout(function() { flashBackdrop.remove(); }, 300);
			};
			flashBackdrop.addEventListener('click', dismissFlash);
			setTimeout(dismissFlash, 4000);
		}

		var instrBtn = document.getElementById('cq-instructions-btn');
		var instrBackdrop = document.getElementById('cq-instructions-backdrop');
		var instrClose = document.getElementById('cq-instructions-close');
		if (instrBtn && instrBackdrop) {
			instrBtn.addEventListener('click', function() { instrBackdrop.classList.add('show'); });
			instrBackdrop.addEventListener('click', function(e) {
				if (e.target === instrBackdrop) instrBackdrop.classList.remove('show');
			});
			if (instrClose) {
				instrClose.addEventListener('click', function() { instrBackdrop.classList.remove('show'); });
			}
		}

		applyThemeState();
	}

	// #cq-player (and the zoom toggle inside it) is a PERMANENT element,
	// outside #cq-game-area -- bound once here instead of inside
	// initGameArea(), unlike the instructions button above which lives
	// inside the swapped markup and gets destroyed/recreated every render.
	var zoomBtn = document.getElementById('cq-zoom-toggle');
	if (zoomBtn) {
		zoomBtn.classList.toggle('off', !getZoomEnabled());
		zoomBtn.title = 'Background zoom: ' + (getZoomEnabled() ? 'on' : 'off');
		zoomBtn.addEventListener('click', function() {
			setZoomEnabled(!getZoomEnabled());
			zoomBtn.classList.toggle('off', !getZoomEnabled());
			zoomBtn.title = 'Background zoom: ' + (getZoomEnabled() ? 'on' : 'off');
			var themeBg = document.getElementById('cq-theme-bg');
			if (themeBg) themeBg.classList.toggle('cq-zoom', getZoomEnabled());
		});
	}

	initGameArea();

	function lockGameAreaBriefly() {
		gameArea.style.opacity = '0.55';
		gameArea.style.pointerEvents = 'none';
		setTimeout(function() {
			gameArea.style.opacity = '';
			gameArea.style.pointerEvents = '';
			busy = false;
		}, 400);
	}
	// Same protection for a fresh load landing directly on a result --
	// see cryptcrawl.php's own copy of this line for why.
	if (gameArea && gameArea.querySelector('.cq-result')) lockGameAreaBriefly();

	var busy = false;
	document.addEventListener('submit', function(e) {
		if (e.defaultPrevented) return; // e.g. Abandon Run's own confirm() said no
		var form = e.target;
		if (!gameArea || form.tagName !== 'FORM' || !gameArea.contains(form)) return;
		e.preventDefault();
		if (busy) return;
		busy = true;
		gameArea.style.opacity = '0.55';
		gameArea.style.pointerEvents = 'none';

		// FormData(form) alone does NOT include a submit button's own
		// name/value pair unless the submitter is passed explicitly --
		// matters here because #cq-hand-form's buttons (Play Selected /
		// Yield / Cover Damage) each carry their own name="action" instead
		// of a shared hidden field (two same-named inputs -- one hidden,
		// one on whichever button got clicked -- would have been genuinely
		// ambiguous about which value wins). e.submitter is the actual
		// button that triggered this submit event; folding it in here is
		// what makes the AJAX path match what a real, no-JS form post
		// already does natively.
		var formData = new FormData(form);
		if (e.submitter && e.submitter.name) {
			formData.set(e.submitter.name, e.submitter.value);
		}

		fetch('ajax/cryptconquest-action.php', { method: 'POST', body: formData })
			.then(function(res) {
				if (!res.ok) throw new Error('bad response');
				return res.text();
			})
			.then(function(html) {
				if (html.indexOf('class="cq-result ') !== -1 && resultOverlay) {
					resultOverlay.innerHTML = html;
					gameArea.innerHTML = ''; // not just hidden -- see cryptcrawl.php's own duplicate-ID lesson
					gameArea.style.display = 'none';
					resultOverlay.style.display = '';
					initGameArea();
					busy = false;
					return;
				}
				gameArea.innerHTML = html;
				initGameArea();
				lockGameAreaBriefly();
			})
			.catch(function() {
				// Genuine network failure -- fall back to a real form
				// submit (cryptconquest.php's own POST handler), same as
				// Crypt Crawl's fallback.
				gameArea.style.opacity = '';
				gameArea.style.pointerEvents = '';
				busy = false;
				form.submit();
			});
	});
})();
</script>
