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

/* An invite rode in on the link. db.php has already stored it in a cookie;
   this only decides whether the page says so. */
$dg_ref = false;
if (is_file(__DIR__ . '/lib/referral-lib.php')) {
	require_once __DIR__ . '/lib/referral-lib.php';
	$dg_ref = !empty($_GET['r']) && ref_decode($_GET['r']) > 0
	          && empty($_SESSION['userData']['user_id']);
}

$dg_token = isset($_GET['d']) ? trim((string) $_GET['d']) : '';
/* Shape-checked before it touches a path. The token is 20 hex characters
   and nothing else, so no ../ and no absolute path can survive this. */
$dg_ok   = dsh_valid_token($dg_token)
        && is_file(__DIR__ . '/' . DSH_DIR . '/' . $dg_token . '.jpg');

$dg_img  = $dg_ok ? DSH_BASE . DSH_DIR . '/' . $dg_token . '.jpg' : DSH_BASE . 'images/og.jpg';
/*
 * MEASURED, NOT ASSUMED. The img carried width="1080" height="1350", which
 * is what the poster used to be. It is 1200x630 now, and every poster
 * shared before the change is still the old shape and still being served,
 * so neither number is right for both -- and a wrong pair reserves a box
 * of the wrong shape and shoves everything below it down the page while
 * the image loads. The file is on this disk; ask it.
 */
$dg_dim = $dg_ok ? @getimagesize(__DIR__ . '/' . DSH_DIR . '/' . $dg_token . '.jpg') : false;
$dg_w   = ($dg_dim && $dg_dim[0] > 0) ? (int) $dg_dim[0] : 1200;
$dg_h   = ($dg_dim && $dg_dim[1] > 0) ? (int) $dg_dim[1] : 630;

/*
 * WE KNOW WHO SENT THEM, SO SAY SO.
 *
 * This page said "whoever shared this gets three as well" while their
 * username and their avatar were drawn into the picture directly above the
 * sentence. ?r= decodes to exactly one user id, which is the account that
 * will actually be paid, so being coy about it was pretending not to know
 * something the page was already showing.
 *
 * NAMED ONLY WHEN THE POSTER IS REALLY THEIRS -- see
 * dsh_token_belongs_to(), which is why that check is a function and not
 * three lines here. Where it says no, the generic wording stands.
 *
 * NOTHING NEW IS DISCLOSED. The name is already in the image, and the
 * image only exists because its owner pressed Share.
 */
$dg_from = '';
if ($dg_ok && $dg_ref) {
	$dg_inviter = ref_decode($_GET['r']);
	if (dsh_token_belongs_to($dg_token, $dg_inviter) && isset($conn) && $conn instanceof mysqli) {
		/* No mysqlnd on this host: query() and fetch_assoc(), never
		   get_result(). The id came out of ref_decode() as an int. */
		$dg_r = @$conn->query('SELECT username FROM users WHERE id = '
		                      . (int) $dg_inviter . ' LIMIT 1');
		if ($dg_r && ($dg_u = $dg_r->fetch_assoc())) $dg_from = trim((string) $dg_u['username']);
	}
}
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
#dg .offer{background:#0d1e2e;border:1px solid #1b3346;border-left:3px solid #00c8a0;
  border-radius:3px;padding:16px 18px;text-align:left;margin-top:22px}
#dg .offer b{color:#00c8a0}
#dg .offer a{color:#00c8a0}
</style>
<?php
/*
 * THE BUTTONS GO ABOVE THE POSTER FOR SOMEBODY WHO WAS INVITED.
 *
 * The poster is a full-width image on a 760px column, so on a phone it is
 * most of a screen on its own -- and the offer and the buttons sat
 * underneath it. An arrival from X landed on a stranger's artwork with
 * nothing to press anywhere in sight, which is the one thing this page
 * exists to avoid. Reported exactly that way.
 *
 * A visitor who was NOT invited still gets the poster first: for them it
 * is the interesting thing on the page and the ask has not been set up
 * yet. Written once and placed twice rather than duplicated.
 */
$dg_cta = function () use ($dg_ref, $e) { ?>
  <div class="cta">
    <a class="go" href="<?php echo $e($dg_ref && !empty($_GET['r'])
          ? 'index.php?r=' . rawurlencode((string) $_GET['r']) : 'index.php'); ?>"><?php
       echo $dg_ref ? 'Claim your traits' : 'Start staking'; ?></a>
    <a href="dhcgallery.php">See the Collection</a>
    <a href="skullpaper.php">How it all works</a>
  </div>
<?php };

$dg_shot = function () use ($dg_token, $dg_w, $dg_h, $e) { ?>
    <img class="shot" src="<?php echo $e(DSH_DIR . '/' . $dg_token . '.jpg'); ?>"
         alt="One player's day on Skulliance"
         width="<?php echo (int) $dg_w; ?>" height="<?php echo (int) $dg_h; ?>">
<?php };
?>
<div id="dg">
  <?php if ($dg_ok && $dg_ref): ?>
    <h1><?php echo $dg_from !== ''
           ? $e($dg_from) . ' wants you to see this'
           : 'Somebody wants you to see this'; ?></h1>
    <p class="offer"><b>Three DHC Fighter traits are waiting for you.</b> Sign in through this
      link and they are yours - drawn on better odds than an ordinary drop, with a real chance at
      a legendary. <?php echo $dg_from !== ''
        ? $e($dg_from) . ' gets three for bringing you in.'
        : 'Whoever shared this gets three as well.'; ?>
      <a href="index.php#traits">What is a DHC Fighter trait?</a></p>
    <?php $dg_cta(); ?>
    <p>Free to join, nothing to install, and you do not need a wallet to start.</p>
    <?php $dg_shot(); ?>
    <p><?php echo $dg_from !== ''
         ? 'This is ' . $e($dg_from) . '&rsquo;s day on Skulliance, drawn the morning after.'
         : 'This is their day on Skulliance, drawn the morning after.'; ?>
      Yours starts the night you stake something.</p>
  <?php elseif ($dg_ok): ?>
    <h1>A day on Skulliance</h1>
    <p>Somebody&rsquo;s day of missions, raids, realms and games &mdash; drawn the morning after.</p>
    <?php $dg_shot(); ?>
    <p>Every staker here gets one of these. Stake an NFT and yours starts tomorrow.</p>
    <?php $dg_cta(); ?>
  <?php else: ?>
    <h1>That day is no longer here</h1>
    <p>Shared posters are kept for a few months and this one has gone. The platform has not.</p>
    <?php $dg_cta(); ?>
  <?php endif; ?>
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
