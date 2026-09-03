<?php
// CRYPT CONQUEST — Regicide-inspired solo card game, built directly off
// Crypt Crawl's own architecture, including its two hardest-won fixes: the
// merge-not-replace SessionCookie restore (see skulliance.php's own
// comment for why a raw replace is dangerous), and the hidden-overlay
// pattern for a reliable win/loss screen (see cryptconquest.md and
// cryptcrawl.php's own long comment on #cc-result-overlay for the saga
// that pattern came out of). Fully platform-integrated as of 2026-08-30
// (leaderboard, monthly CARBON payout, Discord announcements, marketing
// page at cryptconquestgame.php linked from nav/homepage/sitemap) -- see
// skullpaper/games-cryptconquest.md and MAINTENANCE.md's "Games constants"
// section for the current, verified state. Public/guest-playable, same as
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
	// Same deferral as ajax/cryptconquest-action.php -- flush the redirect
	// to the client, then run CARBON payout/Discord announce in the
	// background rather than making a no-JS visitor's browser wait on
	// them before the redirect even fires. See
	// cryptconquestFlushPendingSideEffects() in db.php, and
	// ajax/cryptconquest-action.php for why session_write_close() has to
	// come first.
	session_write_close();
	if (function_exists('fastcgi_finish_request')) {
		fastcgi_finish_request();
	}
	cryptconquestFlushPendingSideEffects($conn);
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
/* The recovered card shown inside an exact-kill flash. Same face treatment as
   any other card here (black ground, two-corner index, art edge-to-edge) so
   it reads as the card you just beat rather than an illustration. Sized to
   sit comfortably inside the modal without pushing the message off a phone
   screen, and it lands with a small pop -- this is the one moment in the game
   that rewards precision, so it's allowed to feel like something. */
@keyframes cqFlashCardIn {
	from { opacity: 0; transform: scale(.72) rotate(-6deg); }
	60%  { opacity: 1; transform: scale(1.06) rotate(2deg); }
	to   { opacity: 1; transform: scale(1) rotate(0deg); }
}
/* Perfect-guard: the saved cards, shown side by side in the one-per-run
   teaching modal. Smaller than the single exact-kill card since there are two
   and the message matters more here. */
.cq-flash-cards { display: flex; gap: 8px; justify-content: center; margin: 2px 0 12px; }
.cq-flash-cards .cq-flash-card { width: 78px; margin: 0; }

/* Cards that just came back from a perfect guard. This is the QUIET path --
   it fires on every exact defense (~68% of turns), so it must never block or
   demand a click: a brief gold glow plus a badge, then it settles. Shows
   which cards returned, which a modal can't do as clearly since they're
   already in your hand. */
/* VIOLET, deliberately not the gold used everywhere else. Saved cards used
   to glow #ffcc4d -- the exact color .cq-card:has(:checked) uses for the
   current SELECTION -- so a card returned by a perfect guard was
   indistinguishable from a card the player had just picked, on the one
   screen where "which cards are selected" is the whole decision. Reported
   as confusing, and it was. Violet is also distinct from Last Stand's teal,
   which matters because both are defensive rewards. */
@keyframes cqSavedGlow {
	0%   { box-shadow: 0 0 0 0 rgba(181,140,255,.85), 0 0 18px rgba(181,140,255,.7); }
	100% { box-shadow: 0 0 0 0 rgba(181,140,255,0), 0 0 0 rgba(181,140,255,0); }
}
/* Cards Diamonds just drew. The hand re-renders as a whole after every
   action, so new cards would otherwise appear already settled and
   indistinguishable from what was there before -- making the suit's single
   most important effect invisible. These instead FILE IN one after another
   (--draw-i staggers the delay), in the Diamonds orange, with a badge naming
   the cause. The rest of the hand is untouched and static, which is what
   makes the arriving cards read as the result of the play. */
