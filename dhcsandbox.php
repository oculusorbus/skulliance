<?php
/**
 * DIGITAL HELL CITIZENS 2 -- TRAIT ASSEMBLY SANDBOX
 *
 * Every trait unlocked, layers draggable, nothing saved. It began as a
 * proof-of-concept handed to Maxingo to verify combinations before anything
 * real was built on top of it, and it is now a platform tool in its own right:
 * the only place you can put ANY two traits together and see what happens,
 * including the ones you do not own.
 *
 * WAS STANDALONE, NOW INTEGRATED. It used to include nothing at all -- no
 * db.php, no header.php -- and wear the collection's ash-and-ochre skin. That
 * made it a page you had to know the URL for, and one that looked like a
 * foreign object next to the rest of the platform. It now takes the Skulliance
 * chrome and palette and sits in the DHC strip with Fighters, the Collection
 * and the Arena.
 *
 * STILL PUBLIC. Same conditional gate as dhcarena.php and dhcgallery.php:
 * skulliance.php redirects an anonymous visitor to error.php, but verify.php
 * defines checkUser() and skulliance.php calls it, so the pair runs in full for
 * a member and not at all for a guest. Handing the link to an artist outside
 * the platform was the original point and still works.
 *
 * $dhca_mode = 'sandbox' is the BEHAVIOUR (every trait, reorderable);
 * $dhca_standalone = false is the SKIN AND LAYOUT (Skulliance navy, embedded).
 * They used to be one flag -- see dhc-assembler.php.
 *
 * The trait art is NOT in this repo (another artist's source work, and large) --
 * it is uploaded separately, and the assembler DISCOVERS what is there at
 * request time with glob() rather than carrying a hardcoded list, so adding or
 * renaming art needs no code change.
 */
include 'db.php';

if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$dhcs_ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($dhcs_ck)) {
		$_SESSION = array_merge((array)$_SESSION, $dhcs_ck);   // merge, never assign
	} else {
		setcookie('SessionCookie', '', time() - 3600);
	}
	unset($dhcs_ck);
}
$dhcs_guest = empty($_SESSION['logged_in']);
if (!$dhcs_guest) { include 'verify.php'; include 'skulliance.php'; }

/* Behaviour: the sandbox's. Skin and layout: the platform's. */
$dhca_mode       = 'sandbox';
$dhca_owned      = null;
$dhca_standalone = false;

/* Rendered first because the assembler resolves $dhc_base (where the art
   landed) as a side effect, and the social card below needs it. */
ob_start();
/* dhcf_artist_link() for the credit below. The assembler pulls the same
   config in, so this is belt and braces -- but leaning on a transitive
   include is exactly how $dhc_base ended up undefined in a page that read
   it before the file defining it had run. */
require_once __DIR__ . '/dhcfighters-config.php';
include __DIR__ . '/dhc-assembler.php';
$dhc_assembler = ob_get_clean();

/*
 * AND PUT THE HEADER'S VARIABLES BACK. The assembler is included at global
 * scope, so anything it leaves behind is a global by the time header.php runs
 * -- and header.php prints $name as the signed-in player's username. It leaked
 * exactly one ($name, a trait's display name) and that was enough to put
 * "U. Vigilance Device #2" where a person's name belongs. The leak is fixed in
 * the assembler, but this page is the only one that includes it BEFORE the
 * header, so it re-derives from the session rather than trusting a big shared
 * file to stay clean.
 */
unset($name, $avatar_url);
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	$name = $_SESSION['userData']['name'] ?? null;
	$dhcs_did = $_SESSION['userData']['discord_id'] ?? null;
	$dhcs_av  = $_SESSION['userData']['avatar'] ?? null;
	if ($name !== null && $dhcs_did && $dhcs_av)
		$avatar_url = "https://cdn.discordapp.com/avatars/$dhcs_did/$dhcs_av.jpg";
}

/*
 * SOCIAL CARD. Both og:image AND twitter:card=summary_large_image, or X renders
 * a small square thumbnail instead of the wide one -- the failure mode already
 * documented in skullpaper/MAINTENANCE.md for the game share buttons.
 *
 * Absolute URLs here and nowhere else: a crawler has no page context to resolve
 * a relative one against, while a clickable absolute link can hop between www
 * and the bare host and drop a host-only session cookie.
 *
 * The card art is a pre-rendered composite shipped with the trait upload rather
 * than generated per request -- no GD dependency and a fast static file for the
 * crawler.
 */
