<?php
// DIGITAL HELL CITIZENS 2 -- TRAIT ASSEMBLY SANDBOX
//
// A public proof-of-concept for the trait layering system, built so Maxingo and
// Oculus Orbus can verify combinations before anything real is built on top of it.
//
// INCLUDES NOTHING -- not db.php, not skulliance.php, not header.php. Same rule
// guardiansgame.php and skullracergame.php follow: no session, no login, no
// database, so it renders identically for every visitor and cannot break when
// something else does. That matters here because the whole point is handing a
// link to an artist outside the platform.
//
// The trait art is NOT in this repo (it is another artist's source work, and it
// is large) -- it is uploaded to the server separately. This page DISCOVERS what
// is there at request time with glob() rather than carrying a hardcoded list, so
// adding, renaming or removing art needs no code change here.
//
// LAYER ORDER is the thing being tested. See dhc/LAYER-MANIFEST.json for how it
// was derived. "Weapon (behind)" exists because some weapons wrap around the
// body -- Maxingo already supplies three of them as two files -- and which half
// belongs behind the torso is the open question this page is meant to answer.


// The assembler itself -- rules, markup and behaviour -- lives in
// dhc-assembler.php so this page and dhcfighters.php cannot drift apart.
// Public mode: no session, no ownership filter, every trait available.
$dhca_mode  = 'sandbox';
$dhca_owned = null;

// The include needs $dhc_base resolved for the social card below, and it also
// resolves it itself, so pull the rules in first and reuse the result.
ob_start();
include __DIR__ . '/dhc-assembler.php';
$dhc_assembler = ob_get_clean();

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DHC2 Trait Sandbox &mdash; Skulliance</title>
<meta name="description" content="Assemble a Digital Hell Citizens 2 Fighter from 194 individual traits.">
<?php
/*
 * SOCIAL CARD. Both og:image AND twitter:card=summary_large_image are required
 * -- with only og:image, X renders a small square thumbnail instead of the wide
 * card, which is the failure mode already documented in skullpaper/MAINTENANCE.md
 * for the game share buttons.
 *
 * ABSOLUTE urls, because a crawler has no page context to resolve a relative one
 * against. That is the one place on this platform where a relative link is wrong;
 * everywhere else it is required, since the login cookie is host-only.
 *
 * The card art is a pre-rendered composite that ships with the trait upload, not
 * something generated per request -- no GD dependency, nothing to fail on a
 * shared host, and a crawler gets a fast static file.
 *
 * NOT noindex any more: a noindex page can still be shared, but leaving it
 * crawlable means the card is validated and cached by X the first time anyone
 * posts it rather than on the visitor's own fetch.
 */
$dhc_url = 'https://skulliance.io/staking/dhcsandbox.php';
$dhc_card = $dhc_base !== ''
    ? 'https://skulliance.io/staking/' . $dhc_base . '/card.png'
    : 'https://skulliance.io/staking/images/skulliance.png';
?>
<link rel="canonical" href="<?php echo $dhc_url; ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="<?php echo $dhc_url; ?>">
<meta property="og:title" content="DHC2 Trait Sandbox">
<meta property="og:description" content="Assemble a Digital Hell Citizens 2 Fighter from 194 individual traits. Art by Maxingo.">
<meta property="og:image" content="<?php echo $dhc_card; ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="A Digital Hell Citizens 2 Fighter assembled from layered traits.">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="DHC2 Trait Sandbox">
<meta name="twitter:description" content="Assemble a Digital Hell Citizens 2 Fighter from 194 individual traits. Art by Maxingo.">
<meta name="twitter:image" content="<?php echo $dhc_card; ?>">
<meta name="twitter:image:alt" content="A Digital Hell Citizens 2 Fighter assembled from layered traits.">
</head>
<body>

<div class="top">
  <h1>DHC2 Trait Sandbox</h1>
  <span class="sub">Digital Hell Citizens 2: Fighters &middot; art by Maxingo</span>
  <span class="spacer"></span>
  <span class="badge">Proof of concept</span>
</div>

<?php if ($dhc_base === ''): ?>
  <div class="warn">
    <b>No trait art found.</b><br>
    This page looks for a folder containing <code>1000/</code> and <code>250/</code> next to it &mdash;
    it tried <code>web/</code>, <code>dhc/</code>, <code>dhc/web/</code> and <code>traits/</code>.<br><br>
    Upload the <code>web</code> folder into the same directory as this file, or add your path to
    the <code>$dhc_base</code> list at the top of <code>dhcsandbox.php</code>.
  </div>
<?php else: ?>
<?php echo $dhc_assembler; ?>
<?php endif; ?>
</body>
</html>
