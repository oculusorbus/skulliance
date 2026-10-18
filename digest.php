<?php
/**
 * digest.php?d=<token> -- the page a shared day poster unfurls from. PUBLIC.
 *
 * x.com/intent/post cannot attach a file; the only picture a post carries
 * is the one the shared URL declares. So the modal stores the poster it
 * drew (ajax/digest-share.php) and posts a link to here, whose og:image is
 * that stored file. One click on Share, picture included.
 *
 * PUBLIC FOR THE SAME REASON skullpaper.php IS: the audience is a crawler
 * and whoever clicks the post, neither of whom is signed in. It must not
 * include skulliance.php -- that redirects anonymous visitors to error.php
 * and X would cache a card of an error page.
 *
 * IT EXPOSES NOTHING THAT WAS NOT PUBLISHED. The only thing on the page is
 * an image its owner pressed Share on, and the page carries no name, no
 * numbers and no link to a profile beyond what the player drew into their
 * own poster.
 */
include_once 'db.php';
require_once __DIR__ . '/lib/digest-share-lib.php';

/* Same session restore as skullpaper.php -- lights up the member navbar if
   a cookie is already there, and leaves a stranger with the public one.
   MERGED, never raw-assigned; see skulliance.php's own note. */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$cookie = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookie)) { $_SESSION = array_merge((array) $_SESSION, $cookie); }
}
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	$name = $_SESSION['userData']['name'] ?? null;
	$dg_did = $_SESSION['userData']['discord_id'] ?? null;
	$dg_av  = $_SESSION['userData']['avatar'] ?? null;
	if ($name !== null && $dg_did && $dg_av) $avatar_url = avatar_url($dg_did, $dg_av);
}

$dg_token = isset($_GET['d']) ? trim((string) $_GET['d']) : '';
/* Shape-checked before it touches a path. The token is 20 hex characters
   and nothing else, so no ../ and no absolute path can survive this. */
$dg_ok   = dsh_valid_token($dg_token)
        && is_file(__DIR__ . '/' . DSH_DIR . '/' . $dg_token . '.jpg');

$dg_img  = $dg_ok ? DSH_BASE . DSH_DIR . '/' . $dg_token . '.jpg' : DSH_BASE . 'images/og.jpg';
$dg_url  = DSH_BASE . 'digest.php' . ($dg_ok ? '?d=' . $dg_token : '');
$dg_t    = 'A day on Skulliance';
$dg_d    = 'Stake your NFTs, run missions, hold a realm and play a dozen games, free and in '
         . 'any browser.';

$page_title_override = $dg_t . ' | Skulliance';
$e = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
$extra_head = '
  <meta name="description" content="' . $e($dg_d) . '">
  ' . ($dg_ok ? '<meta name="robots" content="noindex,follow">' : '') . '
  <link rel="canonical" href="' . $e($dg_url) . '">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Skulliance">
  <meta property="og:url" content="' . $e($dg_url) . '">
  <meta property="og:title" content="' . $e($dg_t) . '">
  <meta property="og:description" content="' . $e($dg_d) . '">
  <meta property="og:image" content="' . $e($dg_img) . '">
  <meta property="og:image:alt" content="One player\'s day on Skulliance">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:site" content="@skulliance">
  <meta name="twitter:title" content="' . $e($dg_t) . '">
  <meta name="twitter:description" content="' . $e($dg_d) . '">
  <meta name="twitter:image" content="' . $e($dg_img) . '">
';

include 'header.php';
/* header.php's navbar lives inside if(isset($name)), so a stranger -- which
   is everyone arriving from a post -- would otherwise land on a page with
   no way anywhere. Same fix the Skull Paper carries. */
if (!isset($name)) include __DIR__ . '/site-header.php';
?>
<style>
#dg{max-width:760px;margin:0 auto;padding:34px 18px 70px;color:#e8eaed;text-align:center;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
#dg h1{font-size:clamp(20px,3.4vw,28px);margin:0;line-height:1.25;text-wrap:balance}
#dg p{margin:14px 0 0;color:#c7d3dc;font-size:13.5px;line-height:1.7}
#dg img.shot{display:block;width:100%;height:auto;margin:24px 0 0;border:1px solid #1b3346;
  border-radius:4px;background:#06101a}
#dg .cta{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin:26px 0 0}
#dg .cta a{display:inline-block;padding:12px 22px;border-radius:3px;font-size:12px;
  letter-spacing:.1em;text-transform:uppercase;text-decoration:none;border:1px solid #1b3346;
  color:#e8eaed}
#dg .cta a.go{background:#00c8a0;border-color:#00c8a0;color:#07111d;font-weight:700}
#dg .cta a:hover{border-color:#00c8a0;color:#00c8a0}
#dg .cta a.go:hover{filter:brightness(1.1);color:#07111d}
</style>
<div id="dg">
  <?php if ($dg_ok): ?>
    <h1>A day on Skulliance</h1>
    <p>Somebody&rsquo;s day of missions, raids, realms and games &mdash; drawn the morning after.</p>
    <img class="shot" src="<?php echo $e(DSH_DIR . '/' . $dg_token . '.jpg'); ?>"
         alt="One player's day on Skulliance" width="1080" height="1350">
    <p>Every staker here gets one of these. Stake an NFT and yours starts tomorrow.</p>
  <?php else: ?>
    <h1>That day is no longer here</h1>
    <p>Shared posters are kept for a few months and this one has gone. The platform has not.</p>
  <?php endif; ?>
  <div class="cta">
    <a class="go" href="index.php">Start staking</a>
    <a href="dhcgallery.php">See the Collection</a>
    <a href="skullpaper.php">How it all works</a>
  </div>
</div>

<!-- Footer -->
<div class="footer">
  <p>Skulliance<br>Copyright © <span id="year"></span>
</div>
</div>
</div>
</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>