$dhcs_url  = 'https://www.skulliance.io/staking/dhcsandbox.php';
$dhcs_card = $dhc_base !== ''
	? 'https://www.skulliance.io/staking/' . $dhc_base . '/card.png'
	: 'https://www.skulliance.io/staking/images/og.jpg';
$dhcs_desc = 'Put any two DHC traits together and see what happens. Every piece '
           . 'unlocked, layers you can reorder by hand, and nothing saved — the '
           . 'workbench for the Fighters you build for real.';
$page_title_override = 'Trait Sandbox — DHC Fighters | Skulliance';
$extra_head = '
<meta name="description" content="'.htmlspecialchars($dhcs_desc).'">
<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">
<link rel="canonical" href="'.$dhcs_url.'">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="'.$dhcs_url.'">
<meta property="og:title" content="DHC Trait Sandbox">
<meta property="og:description" content="'.htmlspecialchars($dhcs_desc).'">
<meta property="og:image" content="'.$dhcs_card.'">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="A Digital Hell Citizens 2 Fighter assembled from layered traits.">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="DHC Trait Sandbox">
<meta name="twitter:description" content="'.htmlspecialchars($dhcs_desc).'">
<meta name="twitter:image" content="'.$dhcs_card.'">
<meta name="twitter:image:alt" content="A Digital Hell Citizens 2 Fighter assembled from layered traits.">
';

include 'header.php';
?>

<style>
/* The same shell the other three DHC pages use, so the strip lands in the same
   place on all four and the sandbox stops reading as a different website. */
.dhcs-wrap{padding:14px;max-width:100%;overflow-x:clip}
.dhcs-head{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin:0 0 4px}
.dhcs-head h1{font-size:22px;letter-spacing:.02em;margin:0}
.dhcs-head .sub{font-size:11px;color:var(--dim,#7a9eb0)}
.dhcs-note{font-size:11.5px;opacity:.65;line-height:1.6;margin:0 0 14px;max-width:70ch}
.dhcs-note a{color:var(--ochre,#00c8a0)}
.dhcs-note b{opacity:.95}
/* The assembler brings .warn with it, styled for a page it no longer owns. */
.dhcs-wrap .warn{margin:0 0 14px}
</style>

<div class="dhcs-wrap">

<?php $dhcnav_at = 'sandbox'; $dhcnav_guest = $dhcs_guest; include 'dhc-nav.php'; ?>

  <div class="dhcs-head">
    <h1>Trait Sandbox</h1>
    <span class="sub">Digital Hell Citizens 2: Fighters &middot; art by <?php echo dhcf_artist_link(); ?></span>
  </div>
  <p class="dhcs-note">
    Every trait unlocked, whether you own it or not, and the layers can be
    <b>dragged into any order</b> — which is the part the real assembler will not
    let you do. Nothing here is saved and nothing counts: it is for seeing what a
    combination looks like before you go and build it.
    <?php if ($dhcs_guest): ?>
      <a href="index.php">Sign in</a> to build one for real in DHC Fighters, or
      <a href="dhcarena.php">play the Arena</a> — no account needed.
    <?php else: ?>
      Build one for real in <a href="dhcfighters.php">DHC Fighters</a>.
    <?php endif; ?>
  </p>

<?php if ($dhc_base === ''): ?>
  <div class="warn">
    <b>No trait art found.</b><br>
    This page looks for a folder containing <code>1000/</code> and <code>250/</code> next to it &mdash;
    it tried <code>web/</code>, <code>dhc/</code>, <code>dhc/web/</code> and <code>traits/</code>.<br><br>
    Upload the <code>web</code> folder into the same directory as this file, or add your path to
    the <code>$dhc_base</code> list at the top of <code>dhc-assembler.php</code>.
  </div>
<?php else: ?>
<?php echo $dhc_assembler; ?>
<?php endif; ?>

</div>

<?php $conn->close(); ?>
</html>
