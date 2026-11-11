<?php
/*
 * admin-tethers.php — who is holding half a tethered weapon.
 *
 * A tethered weapon is one weapon in two art files. The assembler will not
 * save a Fighter wearing one without the other, and the picker only offers
 * what a player OWNS while the coupling places the partner whatever they
 * own -- so an orphaned half looks like a weapon that attaches to the
 * character correctly and then cannot be saved, with nothing in the grid
 * saying why. Reported from the live site exactly that way.
 *
 * Orphans exist because a pair is only paid as a pair from the moment it is
 * tethered: everybody who drew a weapon BEFORE it got its strap is short
 * one. dhc-trait-migrate.php has repaired this twice already, but it is CLI
 * only, which is no use to somebody holding a phone.
 *
 * Reads on GET. The repair is a POST, because a side effect on GET fires on
 * every open including a prefetch.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/dhcfighters-config.php';
require_once __DIR__ . '/dhcfighters-lib.php';
admin_require();

$at_done = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repair'])) {
	list($at_pieces, $at_players) = dhcf_tether_repair($conn, dhcf_tether_orphans($conn));
	/* Redirect after POST so a refresh cannot run it twice. */
	header('Location: admin-tethers.php?done=' . (int)$at_pieces . '&who=' . (int)$at_players);
	exit;
}
if (isset($_GET['done'])) $at_done = array((int)$_GET['done'], (int)$_GET['who']);

$at_orphans = dhcf_tether_orphans($conn);
$at_pieces  = 0; $at_people = array();
foreach ($at_orphans as $o) { $at_pieces += (int)$o['owed']; $at_people[(int)$o['user_id']] = true; }

include 'header.php';
admin_chrome('tethers');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Tethered weapons</h3>
	<small>
		Each of these is one weapon in two art files. A Fighter cannot be saved wearing
		one half without the other, and the picker only shows what a player owns &mdash;
		so a missing half reads as a weapon that attaches and then will not save, with
		nothing on the grid explaining it. A pair is only paid as a pair from the moment
		it is tethered, so anyone who drew the weapon before it got its strap is short one.
	</small>

	<?php if ($at_done !== null): ?>
	<p style="color:#00c8a0"><b><?php echo $at_done[0]; ?></b> piece(s) handed to
	   <b><?php echo $at_done[1]; ?></b> player(s).</p>
	<?php endif; ?>

	<h4>Pairs</h4>
	<ul>
	<?php $at_seen = array();
	      foreach (DHCF_TETHERED as $a => $b):
	        $k = $a < $b ? "$a|$b" : "$b|$a";
	        if (isset($at_seen[$k])) continue; $at_seen[$k] = 1; ?>
		<li><code><?php echo htmlspecialchars($a); ?></code>
		    &harr; <code><?php echo htmlspecialchars($b); ?></code></li>
	<?php endforeach; ?>
	</ul>

	<h4>Outstanding</h4>
	<?php if (!$at_orphans): ?>
	<p style="color:#00c8a0">Nothing outstanding. Every holder of a tethered half holds
	   as many of its partner.</p>
	<?php else: ?>
	<p><b><?php echo $at_pieces; ?></b> piece(s) owed to
	   <b><?php echo count($at_people); ?></b> player(s).</p>
	<div style="overflow-x:auto">
	<table class="adm-t">
		<tr><th>Player</th><th>Pair</th><th>Short of</th><th>Owed</th></tr>
	<?php foreach ($at_orphans as $o): ?>
		<tr>
			<td><?php echo htmlspecialchars($o['username'] !== '' ? $o['username'] : ('#' . $o['user_id'])); ?></td>
			<td><code><?php echo htmlspecialchars($o['pair']); ?></code></td>
			<td><code><?php echo htmlspecialchars($o['slug']); ?></code></td>
			<td><?php echo (int)$o['owed']; ?></td>
		</tr>
	<?php endforeach; ?>
	</table>
	</div>
	<form method="post" style="margin-top:14px">
		<button type="submit" name="repair" value="1" class="button" style="width:auto">
			Hand out the <?php echo $at_pieces; ?> missing piece(s)</button>
		<small style="display:block;margin-top:6px">
			Counts, not presence: two guns and one strap is owed a second strap. Written
			through the same call the draw makes for a tethered partner, with announcing
			off &mdash; a backfill is not news.
		</small>
	</form>
	<?php endif; ?>
</div></div>
</html>