@keyframes cqDrawIn {
	from { opacity: 0; transform: translateY(16px) scale(.9); }
	60%  { opacity: 1; transform: translateY(-3px) scale(1.03); }
	to   { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes cqDrawGlow {
	0%   { box-shadow: 0 0 20px rgba(255,153,0,.75); border-color: rgba(255,153,0,.95); }
	100% { box-shadow: 0 0 0 rgba(255,153,0,0); }
}
.cq-card-drawn { position: relative; animation: cqDrawIn .42s cubic-bezier(.18,.89,.32,1.28) both; animation-delay: calc(var(--draw-i, 0) * .13s); }
.cq-card-drawn .cq-card-face { animation: cqDrawGlow 1.9s ease-out backwards; animation-delay: calc(var(--draw-i, 0) * .13s); }
.cq-drawn-badge {
	position: absolute; top: -7px; left: 50%; transform: translateX(-50%); z-index: 3;
	background: #ff9900; color: #07111d; font-size: .54rem; font-weight: 800;
	letter-spacing: .05em; padding: 2px 7px; border-radius: 999px; pointer-events: none;
	white-space: nowrap; box-shadow: 0 2px 8px rgba(0,0,0,.5);
	animation: cqDrawGlow 1.9s ease-out backwards; animation-delay: calc(var(--draw-i, 0) * .13s);
}
@media (prefers-reduced-motion: reduce) {
	.cq-card-drawn, .cq-card-drawn .cq-card-face, .cq-drawn-badge { animation: none; }
}
.cq-card-saved { position: relative; }
.cq-card-saved .cq-card-face { border-color: rgba(181,140,255,.95); animation: cqSavedGlow 2.2s ease-out backwards; }
.cq-saved-badge {
	position: absolute; top: -7px; left: 50%; transform: translateX(-50%); z-index: 3;
	background: #b58cff; color: #07111d; font-size: .54rem; font-weight: 800;
	letter-spacing: .06em; padding: 2px 7px; border-radius: 999px; pointer-events: none;
	box-shadow: 0 2px 8px rgba(0,0,0,.5); animation: cqSavedGlow 2.2s ease-out backwards;
}
@media (prefers-reduced-motion: reduce) {
	.cq-card-saved .cq-card-face, .cq-saved-badge { animation: none; }
}
.cq-flash-card {
	position: relative; width: 108px; aspect-ratio: 5 / 7; margin: 2px auto 12px;
	border-radius: 10px; background: #000; overflow: hidden; box-sizing: border-box;
	border: 2px solid rgba(255,204,77,.85);
	box-shadow: 0 0 22px rgba(255,204,77,.35), 0 10px 26px rgba(0,0,0,.55);
	animation: cqFlashCardIn .45s cubic-bezier(.18,.89,.32,1.28) both;
}
@media (prefers-reduced-motion: reduce) { .cq-flash-card { animation: none; } }
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
/* Rules broken into labeled sections instead of one prose block -- direct
   feedback that a wall of text made separate ideas hard to tell apart.
   Section label is a small uppercase tag, not a full heading -- this
   renders inside both the no_run intro screen and the instructions
   modal, neither of which should feel like a wiki page. */
.cq-rules-section { margin-bottom: 16px; }
.cq-rules-section:last-child { margin-bottom: 0; }
.cq-rules-section p { margin: 0; }
.cq-rules-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #ffcc4d; opacity: 0.9; margin-bottom: 6px; }
.cq-rules-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
.cq-rules-list li { padding-left: 2px; }
.cq-rules-tips { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
.cq-rules-tips li { font-size: 0.8rem; line-height: 1.5; opacity: 0.95; padding: 8px 10px; background: rgba(0,200,160,.08); border-left: 3px solid #00c8a0; border-radius: 6px; }
.cq-rules-tips li strong { color: #34e3bb; }
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
/* The "immune to" label stays small/uppercase, but the suit glyph itself
   -- the part that actually matters for deciding which cards not to play
   -- gets its own larger, full-opacity, suit-colored treatment instead of
   inheriting the label's tiny size and 60% opacity. That combination was
   what made it genuinely hard to make out, not just small on its own. */
.cq-enemy-immune { font-size: 0.65rem; opacity: 0.85; border: 1px solid rgba(255,255,255,.25); border-radius: 999px; padding: 2px 10px; text-transform: uppercase; letter-spacing: .03em; display: inline-flex; align-items: center; gap: 4px; }
.cq-enemy-immune-suit { font-size: 1.15rem; line-height: 1; font-weight: 700; opacity: 1; text-transform: none; color: var(--cq-suit-color); }
.cq-hp-bar-bg {
	background: linear-gradient(90deg,#ff4444,#ff9900,#00c8a0); border-radius: 6px; height: 12px;
	overflow: hidden; position: relative; margin-bottom: 6px;
}
.cq-hp-bar-fill {
	background: rgba(5,12,20,.88); height: 100%; position: absolute; top: 0; right: 0;
	transition: width .6s cubic-bezier(.22,1,.36,1);
}
.cq-enemy-stats { display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.75rem; opacity: 0.85; }
/* Owner credit for the NFT used as the current court card. Sits under the
   enemy's stats as its own row rather than on the card art -- an arbitrary
   Discord avatar stamped into a curated card face reads as noise and is too
   small to identify anyway. Only one is ever visible at a time (you face one
   court card at once), which is what keeps it feeling like a callout instead
   of clutter. Underline suppressed since .cq-btn's own reset doesn't apply
   here; hover lifts it just enough to read as clickable. */
.cq-enemy-owner {
	display: inline-flex; align-items: center; gap: 7px; margin-top: 8px;
	padding: 4px 10px 4px 4px; border-radius: 999px;
	background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.10);
	text-decoration: none; color: rgba(255,255,255,.72); font-size: 0.7rem;
	transition: background .15s ease, border-color .15s ease, color .15s ease;
}
.cq-enemy-owner:hover { background: rgba(255,204,77,.10); border-color: rgba(255,204,77,.35); color: #ffe08a; text-decoration: none; }
.cq-enemy-owner-avatar { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; flex: none; background: #000; }
.cq-enemy-owner-text strong { color: #ffcc4d; font-weight: 700; }
.cq-enemy-owner:hover .cq-enemy-owner-text strong { color: #ffe08a; }
/* Narrow screens: the enemy badge already eats ~64px of a ~350px row, so the
   full "NFT owned by <name>" sentence is what pushes this onto a second line
   and squeezes the block. Drop the prefix (an avatar plus a bold username
   already reads as ownership), shrink it, and truncate rather than wrap so
   the credit can never add height or force horizontal overflow. */
@media (max-width: 560px) {
	.cq-enemy-owner { margin-top: 6px; padding: 3px 8px 3px 3px; font-size: 0.66rem; max-width: 100%; }
	.cq-enemy-owner-avatar { width: 18px; height: 18px; }
	.cq-enemy-owner-prefix { display: none; }
	.cq-enemy-owner-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
}
.cq-shield { color: #c8dce8; }
.cq-attack { color: #ff7070; }
.cq-hud-meta { display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.72rem; opacity: 0.8; align-items: center; border-top: 1px solid rgba(255,255,255,.08); padding-top: 10px; }
.cq-hud-carbon { display: inline-flex; align-items: center; gap: 3px; color: #ffcc4d; font-weight: 700; white-space: nowrap; margin-left: auto; }
.cq-hud-carbon img { width: 14px; height: 14px; object-fit: contain; }

/* Always-on suit quick reference (cryptconquest-render.php's .cq-suit-key,
   fed by $CRYPTCONQUEST_SUIT_EFFECT) -- a row of small pill chips, same
   dark/blurred panel language as .cq-hud/.cq-suffer-banner but low-profile
   on purpose: a newer player scans it once and it otherwise stays out of
   the way, not another stats panel competing for attention. Wraps to 2
   rows on the narrowest phones rather than truncating or scrolling. */
.cq-suit-key { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; font-size: 0.7rem; }
.cq-suit-key-item {
	display: inline-flex; align-items: center; gap: 5px;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border: 1px solid rgba(255,255,255,.08); border-radius: 999px;
	padding: 5px 10px 5px 8px; white-space: nowrap; color: rgba(255,255,255,.75);
}
.cq-suit-key-icon { color: var(--cq-suit-color, #fff); font-size: 0.95rem; font-weight: 700; line-height: 1; }

/* Was a near-transparent rgba(255,68,68,.12) tint -- fine over the plain
   dark page background, but a themed backdrop can have bright/white
   patches behind it (see .cq-theme-bg's own art), and the pale text
   inside washed out against those. Layered on the same dark, blurred,
   near-opaque base every other HUD panel here uses (.cq-hud, .cq-result,
   etc.) instead of relying on backdrop darkness for contrast -- the red
   tint sits on TOP of that as a gradient overlay, not as the panel's only
   background, so it stays legible regardless of what's behind it. */
.cq-suffer-banner {
	background: linear-gradient(rgba(255,68,68,.22), rgba(255,68,68,.22)), rgba(5,12,20,.88);
	backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border: 1px solid rgba(255,68,68,.5); border-radius: 10px;
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
/* Standalone fallback shown when a Companion card has no S2 art yet --
   just "1" now (its actual card value, see cryptconquestCardValue()),
   plain text like everything else on the card face. No special color/
   outline treatment needed anymore now that it's not an emoji. */
.cq-card-companion-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 800; opacity: 0.85; }
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
/* Two fixed columns, matching Crypt Crawl's own .cc-flee-row, rather than
   flex-wrap. The buttons here are wrapped in <form> elements, so the FORM is
   the flex child -- the old `.cq-controls-row .cq-btn { flex: 1 }` was being
   applied to a nested element that was never a flex item, so the columns
   never actually evened out. Grid sizes the cells directly and the button
   fills its cell via .cq-btn's own width:100%. */
.cq-controls-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 14px; }
.cq-controls-row + .cq-controls-row { margin-top: 8px; }
.cq-controls-row form { display: block; }

.cq-btn {
	position: relative; overflow: hidden; background: #00c8a0; color: #012; border: 1px solid transparent; border-radius: 6px;
	padding: 10px 14px; font-size: 0.82rem; font-weight: 600; cursor: pointer; box-sizing: border-box;
	width: 100%; min-height: 46px; display: flex; align-items: center; justify-content: center; text-align: center;
	text-decoration: none; /* some .cq-btn are <a> (View/Monthly Leaderboard), not <button> */
	transition: transform .12s ease, filter .12s ease, box-shadow .2s ease;
}
.cq-btn:hover:not(:disabled) { filter: brightness(1.12); box-shadow: 0 6px 16px rgba(0,200,160,.35); }
.cq-btn:active:not(:disabled) { transform: scale(.96); }
.cq-btn.secondary { background: rgba(255,255,255,.08); color: #e8f2f8; border-color: rgba(255,255,255,.3); }
.cq-btn.secondary:hover:not(:disabled) { background: rgba(255,255,255,.15); box-shadow: 0 6px 16px rgba(255,255,255,.12); }
.cq-btn.attack { background: #ff4444; color: #012; }
.cq-btn.attack:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(255,68,68,.4); }
/* Solid, not the translucent .secondary treatment -- washes out against
   the loss screen's themed backdrop. Same gold as the CARBON accent
   elsewhere, same choice cryptcrawl.php's own .cc-btn.gold makes for its
   loss-screen leaderboard link. */
.cq-btn.gold { background: #ffcc4d; color: #012; }
.cq-btn.gold:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(255,204,77,.4); }
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
   in the same spot regardless of no_run/active/game_over). Now holds the
   ambient music player too (prev/play-pause/next/track-name/volume,
   re-leveraging Crypt Crawl's own audio files -- see the script block),
   with the zoom toggle folded in alongside it instead of standing alone. */
.cq-player {
	max-width: 720px; margin: 14px auto 0; display: flex; align-items: center; gap: 8px;
	background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); border-radius: 999px;
	padding: 8px 14px; box-sizing: border-box; font-size: 0.72rem; color: #9fb4c2;
}
/* Round icon buttons -- prev/play-pause/next, same shape as Crypt Crawl's
   own .cc-audio-btn. Kept separate from .cq-player-btn (a labeled
   text+icon button, used for the zoom toggle) since these are icon-only. */
.cq-audio-btn {
	background: none; border: none; color: #c8dce8; cursor: pointer;
	font-size: 1rem; line-height: 1; padding: 0; border-radius: 50%; flex: none;
	display: flex; align-items: center; justify-content: center; width: 26px; height: 26px;
}
.cq-audio-btn:hover { background: rgba(255,255,255,.1); }
.cq-audio-track { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
/* Default (desktop-width): full name shown, short word hidden -- the
   @media (max-width: 700px) block further down flips both, same
   breakpoint/approach Crypt Crawl's own player uses. */
.cq-audio-track-full { display: inline; }
.cq-audio-track-short { display: none; }
.cq-audio-vol-icon { flex: none; font-size: 0.8rem; opacity: 0.8; }
.cq-audio-volume { flex: none; width: 64px; accent-color: #ffcc4d; cursor: pointer; }
.cq-player-btn {
	background: none; border: none; color: #c8dce8; cursor: pointer;
	font-size: 0.78rem; font-weight: 600; padding: 4px 10px; border-radius: 999px; flex: none;
	display: flex; align-items: center; gap: 6px; transition: background .12s ease, opacity .12s ease;
}
.cq-player-btn:hover { background: rgba(255,255,255,.1); }
.cq-player-btn.off, .cq-audio-btn.off { opacity: 0.5; }
@media (max-width: 700px) {
	/* Less room for the track label on mobile -- show just the last word
	   ("Theme", "Reprise", "Doom"...) instead of the full "Crypt Conquest
	   X" name, same convention cryptcrawl.php's own player uses. */
	.cq-audio-track-full { display: none; }
	.cq-audio-track-short { display: inline; }
}

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
     based on #cq-mood's data-theme-* attributes (emitted fresh inside
     #cq-game-area on every render, alongside data-mood for the ambient
     music player below), applied by applyThemeState(). Ported directly
     from cryptcrawl.php's #cc-theme-bg/#cc-mood. -->
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
     purpose, so it's visible in every state (no_run/active/game_over) and
     never destroyed/recreated by an AJAX swap (same reason the <audio>
     elements below it have to live out here too -- an AJAX-swapped copy
     would restart/stutter playback on every single action). Same
     pill-bar position/style as Crypt Crawl's own #cc-audio-player, now
     re-leveraging Crypt Crawl's own audio files/mood system directly
     (see the script block) rather than duplicating fresh Conquest-only
     tracks. -->
<div class="cq-player" id="cq-player">
	<button type="button" class="cq-audio-btn" id="cq-audio-prev" title="Previous track">⏮</button>
	<button type="button" class="cq-audio-btn" id="cq-audio-toggle" title="Play/Pause">▶</button>
	<button type="button" class="cq-audio-btn" id="cq-audio-next" title="Next track">⏭</button>
	<span class="cq-audio-track" id="cq-audio-track-name"><span class="cq-audio-track-full">Crypt Conquest Theme</span><span class="cq-audio-track-short">Theme</span></span>
	<span class="cq-audio-vol-icon">🔊</span>
	<input type="range" class="cq-audio-volume" id="cq-audio-volume" min="0" max="100" value="50" title="Volume">
	<button type="button" class="cq-player-btn" id="cq-zoom-toggle" title="Background zoom: on">🎥</button>
</div>
<!-- Two elements, not one -- crossfading between tracks (mood changes,
     manual skips, the normal loop's own advance) means briefly playing
     the outgoing and incoming track at once while one ramps down and the
     other ramps up. Same reasoning as cryptcrawl.php's own pair -- see
     crossfadeTo() in the script block for how these take turns being
     "active". -->
<audio id="cq-audio-el-a" preload="metadata"></audio>
<audio id="cq-audio-el-b" preload="metadata"></audio>
<!-- Card SFX. preload="auto" (not "metadata" like the music above) because
     this fires on a click and any load delay would land it after the card
     has already visibly moved -- it's 8KB, so eager-loading it is free. -->
<audio id="cq-sfx-card" preload="auto" src="audio/sounds/card.mp3"></audio>
<!-- Stingers. preload="metadata", not "auto" like the click above: these are
     ~150KB each and fire at most once or twice a run, so eagerly pulling both
     on every page load would cost far more than it saves. -->
<audio id="cq-sfx-jester" preload="metadata" src="audio/sounds/jester.mp3"></audio>
<audio id="cq-sfx-laststand" preload="metadata" src="audio/sounds/laststand.mp3"></audio>
<!-- Killing a regent. Short like the click (0.53s), not a stinger, so it
     preloads eagerly and plays at the click's level. -->
<audio id="cq-sfx-kill" preload="auto" src="audio/sounds/kill.mp3"></audio>
</div>
<script>
(function() {
	var gameArea = document.getElementById('cq-game-area');
	var resultOverlay = document.getElementById('cq-result-overlay');

	// Assigned once the audio player sets itself up (below) -- exposed here
	// so initGameArea() can call it again after every AJAX swap, since the
	// server recomputes #cq-mood fresh on every render. null on the very
	// first call (this whole IIFE runs after that first call, in source
	// order) -- same null-guard shape as cryptcrawl.php's own pair.
	var syncMood = null;
	var applyThemeState = null;

	// Sizing only -- #cq-theme-bg's active/inactive state and image are
	// applyThemeState()'s job (assigned below, once per render). One
	// resize listener, attached once (not re-added per swap, which would
	// stack up a fresh listener per action) -- same shape as
	// cryptcrawl.php's own sizeTheme().
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

		// Re-check the situational music track (Frantic/Doom/Death/Triumph
		// vs. the normal loop) against whatever #cq-mood the server just
		// rendered. null on the very first call (page load) -- the audio
		// player checks its own initial mood itself once it sets up, just
		// below this function in source order.
		if (applyThemeState) applyThemeState(); else sizeTheme();
		if (syncMood) syncMood();
	}

	initGameArea();

	// Ambient music player. Re-leverages Crypt Crawl's own audio files
	// directly (same audio/tracks/*.mp3 paths, just Conquest-branded
	// display names) rather than duplicating fresh tracks, and the exact
	// same mood-driven architecture -- two crossfading <audio> elements,
	// a normal Theme/Reprise loop, and four situational tracks the game
	// itself cues up via #cq-mood (see cryptconquest-render.php). State
	// (on/off, track, position, volume, Ken Burns on/off) lives in
	// sessionStorage under cq_-prefixed keys, distinct from Crypt Crawl's
	// own cc_-prefixed keys -- both pages share the same origin, so a
	// naming collision would leak one game's player state into the other's.
	(function() {
		var players = [document.getElementById('cq-audio-el-a'), document.getElementById('cq-audio-el-b')];
		var activeIdx = 0;
		function active() { return players[activeIdx]; }
		function inactive() { return players[1 - activeIdx]; }

		var toggleBtn = document.getElementById('cq-audio-toggle');
		var prevBtn = document.getElementById('cq-audio-prev');
		var nextBtn = document.getElementById('cq-audio-next');
		var trackNameEl = document.getElementById('cq-audio-track-name');
		var trackNameFullEl = trackNameEl ? trackNameEl.querySelector('.cq-audio-track-full') : null;
		var trackNameShortEl = trackNameEl ? trackNameEl.querySelector('.cq-audio-track-short') : null;
		var volumeEl = document.getElementById('cq-audio-volume');
		var zoomToggleBtn = document.getElementById('cq-zoom-toggle');
		if (!players[0] || !players[1] || !toggleBtn) return;
		function setTrackName(name) {
			if (trackNameFullEl) trackNameFullEl.textContent = name;
			if (trackNameShortEl) {
				var words = name.trim().split(/\s+/);
				trackNameShortEl.textContent = words[words.length - 1];
			}
		}

		var TRACKS = [
			{ name: 'Crypt Conquest Theme', src: 'audio/tracks/Crypt%20Crawl%20Theme.mp3' },
			{ name: 'Crypt Conquest Reprise', src: 'audio/tracks/Crypt%20Crawl%20Reprise.mp3' }
		];
		// Situational tracks the game itself cues up (see #cq-mood, set by
		// cryptconquestRenderGameArea() in cryptconquest-render.php) --
		// deliberately NOT part of TRACKS above, so prev/next can never
		// cycle to them. The only way to hear Triumph is to actually win.
		var MOOD_TRACKS = {
			frantic: { name: 'Crypt Conquest Frantic', src: 'audio/tracks/Crypt%20Crawl%20Frantic.mp3', loop: true },
			doom:    { name: 'Crypt Conquest Doom',    src: 'audio/tracks/Crypt%20Crawl%20Doom.mp3',    loop: true },
			death:   { name: 'Crypt Conquest Death',   src: 'audio/tracks/Crypt%20Crawl%20Death.mp3',   loop: false },
			triumph: { name: 'Crypt Conquest Triumph', src: 'audio/tracks/Crypt%20Crawl%20Triumph.mp3', loop: false }
		};
		var currentMood = 'normal';
		var FADE_MS = 1200;

		function getEnabled() {
			var v = sessionStorage.getItem('cq_audio_enabled');
			return v === null ? true : v === '1';
		}
		function setEnabled(v) { try { sessionStorage.setItem('cq_audio_enabled', v ? '1' : '0'); } catch (e) {} }
		function getTrackIndex() {
			var v = parseInt(sessionStorage.getItem('cq_audio_track'), 10);
			return (v === 0 || v === 1) ? v : 0;
		}
		function setTrackIndex(v) { try { sessionStorage.setItem('cq_audio_track', String(v)); } catch (e) {} }
		function getPosition() {
			var v = parseFloat(sessionStorage.getItem('cq_audio_position'));
			return isNaN(v) ? 0 : v;
		}
		function setPosition(v) { try { sessionStorage.setItem('cq_audio_position', String(v)); } catch (e) {} }
		function getVolume() {
			var v = parseInt(sessionStorage.getItem('cq_audio_volume'), 10);
			return (v >= 0 && v <= 100) ? v : 50;
		}
		function setVolume(v) { try { sessionStorage.setItem('cq_audio_volume', String(v)); } catch (e) {} }
		function getZoomEnabled() {
			var v = sessionStorage.getItem('cq_zoom_enabled');
			return v === null ? true : v === '1';
		}
		function setZoomEnabled(v) { try { sessionStorage.setItem('cq_zoom_enabled', v ? '1' : '0'); } catch (e) {} }

		var trackIndex = getTrackIndex();
		var enabled = getEnabled();
		var targetVolume = getVolume() / 100;

		players[0].volume = targetVolume;
		players[1].volume = 0;
		if (volumeEl) volumeEl.value = getVolume();
		if (zoomToggleBtn) {
			zoomToggleBtn.classList.toggle('off', !getZoomEnabled());
			zoomToggleBtn.title = 'Background zoom: ' + (getZoomEnabled() ? 'on' : 'off');
		}

		// Ken Burns drift on the theme art -- max ambience while a track is
		// actually audible, so it's tied to play/pause as well as the
		// on/off toggle below. See @keyframes cqKenBurns (CSS) for how the
		// --kb-* custom properties set here get played back.
		function updateZoomClass() {
			var el = document.getElementById('cq-theme-bg');
			if (el) el.classList.toggle('cq-zoom', getZoomEnabled() && !active().paused);
		}
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
		// Exposed to initGameArea() (outer scope), called once per render.
		// #cq-theme-bg is PERMANENT (never destroyed by an AJAX swap) --
		// comparing the incoming image against what's already applied
		// (dataset.currentImg) is what keeps the same background from
		// restarting its drift on every action; only a genuinely different
		// image gets a fresh random direction.
		applyThemeState = function() {
			var themeBg = document.getElementById('cq-theme-bg');
			var moodEl = document.getElementById('cq-mood');
			if (!themeBg || !moodEl) return;
			var active = moodEl.getAttribute('data-theme-active') === '1';
			var img = moodEl.getAttribute('data-theme-img') || '';
			themeBg.classList.toggle('cq-theme-active', active);
			if (active) {
				if (themeBg.dataset.currentImg !== img) {
					themeBg.style.setProperty('--theme-img', img);
					themeBg.dataset.currentImg = img;
					randomizeKenBurns(themeBg);
				}
				updateZoomClass();
			}
			sizeTheme();
		};
		// initGameArea()'s first call ran before applyThemeState existed yet
		// -- run it again now so the initial page's own theme state (if
		// any) actually gets applied instead of only picking one up
		// starting with the next swap.
		applyThemeState();

		if (zoomToggleBtn) {
			zoomToggleBtn.addEventListener('click', function() {
				var next = !getZoomEnabled();
				setZoomEnabled(next);
				zoomToggleBtn.classList.toggle('off', !next);
				zoomToggleBtn.title = 'Background zoom: ' + (next ? 'on' : 'off');
				updateZoomClass();
			});
		}

		function updateToggleIcon() {
			toggleBtn.textContent = (!active().paused) ? '⏸' : '▶';
			updateZoomClass();
		}

		// Hard cut, no fade -- initial page load and manual prev/next.
		function loadTrack(index, resumePosition) {
			stopFade();
			currentMood = 'normal';
			nearEndTriggered = false;
			var a = active();
			a.loop = false;
			a.volume = targetVolume;
			trackIndex = index;
			setTrackIndex(index);
			setTrackName(TRACKS[index].name);
			a.src = TRACKS[index].src;
			if (resumePosition) {
				var resumeAt = getPosition();
				a.addEventListener('loadedmetadata', function once() {
					a.currentTime = resumeAt;
					a.removeEventListener('loadedmetadata', once);
				});
			}
			a.load();
			inactive().pause();
			inactive().volume = 0;
		}

		function tryPlay() {
			var p = active().play();
			if (p && p.catch) {
				p.catch(function() { updateToggleIcon(); });
			}
		}

		var fadeRAF = null;
		function stopFade() { if (fadeRAF) { cancelAnimationFrame(fadeRAF); fadeRAF = null; } }

		// Crossfades to src -- for transitions the GAME forces on its own
		// (a mood change, a fresh conquest forcing the Theme, the normal
		// loop's own advance). Manual prev/next deliberately does NOT go
		// through this -- see loadTrack().
		function crossfadeTo(src, opts) {
			opts = opts || {};
			stopFade();
			nearEndTriggered = false;
			if (!getEnabled()) {
				var a = active();
				a.loop = !!opts.loop;
				if (opts.name) setTrackName(opts.name);
				a.src = src;
				if (opts.resumeAt != null) {
					var resumeAt2 = opts.resumeAt;
					a.addEventListener('loadedmetadata', function once() {
						a.currentTime = resumeAt2;
						a.removeEventListener('loadedmetadata', once);
					});
				}
				a.load();
				return;
			}
			var outgoing = active();
			var incoming = inactive();
			incoming.loop = !!opts.loop;
			incoming.volume = 0;
			if (opts.name) setTrackName(opts.name);
			incoming.src = src;
			if (opts.resumeAt != null) {
				var resumeAt = opts.resumeAt;
				incoming.addEventListener('loadedmetadata', function once() {
					incoming.currentTime = resumeAt;
					incoming.removeEventListener('loadedmetadata', once);
				});
			}
			incoming.load();
			activeIdx = 1 - activeIdx;

			var p = incoming.play();
			if (p && p.catch) p.catch(function() { updateToggleIcon(); });

			var startOutVol = outgoing.volume;
			var startTs = null;
			function step(ts) {
				if (startTs === null) startTs = ts;
				var t = Math.min(1, (ts - startTs) / FADE_MS);
				incoming.volume = targetVolume * t;
				outgoing.volume = startOutVol * (1 - t);
				if (t < 1) {
					fadeRAF = requestAnimationFrame(step);
				} else {
					outgoing.pause();
					outgoing.volume = targetVolume;
					fadeRAF = null;
				}
			}
			fadeRAF = requestAnimationFrame(step);
			updateToggleIcon();
		}

		var nearEndTriggered = false;
		function maybeAdvanceNearEnd(player) {
			if (player !== active() || fadeRAF) return;
			if (currentMood === 'frantic' || currentMood === 'doom') return;
			if (!player.duration || !isFinite(player.duration)) return;
			if (player.duration - player.currentTime > FADE_MS / 1000 || nearEndTriggered) return;
			nearEndTriggered = true;
			if (currentMood === 'death' || currentMood === 'triumph') {
				currentMood = 'normal';
				var idx = getTrackIndex();
				crossfadeTo(TRACKS[idx].src, { name: TRACKS[idx].name, resumeAt: getPosition() });
			} else {
				setPosition(0);
				var next = (trackIndex + 1) % TRACKS.length;
				trackIndex = next;
				setTrackIndex(next);
				crossfadeTo(TRACKS[next].src, { name: TRACKS[next].name });
			}
		}

		loadTrack(trackIndex, true);
		if (enabled) {
			tryPlay();
			// Browsers only allow unmuted autoplay off the back of a
			// *trusted* user gesture -- catch the very first real
			// interaction anywhere on the page (not just this player's own
			// controls) and use it to start audio if tryPlay() above got
			// blocked.
			var playerEl = document.getElementById('cq-player');
			var unlockEvents = ['pointerdown', 'keydown', 'touchstart'];
			var unlockAudio = function(e) {
				unlockEvents.forEach(function(evt) { window.removeEventListener(evt, unlockAudio, true); });
				if (playerEl && e && e.target && playerEl.contains(e.target)) return;
				if (active().paused && getEnabled()) tryPlay();
			};
			unlockEvents.forEach(function(evt) { window.addEventListener(evt, unlockAudio, true); });
		}
		updateToggleIcon();

		players.forEach(function(p) {
			p.addEventListener('timeupdate', function() {
				if (this !== active()) return;
				if (currentMood === 'normal') setPosition(this.currentTime);
				maybeAdvanceNearEnd(this);
			});
			p.addEventListener('play', function() { if (this === active()) updateToggleIcon(); });
			p.addEventListener('pause', function() { if (this === active()) updateToggleIcon(); });
			p.addEventListener('ended', function() {
				if (this !== active()) return;
				if (currentMood === 'death' || currentMood === 'triumph') {
					var idx = getTrackIndex();
					loadTrack(idx, true);
					tryPlay();
					return;
				}
				if (currentMood !== 'normal') return;
				setPosition(0);
				loadTrack((trackIndex + 1) % TRACKS.length, false);
				tryPlay();
			});
			p.addEventListener('error', function() {
				if (this !== active()) return;
				if (currentMood !== 'normal') {
					currentMood = 'normal';
					loadTrack(getTrackIndex(), true);
					if (getEnabled()) tryPlay();
				}
			});
		});

		// Cued automatically by the server (#cq-mood, set in
		// cryptconquestRenderGameArea()) -- never reachable via prev/next,
		// so a player can't skip straight to Triumph without actually
		// winning. Always crossfades -- every path here is the game
		// forcing a transition, never a manual pick.
		syncMood = function() {
			var moodEl = document.getElementById('cq-mood');
			var mood = moodEl ? moodEl.getAttribute('data-mood') : 'normal';
			// A fresh conquest (Begin the Conquest / New Conquest) always
			// opens on the Theme specifically -- checked first,
			// unconditionally, so it wins over both the escape-from-danger
			// case below and even a same-mood no-op.
			if (moodEl && moodEl.getAttribute('data-restarted') === '1') {
				currentMood = 'normal';
				crossfadeTo(TRACKS[0].src, { name: TRACKS[0].name });
				return;
			}
			if (!mood || mood === currentMood) return;
			if (mood === 'normal') {
				if (currentMood === 'frantic' || currentMood === 'doom') {
					// Pulled out of danger (Last Stand saved you, or the
					// threat otherwise resolved without the run actually
					// ending) -- feels like picking back up, not restarting
					// the intro theme from scratch. Land on the Reprise
					// specifically (TRACKS[1]).
					currentMood = 'normal';
					crossfadeTo(TRACKS[1].src, { name: TRACKS[1].name });
				} else {
					currentMood = 'normal';
					var idx = getTrackIndex();
					crossfadeTo(TRACKS[idx].src, { name: TRACKS[idx].name, resumeAt: getPosition() });
				}
				return;
			}
			var special = MOOD_TRACKS[mood];
			if (!special) return;
			currentMood = mood;
			crossfadeTo(special.src, { name: special.name, loop: special.loop });
		};
		syncMood();

		toggleBtn.addEventListener('click', function() {
			if (active().paused) { setEnabled(true); tryPlay(); }
			else {
				setEnabled(false);
				stopFade();
				active().pause();
				inactive().pause();
			}
		});
		prevBtn.addEventListener('click', function() {
			setPosition(0);
			loadTrack((trackIndex - 1 + TRACKS.length) % TRACKS.length, false);
			setEnabled(true);
			tryPlay();
		});
		nextBtn.addEventListener('click', function() {
			setPosition(0);
			loadTrack((trackIndex + 1) % TRACKS.length, false);
			setEnabled(true);
			tryPlay();
		});
		if (volumeEl) {
			volumeEl.addEventListener('input', function() {
				var v = parseInt(volumeEl.value, 10) || 0;
				targetVolume = v / 100;
				if (!fadeRAF) active().volume = targetVolume;
				setVolume(v);
			});
		}
	})();

	// Card sound effects.
	//
	// Volume follows the player's volume slider, but the effect deliberately
	// does NOT respect the play/pause button. That button means "no music",
	// not "no sound" -- gating on it shipped the effect silent for everyone
	// who plays with the soundtrack off, which is most people (caught on the
	// live site: cq_audio_enabled was '0' and the sound never fired). The
	// slider is the mute path instead: drag it to 0 and the page goes fully
	// quiet, music and effects together.
	//
	// A POOL of elements rather than one: Diamond draws fire a click per card
	// 130ms apart (matching the --draw-i visual stagger) while the sound runs
	// 439ms, so they genuinely overlap. One element can only restart, which
	// would clip every card but the last into a stutter.
	var SFX_POOL_SIZE = 5;
	var sfxPool = [], sfxNext = 0;
	// Effects play at twice the slider's music level, clamped to 1.0. A short
	// click needs to cut through a sustained bed to read at the same loudness,
	// and at the default slider position (50) the effect was only using half
	// the available playback range -- so this is a real doubling that costs no
	// audio quality, rather than gaining the FILE further. The file already
	// peaks at -1.9dBFS; another +6dB there would have needed heavy limiting,
	// which squashes the transient that IS the sound.
	//
	// Above slider 50 this is already at digital maximum and can't go louder.
	var SFX_GAIN = 2;
	function sfxVolume() {
		var vol = parseInt(sessionStorage.getItem('cq_audio_volume'), 10);
		if (!(vol >= 0 && vol <= 100)) vol = 50; // never set -> same default as the music
		return Math.min(1, (vol / 100) * SFX_GAIN);
	}
	function playCardSfx() {
		var base = document.getElementById('cq-sfx-card');
		if (!base) return;
		var vol = sfxVolume();
		if (vol === 0) return;
		try {
			if (!sfxPool.length) {
				sfxPool.push(base);
				for (var i = 1; i < SFX_POOL_SIZE; i++) {
					var c = base.cloneNode(true);
					c.removeAttribute('id'); // only the original is addressable by id
					base.parentNode.appendChild(c);
					sfxPool.push(c);
				}
			}
			var el = sfxPool[sfxNext];
			sfxNext = (sfxNext + 1) % sfxPool.length;
			el.volume = vol;
			el.currentTime = 0;
			// Chrome rejects this promise if the tab has no user gesture yet;
			// it's a sound effect, so a silent failure is the right outcome.
			var p = el.play();
			if (p && p.catch) p.catch(function() {});
		} catch (e) {}
	}

	// Long one-shot stingers (Jester ~9.5s, Last Stand ~9.4s) as opposed to the
	// card click. No pool: only one should ever be sounding, so starting either
	// cuts off whichever is already running -- a Jester flipped during a Last
	// Stand turn should replace that cue, not talk over it.
	//
	// Played at HALF the card click's level. These are already mastered near
	// 0dBFS (jester peaks -0.3dB, laststand -1.0dB, against the click's -1.9dB
	// AFTER its +6dB pass), so applying the same 2x gain would have made a
	// 9-second cue overpower both the music and the click it follows.
	var STINGER_GAIN = 0.5;
	var currentStinger = null;
	function playStinger(name) {
		var el = document.getElementById('cq-sfx-' + name);
		if (!el) return;
		var vol = sfxVolume() * STINGER_GAIN;
		if (vol <= 0) return;
		try {
			if (currentStinger && currentStinger !== el) {
				currentStinger.pause();
				currentStinger.currentTime = 0;
			}
			currentStinger = el;
			el.volume = Math.min(1, vol);
			el.currentTime = 0;
			var p = el.play();
			if (p && p.catch) p.catch(function() {});
		} catch (e) {}
	}
	// One click per card drawn by Diamonds, stepped to match the .13s
	// --draw-i stagger in the CSS so the sound lands with each card's
	// animation rather than as one lump. Capped so a big Diamond into an
	// empty hand is a flourish, not a machine-gun burst.
	function playDrawSfx(count) {
		var n = Math.min(count, 6);
		for (var i = 0; i < n; i++) {
			setTimeout(playCardSfx, i * 130);
		}
	}
	// Sounds the SERVER asked for, via data-sfx on a flash modal. Last Stand
	// fires on its own during a Cover Damage turn -- there's no button press
	// to hang it off, and the client can't know it happened until the response
	// comes back. Sniffing the modal's TEXT would have worked too and would
	// break the first time the wording changes.
	function playFlashSfx(container) {
		if (!container) return;
		var el = container.querySelector('[data-sfx]');
		if (el) playNamedSfx(el.getAttribute('data-sfx'));
	}
	// Two classes of sound, and they want opposite handling. Stingers are long
	// (~9.5s), play at half level, and are mutually exclusive. Short one-shots
	// (the kill, 0.53s) play at the click's level, and must NOT cut a stinger
	// off -- killing a regent on the turn a Jester was flipped should layer
	// over that cue, not silence it.
	var STINGERS = { jester: 1, laststand: 1 };
	function playNamedSfx(name) {
		if (STINGERS[name]) { playStinger(name); return; }
		var el = document.getElementById('cq-sfx-' + name);
		if (!el) return;
		var vol = sfxVolume();
		if (vol === 0) return;
		try {
			el.volume = vol;
			el.currentTime = 0;
			var p = el.play();
			if (p && p.catch) p.catch(function() {});
		} catch (e) {}
	}

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

		// Fired here, not on the response: the sound belongs to the click that
		// moved the card, and waiting for the round-trip would drift it a few
		// hundred ms late. This is also still inside the user-gesture window,
		// which is what keeps browser autoplay policy from blocking it.
		//
		// Every action that moves cards. Deliberately NOT 'abandon' (a
		// destructive confirm, not a card move) or 'start_run'.
		var sfxAction = formData.get('action');
		if (sfxAction === 'flip_jester') {
			// Its own stinger instead of the card click -- flipping a Jester
			// discards and redeals the whole hand, so a single click would
			// undersell it.
			playStinger('jester');
		} else {
			var movesCards = (sfxAction === 'play' || sfxAction === 'suffer' ||
			                  sfxAction === 'yield');
			// 'play' and 'suffer' both need a selection to do anything -- an
			// empty one is rejected server-side, and a card sound in front of
			// that error would be a lie. Yield takes no selection at all.
			var needsSelection = (sfxAction === 'play' || sfxAction === 'suffer');
			if (movesCards && (!needsSelection || formData.getAll('card_indices[]').length)) {
				playCardSfx();
			}
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
					playFlashSfx(resultOverlay); // no server sound ends a run today, but keep the paths symmetric
					busy = false;
					return;
				}
				gameArea.innerHTML = html;
				initGameArea();
				lockGameAreaBriefly();
				// Cards arriving from a Diamonds draw. Unlike the click sounds
				// above this can only fire on the RESPONSE -- how many cards
				// Diamonds pulled is decided server-side (hand space caps it,
				// so a 9 into a full hand may draw one card or none), and the
				// rendered .cq-card-drawn markers are the first place the
				// client learns the real number.
				var drawn = gameArea.querySelectorAll('.cq-card-drawn').length;
				if (drawn) playDrawSfx(drawn);
				playFlashSfx(gameArea);
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
