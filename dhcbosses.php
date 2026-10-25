<?php
/**
 * dhcbosses.php — the public face of the daily boss crew.
 *
 * WHY THIS EXISTS WHEN THE FEATURE LIVES ON dhcarena.php. Two reasons, and
 * neither is a second copy of the game:
 *
 *   The X share posts this URL, so it has to resolve to something worth
 *   unfurling rather than to a redirect.
 *   Somebody who is not signed in can see what is happening today, which is
 *   the only advertisement a feature like this needs.
 *
 * PUBLIC, like skullpaper.php and partner.php, and for the same reason: the
 * people most worth reaching do not have a session. So it must not include
 * skulliance.php (which sends an anonymous visitor to error.php) and it reads
 * nothing but the shared bars.
 */
include_once 'db.php';

require_once __DIR__ . '/dhcbosses-lib.php';

/* Same session restore the other public pages use: light up the member navbar
   if a cookie is already there. MERGE, never replace. */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($ck)) $_SESSION = array_merge((array)$_SESSION, $ck);
}
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
	$name = $_SESSION['userData']['name'] ?? null;
	$db_did = $_SESSION['userData']['discord_id'] ?? null;
	$db_av  = $_SESSION['userData']['avatar'] ?? null;
	if ($name !== null && $db_did && $db_av) $avatar_url = avatar_url($db_did, $db_av);
}

$db_state = dhcb_state($conn);
$db_prog  = $db_state ? dhcb_progress($db_state) : null;
$db_table = $db_prog ? dhcb_tier_table($db_prog) : 'run';
$db_labels = array('run'=>'standard', 'placement_10'=>'improved',
                   'placement_3'=>'rare', 'placement_1'=>'best');

$page_title_override = 'DHC Bosses';
$db_desc = $db_prog
	? 'Three bosses, one shared health bar each. The community has taken them to '
	  . round(100 * $db_prog['pct']) . '% today.'
	: 'Three bosses, one shared health bar each. Everybody grinds them down together.';
$extra_head = '
  <meta name="description" content="' . htmlspecialchars($db_desc, ENT_QUOTES) . '">
  <meta property="og:type" content="website">
  <meta property="og:title" content="DHC Bosses">
  <meta property="og:description" content="' . htmlspecialchars($db_desc, ENT_QUOTES) . '">
  <meta property="og:image" content="https://skulliance.io/staking/' . dhcb_art('d34thbrngr') . '">
  <meta name="twitter:card" content="summary_large_image">';

include 'header.php';
?>
<div class="container">
  <style>
  .dbp{max-width:1000px;margin:0 auto;padding:0 16px}
  .dbp h1{font-size:1.6rem;margin:0 0 6px;color:#e8eaed}
  .dbp .lede{color:#8fa8b8;font-size:.9rem;margin:0 0 20px;line-height:1.6}
  .dbp .crew{display:flex;gap:14px;flex-wrap:wrap;margin:0 0 18px}
  .dbp .b{flex:1 1 240px;min-width:0;background:#0d2030;border:1px solid rgba(255,255,255,.10);
    border-radius:8px;padding:12px}
  .dbp .b img{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:6px;display:block;
    background:#06151e;transform:translateZ(0)}
  .dbp .b.dead img{filter:grayscale(100%);opacity:.45}
  .dbp .b h2{font-size:.95rem;margin:10px 0 6px;color:#e8eaed}
  .dbp .bar{height:8px;border-radius:4px;background:rgba(255,255,255,.10);overflow:hidden}
  .dbp .bar i{display:block;height:100%;background:#c2566a;border-radius:4px}
  .dbp .b.dead .bar i{background:rgba(255,255,255,.18)}
  .dbp .hp{font-size:.74rem;color:#8fa8b8;margin-top:5px;font-variant-numeric:tabular-nums}
  .dbp .bl{font-size:.74rem;color:#5f7d90;margin-top:7px;line-height:1.5}
  .dbp .sum{font-size:.86rem;color:#8fa8b8;margin:0 0 16px}
  .dbp .sum b{color:#00c8a0}
  .dbp .cta{margin:0 0 26px}
  </style>
  <div class="dbp">
    <h1>DHC Bosses</h1>
    <?php if (!$db_state): ?>
      <p class="lede">The boss crew is not up yet.</p>
    <?php else: ?>
      <p class="lede">Three bosses stand up every day with a health bar each, and everybody
        shares them. Send a Crew of Fighters as often as your allowance lasts. Every point of
        damage anyone lands counts toward the same total, and the trait everyone who took part
        rolls for when the day closes gets better the further the crew falls. Drop all three and
        the best table unlocks.</p>

      <div class="crew">
        <?php foreach ($db_state as $b):
          $pct = $b['max_health'] > 0 ? max(0, min(100, round(100 * $b['health'] / $b['max_health']))) : 0; ?>
        <div class="b<?php echo $b['dead'] ? ' dead' : ''; ?>">
          <img loading="lazy" alt="<?php echo htmlspecialchars($b['name']); ?>"
               src="<?php echo htmlspecialchars($b['art']); ?>" onerror="this.remove()">
          <h2><?php echo htmlspecialchars($b['name']); ?></h2>
          <div class="bar"><i style="width:<?php echo $pct; ?>%"></i></div>
          <div class="hp"><?php echo $b['dead'] ? 'down'
            : number_format($b['health']) . ' / ' . number_format($b['max_health']); ?></div>
          <div class="bl"><?php echo htmlspecialchars($b['blurb']); ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <p class="sum"><b><?php echo round(100 * $db_prog['pct']); ?>%</b> of the crew is down today
        · <?php echo number_format($db_prog['dealt']); ?> of
        <?php echo number_format($db_prog['max']); ?> damage
        · rolling on the <b><?php echo $db_labels[$db_table]; ?></b> table</p>

      <p class="cta"><a class="button" href="dhcarena.php#aBossPanel">Send a Crew</a></p>
    <?php endif; ?>
  </div>
</div>
<?php include 'footer.php'; ?>
