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
        // Merge, not replace -- an outright assignment wipes every other
        // key this same session already has (e.g. cryptcrawl_flash/
        // cryptcrawl_guest_run), not just restores login. See
        // skulliance.php's own fix for the platform-wide version of this.
        $_SESSION = array_merge((array)$_SESSION, $cookieData);
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
	// No-JS visitor, a real page navigation either way -- unlike
	// ajax/cryptcrawl-action.php's fetch() (which a user can watch sit
	// there with nothing happening), a redirect+reload has its own
	// browser-native loading state, so there's no "looks stuck" risk in
	// just running CARBON payout/Discord announce inline before it fires.
	// See cryptcrawlFlushPendingSideEffects() in db.php -- still needed
	// here since cryptcrawlPersist() queues rather than pays out inline
	// regardless of caller.
	cryptcrawlFlushPendingSideEffects($conn);
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
/* #cc-game-area (the AJAX swap target -- see cryptcrawl-render.php) and
   #cc-result-overlay (its hidden sibling a win/loss reveals into, see the
   markup comment) both sit between .cc-theme-bg and .cc-inner with no
   rules of their own otherwise. Harmless when .cc-theme-bg is "bare"
   (plain block flow), but .cc-theme-active makes it a flex container
   (display:flex; justify-content:center) to center its content -- and a
   flex item with no explicit width shrinks to its content's own size
   instead of stretching to fill available space, so .cc-inner's own
   max-width:720px never actually got 720px of container to be 100% of.
   Explicit width:100% here is what lets it reach that cap at real desktop
   widths again -- without it, wide/desktop views quietly collapsed down
   toward .cc-room's minimum column width instead. Both elements need it,
   since either can be the one .cc-theme-bg is actually centering at any
   given moment. */
#cc-game-area, #cc-result-overlay { width: 100%; }
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
/* .cc-theme-bg is a PERMANENT element (see the markup, right after
   .cc-wrap opens) -- present in every state, "bare" (this rule only) when
   no themed backdrop applies, and switched into the actual themed-panel
   look by JS adding .cc-theme-active once #cc-mood says so. It used to be
   PHP itself that emitted/omitted this element per state, which meant an
   AJAX swap destroyed and recreated it (and restarted its Ken Burns
   animation below) on every single action, not just when the scene
   actually changed -- see MAINTENANCE.md. */
.cc-theme-bg { position: relative; }
.cc-theme-bg.cc-theme-active {
	overflow: hidden; border-radius: 14px; padding: 18px; margin: 0 -16px;
	display: flex; align-items: center; justify-content: center; box-sizing: border-box; min-height: 200px;
}
/* Background image lives on a pseudo-element, not the box itself, so the Ken
   Burns drift below (transform only) animates just the art -- never the real
   content (.cc-inner) sitting on top of it. inset:-5% (bigger than its own
   container) gives the pan/zoom room to move without ever exposing an edge;
   .cc-theme-active's overflow:hidden clips that oversized margin off. Hidden
   entirely outside .cc-theme-active -- bare mode has no image to show. */
.cc-theme-bg::before {
	content: ''; position: absolute; inset: -5%; background-image: var(--theme-img);
	background-size: cover; background-position: center; transition: background-image .6s ease;
	will-change: transform; display: none;
}
.cc-theme-bg.cc-theme-active::before { display: block; }
/* Ken Burns drift while the music is playing (toggleable -- see the audio
   player's zoom button) and a track is actually audible. --kb-* custom
   properties are randomized in JS only when the theme image actually
   changes (see applyThemeState() in the script block) -- this rule just
   plays back whatever values got set. animation-direction: alternate is
   what makes it loop seamlessly (ping-pongs back to its start rather than
   snapping), regardless of which random pair got picked. */
.cc-theme-bg.cc-zoom::before {
	animation: ccKenBurns var(--kb-duration, 26s) ease-in-out infinite alternate;
}
@keyframes ccKenBurns {
	from { transform: scale(var(--kb-scale-from, 1)) translate(var(--kb-x-from, 0%), var(--kb-y-from, 0%)); }
	to   { transform: scale(var(--kb-scale-to, 1.12)) translate(var(--kb-x-to, 2%), var(--kb-y-to, -2%)); }
}
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
/* Rules broken into labeled sections instead of one prose block -- same
   fix as cryptconquest.php's own .cq-rules-section/.cq-rules-label, see
   cryptcrawlRulesHtml()'s own comment for why. */
