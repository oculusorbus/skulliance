<?php
/*
 * dhcportal.php — the DHC Portal.
 *
 * A 4x4 board. Traits take up space. Rows filled is the tier. That is
 * the whole mechanic and there is not a number in it: the board is not
 * a meter beside the hopper, it IS the hopper and it IS the meter, so a
 * player reads their own odds by looking at how full it is.
 *
 * See dhcportal-design.md for why it is shaped this way, what was
 * rejected on the way here, and the two mistakes that are recorded
 * rather than quietly fixed.
 *
 * ITS OWN PAGE, not a section. Every reveal on the platform so far has
 * been boxed -- the crafting ring is 276px inside a 392px dialog, the
 * chest is 210px -- and none of them had to sell "this is a machine you
 * feed". The art needs the viewport.
 */
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
require_once __DIR__ . '/lib/dhcportal-lib.php';
require_once __DIR__ . '/dhc-type.php';

$dhcp_uid = (int) ($_SESSION['userData']['user_id'] ?? 0);

/* The board first, because it self-heals and the tray has to be drawn
   against what is left rather than what was staged. */
$dhcp_board  = dhcp_board($conn, $dhcp_uid);
$dhcp_free   = $dhcp_uid > 0 ? dhcf_available($conn, $dhcp_uid) : array();
$dhcp_rarity = dhcf_rarity();

/*
 * THE TRAY: every free copy, one entry per copy, cheapest first.
 *
 * ONE ENTRY PER COPY rather than a stack with a count, because the board
 * holds specific drop_ids and the player is choosing which copy to
 * surrender. A pile of "x3" would have to pick one for them.
 *
 * Cheapest first because that is the order a clear-out happens in: the
 * commons are what the Portal is for, and the rares should take a
 * deliberate scroll to reach rather than sitting under the cursor.
 */
