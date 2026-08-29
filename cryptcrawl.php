<?php
// CRYPT CRAWL — linked in nav (Play > Crypt Crawl, after Gauntlets). Public:
// playable while logged out, same as skullswap.php/match3rpg.php — but only
// persisted to the DB for a real account (see the storage functions in
// db.php's "CRYPT CRAWL" block, which branch on $user_id/run id > 0). A
// guest's run lives in $_SESSION only and is gone with their session, so
// guests never appear on the leaderboard (see checkCryptCrawlLeaderboard()
// in db.php, alongside the other leaderboard checks) or earn CARBON —
// explicitly account-only, like every other weekly leaderboard payout here.
include_once 'db.php';
include 'message.php';
include 'verify.php';
include_once 'cryptcrawl-actions.php';
include_once 'cryptcrawl-render.php';

// Unlike most guest-playable pages here, Crypt Crawl needs a real working
// session even for a brand-new anonymous visitor. Actions are now handled
// over AJAX (see the script block below and ajax/cryptcrawl-action.php),
// but this page is still the no-JS/first-load fallback, so a guest's flash
// messages and run state still need somewhere to live between requests.
// db.php only starts a session when a cookie already
// exists — force one here regardless, so a first-ever visitor still gets a
// session cookie and can actually play.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Restore the staking session from the 6-month SessionCookie when PHPSESSID
// has lapsed (same pattern as skullswap.php/monstrocity.php/match3rpg.php).
// No hard gate — a missing/absent session just means a guest.
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
    $cookieData = json_decode($_COOKIE['SessionCookie'], true);
    if (is_array($cookieData)) {
        $_SESSION = $cookieData;
    }
}
$user_id = isset($_SESSION['userData']['user_id']) ? intval($_SESSION['userData']['user_id']) : 0;

if (!isset($_SESSION['cryptcrawl_flash'])) $_SESSION['cryptcrawl_flash'] = [];

// ── POST action handling — must run before any output ───────
// No-JS fallback only now: with JS, the forms below are intercepted and
// posted to ajax/cryptcrawl-action.php instead (see the script block),
// which calls this exact same cryptcrawlHandleAction() — real navigation
// here is what used to tear down and rebuild the <audio> element on every
// single action, audibly stuttering the ambient music player.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	cryptcrawlHandleAction($conn, $user_id, $_POST);
	header('Location: cryptcrawl.php');
	exit;
}

// header.php's shared nav is entirely gated on isset($name) (plus $avatar_url for
// the avatar image) — normally supplied by skulliance.php's extract($_SESSION
// ['userData']). This page deliberately skips that hard-gated include (see the
// header comment above) so a logged-in visitor needs the same two values computed
// here, or the whole nav — Play/NFTs/Stats/Account menus, Logout, wallet button,
// all of it — silently renders empty. Guests (user_id 0) get no $name, same as any
// other page when logged out; that part is an existing site-wide convention, not
// something specific to this page.
if ($user_id > 0 && isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	extract($_SESSION['userData']);
	if (isset($discord_id) && isset($avatar)) {
		$avatar_url = "https://cdn.discordapp.com/avatars/$discord_id/$avatar.jpg";
	}
}

include 'header.php';
// $active_run/$recent_run/$state/$flashes/$suit_symbol/$suit_color are all
// computed inside cryptcrawlRenderGameArea() now (cryptcrawl-render.php),
// since that function needs to be independently callable from the AJAX
// endpoint too, not just this page's own initial GET.
?>
<style>
/* Card index numerals only — rest of the page stays the site's normal Arial.
   Poppins ExtraBold approximates the bold, slightly-rounded look of a
   classic playing-card corner index (not an exact clone of any specific
   card brand's proprietary face — Google Fonts doesn't host one — but the
   closest properly-licensed match). */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@800&display=swap');

