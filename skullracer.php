<?php
// SKULL RACER -- wrapper page. Linked in nav (Play > Skull Racer). The game
// itself is a self-contained static build (racing/index.html: its own
// HTML/CSS/JS, no PHP, no session) -- this page's only job is to put the
// normal Skulliance header/nav around it for a logged-in staker.
//
// Used to iframe racing/index.html instead of inlining it directly, because
// header.php's nav links are bare-relative (only resolve from the repo
// root) while racing/'s own asset references were folder-relative (only
// resolve from racing/ itself) -- mutually incompatible in one document.
// Fixed at the source instead of worked around: every asset reference in
// racing/index.html and racing/common.js is now root-absolute
// (/staking/racing/...), so the exact same file resolves correctly whether
// visited directly at racing/index.html or read and re-served from here.
// No iframe now -- an iframe is its own document with its own focus and
// scroll, which caused real bugs for no remaining benefit once the actual
// path conflict was fixed: keyboard input needed an explicit click first
// (a player's first keypress went nowhere until they clicked into the
// iframe), and a leaderboard link needed target="_top" just to escape it.
include_once 'db.php';

// Same session-restore pattern as cryptcrawl.php/skullswap.php/match3rpg.php
// -- no hard gate, a missing/absent session just means a guest (no nav).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
    $cookieData = json_decode($_COOKIE['SessionCookie'], true);
    if (is_array($cookieData)) {
        // Merge, not replace -- see skulliance.php's own fix for the
        // platform-wide version of this (a raw assign here would wipe
        // every other key this session already has, not just restore login).
        $_SESSION = array_merge((array)$_SESSION, $cookieData);
    }
}
$user_id = isset($_SESSION['userData']['user_id']) ? intval($_SESSION['userData']['user_id']) : 0;

// header.php's shared nav is entirely gated on isset($name) (plus
// $avatar_url for the avatar image) -- normally supplied by skulliance.php's
// extract($_SESSION['userData']). Same two-value setup cryptcrawl.php does.
if ($user_id > 0 && isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
    extract($_SESSION['userData']);
    if (isset($discord_id) && isset($avatar)) {
        $avatar_url = "https://cdn.discordapp.com/avatars/$discord_id/$avatar.jpg";
    }
}

$page_title_override = 'Skull Racer - Skulliance';

include 'header.php';

// Pull racing/index.html's <style> and <body> content directly from the
// one canonical file at request time -- not a copy-pasted duplicate kept
// in sync by hand, so there's nothing here that can drift out of sync
// with it. header.php has already emitted </head><body> by this point
// (same as every other page here that adds its own CSS after including
// it), so this <style> block lands in the body -- valid HTML5, applies
// identically to one placed in <head>.
$racing_html = file_get_contents(__DIR__ . '/racing/index.html');
$style_start  = strpos($racing_html, '<style>') + strlen('<style>');
$style_end    = strpos($racing_html, '</style>');
$racing_style = substr($racing_html, $style_start, $style_end - $style_start);

$body_start  = strpos($racing_html, '<body>') + strlen('<body>');
$body_end    = strpos($racing_html, '</body>');
$racing_body = substr($racing_html, $body_start, $body_end - $body_start);

// racing/index.html is meant to also work as its own complete page (visited
// directly at /staking/racing/index.html), where styling the real <body>
// (flex-centered, min-height:100vh, its own dark background) is exactly
// right -- it IS the whole page there. Embedded here, the real <body> is
// shared with the header/nav above it; letting those same rules target it
// would blow away the site's own page layout. Scope them to the wrapper
// div below instead -- #skullracer-embed stands in for racing/index.html's
// own <body> as this stylesheet's "page root" for everything the game does.
$racing_style = str_replace(
    ['body {', 'body { padding: 8px; }'],
    ['#skullracer-embed {', '#skullracer-embed { padding: 8px; }'],
    $racing_style
);

