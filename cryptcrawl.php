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

// Unlike most guest-playable pages here, Crypt Crawl needs a real working
// session even for a brand-new anonymous visitor: it's a full page-reload
// per action (not a client-side JS game with an occasional AJAX save), so
// a guest's flash messages and run state have nowhere to live between
// clicks without one. db.php only starts a session when a cookie already
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
function cryptcrawlFlash($msg, $type = 'info') {
	$_SESSION['cryptcrawl_flash'][] = ['msg' => $msg, 'type' => $type];
}

// ── POST action handling — must run before any output ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'start_run') {
		cryptcrawlStartRun($conn, $user_id);

	} elseif ($action === 'play_card') {
		$run = cryptcrawlGetActiveRun($conn, $user_id);
		if ($run) {
			$card_index = intval($_POST['card_index'] ?? -1);
			$use_weapon = isset($_POST['use_weapon']) && $_POST['use_weapon'] === '1';
			// Detect a diminished heal before playing it, so we can flash a
			// clear message — the small note under the Heal button wasn't
			// loud enough on its own (a player used two medkits back to back
			// and didn't notice the second one healed for less).
			$room_before = json_decode($run['room'], true) ?: [];
			$card_before = $room_before[$card_index] ?? null;
			$diminished_potion = $card_before && $card_before['type'] === 'potion' && intval($run['potion_used_this_room']) === 1;
			$second_wind_was_available = intval($run['second_wind_used'] ?? 0) === 0;
			// No flash for a run-ending outcome — the game_over screen's own
			// result panel says the same thing, better, and having both was
			// redundant (two near-identical sentences stacked on load).
			$updated = cryptcrawlPlayCard($conn, intval($run['id']), $card_index, $use_weapon);
			if ($diminished_potion) {
				$half_heal = max(1, intval(intval($card_before['rank']) / 2));
				cryptcrawlFlash("Half effect - you've already used a medkit this crypt. (+$half_heal HP)", 'info');
			}
			// Second Wind fired this exact play if it was available going in
			// and is now spent -- the only place that flag ever changes.
			if ($second_wind_was_available && $updated && intval($updated['second_wind_used']) === 1) {
				cryptcrawlFlash('SECOND WIND! You refuse to fall - surviving at 1 HP. (once per delve)', 'win');
			}
		}

	} elseif ($action === 'flee') {
		$run = cryptcrawlGetActiveRun($conn, $user_id);
		if ($run) {
			$before = json_decode($run['room'], true) ?: [];
			$updated = cryptcrawlFleeRoom($conn, intval($run['id']));
			if ($updated && intval($updated['fled_last_room']) === 1 && count($before) === 4) {
				cryptcrawlFlash('You slipped past that crypt.', 'info');
			} else {
				cryptcrawlFlash("Can't flee twice in a row - face the crypt.", 'error');
			}
		}

	} elseif ($action === 'abandon') {
		cryptcrawlAbandonRun($conn, $user_id);
		cryptcrawlFlash('Run abandoned.', 'info');
	}

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

$active_run = cryptcrawlGetActiveRun($conn, $user_id);
$recent_run = $active_run ? null : cryptcrawlGetMostRecentRun($conn, $user_id);

if ($active_run)                                              $state = 'active';
elseif ($recent_run && in_array($recent_run['status'], ['won', 'lost'], true)) $state = 'game_over';
else                                                           $state = 'no_run';

$flashes = $_SESSION['cryptcrawl_flash'];
$_SESSION['cryptcrawl_flash'] = [];

$suit_symbol = ['C' => '♣', 'S' => '♠', 'D' => '♦', 'H' => '♥'];
$suit_color  = ['C' => '#c8dce8', 'S' => '#c8dce8', 'D' => '#ff9900', 'H' => '#ff6b6b'];
?>
<style>
/* Card index numerals only — rest of the page stays the site's normal Arial.
   Poppins ExtraBold approximates the bold, slightly-rounded look of a
   classic playing-card corner index (not an exact clone of any specific
   card brand's proprietary face — Google Fonts doesn't host one — but the
   closest properly-licensed match). */
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@800&display=swap');