$dhcp_tray = array();
if ($dhcp_uid > 0) {
	$staged = array();
	foreach ($dhcp_board['pieces'] as $p) $staged[$p['drop_id']] = true;
	$r = @$conn->query("SELECT id, category, slug FROM dhc_trait_drops
	                     WHERE user_id = '" . $dhcp_uid . "' ORDER BY id ASC");
	$seen = array();
	if ($r) while ($x = $r->fetch_assoc()) {
		if (isset($staged[(int)$x['id']])) continue;
		$cat = $x['category']; $slug = $x['slug'];
		$free = isset($dhcp_free[$cat][$slug]) ? (int) $dhcp_free[$cat][$slug]['free'] : 0;
		$k = $cat . '|' . $slug;
		$seen[$k] = isset($seen[$k]) ? $seen[$k] + 1 : 1;
		/* Copies beyond the free count are wearing a Fighter. */
		if ($seen[$k] > $free) continue;
		$tier = isset($dhcp_rarity[$cat][$slug][0]) ? $dhcp_rarity[$cat][$slug][0] : 'common';
		$dhcp_tray[] = array('drop_id' => (int) $x['id'], 'category' => $cat,
		                     'slug' => $slug, 'tier' => $tier);
	}
	/* PAIRS ARE ONE CHIP. A tethered weapon and its strap are one object
	   and the assembler refuses to build with half of one, so the tray
	   must not offer them separately either. */
	$dhcp_tray = dhcp_pieces($dhcp_tray);
	$order = array('common' => 0, 'uncommon' => 1, 'epic' => 2, 'legendary' => 3, 'mythic' => 4);
	usort($dhcp_tray, function ($a, $b) use ($order) {
		$d = $order[$a['tier']] - $order[$b['tier']];
		return $d !== 0 ? $d : strcmp($a['slug'], $b['slug']);
	});
}

$dhcp_tables = dhcp_tables();
$dhcnav_at = 'portal';
include 'header.php';
?>
<?php echo dhc_type_styles(); ?>
<div class="container"><div class="row"><div class="col1of3" style="margin:0 auto;flex:1 1 100%;max-width:none;">
<?php include 'dhc-nav.php'; ?>

<div class="dhcp">
  <div class="dhcp-head">
    <h2>The Portal</h2>
    <p>
      Feed it what you will never use. Fill a row and it gives something back,
      fill the board and it rolls on the best table on the platform. Nothing you
      send in comes back to you. Every piece goes to another player.
    </p>
  </div>

<?php if ($dhcp_uid <= 0): ?>
  <p class="dhcp-note">Sign in to use the Portal.</p>
<?php else: ?>

  <?php if ($dhcp_board['dropped'] > 0): ?>
  <p class="dhcp-warn" role="status">
    <?php echo (int) $dhcp_board['dropped']; ?>
    piece<?php echo $dhcp_board['dropped'] === 1 ? '' : 's'; ?>
    came off your board: you built with
    <?php echo $dhcp_board['dropped'] === 1 ? 'it' : 'them'; ?> since.
  </p>
  <?php endif; ?>

  <?php /* <div>, NOT <section>. flexbox.css:1735 starts every <section>
           on the platform at opacity:0 for a scroll reveal that adds
           .active -- so all three columns rendered, measured 1960px
           wide, and were completely invisible. Reported as "nothing is
           displayed on the page at all", which is exactly what it was.

           THREE COLUMNS: the Portal, the board, your spares. DOM order
           is screen order so a keyboard walks the page the way it reads,
           and the phone stack is re-ordered in CSS rather than here --
           there it goes board, Portal, spares, because the board is what
           you came to look at and the spares list is long enough to bury
           everything under it. */ ?>
  <div class="dhcp-rig">

    <?php /* ---- 1. the portal ---- */ ?>
    <div class="dhcp-chute" aria-label="The Portal">
      <?php /* NO DRAWN SIGN. The vortex art already carries the green
               plate and the down-arrow, and the page was stacking a CSS
               copy of it directly above the real one. Two arrows saying
               the same thing, one of them redundant by construction. */ ?>
      <div class="dhcp-vortex" id="dhcpVortex" aria-hidden="true">
        <canvas id="dhcpCanvas"></canvas>
      </div>
      <button type="button" id="dhcpGo" disabled>Jettison</button>
      <p class="dhcp-say" id="dhcpSay">Fill at least one row.</p>
    </div>
    <?php /* ---- 2. the board, in the middle: it is what both sides act on ---- */ ?>
    <div class="dhcp-mid">
      <div class="dhcp-boardwrap" aria-label="Jettison board">
      <?php /* THE PANEL IS THE ART'S SHAPE, so the picture shows whole --
               and a square board in a 2:3 frame leaves room above and
               below it. That room holds the status and the readout
               rather than sitting empty, which is what it did first. */ ?>
      <div class="dhcp-status" id="dhcpStatus"></div>
      <div class="dhcp-board" id="dhcpBoard">
        <?php for ($r = 0; $r < DHCP_ROWS; $r++): ?>
        <div class="dhcp-row" data-row="<?php echo $r; ?>">
          <?php /* The row's own name, from the one place those four words
                   live -- lifted out of dhcf_band_label() for exactly this,
                   so the Portal cannot describe the same odds differently
                   from the drop modal. */ ?>
          <span class="dhcp-rowtag"><?php
            $dhcp_lab = dhcf_table_labels();
            echo htmlspecialchars(isset($dhcp_lab[$dhcp_tables[$r]]) ? $dhcp_lab[$dhcp_tables[$r]] : ''); ?></span>
          <?php for ($c = 0; $c < DHCP_COLS; $c++): ?>
          <i class="dhcp-cell"></i>
          <?php endfor; ?>
        </div>
        <?php endfor; ?>
        <div class="dhcp-pieces" id="dhcpPieces"></div>
      </div>
      </div>
      <?php /* OUTSIDE THE ART PANEL, and it stays outside. It was moved
               in when the board was centred and there was room under it;
               now the board rides at the top and the scrim is clear
               below the halfway mark, so in there it would sit on bright
               art with nothing behind it. The status line above the
               board is the only text inside, where the scrim is heavy. */ ?>
      <div class="dhcp-readout" id="dhcpReadout"></div>
    </div>

    <?php /* ---- 3. what you have, on the right where a trait picker lives everywhere else ---- */ ?>
    <div class="dhcp-pick" aria-label="Your unused traits">
      <h3>Your spares</h3>
      <?php /* CATEGORY TABS, the same way the assembler browses traits --
               a flat dump of everything was the first version and it is
               unusable past a few dozen. Built in script so the counts can
               fall as pieces move onto the board. */ ?>
      <div class="dhcp-tabs" id="dhcpTabs" role="tablist"></div>
      <div class="dhcp-rar" id="dhcpRar"></div>
      <div class="dhcp-tray" id="dhcpTray"></div>
      <p class="dhcp-empty" id="dhcpEmpty" hidden>Nothing spare here.</p>
    </div>

  </div>

<?php endif; ?>
</div>
</div></div></div>

<script>
window.DHCP = {
  cols: <?php echo DHCP_COLS; ?>,
  rows: <?php echo DHCP_ROWS; ?>,
  slots: <?php echo DHCP_SLOTS; ?>,
  sizes: <?php echo json_encode(dhcp_sizes()); ?>,
  tables: <?php echo json_encode($dhcp_tables); ?>,
  board: <?php echo json_encode($dhcp_board['pieces']); ?>,
  tray: <?php echo json_encode($dhcp_tray); ?>,
  art: '<?php echo htmlspecialchars(dhcf_art_dir(), ENT_QUOTES); ?>'
};
</script>
<?php include 'dhcportal-ui.php'; ?>
<?php
$conn->close();
?>
</html>