.cc-rules-section { margin-bottom: 16px; }
.cc-rules-section:last-child { margin-bottom: 0; }
.cc-rules-section p { margin: 0; }
.cc-rules-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #34e3bb; opacity: 0.9; margin-bottom: 6px; }
.cc-rules-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
.cc-rules-list li { padding-left: 2px; }
.cc-rules-tips { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px; }
.cc-rules-tips li { font-size: 0.8rem; line-height: 1.5; opacity: 0.95; padding: 8px 10px; background: rgba(0,200,160,.08); border-left: 3px solid #00c8a0; border-radius: 6px; }
.cc-rules-tips li strong { color: #34e3bb; }
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
/* Default (desktop-width): full name shown, short word hidden -- the
   @media (max-width: 700px) block further down flips both. */
.cc-audio-track-full { display: inline; }
.cc-audio-track-short { display: none; }
.cc-audio-vol-icon { flex: none; font-size: 0.8rem; opacity: 0.8; }
.cc-audio-volume { flex: none; width: 64px; accent-color: #ffcc4d; cursor: pointer; }
.cc-audio-btn.off { opacity: 0.35; } /* shared dimmed state for the player's toggle buttons (zoom, notifications) */
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
	.cc-result-icon, .cc-result-title, .cc-result-sub, .cc-result-carbon, .cc-theme-bg::before { animation: none !important; }
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
	/* Less room for the audio player's track label here -- show just the
	   last word ("Theme", "Reprise", "Doom"...) instead of the full
	   "Crypt Crawl X" name. JS keeps both spans in sync on every track
	   change (see #cc-audio-track-name); this media query is the only
	   thing that decides which one's actually visible, so rotating the
	   phone or resizing the window swaps it live with no JS involved. */
	.cc-audio-track-full { display: none; }
	.cc-audio-track-short { display: inline; }
}
</style>
<div class="cc-wrap">
<!-- Permanent wrapper -- unlike #cc-game-area inside it, this element is
     NEVER destroyed/recreated by an AJAX swap, specifically so its Ken
     Burns CSS animation (.cc-zoom, further up) keeps running continuously
     across actions instead of restarting on every single one. Starts
     "bare" (no cc-theme-active class, no background) since PHP no longer
     decides whether to emit it at all -- that's now a class JS toggles
     based on #cc-mood's data-theme-* attributes, applied by
     applyThemeState() in the script block below. -->
<div class="cc-theme-bg" id="cc-theme-bg">
<?php
// A "safety net" finalize call used to run here on every normal page load
// (removed 2026-08-31) -- it was a real bug, not a safety net. Its
// idempotency guard is $_SESSION-based (cryptcrawlFinalizeRun() in
// db.php), which does not survive a cleared cache / fresh login / new
// browser session -- exactly the condition a returning player hits
// constantly. On a brand-new session it ran a full re-payout attempt
// (a DB fetch, updateBalance/logCredit, a Discord webhook POST with its
// own 8-second timeout) for whatever the player's most recent WON/LOST
// run happened to be -- on EVERY page load, not just right after an
// action -- meaning CARBON they'd already been paid could get re-credited
// on every fresh session, and ordinary page loads on a fresh session
// carried real hidden latency this file's own history is full of chasing.
// Live symptom that caught it: worked fine on the PWA (a warm, reused
// session already had the guard set) and broke specifically on a freshly
// cleared/logged-in browser session (an empty guard, so this ran for
// real, every time). The one real finalize trigger is the fire-and-forget
// fetch(..., {keepalive:true}) in the AJAX swap handler below, fired only
// once, only right after the result screen it belongs to is on screen --
// see cryptcrawl-loss-screen-bug.md for the full history before adding
// anything like this back.
?>
<div id="cc-game-area"><?php
// Full-page-load context, unlike ajax/cryptcrawl-action.php's own fragment
// response -- a real exception here would fatal everything after this
// point in the page (the closing markup, every script tag), not just this
// one div. Same guarantee, adapted for having no in-memory $run to fall
// back on (a fresh GET has none): try the minimal confirmation off a
// fresh, much simpler re-fetch, and if even THAT fails, a plain message
// beats a broken page.
try {
	cryptcrawlRenderGameArea($conn, $user_id);
} catch (\Throwable $e) {
	error_log('cryptcrawlRenderGameArea failed on full page load: ' . $e->getMessage());
	try {
		$fallback_run = cryptcrawlGetMostRecentRun($conn, $user_id);
		if ($fallback_run && in_array($fallback_run['status'] ?? '', ['won', 'lost'], true)) {
			cryptcrawlMinimalGameOverHtml($fallback_run, $user_id);
		} else {
			echo '<p style="text-align:center;opacity:0.6;padding:40px 20px;">Something went wrong loading your delve. <a href="cryptcrawl.php" style="color:#00c8a0;">Try reloading</a>.</p>';
		}
	} catch (\Throwable $e2) {
		echo '<p style="text-align:center;opacity:0.6;padding:40px 20px;">Something went wrong loading your delve. <a href="cryptcrawl.php" style="color:#00c8a0;">Try reloading</a>.</p>';
	}
}
?></div>
<!-- Permanent, hidden sibling of #cc-game-area -- added 2026-08-30, replacing
     an earlier approach that forced a real page navigation on a win/loss
     specifically to guarantee the result could never be raced past by a
     stray tap. That worked, but cost a full reload (music restart) and
     turned out to have its own real failure mode (some engines resubmitting
     stale POST data on reload() depending on how the page was originally
     reached). This is simpler and has neither problem: the result HTML the
     server already renders gets dropped in here, then revealing it is a
     single synchronous style.display flip -- not a network-dependent swap,
     not a navigation, nothing with a timing window at all. "Delve Again"/
     "Weekly Leaderboard" inside it are deliberately OUTSIDE #cc-game-area,
     so the delegated AJAX submit listener below (scoped to
     gameArea.contains(form)) never intercepts them -- clicking either is a
     perfectly ordinary link/POST, the exact same kind of real navigation
     Start Delve itself already is, which is completely fine for the "start
     a fresh game" moment (unlike the "did you even see you died" moment,
     which is what this element exists to make bulletproof). -->
<div id="cc-result-overlay" style="display:none;"></div>
</div>

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
		<span class="cc-audio-track" id="cc-audio-track-name"><span class="cc-audio-track-full">Crypt Crawl Theme</span><span class="cc-audio-track-short">Theme</span></span>
		<span class="cc-audio-vol-icon">🔊</span>
		<input type="range" class="cc-audio-volume" id="cc-audio-volume" min="0" max="100" value="50" title="Volume">
		<button type="button" class="cc-audio-btn" id="cc-audio-zoom-toggle" title="Background zoom: on">🎥</button>
		<button type="button" class="cc-audio-btn" id="cc-audio-notif-toggle" title="Flee/medkit/Last Stand pop-ups: on">🔔</button>
	</div>
	<!-- Two elements, not one -- crossfading between tracks (mood changes,
	     manual skips, the normal loop's own advance) means briefly playing
	     the outgoing and incoming track at once while one ramps down and
	     the other ramps up. A single <audio> can only ever hold one src, so
	     that's not possible with just one element. See the script block's
	     crossfadeTo() for how these two take turns being "active". -->
	<audio id="cc-audio-el-a" preload="metadata"></audio>
	<audio id="cc-audio-el-b" preload="metadata"></audio>
	<!-- Card-action SFX -- see the SFX block in the script below for how
	     each fires and at what level. Short, frequent ones preload="auto"
	     (fired on click, no gap to hide); the long narrative cues
	     (laststand/death/victory, shared mp3s with Crypt Conquest) and the
	     rarer big-explosive weapons preload="metadata" instead, same
	     reasoning as the music tracks above -- no point eagerly pulling
	     100KB+ files that may never play this session. -->
	<audio id="cc-sfx-fist" preload="auto" src="audio/sounds/fist.mp3"></audio>
	<audio id="cc-sfx-equip" preload="auto" src="audio/sounds/equip.mp3"></audio>
	<audio id="cc-sfx-heal" preload="auto" src="audio/sounds/heal.mp3"></audio>
	<audio id="cc-sfx-flee" preload="auto" src="audio/sounds/flee.mp3"></audio>
	<audio id="cc-sfx-kill" preload="auto" src="audio/sounds/kill.mp3"></audio>
	<audio id="cc-sfx-melee" preload="auto" src="audio/sounds/melee.mp3"></audio>
	<audio id="cc-sfx-tacticalkatana" preload="auto" src="audio/sounds/tacticalkatana.mp3"></audio>
	<audio id="cc-sfx-pistol" preload="auto" src="audio/sounds/pistol.mp3"></audio>
	<audio id="cc-sfx-sniperrifle" preload="auto" src="audio/sounds/sniperrifle.mp3"></audio>
	<audio id="cc-sfx-machinegun" preload="auto" src="audio/sounds/machinegun.mp3"></audio>
	<audio id="cc-sfx-rocketlauncher" preload="metadata" src="audio/sounds/rocketlauncher.mp3"></audio>
	<audio id="cc-sfx-grenade" preload="metadata" src="audio/sounds/grenade.mp3"></audio>
	<audio id="cc-sfx-demolition" preload="metadata" src="audio/sounds/demolition.mp3"></audio>
	<audio id="cc-sfx-flamethrower" preload="metadata" src="audio/sounds/flamethrower.mp3"></audio>
	<audio id="cc-sfx-artillery" preload="metadata" src="audio/sounds/artillery.mp3"></audio>
	<audio id="cc-sfx-laststand" preload="metadata" src="audio/sounds/laststand.mp3"></audio>
	<audio id="cc-sfx-death" preload="metadata" src="audio/sounds/death.mp3"></audio>
	<audio id="cc-sfx-victory" preload="metadata" src="audio/sounds/victory.mp3"></audio>
</div>
<script>
(function() {
	var gameArea = document.getElementById('cc-game-area');
	// Permanent, hidden sibling of gameArea -- see the markup comment where
	// it's declared. A win/loss result gets dropped in here and revealed
	// with a synchronous style.display flip instead of an innerHTML swap
	// into gameArea itself, so "Delve Again" and "Weekly Leaderboard" end
	// up outside gameArea's own DOM subtree -- the delegated submit
	// listener further down only intercepts forms gameArea.contains(),
	// so clicking either is deliberately just a normal, uninterrupted
	// link/POST, not something this script touches at all.
	var resultOverlay = document.getElementById('cc-result-overlay');
	// Assigned once the audio player sets itself up (below) -- exposed here
	// so initGameArea() can call it again after every AJAX swap, since the
	// server recomputes #cc-mood fresh on every render.
	var syncMood = null;
	// Assigned once the audio player sets itself up (below) -- reconciles
	// the PERMANENT #cc-theme-bg element against whatever #cc-mood's
	// data-theme-* says this render's theme state is (see the markup right
	// after .cc-wrap opens, and MAINTENANCE.md, for why it's permanent:
	// letting an AJAX swap destroy/recreate it was restarting its Ken Burns
	// animation on every single action instead of only when the actual
	// scene changed).
	var applyThemeState = null;

	// Sizing only -- #cc-theme-bg's active/inactive state and image are
	// applyThemeState()'s job (called from initGameArea() below, once per
	// render), not this. One resize listener, attached once (not re-added
	// per swap, which would stack up a fresh listener per action).
	function sizeTheme() {
		var el = document.getElementById('cc-theme-bg');
		if (!el) return;
		if (!el.classList.contains('cc-theme-active')) {
			el.style.height = ''; // bare mode -- size naturally, no forced viewport-filling height
			return;
		}
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

	// Flee/medkit/Last Stand pop-ups toggle (🔔 on the audio player). Lives
	// here, not inside the audio-setup IIFE below, since sessionStorage
	// access needs nothing from that closure and initGameArea() needs it
	// directly. The categories suppressible this way are tagged server-side
	// (cryptcrawlFlash()'s $source param, see cryptcrawl-actions.php) and
	// come through as data-source on each .cc-flash-modal -- anything
	// untagged (e.g. Abandon Run's confirmation) is never suppressed.
	var SUPPRESSIBLE_FLASH_SOURCES = ['flee', 'medkit', 'laststand'];
	function getFlowNotifsEnabled() {
		var v = sessionStorage.getItem('cc_flow_notifs_enabled');
		return v === null ? true : v === '1'; // on by default -- opt out, not opt in
	}
	function setFlowNotifsEnabled(v) { try { sessionStorage.setItem('cc_flow_notifs_enabled', v ? '1' : '0'); } catch (e) {} }

	// ── CARD-ACTION SFX ──────────────────────────────────────────────
	// Same architecture as Crypt Conquest's own SFX block in
	// cryptconquest.php (see that file's comments for the fuller
	// rationale) -- ported here as-is rather than reinvented, so both
	// games' sound systems stay maintainable as one mental model. cc_-
	// prefixed sessionStorage keys, matching this page's own audio player
	// above (not cq_ -- the two games' state has always been kept apart so
	// muting one never silences the other).
	//
	// Effects play at up to 2x the slider's music level (clamped to 1.0),
	// same reasoning as Conquest: a short action sound needs to cut through
	// a sustained music bed to read at the same loudness, and at the
	// default slider position (50) that 2x already saturates to the
	// digital ceiling. Per-sound LEVEL then scales down from THAT ceiling.
	//
	// Every LEVEL value below was set from measured integrated loudness
	// (LUFS) against the actual music track these sounds play over, not
	// from file RMS or by ear -- RMS was tried first on Conquest's own
	// exact-match cue and shipped it audibly louder than the music twice
	// in a row before being caught. Two tiers:
	//   "impact"  target -4.6 LU under the music -- matches Conquest's own
	//             card/kill reference level, for the sounds a player hears
	//             on nearly every action: fist, kill (shared), and the
	//             faster weapons (melee/katana/pistol/sniper/machine gun).
	//   "utility/long" target -8.5 LU under -- matches Conquest's own
	//             exactmatch tier, for equip/heal/flee (secondary feedback, not
	//             combat impact) and the slow, sustained explosive weapons
	//             (grenade/demolition/flamethrower/rocket launcher/
	//             artillery) -- a 9-second artillery boom at the SAME
	//             level as a 0.9s punch would dominate the mix simply by
	//             lasting longer, the same mistake the impact tier exists
	//             to avoid.
	// laststand/death/victory reuse Conquest's own already-measured levels
	// verbatim -- same mp3 files, same music tracks, so the same numbers
	// are still correct; no need to remeasure.
	var SFX_GAIN = 2;
	var SFX_LEVEL = {
		fist: 0.548, kill: 0.17,
		melee: 0.275, tacticalkatana: 0.162, pistol: 0.109,
		sniperrifle: 0.524, machinegun: 0.073,
		equip: 0.095, heal: 0.388, flee: 0.446,
		grenade: 0.083, demolition: 0.148, rocketlauncher: 0.204,
		artillery: 0.080, flamethrower: 0.090,
		laststand: 0.5, death: 0.15, victory: 0.4
	};
	// Long, narrative "alert" cues only -- mutually exclusive so Last
	// Stand, a death and a victory can never talk over each other. The big
	// explosive WEAPON sounds are deliberately NOT in this set even though
	// several run just as long: they're the direct result of the player's
	// own click and should be free to overlap with a laststand/death/
	// victory cue that lands on the very same action (e.g. an artillery
	// shot that also triggers Last Stand is exactly the high-stakes
	// moment where both sounds landing together is right, not a bug).
	var SFX_STINGERS = { laststand: 1, death: 1, victory: 1 };
	var ccCurrentStinger = null;
	function sfxVolume() {
		var vol = parseInt(sessionStorage.getItem('cc_audio_volume'), 10);
		if (!(vol >= 0 && vol <= 100)) vol = 50;
		return Math.min(1, (vol / 100) * SFX_GAIN);
	}
	function sfxVolumeFor(name) {
		var lvl = SFX_LEVEL[name];
		return sfxVolume() * (lvl === undefined ? 1 : lvl);
	}
	function playNamedSfx(name) {
		var el = document.getElementById('cc-sfx-' + name);
		if (!el) return;
		var vol = sfxVolumeFor(name);
		if (vol === 0) return;
		try {
			if (SFX_STINGERS[name]) {
				if (ccCurrentStinger && ccCurrentStinger !== el) {
					ccCurrentStinger.pause();
					ccCurrentStinger.currentTime = 0;
				}
				ccCurrentStinger = el;
			}
			el.volume = Math.min(1, vol);
			el.currentTime = 0;
			// Chrome rejects this promise with no user gesture yet; it's a
			// sound effect, so a silent failure is the right outcome.
			var p = el.play();
			if (p && p.catch) p.catch(function() {});
		} catch (e) {}
	}
	// Fired from the submit handler at CLICK time, reading data-sfx off
	// whichever button was actually pressed (see e.submitter there) -- a
	// space-separated list, since e.g. "Use Weapon" queues both the
	// weapon-specific sound and the shared 'kill' cue.
	function playClickSfx(names) {
		if (!names) return;
		var list = names.split(/\s+/);
		for (var i = 0; i < list.length; i++) if (list[i]) playNamedSfx(list[i]);
	}
	// Sounds the SERVER queued for this render, read off #cc-mood -- see
	// cryptcrawlRenderGameArea()'s own data-sfx in cryptcrawl-render.php.
	// Only Last Stand and death/victory arrive this way; everything else
	// is click-fired above because Crypt Crawl's actions (unlike
	// Conquest's multi-card combos) are each a single deterministic click.
	function playMoodSfx() {
		var el = document.getElementById('cc-mood');
		if (!el) return;
		var names = (el.getAttribute('data-sfx') || '').split(/\s+/);
		for (var i = 0; i < names.length; i++) if (names[i]) playNamedSfx(names[i]);
	}

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
		if (flashBackdrop && !getFlowNotifsEnabled()) {
			flashBackdrop.querySelectorAll('.cc-flash-modal').forEach(function(modal) {
				if (SUPPRESSIBLE_FLASH_SOURCES.indexOf(modal.getAttribute('data-source')) !== -1) modal.remove();
			});
			// Only remove the whole backdrop once every modal it held was
			// suppressed -- a request could in principle queue an untagged
			// flash (Abandon Run) alongside a suppressible one; that one
			// should still show.
			if (!flashBackdrop.querySelector('.cc-flash-modal')) {
				flashBackdrop.remove();
				flashBackdrop = null;
			}
		}
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

		// Reconciles #cc-theme-bg's active/inactive state + image against
		// this render's #cc-mood, then sizes it. applyThemeState() is null
		// on the very first call (this whole function runs once before the
		// audio player -- and applyThemeState with it -- has set itself up
		// further down); sizeTheme() alone still keeps it sized correctly
		// for the "bare" case, and the audio setup re-applies the real
		// theme state for the initial page once it's ready (see below).
		if (applyThemeState) applyThemeState(); else sizeTheme();

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

		// Re-check the situational music track (Frantic/Doom/Death/Triumph
		// vs. the normal loop) against whatever #cc-mood the server just
		// rendered. null on the very first call (page load) -- the audio
		// player checks its own initial mood itself once it sets up, just
		// below this function in source order.
		if (syncMood) syncMood();
		// Runs on every render this function handles -- initial load, every
		// in-place swap, and the result-overlay swap (see initGameArea()'s
		// own two call sites below) -- exactly the renders where #cc-mood's
		// data-sfx can carry something new. Safe on a plain page load too:
		// there's no user gesture yet, so play() rejects silently (see
		// playNamedSfx()'s own try/catch).
		playMoodSfx();
	}

	initGameArea();

	// Locks #cc-game-area against interaction for a beat, then releases it.
	// Shared by every path that can land a visitor on a fresh render of
	// this page -- the AJAX swap, and (below) a plain page load/reload --
	// so a rapid, repeated tap can never land on whatever's newly
	// interactive (most importantly "Delve Again") before there's been any
	// real chance to see what just rendered. 400ms comfortably covers a
	// fast repeated-tap gesture without being noticeable on a single
	// deliberate action. `busy` itself is declared below (hoisted -- this
	// function's body only ever runs later, inside a callback, by which
	// point that declaration has always already executed).
	function lockGameAreaBriefly() {
		gameArea.style.opacity = '0.55';
		gameArea.style.pointerEvents = 'none';
		setTimeout(function() {
			gameArea.style.opacity = '';
			gameArea.style.pointerEvents = '';
			busy = false;
		}, 400);
	}

	// A fresh load of this exact page landing directly on a game-ending
	// result needs the exact same protection the AJAX path gets -- added
	// 2026-08-30 after the AJAX path's own game-ending reload (below)
	// turned out to *remove* the only guard that used to exist for this
	// moment instead of strengthening it: a freshly loaded page has no
	// cooldown applied to it at all by default, so a rapid/repeated tap
	// (fighting fast enough to trigger that reload in the first place)
	// could land immediately on "Delve Again" the instant the reloaded
	// page finished loading, completely unguarded. Also covers the plainer
	// cases of a manual refresh or the no-JS POST->redirect fallback
	// landing here directly.
	if (gameArea && gameArea.querySelector('.cc-result')) lockGameAreaBriefly();

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
		// Fired here, not on the response: the sound belongs to the click
		// that actually resolved the card (Fist Fight/Use Weapon/Equip/
		// Heal/Flee are each a single deterministic action, so the button
		// pressed is already enough to know the outcome), and firing at
		// click time keeps it inside the user-gesture window autoplay
		// policy requires. e.submitter is the actual button that triggered
		// this submit -- Abandon Run carries no data-sfx, so it stays silent.
		if (e.submitter) playClickSfx(e.submitter.getAttribute('data-sfx'));
		gameArea.style.opacity = '0.55';
		gameArea.style.pointerEvents = 'none';
		fetch('ajax/cryptcrawl-action.php', { method: 'POST', body: new FormData(form) })
			.then(function(res) {
				if (!res.ok) throw new Error('bad response');
				return res.text();
			})
			.then(function(html) {
				// A game-ending result (win or loss) reveals a permanent,
				// pre-existing hidden panel instead of swapping into
				// gameArea -- rebuilt 2026-08-30, replacing two earlier
				// attempts that both relied on gameArea's own AJAX-swap
				// machinery in one form or another (an in-place swap with a
				// timed lock, then a forced page navigation) to make this
				// one transition reliable. Both were still fundamentally
				// "wait for some network/timing-dependent step to resolve
				// correctly" -- the navigation version in particular turned
				// out to have a real failure mode (some engines resubmitting
				// stale POST data on reload() depending on how the page was
				// originally reached). This has none of that: the result
				// HTML the server already rendered gets dropped into
				// resultOverlay (a synchronous, always-succeeds DOM write),
				// then revealing it is a single synchronous style.display
				// flip -- nothing left that depends on a network round trip,
				// a navigation, or a timing window to actually show up.
				// "Delve Again"/"Weekly Leaderboard" inside it are outside
				// gameArea's own subtree on purpose (see resultOverlay's
				// declaration above) -- clicking either is a perfectly
				// ordinary link/POST, not something this handler is
				// involved in at all, so there's no interaction race to
				// guard against here the way ordinary in-place actions
				// still need lockGameAreaBriefly() for. Also fixes the one
				// cost the navigation-based version accepted on purpose:
				// since there's no real navigation at all now, the <audio>
				// elements (permanent siblings of gameArea, never touched by
				// this) are never torn down -- #cc-mood's data-theme-*/
				// data-mood on the fresh content still drive
				// applyThemeState()/syncMood() same as any other render, and
				// now that means a real crossfade into Death/Triumph, the
				// same smooth transition every other mood change already
				// gets, instead of a hard restart.
				if (html.indexOf('class="cc-result ') !== -1 && resultOverlay) {
					resultOverlay.innerHTML = html;
					// Empty gameArea, not just hide it -- fixed 2026-08-30,
					// reported directly by the user (Doom still playing on a
					// loss instead of Death). Hiding alone left the old
					// #cc-mood (and everything else) still sitting in the
					// DOM inside the now-invisible gameArea -- a real bug,
					// not cosmetic: with two #cc-mood elements briefly
					// existing at once (the stale one in gameArea, the fresh
					// one in resultOverlay), document.getElementById('cc-mood')
					// returns whichever comes first in document order, which
					// was the stale one, so syncMood()/applyThemeState() were
					// reading the leftover in-delve mood/theme instead of the
					// result's own.
					gameArea.innerHTML = '';
					gameArea.style.display = 'none';
					resultOverlay.style.display = '';
					// TAP-GUARD THE RESULT SCREEN. This is the actual cause of
					// the long-running "loss screen doesn't show" bug --
					// reproduced live 2026-08-31, see
					// cryptcrawl-loss-screen-bug.md.
					//
					// Every ORDINARY action ends in lockGameAreaBriefly(), which
					// makes the freshly-rendered board untappable for 400ms so a
					// rapid repeated tap can't land on whatever just appeared
					// under the player's finger. The game-ending path returns
					// early (below) and never called it -- so the single most
					// important screen in the game was the ONLY one with no tap
					// guard at all. lockGameAreaBriefly() wouldn't have helped
					// here either: it locks gameArea, which by this point is
					// empty and display:none, while the result (and its "Delve
					// Again" button) lives in resultOverlay.
					//
					// "Delve Again" is deliberately outside gameArea so the
					// delegated submit listener ignores it (see resultOverlay's
					// declaration) -- which means it is a raw, real POST
					// navigation that immediately starts a NEW delve. A tap
					// already in flight from fighting fast (exactly how a player
					// dies) landed on it the instant the overlay appeared: the
					// browser navigated, the loss screen was destroyed before it
					// could be read, and the player landed in a fresh HP 20/20
					// run. Confirmed live: after a failed-to-show loss, the page
					// had navCount=1 (a real document load) with HP 20/20 and 0
					// crypts cleared -- an unmistakable start_run, not a render
					// failure. That is why every server-side fix in this saga
					// changed nothing: the server was always sending the right
					// HTML, and the client was throwing it away.
					//
					// 700ms rather than lockGameAreaBriefly()'s 400ms: this is
					// the highest-stakes moment in the game and the action being
					// guarded discards the result outright. Imperceptible on a
					// deliberate click, decisive against an in-flight tap.
					resultOverlay.style.pointerEvents = 'none';
					setTimeout(function() { resultOverlay.style.pointerEvents = ''; }, 700);
					// The win/loss screen is genuinely on screen now -- vanilla
					// synchronous DOM writes above, nothing left to wait on for
					// that part. CARBON payout + the Discord announce happen in
					// a completely separate, fire-and-forget request fired only
					// from here, never before -- ajax/cryptcrawl-action.php
					// (the request that just resolved, above) no longer does
					// any of that itself. See ajax/cryptcrawl-finalize.php's
					// own comment for the full story: three earlier attempts at
					// keeping this in the same request/response all failed to
					// actually get the result to the browser first in
					// production. Not awaited on purpose -- the player never
					// needs to wait on this succeeding, and doesn't need to
					// know if it fails (server-side error_log covers that).
					var resultEl = resultOverlay.querySelector('.cc-result');
					var runId = resultEl ? resultEl.getAttribute('data-run-id') : null;
					if (runId && runId !== '0') {
						// keepalive: true -- this request must survive even if
						// the player closes the tab/app right after seeing the
						// result (the whole reason this is fire-and-forget in
						// the first place). Without it, a browser can abort an
						// in-flight fetch on page unload.
						fetch('ajax/cryptcrawl-finalize.php', { method: 'POST', body: new URLSearchParams({ run_id: runId }), keepalive: true }).catch(function() {});
					}
					initGameArea();
					busy = false;
					return;
				}
				gameArea.innerHTML = html;
				initGameArea();
				// Ordinary (non-game-ending) action -- same lock, shared with
				// the fresh-page-load case above.
				lockGameAreaBriefly();
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
	// which track, playback position, volume, Ken Burns on/off) lives in
	// sessionStorage so it survives a fresh page load/reload -- continuity
	// *within* a session no longer depends on this now that actions are
	// AJAX (the <audio> element itself just never gets destroyed between
	// actions), but a manual refresh or a brand-new tab still needs it.
	(function() {
		// Two <audio> elements taking turns being "active" (see crossfadeTo()
		// below) so a track change can briefly overlap the outgoing and
		// incoming track instead of a hard cut -- especially noticeable
		// flapping in and out of Frantic, per the user.
		var players = [document.getElementById('cc-audio-el-a'), document.getElementById('cc-audio-el-b')];
		var activeIdx = 0;
		function active() { return players[activeIdx]; }
		function inactive() { return players[1 - activeIdx]; }

		var toggleBtn = document.getElementById('cc-audio-toggle');
		var prevBtn = document.getElementById('cc-audio-prev');
		var nextBtn = document.getElementById('cc-audio-next');
		var trackNameEl = document.getElementById('cc-audio-track-name');
		var trackNameFullEl = trackNameEl ? trackNameEl.querySelector('.cc-audio-track-full') : null;
		var trackNameShortEl = trackNameEl ? trackNameEl.querySelector('.cc-audio-track-short') : null;
		var volumeEl = document.getElementById('cc-audio-volume');
		var zoomToggleBtn = document.getElementById('cc-audio-zoom-toggle');
		var notifToggleBtn = document.getElementById('cc-audio-notif-toggle');
		if (!players[0] || !players[1] || !toggleBtn) return;
		// Keeps both spans in the track-name element in sync -- CSS (the
		// @media max-width:700px block) decides which one's actually
		// visible, so this doesn't need to know or care about viewport
		// width itself. Last word of "Crypt Crawl X" is always the
		// distinctive part (Theme/Reprise/Frantic/Doom/Death/Triumph).
		function setTrackName(name) {
			if (trackNameFullEl) trackNameFullEl.textContent = name;
			if (trackNameShortEl) {
				var words = name.trim().split(/\s+/);
				trackNameShortEl.textContent = words[words.length - 1];
			}
		}

		var TRACKS = [
			{ name: 'Crypt Crawl Theme', src: 'audio/tracks/Crypt%20Crawl%20Theme.mp3' },
			{ name: 'Crypt Crawl Reprise', src: 'audio/tracks/Crypt%20Crawl%20Reprise.mp3' }
		];
		// Situational tracks the game itself cues up (see #cc-mood, set by
		// cryptcrawlRenderGameArea() in cryptcrawl-render.php) -- deliberately
		// NOT part of TRACKS above, so prev/next can never cycle to them.
		// The only way to hear Triumph is to actually win.
		var MOOD_TRACKS = {
			frantic: { name: 'Crypt Crawl Frantic', src: 'audio/tracks/Crypt%20Crawl%20Frantic.mp3', loop: true },
			doom:    { name: 'Crypt Crawl Doom',    src: 'audio/tracks/Crypt%20Crawl%20Doom.mp3',    loop: true },
			death:   { name: 'Crypt Crawl Death',   src: 'audio/tracks/Crypt%20Crawl%20Death.mp3',   loop: false },
			triumph: { name: 'Crypt Crawl Triumph', src: 'audio/tracks/Crypt%20Crawl%20Triumph.mp3', loop: false }
		};
		var currentMood = 'normal';
		var FADE_MS = 1200; // long enough to actually read as a crossfade, short enough not to feel sluggish on a deliberate skip

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
		function getZoomEnabled() {
			var v = sessionStorage.getItem('cc_zoom_enabled');
			return v === null ? true : v === '1'; // on by default -- noticed once, then a deliberate choice either way
		}
		function setZoomEnabled(v) { try { sessionStorage.setItem('cc_zoom_enabled', v ? '1' : '0'); } catch (e) {} }

		var trackIndex = getTrackIndex();
		var enabled = getEnabled();
		var targetVolume = getVolume() / 100; // the user's actual volume setting -- what a fade ramps TOWARD, not necessarily what's playing right now mid-fade

		players[0].volume = targetVolume;
		players[1].volume = 0; // inactive by default; only ever raised by a crossfade
		if (volumeEl) volumeEl.value = getVolume();
		if (zoomToggleBtn) {
			zoomToggleBtn.classList.toggle('off', !getZoomEnabled());
			zoomToggleBtn.title = 'Background zoom: ' + (getZoomEnabled() ? 'on' : 'off');
		}
		if (notifToggleBtn) {
			notifToggleBtn.classList.toggle('off', !getFlowNotifsEnabled());
			notifToggleBtn.title = 'Flee/medkit/Last Stand pop-ups: ' + (getFlowNotifsEnabled() ? 'on' : 'off');
		}

		// Ken Burns drift on the theme art -- max ambience while a track is
		// actually audible, so it's tied to play/pause as well as the
		// on/off toggle below. See @keyframes ccKenBurns (CSS) for how the
		// --kb-* custom properties set here get played back, and .cc-zoom's
		// animation-direction:alternate for why it loops seamlessly rather
		// than snapping at the end of each pass.
		function updateZoomClass() {
			var el = document.getElementById('cc-theme-bg');
			if (el) el.classList.toggle('cc-zoom', getZoomEnabled() && !active().paused);
		}
		function randomizeKenBurns(el) {
			var scaleFrom = 1 + Math.random() * 0.04;   // 1.00 - 1.04
			var scaleTo = 1.08 + Math.random() * 0.08;  // 1.08 - 1.16
			// Pan between two OPPOSITE points around center (not center -> a
			// corner) so it reads as a continuous drift across the image,
			// not a zoom that jerks toward one corner. Angle is fully
			// random -- that's what makes the direction unpredictable.
			var angle = Math.random() * Math.PI * 2;
			var dist = 1.5 + Math.random() * 2; // 1.5% - 3.5%, safely inside the pseudo's 5% inset buffer
			var xFrom = (Math.cos(angle) * dist).toFixed(2) + '%';
			var yFrom = (Math.sin(angle) * dist).toFixed(2) + '%';
			var xTo = (Math.cos(angle + Math.PI) * dist).toFixed(2) + '%';
			var yTo = (Math.sin(angle + Math.PI) * dist).toFixed(2) + '%';
			var duration = (20 + Math.random() * 14).toFixed(1) + 's'; // 20s - 34s, varies pace too
			el.style.setProperty('--kb-scale-from', scaleFrom.toFixed(3));
			el.style.setProperty('--kb-scale-to', scaleTo.toFixed(3));
			el.style.setProperty('--kb-x-from', xFrom);
			el.style.setProperty('--kb-y-from', yFrom);
			el.style.setProperty('--kb-x-to', xTo);
			el.style.setProperty('--kb-y-to', yTo);
			el.style.setProperty('--kb-duration', duration);
		}
		// Exposed to initGameArea() (outer scope), called once per render.
		// #cc-theme-bg is PERMANENT now (never destroyed by an AJAX swap --
		// see the markup and MAINTENANCE.md), so re-randomizing on every
		// single call would be wrong the same way the old kbInit-per-swap
		// approach was: it'd restart the drift on every card played, not
		// just when the scene actually changed. Comparing the incoming
		// image against what's already applied (data-current-img) is what
		// actually fixes that -- same image, same running animation,
		// completely untouched; different image, fresh random direction.
		applyThemeState = function() {
			var themeBg = document.getElementById('cc-theme-bg');
			var moodEl = document.getElementById('cc-mood');
			if (!themeBg || !moodEl) return;
			var active = moodEl.getAttribute('data-theme-active') === '1';
			var img = moodEl.getAttribute('data-theme-img') || '';
			themeBg.classList.toggle('cc-theme-active', active);
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
		// (this whole setup runs after that first call, in source order) --
		// run it again now so the initial page's own theme state (if any)
		// actually gets applied instead of only ever picking one up
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
		if (notifToggleBtn) {
			notifToggleBtn.addEventListener('click', function() {
				var next = !getFlowNotifsEnabled();
				setFlowNotifsEnabled(next);
				notifToggleBtn.classList.toggle('off', !next);
				notifToggleBtn.title = 'Flee/medkit/Last Stand pop-ups: ' + (next ? 'on' : 'off');
				// Retroactively suppress/restore-visibility isn't attempted
				// here -- a currently-open flash modal (if any) just plays
				// out its own dismiss timer as normal; this only changes
				// what shows up starting with the *next* one.
			});
		}

		function updateToggleIcon() {
			toggleBtn.textContent = (!active().paused) ? '⏸' : '▶';
			updateZoomClass();
		}

		// Hard cut, no fade -- for the initial page load (nothing playing
		// yet to fade from) and manual prev/next (a deliberate skip should
		// feel instant, not crossfade into it). Only ever touches active();
		// inactive() is reset to silence too, in case it was mid-fade from
		// something the user just cut across.
		function loadTrack(index, resumePosition) {
			stopFade();
			currentMood = 'normal'; // back to the regular loop -- also resets any stale loop flag a mood track left set
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
				// Autoplay blocked -- normal on a fresh visit with no prior
				// interaction yet. Leave it paused/ready; the toggle button
				// (or any click that lands after this) will work.
				p.catch(function() { updateToggleIcon(); });
			}
		}

		var fadeRAF = null;
		function stopFade() { if (fadeRAF) { cancelAnimationFrame(fadeRAF); fadeRAF = null; } }

		// Crossfades to src -- for transitions the GAME forces on its own
		// (a mood change, a fresh delve forcing the Theme, the normal loop's
		// own advance to its next track) so flapping in and out of Frantic
		// (say) doesn't hard-cut the music every time, per the user. Manual
		// prev/next deliberately does NOT go through this -- see loadTrack().
		// If audio is currently off there's nothing audible to fade, so this
		// just silently points the active player at the new track instead
		// (still needed so turning audio back on later resumes the right
		// thing, not something stale).
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
			activeIdx = 1 - activeIdx; // incoming is the new "active" immediately, even mid-fade-in

			var p = incoming.play();
			if (p && p.catch) p.catch(function() { updateToggleIcon(); });

			var startOutVol = outgoing.volume;
			var startTs = null;
			// requestAnimationFrame always supplies a real timestamp to its
			// callback -- calling step() directly (no rAF) would invoke it
			// with ts=undefined on the first frame, making startTs itself
			// undefined and every volume computation NaN (which .volume
			// rejects outright, throwing). Always go through rAF, including
			// for this first frame.
			function step(ts) {
				if (startTs === null) startTs = ts;
				var t = Math.min(1, (ts - startTs) / FADE_MS);
				incoming.volume = targetVolume * t;
				outgoing.volume = startOutVol * (1 - t);
				if (t < 1) {
					fadeRAF = requestAnimationFrame(step);
				} else {
					outgoing.pause();
					outgoing.volume = targetVolume; // resting state for next time this element gets reused
					fadeRAF = null;
				}
			}
			fadeRAF = requestAnimationFrame(step);
			updateToggleIcon();
		}

		// Proactively crossfades a non-looping track to whatever comes next
		// slightly BEFORE it actually finishes -- waiting for 'ended' would
		// mean there's nothing left to fade FROM (it's already silent by
		// then). Frantic/Doom loop natively (audio.loop = true) so they
		// never reach this. 'ended' below is still wired as a safety net in
		// case duration is ever unavailable for some reason.
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
			// Browsers only allow unmuted autoplay off the back of a *trusted*
			// user gesture -- nothing in JS can fake that (see Skull Paper /
			// MAINTENANCE.md for why). If the tryPlay() above got blocked,
			// catch the very first real interaction anywhere on the page --
			// not just a tap on this player's own button -- and use it to
			// start audio too, since a player's first move is usually
			// something else entirely (Start Delve, playing a card).
			var playerEl = document.getElementById('cc-audio-player');
			var unlockEvents = ['pointerdown', 'keydown', 'touchstart'];
			var unlockAudio = function(e) {
				unlockEvents.forEach(function(evt) { window.removeEventListener(evt, unlockAudio, true); });
				// Skip if the gesture landed on the player's own controls --
				// their click handlers already start/stop playback correctly
				// on a genuine trusted click, and firing tryPlay() here too
				// raced with them: clicking Play itself started playback on
				// 'pointerdown' (audio.paused flips to false synchronously
				// inside .play()), then the toggle button's own 'click'
				// handler -- now seeing paused already false -- immediately
				// paused it right back again, thinking it was already
				// playing. Every other first-interaction spot (Start Delve,
				// a card, anywhere outside the player) still unlocks here as
				// before.
				if (playerEl && e && e.target && playerEl.contains(e.target)) return;
				if (active().paused && getEnabled()) tryPlay();
			};
			unlockEvents.forEach(function(evt) { window.addEventListener(evt, unlockAudio, true); });
		}
		updateToggleIcon();

		players.forEach(function(p) {
			// Only track position for the normal loop -- while a mood track
			// is playing, its currentTime has nothing to do with where the
			// normal loop should resume, and would corrupt that saved spot.
			// Guarded to the currently-active player so a still-fading-out
			// outgoing player's own timeupdate doesn't fight over state.
			p.addEventListener('timeupdate', function() {
				if (this !== active()) return;
				if (currentMood === 'normal') setPosition(this.currentTime);
				maybeAdvanceNearEnd(this);
			});
			p.addEventListener('play', function() { if (this === active()) updateToggleIcon(); });
			p.addEventListener('pause', function() { if (this === active()) updateToggleIcon(); });
			p.addEventListener('ended', function() {
				if (this !== active()) return;
				// Safety net -- maybeAdvanceNearEnd() should already have
				// crossfaded to the next track slightly before this could
				// ever fire. Hard cut here (nothing left to fade from
				// anyway, it's already silent) rather than a crossfade.
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
				// A mood track that hasn't been generated/uploaded yet (or
				// any other load failure) shouldn't leave the player stuck
				// on dead, silent audio -- fall back to the normal loop.
				// Guarded so a genuine failure loading a *normal* track
				// can't retry forever.
				if (currentMood !== 'normal') {
					currentMood = 'normal';
					loadTrack(getTrackIndex(), true);
					if (getEnabled()) tryPlay();
				}
			});
		});

		// Cued automatically by the server (#cc-mood, set in
		// cryptcrawlRenderGameArea()) -- never reachable via prev/next, so a
		// player can't skip straight to Triumph without actually winning.
		// Always crossfades (see crossfadeTo()) -- every path here is the
		// game forcing a transition, never a manual pick.
		syncMood = function() {
			var moodEl = document.getElementById('cc-mood');
			var mood = moodEl ? moodEl.getAttribute('data-mood') : 'normal';
			// A fresh delve (Start Delve / Delve Again) always opens on the
			// Theme specifically -- checked first, unconditionally, so it
			// wins over both the escape-from-danger case below and even a
			// same-mood no-op (restarting while already on the Theme should
			// still restart it from 0:00, not leave it mid-track).
			if (moodEl && moodEl.getAttribute('data-restarted') === '1') {
				currentMood = 'normal';
				crossfadeTo(TRACKS[0].src, { name: TRACKS[0].name });
				return;
			}
			if (!mood || mood === currentMood) return; // no change -- don't interrupt what's already playing
			if (mood === 'normal') {
				if (currentMood === 'frantic' || currentMood === 'doom') {
					// Pulled out of danger (healed up, geared up -- survived
					// without the delve actually ending) -- that should feel
					// like picking back up, not restarting the intro theme
					// from scratch. Land on the Reprise specifically
					// (TRACKS[1]) rather than whatever the normal loop's
					// last-saved track happened to be.
					currentMood = 'normal';
					crossfadeTo(TRACKS[1].src, { name: TRACKS[1].name });
				} else {
					currentMood = 'normal';
					var idx = getTrackIndex();
					crossfadeTo(TRACKS[idx].src, { name: TRACKS[idx].name, resumeAt: getPosition() }); // ordinary resume
				}
				return;
			}
			var special = MOOD_TRACKS[mood];
			if (!special) return; // unrecognized value -- ignore rather than break
			currentMood = mood;
			crossfadeTo(special.src, { name: special.name, loop: special.loop });
		};
		syncMood(); // handle whatever mood the very first render already has

		// Manual controls -- hard cuts via loadTrack(), deliberately NOT
		// crossfaded (see loadTrack()'s own comment): a deliberate skip
		// should feel instant, not fade into place.
		toggleBtn.addEventListener('click', function() {
			if (active().paused) { setEnabled(true); tryPlay(); }
			else {
				// Also stop+silence the inactive player -- if this lands
				// mid-crossfade, its own fade-out would otherwise keep
				// audibly playing (just getting quieter) for up to FADE_MS
				// after the user asked for silence right now.
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
				if (!fadeRAF) active().volume = targetVolume; // mid-fade, the fade loop itself reads targetVolume fresh each frame -- don't stomp on it directly
				setVolume(v);
			});
		}
	})();
})();
</script>