@keyframes ccCardFlip { from { transform: rotateY(180deg); } to { transform: rotateY(0deg); } }
@keyframes ccCardDeal {
	from { opacity: 0; transform: translate(var(--deal-x, 0), -20px) rotate(var(--deal-r, 0deg)) scale(.88); }
	to   { opacity: 1; transform: translate(0, 0) rotate(0deg) scale(1); }
}
@keyframes ccResultPop { from { opacity: 0; transform: scale(.6); } to { opacity: 1; transform: scale(1); } }
@keyframes ccFlashIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
@keyframes ccPulse { 0%, 100% { box-shadow: 0 0 0 rgba(255,68,68,0); } 50% { box-shadow: 0 0 16px 2px rgba(255,68,68,.65); } }
@keyframes ccBtnSheen { from { transform: translateX(-120%) skewX(-20deg); } to { transform: translateX(220%) skewX(-20deg); } }

.cc-wrap { padding: 20px 16px 60px; }
.cc-inner { max-width: 720px; width: 100%; margin: 0 auto; }
/* Floats over the game instead of sitting in normal document flow at the top
   of the page -- the old in-flow placement pushed the HUD/room down every
   time a flash fired, and stuck around "hanging" there until the next action
   reloaded the page. pointer-events:none on the stack so it never blocks a
   tap on whatever's underneath; each toast still gets its own auto-dismiss
   (see the bottom script block) so it clears itself instead of lingering. */
