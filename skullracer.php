<?php
// SKULL RACER -- wrapper page. Linked in nav (Play > Skull Racer). The game
// itself is a self-contained static build (racing/index.html: its own
// HTML/CSS/JS, no PHP, no session) -- this page's only job is to put the
// normal Skulliance header/nav around it for a logged-in staker, then embed
// the game in an iframe.
//
// Why an iframe instead of including header.php directly into racing/'s own
// markup: header.php's nav links are bare-relative (href="cryptcrawlgame.php"
// etc.), which only resolves correctly for a page living at the repo root.
// racing/index.html lives in its own subfolder, so every asset reference in
// it (images/, music/, sounds/, common.js) is written relative to THAT
// folder. Iframing it keeps both sides working unmodified: this page gets
// the real nav (its own links resolve fine, it's at the root), and the game
// keeps resolving its own assets relative to racing/ exactly as it does when
// visited directly at racing/index.html.
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
?>
<style>
#burger-menu { <?php echo isset($name) ? '' : 'display: none;'; ?> } /* same guest-hide as cryptcrawl.php/cryptconquest.php */
.skullracer-wrap {
  display: flex;
  justify-content: center;
  padding: 24px 16px;
}
#skullracer-frame {
  /* Was capped at max-width:1400px/height:900px -- an arbitrary conservative
     box from before racing/index.html itself tried to fill its own window.
     Now that it does, this needs to actually give it the room: the box art
     flanking the game inside the iframe wraps to its own line (looking
     broken) if the iframe itself isn't wide enough for both, regardless of
     how generous the inner page's own sizing is. */
  width: 100%;
  height: 85vh;
  border: 0;
  border-radius: 0.75rem;
}
</style>

<div class="skullracer-wrap">
  <iframe id="skullracer-frame" src="racing/index.html" title="Skull Racer" allow="autoplay"></iframe>
</div>