// ── Cache busting ─────────────────────────────────────────────────────
// The server sends `Cache-Control: max-age=604800, public` (SEVEN DAYS,
// no revalidation) for everything static under racing/ -- common.js, the
// sounds/*-data.js blobs, sprites.png, background.png. That's server
// config, not something in this repo, so the only lever here is the URL:
// change it and the old cached copy simply doesn't match any more.
//
// This is not hypothetical. A player hit 404s on sprites.png and
// background.png in Chrome while both files were perfectly fine on the
// server -- they were running a common.js from before asset paths were
// made root-absolute (see this file's own top comment), so it was still
// requesting them folder-relative, resolving to /staking/images/... which
// 301s to the apex host and 404s there. Their HTML was current; a
// week-stale script was not. Worse and quieter: sprites.png is repacked
// whenever a sprite is added (see racing/common.js's SPRITES comment), so
// a stale sheet + fresh coordinates means every sprite silently samples
// the wrong part of the image.
//
// Token is the root VERSION file, which is already bumped on every commit
// for the PWA update banner -- so this rides on a discipline that exists
// rather than adding a second thing to remember. filemtime() as the
// fallback keeps it working even if VERSION ever goes missing.
$sr_version = @file_get_contents(__DIR__ . '/VERSION');
$sr_asset_v = substr(preg_replace('/[^A-Za-z0-9]/', '', (string)$sr_version), 0, 20);
if ($sr_asset_v === '') $sr_asset_v = (string)@filemtime(__DIR__ . '/racing/common.js');

// <script src> tags live in the extracted body. Images are NOT here --
// common.js builds those URLs itself at runtime, so it gets the token as
// a global instead (see loadImages() there, and the inline script below).
$racing_body = str_replace(
    ['/staking/racing/common.js',
     '/staking/racing/sounds/engine-data.js',
     '/staking/racing/sounds/collision-data.js',
     '/staking/racing/sounds/lap-data.js'],
    ['/staking/racing/common.js?v=' . $sr_asset_v,
     '/staking/racing/sounds/engine-data.js?v=' . $sr_asset_v,
     '/staking/racing/sounds/collision-data.js?v=' . $sr_asset_v,
     '/staking/racing/sounds/lap-data.js?v=' . $sr_asset_v],
    $racing_body
);
?>
<style>
#burger-menu { <?php echo isset($name) ? '' : 'display: none;'; ?> } /* same guest-hide as cryptcrawl.php/cryptconquest.php */
<?php echo $racing_style; ?>
/* The standalone page's own min-height:100vh is sized to fill an entire
   browser window by itself -- here it's below a real header/nav, so a
   full 100vh would push the game a full screen's height further down the
   page than it needs to. Similar proportions to what the old iframe used
   (height: 85vh). */
#skullracer-embed { min-height: 85vh; }

/* Phone turned landscape (same threshold as racing/index.html's own
   landscape query above, for the exact same reason -- max-height, not
   max-width, so it catches a real phone in landscape without matching an
   ordinary desktop window). racing/index.html's own landscape rules
   (embedded in $racing_style above) already size the GAME's internal
   layout to fit -- but that page has no idea it's sitting below
   header.php's site nav here, only the standalone racing/index.html's own
   markup. The nav itself (#burger-menu/#navbar), the version-update
   banner, and the floating back-to-top button are all header.php's, not
   the game's, so they need hiding from HERE, not from racing/'s own CSS
   -- exactly what was reported: the game screen fit, but the site's own
   menu was still sitting there taking up the rest of the height, the one
   thing a controller-and-TV setup has zero use for. */
@media (orientation: landscape) and (max-height: 500px) {
  #burger-menu, #navbar, #app-version-banner, #back-to-top-button { display: none !important; }
  /* dvh, not vh, same reason racing/index.html's own landscape query just
     switched for the SAME symptom (reported: "almost fits, have to scroll
     a bit" on an iPhone 16) -- iOS Safari's 100vh reports the viewport as
     if its address bar weren't there, taller than what's actually
     visible. A min-height set from that overestimate forces the whole
     page taller than the real visible area regardless of how well
     anything INSIDE it fits, which is exactly the kind of scroll this
     whole block exists to prevent. Two declarations, not one -- a browser
     that doesn't understand dvh drops that whole line and keeps the vh
     one already set, rather than losing the min-height outright. */
  #skullracer-embed { min-height: 100vh; }
  #skullracer-embed { min-height: 100dvh; }
}
</style>
<!-- Must come BEFORE the game's own scripts in $racing_body below, since
     common.js reads it while loading sprites/background. See the cache
     busting block up top for why it exists at all. -->
<script>var RACER_ASSET_V = <?php echo json_encode($sr_asset_v); ?>;</script>
<div id="skullracer-embed">
<?php echo $racing_body; ?>
</div>