.cc-flash-stack { position: fixed; top: 14px; left: 50%; transform: translateX(-50%); z-index: 50; display: flex; flex-direction: column; gap: 8px; width: min(92vw, 460px); pointer-events: none; }
.cc-flash { padding: 10px 14px; border-radius: 8px; font-size: 0.85rem; text-align: center; background: rgba(5,12,20,.92); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); box-shadow: 0 8px 24px rgba(0,0,0,.5); animation: ccFlashIn .4s ease both; }
.cc-flash.win  { border: 1px solid rgba(0,200,160,.5); color: #00c8a0; }
.cc-flash.loss { border: 1px solid rgba(255,68,68,.5); color: #ff7070; }
.cc-flash.error{ border: 1px solid rgba(255,68,68,.5); color: #ff7070; }
.cc-flash.info { border: 1px solid rgba(255,255,255,.25); color: #c8dce8; }
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
.cc-weapon-icon { width: 20px; height: 20px; object-fit: contain; flex-shrink: 0; }
.cc-room { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; margin-bottom: 18px; }
/* Dealt into place first, THEN flipped -- two separate animations on two
   separate elements on purpose. This one (position/rotation/opacity) lives
   on .cc-card, the outermost wrapper; the flip (rotateY) lives two levels
   down on .cc-card-flip-inner. Keeping them apart also matters for a reason
   that has nothing to do with looks: the desktop mouse-tilt effect (bottom
   script block) sets an inline transform directly on .cc-card-flip on every
   mousemove, and a CSS animation sharing that same element/property would
   fight it. .cc-card-flip itself stays untouched by either animation. */
.cc-card { text-align: center; animation: ccCardDeal .45s cubic-bezier(.22,.85,.32,1) both; }
.cc-room .cc-card:nth-child(1) { --deal-x: -34px; --deal-r: -7deg; animation-delay: 0s; }
.cc-room .cc-card:nth-child(2) { --deal-x: 34px;  --deal-r: 7deg;  animation-delay: .06s; }
.cc-room .cc-card:nth-child(3) { --deal-x: -34px; --deal-r: -7deg; animation-delay: .12s; }
.cc-room .cc-card:nth-child(4) { --deal-x: 34px;  --deal-r: 7deg;  animation-delay: .18s; }
.cc-card-flip {
	perspective: 1000px; margin-bottom: 10px;
	transition: transform .18s ease-out, box-shadow .25s ease;
}
.cc-card-flip-inner {
	position: relative; aspect-ratio: 5 / 7; transform-style: preserve-3d;
	animation: ccCardFlip .6s cubic-bezier(.3,.9,.4,1) both;
}
/* Delay starts after each card's own deal-in animation has settled, plus a
   deliberate ~.4s hold so the card back (the Skulliance skull icon) is
   clearly visible before it flips, not just a blur mid-motion. */
.cc-room .cc-card:nth-child(1) .cc-card-flip-inner { animation-delay: .85s; }
.cc-room .cc-card:nth-child(2) .cc-card-flip-inner { animation-delay: .92s; }
.cc-room .cc-card:nth-child(3) .cc-card-flip-inner { animation-delay: .99s; }
.cc-room .cc-card:nth-child(4) .cc-card-flip-inner { animation-delay: 1.06s; }
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
.cc-flee-row {
	text-align: center; margin-bottom: 20px;
	background: rgba(5,12,20,.72); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
	border-radius: 12px; padding: 14px; box-sizing: border-box;
}
@media (prefers-reduced-motion: reduce) {
	.cc-card, .cc-card-flip-inner, .cc-card-controls, .cc-flash, .cc-hud, .cc-hp-wrap.low .cc-hp-bar-bg, .cc-btn::after,
	.cc-result-icon, .cc-result-title, .cc-result-sub { animation: none !important; }
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
<div class="cc-inner">
	<?php if ($flashes): ?>
	<div class="cc-flash-stack">
		<?php foreach ($flashes as $f): ?>
			<div class="cc-flash <?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['msg']); ?></div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php if ($state === 'no_run'): ?>
		<div class="cc-rules">
			Delve a 44-card crypt deck alone. <strong style="color:#ff9900;">♦ Diamonds</strong> are weapons -
			equip one and it stays until you use it, degrading so it can only beat weaker enemies after each kill.
			<strong style="color:#ff6b6b;">♥ Hearts</strong> are medkits - the first one you use each crypt heals in
			full, and any more after that in the same crypt still heal, just for half.
			<strong style="color:#c8dce8;">♣♠ Clubs &amp; Spades</strong> are enemies - fight bare-handed and take full
			damage, or spend your weapon and take the difference. Resolve 3 of the 4 cards in a crypt and the 4th carries
			into the next; or flee a fresh crypt once (not twice in a row) to reshuffle it back into the deck. Clear the
			deck to win, or run out of HP and the delve ends - except the first hit that would take you to 0 HP each
			delve instead leaves you standing at 1, <span class="cc-second-wind">Second Wind</span>, once per delve.
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Start Delve</button>
		</form>
	</div><!-- /cc-inner -->

	<?php elseif ($state === 'game_over'):
			$fell = ($recent_run['status'] === 'lost');
		?>
		<?php if ($fell): ?>
	</div><!-- /cc-inner -->
		<div class="cc-theme-bg" style="background-image:linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('/staking/images/themes/8.jpg');">
		<div class="cc-inner">
		<?php endif; ?>
		<div class="cc-result <?php echo $fell ? 'lost' : 'won'; ?>">
			<div class="cc-result-icon"><?php echo $fell ? '💀' : '🏆'; ?></div>
			<div class="cc-result-title"><?php echo $fell ? 'You Died' : 'You Escaped'; ?></div>
			<div class="cc-result-sub">
				<?php echo intval($recent_run['rooms_cleared']); ?> crypts cleared
				<?php if (!$fell): ?> &middot; <?php echo intval($recent_run['hp']); ?> HP remaining<?php endif; ?>
			</div>
		</div>
		<form method="post"><input type="hidden" name="action" value="start_run">
			<button type="submit" class="cc-btn">💀 Delve Again</button>
		</form>
		<?php if ($fell): ?>
		</div><!-- /cc-inner -->
		</div><!-- /cc-theme-bg -->
		<?php else: ?>
	</div><!-- /cc-inner -->
		<?php endif; ?>

	<?php else: // active
		$room = json_decode($active_run['room'], true) ?: [];
		$deck_count = count(json_decode($active_run['deck'], true) ?: []);
		$hp = intval($active_run['hp']);
		$max_hp = intval($active_run['max_hp']);
		$hp_pct = $max_hp > 0 ? max(0, min(100, round(($hp / $max_hp) * 100))) : 0;
		$weapon_power = $active_run['weapon_power'] !== null ? intval($active_run['weapon_power']) : null;
		$weapon_name  = $active_run['weapon_name'];
		$weapon_beaten_rank = $active_run['weapon_beaten_rank'] !== null ? intval($active_run['weapon_beaten_rank']) : null;
		$can_flee = (intval($active_run['fled_last_room']) === 0) && (count($room) === 4);

		// See cryptcrawlRoomThemeFile() in db.php -- shared with the Discord
		// per-round announcement so there's one theme list, not two.
		$room_theme_url = '/staking/images/themes/' . cryptcrawlRoomThemeFile($active_run['rooms_cleared']);
	?>
	</div><!-- /cc-inner (theme backdrop below spans the full page-content width) -->
		<div class="cc-theme-bg" style="background-image:linear-gradient(180deg, rgba(7,17,26,.55), rgba(7,17,26,.88)), url('<?php echo htmlspecialchars($room_theme_url); ?>');">
		<div class="cc-inner">
		<div class="cc-hud">
			<div class="cc-hp-wrap<?php echo $hp_pct <= 30 ? ' low' : ''; ?>">
				<div style="font-size:0.72rem;opacity:0.6;margin-bottom:3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
					<span>HP <?php echo $hp; ?> / <?php echo $max_hp; ?></span>
					<?php if (intval($active_run['second_wind_used'] ?? 0) === 0): ?>
						<span class="cc-second-wind" title="The first hit that would drop you to 0 HP this delve instead leaves you at 1 -- once per delve.">🛡️ Second Wind ready</span>
					<?php endif; ?>
				</div>
				<div class="cc-hp-bar-bg"><div class="cc-hp-bar-fill" data-target-width="<?php echo 100 - $hp_pct; ?>" style="width:100%;"></div></div>
			</div>
			<div class="cc-hud-meta">
				<div class="cc-weapon">
					<?php if ($weapon_power !== null):
						// Same icon convention gauntlets.php uses for its own gear
						// panel: lowercase the weapon's name, hyphenate, .png — with
						// the same graceful onerror hide for any name with no icon
						// on disk (e.g. the "Rusty Blade" fallback name).
						$weapon_icon = 'icons/' . strtolower(str_replace(['%', ' '], ['', '-'], $weapon_name)) . '.png';
					?>
						<img class="cc-weapon-icon" src="<?php echo htmlspecialchars($weapon_icon); ?>" alt="" onerror="this.style.display='none';">
						<strong><?php echo htmlspecialchars($weapon_name); ?></strong> (pwr <?php echo $weapon_power; ?>) - <?php echo $weapon_beaten_rank !== null ? 'beats up to ' . cryptcrawlRankLabel($weapon_beaten_rank) : 'no limit yet, fresh'; ?>
					<?php else: ?>
						👊 Bare-handed
					<?php endif; ?>
				</div>
				<div style="font-size:0.72rem;opacity:0.5;">Crypts cleared: <?php echo intval($active_run['rooms_cleared']); ?> · Deck: <?php echo $deck_count; ?> left</div>
			</div>
		</div>

		<div class="cc-room">
			<?php foreach ($room as $i => $card):
				$suit = $card['suit']; $rank = intval($card['rank']); $type = $card['type'];
				// Display only — internal type stays 'monster' (game logic, DB
				// data) so this is purely a label swap, not a data rename.
				$type_label = $type === 'monster' ? 'enemy' : ($type === 'potion' ? 'medkit' : $type);
				$weapon_eligible = ($type === 'monster') && $weapon_power !== null && ($weapon_beaten_rank === null || $rank <= $weapon_beaten_rank);
				$dom_rgb = cryptcrawlDominantColor($card['image_url'] ?? '');
				$glow_rgba = $dom_rgb ? sprintf('rgba(%d,%d,%d,.45)', $dom_rgb[0], $dom_rgb[1], $dom_rgb[2]) : 'rgba(255,153,0,.35)';
				// Weapon cards get an icon-on-black face instead of NFT art (see the card
				// face rendering below) -- computed here, once, so the same icon shows on
				// the card face and the Equip button below it. Same lookup
				// cryptcrawlPlayCard() itself uses on equip, so it always matches what
				// actually gets equipped.
				if ($type === 'weapon') {
					$preview_weapon_name = cryptcrawlWeaponName($conn, $rank);
					$preview_weapon_icon = 'icons/' . strtolower(str_replace(['%', ' '], ['', '-'], $preview_weapon_name)) . '.png';
				}
				$medkit_icon = 'https://madballs.net/drop-ship/icons/medkit.png';
			?>
				<div class="cc-card" style="--cc-glow:<?php echo htmlspecialchars($glow_rgba); ?>;">
					<div class="cc-card-flip">
					<div class="cc-card-flip-inner">
						<div class="cc-card-face cc-card-back">
							<img class="cc-card-back-icon" src="/staking/pwa/skulliance-logo-icon.png" alt="">
						</div>
						<div class="cc-card-face cc-card-front">
						<?php if ($type === 'monster' && !empty($card['image_url'])): ?>
							<div class="cc-card-art">
								<img class="cc-card-img" src="<?php echo htmlspecialchars($card['image_url']); ?>" alt="" loading="lazy" onerror="this.remove();">
								<div class="cc-card-corner tl" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
								<div class="cc-card-corner br" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
							</div>
						<?php elseif ($type === 'monster'): ?>
							<div class="cc-card-badge-standalone">
								<div class="cc-card-rank" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo cryptcrawlRankLabel($rank); ?></div>
								<div class="cc-card-suit" style="color:<?php echo $suit_color[$suit]; ?>;"><?php echo $suit_symbol[$suit]; ?></div>
							</div>
						<?php else: // weapon or potion -- icon on black instead of NFT art, keeping
							// the curated Crypties art reserved for enemies specifically. ?>
							<div class="cc-card-art cc-card-icon-face">
								<img class="cc-card-icon" src="<?php echo htmlspecialchars($type === 'weapon' ? $preview_weapon_icon : $medkit_icon); ?>" alt="" onerror="this.style.display='none';">
								<div class="cc-card-corner tl" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
								<div class="cc-card-corner br" style="color:<?php echo $suit_color[$suit]; ?>;">
									<div class="cc-card-rank"><?php echo cryptcrawlRankLabel($rank); ?></div>
									<div class="cc-card-suit"><?php echo $suit_symbol[$suit]; ?></div>
								</div>
							</div>
						<?php endif; ?>
						</div>
					</div>
					</div>
					<div class="cc-card-controls">
					<div class="cc-card-label"><?php echo htmlspecialchars($type_label); ?></div>
					<div class="cc-card-actions">
						<?php if ($type === 'monster'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn bare punchy">
									<span class="cc-btn-icon-big">👊</span>
									<span class="cc-btn-action">Fist Fight</span>
									<span class="cc-btn-detail">-<?php echo $rank; ?> HP</span>
								</button>
							</form>
							<?php if ($weapon_power !== null): ?>
								<form method="post"><input type="hidden" name="action" value="play_card">
									<input type="hidden" name="card_index" value="<?php echo $i; ?>">
									<input type="hidden" name="use_weapon" value="1">
									<button type="submit" class="cc-btn attack punchy" <?php echo $weapon_eligible ? '' : 'disabled'; ?>>
										<!-- Same $weapon_icon the HUD line above already computed for the
										     currently equipped weapon -- reused as-is so this always matches. -->
										<img class="cc-btn-icon-big-img" src="<?php echo htmlspecialchars($weapon_icon); ?>" alt="" onerror="this.style.display='none';">
										<span class="cc-btn-action">Use Weapon</span>
										<span class="cc-btn-detail"><?php echo $weapon_eligible ? '-' . max(0, $rank - $weapon_power) . ' HP' : 'Too worn'; ?></span>
									</button>
								</form>
							<?php endif; ?>
						<?php elseif ($type === 'weapon'): ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn warn punchy">
									<img class="cc-btn-icon-big-img" src="<?php echo htmlspecialchars($preview_weapon_icon); ?>" alt="" onerror="this.style.display='none';">
									<span class="cc-btn-action">Equip</span>
									<span class="cc-btn-detail"><?php echo htmlspecialchars($preview_weapon_name); ?></span>
								</button>
							</form>
						<?php else: ?>
							<form method="post"><input type="hidden" name="action" value="play_card">
								<input type="hidden" name="card_index" value="<?php echo $i; ?>">
								<input type="hidden" name="use_weapon" value="0">
								<button type="submit" class="cc-btn heal punchy">
									<img class="cc-btn-icon-big-img" src="<?php echo htmlspecialchars($medkit_icon); ?>" alt="" onerror="this.style.display='none';">
									<span class="cc-btn-action">Heal</span>
									<span class="cc-btn-detail"><?php echo intval($active_run['potion_used_this_room']) === 1 ? '+' . max(1, intval($rank / 2)) . ' HP (half)' : '+' . $rank . ' HP'; ?></span>
								</button>
							</form>
						<?php endif; ?>
					</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="cc-flee-row">
			<form method="post"><input type="hidden" name="action" value="flee">
				<button type="submit" class="cc-btn secondary" <?php echo $can_flee ? '' : 'disabled'; ?>>🏃 Flee this crypt</button>
			</form>
			<?php if (!$can_flee): ?><div class="cc-note">already fled last crypt, or mid-crypt - can't flee now</div><?php endif; ?>
			<form method="post" style="margin-top:8px;" onsubmit="return confirm('Abandon this run? It counts as a loss.');">
				<input type="hidden" name="action" value="abandon">
				<button type="submit" class="cc-btn secondary" style="font-size:0.68rem;opacity:0.6;padding:4px 8px;">🏳️ Abandon run</button>
			</form>
		</div>
		</div><!-- /cc-inner -->
		</div><!-- /cc-theme-bg -->
	<?php endif; ?>
</div>
<script>
(function() {
	// Flash toasts float over the game now instead of sitting in the page
	// flow, so nothing dismisses them automatically the way scrolling past an
	// in-flow banner used to -- clear each one on a timer instead of letting
	// it sit there until the next action reloads the page. Reduced-motion
	// still gets the timed removal, just without the opacity transition.
	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	document.querySelectorAll('.cc-flash').forEach(function(el) {
		setTimeout(function() {
			if (reduceMotion) {
				el.remove();
				return;
			}
			el.style.transition = 'opacity .4s ease';
			el.style.opacity = '0';
			setTimeout(function() { el.remove(); }, 400);
		}, 4000);
	});

	// HP bar renders fully covered (width:100%, i.e. empty-looking) so that
	// nudging it to its real data-target-width one frame later animates the
	// gradient revealing in on every page load, not just on HP changes —
	// the bar's own CSS transition (.cc-hp-bar-fill) does the actual tween.
	var hpFill = document.querySelector('.cc-hp-bar-fill');
	if (hpFill) {
		var target = hpFill.getAttribute('data-target-width');
		requestAnimationFrame(function() {
			requestAnimationFrame(function() {
				hpFill.style.width = target + '%';
			});
		});
	}

	// Fill whatever viewport space is left below the backdrop rather than
	// forcing the page to scroll to show the full theme image — cropping
	// via background-size:cover is fine, scrolling isn't. No-ops cleanly
	// when there's no .cc-theme-bg on the page at all (no_run state, or a
	// game_over 'won' screen, which doesn't get a themed backdrop).
	var el = document.querySelector('.cc-theme-bg');
	if (el) {
		function sizeTheme() {
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
		sizeTheme();
		window.addEventListener('resize', sizeTheme);
	}

	// Desktop-only card tilt — skip entirely on touch devices so there's no
	// stuck "hover" state after a tap. Targets .cc-card-flip specifically
	// (the "card" object) so the controls panel below stays flat/static
	// instead of tilting along with it. No-ops on states with no cards.
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
	// back face at the midpoint -- a tactile "yep, that reacted" flourish.
	// Purely cosmetic: the actual actions are the buttons below, a sibling
	// of this element in the DOM, so this listener never touches them.
	// Uses the Web Animations API rather than a CSS class + keyframe so it
	// can't collide with .cc-card-flip-inner's own intro-flip CSS animation
	// -- toggling a class that shares the `animation` property would make
	// the browser treat animation-name as having changed and replay the
	// intro flip from its rotateY(180deg) starting point every time this
	// spin finishes and the class comes back off.
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
})();
</script>
</html>