@keyframes ccCardFlip { from { transform: rotateY(180deg); } to { transform: rotateY(0deg); } }
@keyframes ccResultPop { from { opacity: 0; transform: scale(.6); } to { opacity: 1; transform: scale(1); } }
@keyframes ccFlashIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes ccFlashBackdropIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes ccFlashModalIn { from { opacity: 0; transform: scale(.85) translateY(10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
@keyframes ccPulse { 0%, 100% { box-shadow: 0 0 0 rgba(255,68,68,0); } 50% { box-shadow: 0 0 16px 2px rgba(255,68,68,.65); } }
@keyframes ccBtnSheen { from { transform: translateX(-120%) skewX(-20deg); } to { transform: translateX(220%) skewX(-20deg); } }

.cc-wrap { padding: 20px 16px 60px; }
.cc-inner { max-width: 720px; width: 100%; margin: 0 auto; }
/* A real modal instead of an edge/corner toast -- a small floating banner
   was easy to miss entirely on mobile (fixed-position elements can end up
   fighting the browser's own address-bar chrome, and a corner is rarely
   where the eye is looking right after tapping a button). The backdrop
   covers and dims the whole game, blocking taps on whatever's underneath
   until it's dismissed -- tapping anywhere closes it, and it also
   auto-dismisses on its own (see the bottom script block) so it never
   blocks play if left alone. */
.cc-flash-backdrop {
	position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.65);
	display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px;
	padding: 24px; box-sizing: border-box; cursor: pointer;
	animation: ccFlashBackdropIn .25s ease both;
}
.cc-flash-modal {
	background: rgba(10,16,24,.97); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
	border-radius: 16px; padding: 26px 24px; max-width: 380px; width: 100%; box-sizing: border-box;
	text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,.6);
	animation: ccFlashModalIn .35s cubic-bezier(.18,.89,.32,1.28) both;
}
.cc-flash-icon { font-size: 2rem; margin-bottom: 8px; line-height: 1; }
.cc-flash-text { font-size: 1rem; font-weight: 700; line-height: 1.4; }
.cc-flash-modal.win  { border: 1px solid rgba(0,200,160,.5); }
.cc-flash-modal.win .cc-flash-text { color: #00c8a0; }
.cc-flash-modal.loss, .cc-flash-modal.error { border: 1px solid rgba(255,68,68,.5); }
.cc-flash-modal.loss .cc-flash-text, .cc-flash-modal.error .cc-flash-text { color: #ff7070; }
.cc-flash-modal.info { border: 1px solid rgba(255,255,255,.25); }
.cc-flash-modal.info .cc-flash-text { color: #c8dce8; }
/* "View Instructions" -- same backdrop+card look as the flash notifications
   above, but a plain hide/show toggle instead of something PHP renders
   conditionally: the rules text is static, so there's nothing server-side
   to drive it. display:none by default; JS just adds/removes .show. */
.cc-instructions-backdrop { display: none; position: fixed; inset: 0; z-index: 60; background: rgba(0,0,0,.65); align-items: center; justify-content: center; padding: 24px; box-sizing: border-box; }
.cc-instructions-backdrop.show { display: flex; animation: ccFlashBackdropIn .2s ease both; }
.cc-instructions-modal {
	background: rgba(10,16,24,.97); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
	border-radius: 16px; padding: 22px 20px; max-width: 440px; width: 100%; box-sizing: border-box;
	box-shadow: 0 20px 60px rgba(0,0,0,.6); animation: ccFlashModalIn .3s cubic-bezier(.18,.89,.32,1.28) both;
	max-height: 85vh; overflow-y: auto;
}
.cc-instructions-modal h3 { margin: 0 0 10px; font-size: 1.05rem; }
.cc-instructions-close { margin-top: 16px; width: 100%; }
.cc-theme-bg { background-size: cover; background-position: center; border-radius: 14px; padding: 18px; margin: 0 -16px; transition: background-image .6s ease; display: flex; align-items: center; justify-content: center; box-sizing: border-box; min-height: 200px; }
.cc-hud {
	display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; animation: ccFlashIn .5s ease .15s both;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border-radius: 12px; padding: 12px 14px; box-sizing: border-box;
}
.cc-hp-wrap { width: 100%; }
.cc-hud-meta { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; justify-content: space-between; }
.cc-hp-bar-bg {
	background: linear-gradient(90deg,#ff4444,#ff9900,#00c8a0); border-radius: 6px; height: 14px;
	overflow: hidden; position: relative;
}
.cc-hp-bar-fill {
	/* A right-anchored cover over the un-filled portion, not a growing fill —
	   the gradient lives on .cc-hp-bar-bg at a fixed, always-full-bar-width
	   position so low HP shows solid red instead of the whole red-to-teal
	   spectrum getting squeezed into a sliver a few px wide. */
	background: rgba(5,12,20,.88); height: 100%; position: absolute; top: 0; right: 0;
	transition: width 1s cubic-bezier(.22,1,.36,1);
}
.cc-hp-wrap.low .cc-hp-bar-bg { animation: ccPulse 1.1s ease-in-out infinite; border-radius: 6px; }
.cc-weapon { font-size: 0.8rem; opacity: 0.8; display: flex; align-items: center; gap: 6px; }
.cc-second-wind { color: #00c8a0; font-weight: 700; white-space: nowrap; }
.cc-second-wind.used { color: rgba(255,255,255,.4); }
.cc-hud-carbon { display: inline-flex; align-items: center; gap: 3px; color: #ffcc4d; font-weight: 700; white-space: nowrap; margin-left: auto; }
.cc-hud-carbon img { width: 14px; height: 14px; object-fit: contain; }
.cc-weapon-icon { width: 20px; height: 20px; object-fit: contain; flex-shrink: 0; }
.cc-room { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
.cc-card { text-align: center; }
.cc-card-flip {
	perspective: 1000px; margin-bottom: 10px;
	transition: transform .18s ease-out, box-shadow .25s ease;
}
.cc-card-flip-inner {
	position: relative; aspect-ratio: 5 / 7; transform-style: preserve-3d;
	animation: ccCardFlip .6s cubic-bezier(.3,.9,.4,1) both;
}
.cc-room .cc-card:nth-child(1) .cc-card-flip-inner { animation-delay: .05s; }
.cc-room .cc-card:nth-child(2) .cc-card-flip-inner { animation-delay: .13s; }
.cc-room .cc-card:nth-child(3) .cc-card-flip-inner { animation-delay: .21s; }
.cc-room .cc-card:nth-child(4) .cc-card-flip-inner { animation-delay: .29s; }
.cc-card-face {
	position: absolute; inset: 0; backface-visibility: hidden; -webkit-backface-visibility: hidden;
	border-radius: 10px; overflow: hidden; background: #000; border: 2px solid rgba(255,255,255,.08); box-sizing: border-box;
}
.cc-card-back { transform: rotateY(180deg); display: flex; align-items: center; justify-content: center; }
.cc-card-back-icon { width: 38%; height: auto; display: block; }
@media (hover: hover) and (pointer: fine) {
	.cc-room { perspective: 900px; }
	.cc-card-flip:hover { box-shadow: 0 16px 32px rgba(0,0,0,.55), 0 0 28px var(--cc-glow, rgba(255,153,0,.35)); }
}
@media (hover: none), (pointer: coarse) {
	/* No hover/tilt on touch — a tap-press scale gives the same "this reacted
	   to me" feedback without a stuck hover state after lifting the finger. */
	.cc-card-flip:active { transform: scale(.97); box-shadow: 0 0 22px var(--cc-glow, rgba(255,153,0,.3)); }
}
/* Mid-spin, the 3D-rotated card can go edge-on (near-invisible) while this
   glow — sized to the flat, unrotated card — stays put as a static
   rectangle, reading as a stray outline hovering behind the animation.
   Suppressed for the animation's duration (JS toggles this class). */
.cc-card-flip.cc-spinning { box-shadow: none !important; }
.cc-card-art { position: relative; width: 100%; height: 100%; }
.cc-card-img { width: 100%; height: 100%; object-fit: contain; display: block; background: #000; }
/* Weapon/potion card faces: plain black instead of NFT art (curated Crypties
   art is reserved for enemies), with just the weapon/medkit icon centered --
   same idea as the card back's centered logo. Forced to solid white via the
   filter so an arbitrarily-colored source icon always reads clearly against
   the black card, regardless of its own native colors. */
.cc-card-icon-face { display: flex; align-items: center; justify-content: center; background: #000; }
.cc-card-icon { width: 42%; height: auto; filter: brightness(0) invert(1); opacity: 0.92; }
.cc-card-rank, .cc-card-suit { font-family: 'Poppins', Arial, sans-serif; }
.cc-card-corner {
	position: absolute; display: flex; flex-direction: column; align-items: center; line-height: 1;
	text-shadow:
		-1.5px -1.5px 2px rgba(0,0,0,.95), 1.5px -1.5px 2px rgba(0,0,0,.95),
		-1.5px  1.5px 2px rgba(0,0,0,.95), 1.5px  1.5px 2px rgba(0,0,0,.95),
		0 0 8px rgba(0,0,0,.85), 0 0 3px rgba(0,0,0,.9);
}
.cc-card-corner .cc-card-rank { font-size: 1rem; font-weight: 800; }
.cc-card-corner .cc-card-suit { font-size: 1.3rem; margin-top: 1px; }
.cc-card-corner.tl { top: 8%; left: 12%; }
.cc-card-corner.br { bottom: 8%; right: 12%; transform: rotate(180deg); }
.cc-card-badge-standalone { display: inline-flex; flex-direction: column; align-items: center; gap: 2px; height: 100%; justify-content: center; }
.cc-card-badge-standalone .cc-card-rank { font-size: 1.8rem; font-weight: 800; }
.cc-card-badge-standalone .cc-card-suit { font-size: 2.1rem; }
.cc-card-controls {
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border-radius: 10px; box-sizing: border-box;
	animation: ccFlashIn .4s ease .35s both;
}
.cc-card-label { font-size: 0.68rem; text-transform: uppercase; opacity: 0.55; letter-spacing: .05em; padding: 8px 10px 0; }
.cc-card-actions { padding: 8px 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.cc-btn {
	position: relative; overflow: hidden; background: #00c8a0; color: #012; border: 1px solid transparent; border-radius: 6px;
	padding: 7px 10px; font-size: 0.78rem; font-weight: 600; cursor: pointer; box-sizing: border-box;
	width: 100%; min-height: 48px; display: flex; align-items: center; justify-content: center; text-align: center;
	transition: transform .12s ease, filter .12s ease, box-shadow .2s ease;
}
.cc-btn:hover:not(:disabled) { filter: brightness(1.12); box-shadow: 0 6px 16px rgba(0,200,160,.35); }
.cc-btn:active:not(:disabled) { transform: scale(.95); }
.cc-btn.secondary { background: rgba(255,255,255,.08); color: #e8f2f8; border-color: rgba(255,255,255,.3); }
.cc-btn.secondary:hover:not(:disabled) { background: rgba(255,255,255,.15); box-shadow: 0 6px 16px rgba(255,255,255,.12); }
.cc-btn.warn { background: #ff9900; color: #012; }
/* Punchy 3-tier layout: big icon, bold action word, small effect/detail
   line — deliberate hierarchy instead of everything crammed onto one or
   two lines. Only the four card-action buttons use this; Flee/Abandon/
   Start Delve stay single-line since they don't have an icon+action+detail
   shape to begin with. */
.cc-btn.punchy { flex-direction: column; justify-content: center; gap: 3px; min-height: 84px; padding: 10px 6px; }
.cc-btn-icon-big { font-size: 1.5rem; line-height: 1; }
.cc-btn-icon-big-img {
	width: 30px; height: 30px; object-fit: contain;
	/* Same reasoning as before: force solid black so it matches the
	   button's dark text regardless of the weapon icon's own colors. */
	filter: brightness(0);
}
.cc-btn-action { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; line-height: 1.1; }
.cc-btn-detail { font-size: 0.7rem; font-weight: 500; opacity: 0.75; line-height: 1.1; text-align: center; }
.cc-btn.warn:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(255,153,0,.4); }
.cc-btn.heal { background: #00c8a0; color: #012; }
.cc-btn.heal:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(0,200,160,.4); }
.cc-btn.attack { background: #ff4444; color: #012; }
.cc-btn.attack:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(255,68,68,.4); }
.cc-btn.bare { background: #a855f7; color: #012; }
.cc-btn.bare:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(168,85,247,.4); }
/* Solid, not the translucent .secondary treatment -- that one reads fine on
   the app's own dark background, but washes out against the death screen's
   black-and-white themed backdrop (images/themes/8.jpg). Same gold as the
   CARBON accent elsewhere on this page for a bit of visual continuity. */
.cc-btn.gold { background: #ffcc4d; color: #012; }
.cc-btn.gold:hover:not(:disabled) { box-shadow: 0 6px 16px rgba(255,204,77,.4); }
.cc-btn:disabled { opacity: 0.35; cursor: default; }
@media (hover: hover) and (pointer: fine) {
	.cc-btn:not(:disabled)::after {
		content: ''; position: absolute; top: 0; left: 0; width: 40%; height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
		transform: translateX(-120%) skewX(-20deg); pointer-events: none;
	}
	.cc-btn:not(:disabled):hover::after { animation: ccBtnSheen .6s ease; }
}
.cc-note { font-size: 0.68rem; opacity: 0.5; margin-top: 4px; }
.cc-rules { font-size: 0.85rem; line-height: 1.6; opacity: 0.75; margin-bottom: 20px; }
/* Ambient music player -- sits below every state's bottom buttons (in normal
   flow, not fixed) so it's always in the same spot regardless of no_run /
   active / game_over. Deliberately minimal: 3 icon buttons + a track name. */
.cc-audio-player {
	max-width: 720px; margin: 14px auto 0; display: flex; align-items: center; gap: 8px;
	background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); border-radius: 999px;
	padding: 8px 14px; box-sizing: border-box; font-size: 0.72rem; color: #9fb4c2;
}
.cc-audio-btn {
	background: none; border: none; color: #c8dce8; cursor: pointer;
	font-size: 1rem; line-height: 1; padding: 0; border-radius: 50%; flex: none;
	display: flex; align-items: center; justify-content: center; width: 26px; height: 26px;
}
.cc-audio-btn:hover { background: rgba(255,255,255,.1); }
.cc-audio-track { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cc-audio-vol-icon { flex: none; font-size: 0.8rem; opacity: 0.8; }
.cc-audio-volume { flex: none; width: 64px; accent-color: #ffcc4d; cursor: pointer; }
.cc-result {
	text-align: center; border-radius: 12px; padding: 30px 20px; margin-bottom: 20px; box-sizing: border-box;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
}
.cc-result.lost { box-shadow: 0 0 40px rgba(224,85,85,.15) inset, 0 0 1px rgba(224,85,85,.4); }
.cc-result.won  { box-shadow: 0 0 40px rgba(0,200,160,.15) inset, 0 0 1px rgba(0,200,160,.4); }
.cc-result-icon  { font-size: 3.2rem; margin-bottom: 10px; animation: ccResultPop .5s cubic-bezier(.18,.89,.32,1.28) .1s both; }
.cc-result-title { font-size: 1.6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; animation: ccResultPop .5s cubic-bezier(.18,.89,.32,1.28) .2s both; }
.cc-result.lost .cc-result-title { color: #ff7070; }
.cc-result.won  .cc-result-title { color: #00c8a0; }
.cc-result-sub { font-size: .85rem; color: rgba(255,255,255,.5); animation: ccResultPop .5s cubic-bezier(.18,.89,.32,1.28) .3s both; }
.cc-result-carbon {
	margin-top: 10px; font-size: 1rem; font-weight: 700; color: #ffcc4d;
	display: flex; align-items: center; justify-content: center; gap: 6px;
	animation: ccResultPop .5s cubic-bezier(.18,.89,.32,1.28) .4s both;
}
.cc-result-carbon img { width: 20px; height: 20px; object-fit: contain; }
.cc-flee-row {
	display: grid; grid-template-columns: 1fr 1fr; gap: 8px;
	text-align: center; margin-bottom: 20px;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border-radius: 12px; padding: 14px; box-sizing: border-box;
}
/* Flee's button and its conditional "can't flee" note need to land in the
   SAME grid cell, not two -- otherwise a 3-button-plus-a-note layout would
   throw off the clean 2x2 (5 items instead of 4) exactly when the note is
   showing. */
.cc-flee-cell { display: flex; flex-direction: column; }
.cc-flee-cell form { height: 100%; }
.cc-flee-cell .cc-btn { height: 100%; }
@media (prefers-reduced-motion: reduce) {
	.cc-card-flip-inner, .cc-card-controls, .cc-flash-backdrop, .cc-flash-modal, .cc-instructions-backdrop.show,
	.cc-instructions-modal, .cc-hud, .cc-hp-wrap.low .cc-hp-bar-bg, .cc-btn::after,
	.cc-result-icon, .cc-result-title, .cc-result-sub, .cc-result-carbon { animation: none !important; }
	.cc-card-flip, .cc-btn, .cc-hp-bar-fill { transition: none !important; }
}
/* Mobile: cards and buttons at 75% scale, with the button panel pulled up
   to sit over the card art's lower half instead of stacked below it —
   halves a room's vertical footprint instead of just shrinking it, which
   is what actually gets the whole game onto one phone screen.
   margin-top:-70% is deliberate, not a rounded guess: percentage margins
   resolve against the containing block's WIDTH (not height), and this
   card's aspect-ratio is 5/7 (height = 1.4x width) — so -70% of width is
   exactly -50% of the card's own height, regardless of the actual column
   width any given phone's grid lands on. */
@media (max-width: 700px) {
	/* Fixed 2 columns, not auto-fill -- a room is always exactly 4 cards,
	   and auto-fill's minmax(105px,1fr) was free to fit 3 across on wider
	   phones, splitting the room 3-and-1 instead of a clean 2x2. */
	.cc-room { grid-template-columns: repeat(2, 1fr); gap: 9px; }
	.cc-card-flip { margin-bottom: 0; }
	.cc-card-corner .cc-card-rank { font-size: 0.75rem; }
	.cc-card-corner .cc-card-suit { font-size: 0.975rem; }
	.cc-card-badge-standalone .cc-card-rank { font-size: 1.35rem; }
	.cc-card-badge-standalone .cc-card-suit { font-size: 1.575rem; }
	.cc-card-controls { position: relative; z-index: 2; margin-top: -70%; }
	.cc-card-label { font-size: 0.51rem; padding: 6px 8px 0; }
	.cc-card-actions { padding: 6px 8px 9px; gap: 4px; }
	.cc-btn.punchy { min-height: 63px; padding: 7px 5px; gap: 2px; }
	.cc-btn-icon-big { font-size: 1.125rem; }
	.cc-btn-icon-big-img { width: 22px; height: 22px; }
	.cc-btn-action { font-size: 0.6rem; }
	/* Bolder + more opaque than the desktop rule -- at this size, 500-weight
	   text at 0.75 opacity was hard to make out. */
	.cc-btn-detail { font-size: 0.53rem; font-weight: 700; opacity: 0.9; }
	/* The button panel covers the card's bottom half here (see margin-top:-70%
	   above) -- a dead-centered icon on a weapon/potion card face would sit
	   right on that fold and get covered. Move it up into the clear top
	   portion instead of just hoping it peeks out. */
	.cc-card-icon-face { align-items: flex-start; padding-top: 14%; box-sizing: border-box; }
	/* Icon + action + detail no longer fits three rows worth of height at this
	   scale without help -- drop the icon row on mobile specifically (the
	   action word plus detail number still say everything the icon did) to
	   claim back vertical space for the rest of the UI. */
	.cc-btn-icon-big, .cc-btn-icon-big-img { display: none; }
	.cc-btn.punchy { min-height: 44px; padding: 6px 5px; gap: 1px; }
}
</style>
<div class="cc-wrap">
<div id="cc-game-area"><?php cryptcrawlRenderGameArea($conn, $user_id); ?></div>

	<!-- Ambient music player -- OUTSIDE #cc-game-area above on purpose, so
	     AJAX-swapping the game area on every action (see the script block
	     below) never touches this element or the <audio> below it: this is
	     what actually keeps the music playing continuously across actions.
	     Playback state (on/off, track, position) still persists in
	     sessionStorage too, for the no-JS fallback and the first page load. -->
	<div class="cc-audio-player" id="cc-audio-player">
		<button type="button" class="cc-audio-btn" id="cc-audio-prev" title="Previous track">⏮</button>
		<button type="button" class="cc-audio-btn" id="cc-audio-toggle" title="Play/Pause">▶</button>
		<button type="button" class="cc-audio-btn" id="cc-audio-next" title="Next track">⏭</button>
		<span class="cc-audio-track" id="cc-audio-track-name">Crypt Crawl Theme</span>
		<span class="cc-audio-vol-icon">🔊</span>
		<input type="range" class="cc-audio-volume" id="cc-audio-volume" min="0" max="100" value="50" title="Volume">
	</div>
	<audio id="cc-audio-el" preload="metadata"></audio>
</div>
<script>
(function() {
	var gameArea = document.getElementById('cc-game-area');

	// .cc-theme-bg sizing -- one resize listener attached once below (not
	// re-added on every AJAX swap, which would stack up a fresh listener
	// per action), re-querying .cc-theme-bg fresh each time it fires so it
	// always matches whatever's currently in #cc-game-area.
	function sizeTheme() {
		var el = document.querySelector('.cc-theme-bg');
		if (!el) return;
		var top = el.getBoundingClientRect().top;
		var bottomPad = 60; // matches .cc-wrap's bottom padding
		var available = window.innerHeight - top - bottomPad;
		// Never shrink below what the content actually needs — on narrow
		// viewports the room grid wraps to more rows, and with
		// align-items:center a too-short box would center-overflow,
		// clipping the HUD off the top of the page instead of just
		// letting the page scroll a little. Cropping the art via
		// background-size:cover is fine; clipping the UI isn't.
		el.style.height = 'auto';
		var natural = el.scrollHeight;
		el.style.height = Math.max(200, available, natural) + 'px';
	}
	window.addEventListener('resize', sizeTheme);

	// Everything below re-wires whatever's currently inside #cc-game-area --
	// run once for the initial page load, then again after every AJAX swap
	// (see the submit-interception further down), since a swap destroys and
	// recreates all of it. Each piece re-queries the DOM fresh rather than
	// caching elements from a previous call.
	function initGameArea() {
		// Flash notifications are a real modal (backdrop + centered card),
		// not a small corner toast -- easy to miss on mobile, especially
		// fixed-position elements fighting the browser's own address-bar
		// chrome. Dismissible by tapping anywhere (nothing inside is
		// interactive, so a tap on the card itself is just as valid as
		// tapping the dimmed area around it) and auto-dismisses on its own
		// after a hold so it never blocks play if left alone. Reduced-
		// motion still gets the timed removal, just without the fade.
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var flashBackdrop = document.getElementById('cc-flash-backdrop');
		if (flashBackdrop) {
			var dismissFlash = function() {
				if (!flashBackdrop.isConnected) return; // already dismissed
				if (reduceMotion) {
					flashBackdrop.remove();
					return;
				}
				flashBackdrop.style.transition = 'opacity .3s ease';
				flashBackdrop.style.opacity = '0';
				setTimeout(function() { flashBackdrop.remove(); }, 300);
			};
			flashBackdrop.addEventListener('click', dismissFlash);
			setTimeout(dismissFlash, 4000);
		}

		// "View Instructions" -- purely a client-side show/hide, no server
		// round trip needed since the rules text is static. Unlike the flash
		// modal above (nothing inside it is interactive, so any tap
		// dismisses it), this one only closes on a tap OUTSIDE the card or
		// the explicit Close button -- the modal can scroll on a small
		// screen, and a stray tap while reading shouldn't close it out from
		// under you.
		var instrBtn = document.getElementById('cc-instructions-btn');
		var instrBackdrop = document.getElementById('cc-instructions-backdrop');
		var instrClose = document.getElementById('cc-instructions-close');
		if (instrBtn && instrBackdrop) {
			instrBtn.addEventListener('click', function() {
				instrBackdrop.classList.add('show');
			});
			instrBackdrop.addEventListener('click', function(e) {
				if (e.target === instrBackdrop) instrBackdrop.classList.remove('show');
			});
			if (instrClose) {
				instrClose.addEventListener('click', function() {
					instrBackdrop.classList.remove('show');
				});
			}
		}

		// HP bar renders fully covered (width:100%, i.e. empty-looking) so
		// that nudging it to its real data-target-width one frame later
		// animates the gradient revealing in on every render, not just on
		// HP changes -- the bar's own CSS transition (.cc-hp-bar-fill) does
		// the actual tween.
		var hpFill = document.querySelector('.cc-hp-bar-fill');
		if (hpFill) {
			var target = hpFill.getAttribute('data-target-width');
			requestAnimationFrame(function() {
				requestAnimationFrame(function() {
					hpFill.style.width = target + '%';
				});
			});
		}

		sizeTheme();

		// Desktop-only card tilt — skip entirely on touch devices so there's
		// no stuck "hover" state after a tap. Targets .cc-card-flip
		// specifically (the "card" object) so the controls panel below
		// stays flat/static instead of tilting along with it. No-ops on
		// states with no cards.
		if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
			document.querySelectorAll('.cc-card-flip').forEach(function(card) {
				card.addEventListener('mousemove', function(e) {
					var r = card.getBoundingClientRect();
					var x = (e.clientX - r.left) / r.width - 0.5;
					var y = (e.clientY - r.top) / r.height - 0.5;
					card.style.transform = 'perspective(700px) rotateX(' + (-y * 8) + 'deg) rotateY(' + (x * 8) + 'deg) translateY(-6px) scale(1.03)';
				});
				card.addEventListener('mouseleave', function() {
					card.style.transform = '';
				});
			});
		}

		// Click/tap a card and it does a full 360° spin, passing through the
		// back face at the midpoint -- a tactile "yep, that reacted"
		// flourish. Purely cosmetic: the actual actions are the buttons
		// below, a sibling of this element in the DOM, so this listener
		// never touches them. Uses the Web Animations API rather than a CSS
		// class + keyframe so it can't collide with .cc-card-flip-inner's
		// own intro-flip CSS animation -- toggling a class that shares the
		// `animation` property would make the browser treat animation-name
		// as having changed and replay the intro flip from its
		// rotateY(180deg) starting point every time this spin finishes and
		// the class comes back off.
		if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
			document.querySelectorAll('.cc-card-flip').forEach(function(card) {
				var inner = card.querySelector('.cc-card-flip-inner');
				if (!inner) return;
				var spinning = false;
				card.addEventListener('click', function() {
					if (spinning) return;
					spinning = true;
					card.classList.add('cc-spinning');
					var anim = inner.animate(
						[{ transform: 'rotateY(0deg)' }, { transform: 'rotateY(360deg)' }],
						{ duration: 650, easing: 'cubic-bezier(.3,.9,.4,1)' }
					);
					anim.onfinish = anim.oncancel = function() {
						spinning = false;
						card.classList.remove('cc-spinning');
					};
				});
			});
		}
	}

	initGameArea();

	// Intercept every action form inside #cc-game-area and post to the AJAX
	// endpoint instead of letting the browser navigate there -- this is what
	// actually keeps the ambient <audio> element (a sibling of #cc-game-area,
	// outside it entirely, never touched by the innerHTML swap below)
	// playing continuously across actions instead of restarting on every
	// single one. Delegated on document (stable across swaps) rather than
	// bound to each form directly, since every swap destroys and recreates
	// all of them; cryptcrawl.php's own POST handling stays in place as the
	// fallback for when this fails or JS is unavailable.
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
		fetch('ajax/cryptcrawl-action.php', { method: 'POST', body: new FormData(form) })
			.then(function(res) {
				if (!res.ok) throw new Error('bad response');
				return res.text();
			})
			.then(function(html) {
				gameArea.innerHTML = html;
				gameArea.style.opacity = '';
				gameArea.style.pointerEvents = '';
				busy = false;
				initGameArea();
			})
			.catch(function() {
				// AJAX failed for some reason -- fall back to a real submit
				// (full page reload) rather than leaving the action stuck.
				gameArea.style.opacity = '';
				gameArea.style.pointerEvents = '';
				busy = false;
				form.submit();
			});
	});

	// Ambient music player. Two tracks, cycled on 'ended'. State (on/off,
	// which track, playback position) lives in sessionStorage because this
	// page reloads on every single game action -- without saving position,
	// the music would restart from 0:00 on every card played.
	(function() {
		var audio = document.getElementById('cc-audio-el');
		var toggleBtn = document.getElementById('cc-audio-toggle');
		var prevBtn = document.getElementById('cc-audio-prev');
		var nextBtn = document.getElementById('cc-audio-next');
		var trackNameEl = document.getElementById('cc-audio-track-name');
		var volumeEl = document.getElementById('cc-audio-volume');
		if (!audio || !toggleBtn) return;

		var TRACKS = [
			{ name: 'Crypt Crawl Theme', src: 'audio/tracks/Crypt%20Crawl%20Theme.mp3' },
			{ name: 'Crypt Crawl Reprise', src: 'audio/tracks/Crypt%20Crawl%20Reprise.mp3' }
		];

		function getEnabled() {
			var v = sessionStorage.getItem('cc_audio_enabled');
			return v === null ? true : v === '1'; // ambience on by default
		}
		function setEnabled(v) { try { sessionStorage.setItem('cc_audio_enabled', v ? '1' : '0'); } catch (e) {} }
		function getTrackIndex() {
			var v = parseInt(sessionStorage.getItem('cc_audio_track'), 10);
			return (v === 0 || v === 1) ? v : 0;
		}
		function setTrackIndex(v) { try { sessionStorage.setItem('cc_audio_track', String(v)); } catch (e) {} }
		function getPosition() {
			var v = parseFloat(sessionStorage.getItem('cc_audio_position'));
			return isNaN(v) ? 0 : v;
		}
		function setPosition(v) { try { sessionStorage.setItem('cc_audio_position', String(v)); } catch (e) {} }
		function getVolume() {
			var v = parseInt(sessionStorage.getItem('cc_audio_volume'), 10);
			return (v >= 0 && v <= 100) ? v : 50; // tracks are mixed loud -- half by default
		}
		function setVolume(v) { try { sessionStorage.setItem('cc_audio_volume', String(v)); } catch (e) {} }

		var trackIndex = getTrackIndex();
		var enabled = getEnabled();

		audio.volume = getVolume() / 100;
		if (volumeEl) volumeEl.value = getVolume();

		function updateToggleIcon() {
			toggleBtn.textContent = (!audio.paused) ? '⏸' : '▶';
		}

		function loadTrack(index, resumePosition) {
			trackIndex = index;
			setTrackIndex(index);
			trackNameEl.textContent = TRACKS[index].name;
			audio.src = TRACKS[index].src;
			if (resumePosition) {
				var resumeAt = getPosition();
				audio.addEventListener('loadedmetadata', function once() {
					audio.currentTime = resumeAt;
					audio.removeEventListener('loadedmetadata', once);
				});
			}
			audio.load();
		}

		function tryPlay() {
			var p = audio.play();
			if (p && p.catch) {
				// Autoplay blocked -- normal on a fresh visit with no prior
				// interaction yet. Leave it paused/ready; the toggle button
				// (or any click that lands after this) will work.
				p.catch(function() { updateToggleIcon(); });
			}
		}

		loadTrack(trackIndex, true);
		if (enabled) {
			tryPlay();
			// Browsers only allow unmuted autoplay off the back of a *trusted*
			// user gesture -- nothing in JS can fake that (see Skull Paper /
			// MAINTENANCE.md for why). If the tryPlay() above got blocked,
			// catch the very first real interaction anywhere on the page --
			// not just a tap on this player's own button -- and use it to
			// start audio too, since a player's first move is usually
			// something else entirely (Start Delve, playing a card).
			var unlockEvents = ['pointerdown', 'keydown', 'touchstart'];
			var unlockAudio = function() {
				unlockEvents.forEach(function(evt) { window.removeEventListener(evt, unlockAudio, true); });
				if (audio.paused && getEnabled()) tryPlay();
			};
			unlockEvents.forEach(function(evt) { window.addEventListener(evt, unlockAudio, true); });
		}
		updateToggleIcon();

		audio.addEventListener('timeupdate', function() { setPosition(audio.currentTime); });
		audio.addEventListener('play', updateToggleIcon);
		audio.addEventListener('pause', updateToggleIcon);
		audio.addEventListener('ended', function() {
			setPosition(0);
			loadTrack((trackIndex + 1) % TRACKS.length, false);
			tryPlay();
		});

		toggleBtn.addEventListener('click', function() {
			if (audio.paused) { setEnabled(true); tryPlay(); }
			else { setEnabled(false); audio.pause(); }
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
				audio.volume = v / 100;
				setVolume(v);
			});
		}
	})();
})();
</script>
