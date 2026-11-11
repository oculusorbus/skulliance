<?php
/*
 * ajax/dhcportal-jettison.php — the trust boundary for the DHC Portal.
 *
 * THE PAGE DECIDES NOTHING. The board it drew may be minutes old, may be
 * a second tab, may be forged. Everything below is re-read from the
 * database and re-checked here, and the staging area's own validation
 * exists only so the player gets told early rather than politely.
 *
 * THE ONE THING THAT MUST NOT HAPPEN is a half-completed jettison: traits
 * gone, nothing back. That is the failure that costs trust permanently
 * and cannot be apologised away, so the whole move runs inside a
 * transaction and the roll happens BEFORE anything is destroyed. If
 * there is nothing to win, the player keeps their board.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../skulliance.php';
require_once __DIR__ . '/../lib/dhcportal-lib.php';

header('Content-Type: application/json');

function dhcp_out($ok, $extra = array()) {
	echo json_encode(array_merge(array('ok' => $ok), $extra));
	exit;
}

$uid = (int) ($_SESSION['userData']['user_id'] ?? 0);
if ($uid <= 0) dhcp_out(false, array('why' => 'Sign in first.'));

/*
 * RE-READ, AND SELF-HEAL FIRST. dhcp_board() drops any piece that has
 * since gone onto a Fighter, which is the double-spend the staged board
 * makes possible: the trait stays available to the assembler while it
 * sits here, deliberately, because a draft that silently locks your
 * traits is worse than one that goes stale.
 */
$board = dhcp_board($conn, $uid);
$pieces = $board['pieces'];
$slots  = $board['slots'];

if ($board['dropped'] > 0) {
	/*
	 * SOMETHING LEFT THE BOARD BETWEEN STAGING AND FIRING. Stop rather
	 * than quietly jettisoning a smaller board than the player assembled
	 * -- they charged it to a tier and the tier may have just dropped.
	 */
	dhcp_out(false, array(
		'why'     => $board['dropped'] === 1
		           ? 'One piece left your board: you built with it. Have another look.'
		           : $board['dropped'] . ' pieces left your board: you built with them. Have another look.',
		'board'   => $pieces,
		'slots'   => $slots,
		'reload'  => true,
	));
}

if (dhcp_rows($slots) < 1) {
	dhcp_out(false, array('why' => 'Fill at least one row first.'));
}

$tiers = dhcp_table($slots);
if ($tiers === null) dhcp_out(false, array('why' => 'Fill at least one row first.'));

/*
 * PRE-FLIGHT. Refusing after the traits are gone is the worst version of
 * this feature, so the pool is checked before anything moves and the
 * roll is taken before anything is destroyed.
 */
if (!dhcp_pool_remains($pieces)) {
	dhcp_out(false, array('why' => 'Nothing left to win against that board. Take something off it.'));
}
$won = dhcp_roll($pieces, $tiers);
if (!$won) {
	dhcp_out(false, array('why' => 'The Portal could not find anything to give back. Nothing was taken.'));
}

/* ---- from here the traits move ------------------------------------- */

$conn->begin_transaction();
$ids = array();
foreach ($pieces as $p) $ids[] = (int) $p['drop_id'];

/*
 * TAKEN, WITH THE OWNER NAMED IN THE DELETE. Two players firing at once
 * cannot take the same row, and a row that moved since the read above
 * simply is not deleted -- which the affected-rows check below catches
 * before anything is handed out.
 */
$del = $conn->query("DELETE FROM dhc_trait_drops
                      WHERE user_id = '$uid' AND id IN (" . implode(',', $ids) . ")");
if (!$del || $conn->affected_rows !== count($ids)) {
	$conn->rollback();
	dhcp_out(false, array('why' => 'Your board changed while that was firing. Nothing was taken.',
	                      'reload' => true));
}
dhcp_clear($conn, $uid);   /* the board is session state; see the note in the lib */

/*
 * THE WIN. Committed with source 'portal' so it announces like any other
 * drop and carries its own provenance for ever.
 */
$detail = dhcp_rows($slots) . ' row' . (dhcp_rows($slots) === 1 ? '' : 's') . ', ' . count($pieces) . ' in';
$drop = dhcf_commit_drop($conn, $uid, $won[0], $won[1], $won[2], 'portal', $detail);
if (!$drop) {
	$conn->rollback();
	dhcp_out(false, array('why' => 'The Portal jammed. Nothing was taken.'));
}

$conn->commit();

/*
 * ===== AND OUT THE OTHER SIDE ===========================================
 *
 * AFTER THE COMMIT, DELIBERATELY. The player's side of the bargain is
 * complete and durable at this point; the redistribution is a separate
 * promise and must not be able to roll back a jettison that succeeded.
 * A gift that fails to find a home is logged and retried by nobody,
 * which is a smaller harm than a jettison that un-happened.
 *
 * ONE TRAIT, ONE RECIPIENT. Sixteen pieces can reach sixteen different
 * people, which is the point -- the owner's call that lighting up the
 * server with this is fine. Each is announced in real time by
 * dhcf_commit_drop(), which is the entire woo-back.
 */
$sent = 0;
$rarity = dhcf_rarity();
foreach ($pieces as $p) {
	$to = dhcp_pick_recipient($conn, $uid);
	if ($to <= 0) { error_log('dhcportal: no recipient for ' . $p['slug']); continue; }
	$info = isset($rarity[$p['category']][$p['slug']]) ? $rarity[$p['category']][$p['slug']] : null;
	if (!$info) continue;
	if (dhcf_commit_drop($conn, $to, $p['category'], $p['slug'], $info, 'portalgift',
	                     'jettisoned by another player')) $sent++;
}

dhcp_out(true, array(
	'won'   => $drop,
	'fed'   => array_map(function ($p) {
		return array('category' => $p['category'], 'slug' => $p['slug'],
		             'tier' => $p['tier'], 'size' => $p['size']);
	}, $pieces),
	'rows'  => dhcp_rows($slots),
	'table' => dhcp_table_name($slots),
	'sent'  => $sent,
));
