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

/**
 * Answer the player and let go of their browser, with work still to do.
 *
 * THE PLAYER IS WATCHING A TEN SECOND WAIT OTHERWISE. The redistribution
 * below this is up to sixteen recipient cascades and twenty-three
 * dhcf_commit_drop() calls, each of which posts to Discord over the
 * network. All of it used to run before the JSON was written, so the
 * reveal could not begin until every lapsed player on the platform had
 * been pinged. The player earned the win; they should not be queuing
 * behind everyone else's notification.
 *
 * THREE WAYS TO LET GO, because the right one depends on the SAPI and
 * this platform has run under more than one. fastcgi_finish_request()
 * under FPM, litespeed_finish_request() under LSAPI, and otherwise the
 * Content-Length-and-close handshake, which is what actually frees a
 * browser under mod_php. Declaring the length is the load-bearing part:
 * without it the browser holds the connection open waiting for EOF and
 * the whole exercise is pointless.
 *
 * ignore_user_abort, because the connection the work used to depend on
 * is the one being closed. Nothing after this point may touch the
 * session: it is written and closed here so the page reload that
 * follows the reveal is not blocked on the session lock.
 */
function dhcp_answer($payload) {
	$body = json_encode($payload);
	ignore_user_abort(true);
	if (!headers_sent()) {
		header('Content-Length: ' . strlen($body));
		header('Connection: close');
	}
	echo $body;
	/*
	 * ONE RELEASE PER REQUEST. webhooks.php owns this now -- every
	 * discordmsg() on the platform queues and releases through
	 * skl_discord_release() -- but it is loaded lazily, so it may not
	 * exist yet at this point in the request. Either way the flag is
	 * shared, so whichever of the two gets here first wins and the other
	 * does not try to close an already closed request.
	 */
	if (function_exists('skl_discord_release')) { skl_discord_release(); return; }
	$GLOBALS['skl_discord_released'] = true;
	if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
	if (function_exists('litespeed_finish_request')) { litespeed_finish_request(); return; }
	if (function_exists('fastcgi_finish_request'))   { fastcgi_finish_request();   return; }
	while (ob_get_level() > 0) { @ob_end_flush(); }
	@flush();
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
/* EVERY ROW OF EVERY PIECE. A tethered pair is one piece and two rows;
   taking only the handle would leave the strap behind and brick it. */
$ids = array();
foreach ($pieces as $p) {
	foreach (isset($p['drop_ids']) ? $p['drop_ids'] : array($p['drop_id']) as $i) $ids[] = (int) $i;
}

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
 *
 * reveal: false, because this page runs its own. dhcf_commit_drop()
 * parks a win in $_SESSION['dhcf_unseen'] for dhc-dropmodal.php to
 * flush, and that modal is included from header.php on EVERY page --
 * so the vortex reveal played, the page reloaded behind it, and the
 * generic trait modal opened on the same trait.
 *
 * announce: false TOO, but only deferred, not skipped. One jettison can
 * post two dozen messages to Discord, and the player's own win posting
 * FIRST buries it under everything that follows. It goes out last, at
 * the foot of this file, so the message they are looking for is the one
 * at the bottom of the channel.
 */
$detail = dhcp_rows($slots) . ' row' . (dhcp_rows($slots) === 1 ? '' : 's') . ', ' . count($pieces) . ' in';
$drop = dhcf_commit_drop($conn, $uid, $won[0], $won[1], $won[2], 'portal', $detail, false, false);
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
 *
 * AND IT RUNS AFTER THE PLAYER HAS BEEN ANSWERED. See dhcp_answer()
 * above: the reveal is already playing while this loop works. The
 * ordering that matters is unchanged -- the player's trait is committed
 * and the transaction closed before any of this -- so a gift that fails
 * still cannot touch a jettison that succeeded.
 *
 * 'sent' IS WHAT WENT IN, NOT WHAT LANDED. The count cannot be known
 * before the loop runs and the loop no longer runs before the answer.
 * It is the honest number anyway: dhcp_pick_recipient()'s last rung is
 * every active player, so the only way a piece finds no home is a
 * platform with nobody on it, and that case is logged below.
 */
dhcp_answer(array(
	'ok'    => true,
	'won'   => $drop,
	'fed'   => array_map(function ($p) {
		return array('category' => $p['category'], 'slug' => $p['slug'],
		             'tier' => $p['tier'], 'size' => $p['size']);
	}, $pieces),
	'rows'  => dhcp_rows($slots),
	'table' => dhcp_table_name($slots),
	'sent'  => count($pieces),
));

/* The browser is gone; this must not die halfway through handing out
   sixteen traits because of a default web timeout. */
@set_time_limit(180);

$sent = 0;
$rarity = dhcf_rarity();
foreach ($pieces as $p) {
	$to = dhcp_pick_recipient($conn, $uid);
	if ($to <= 0) { error_log('dhcportal: no recipient for ' . $p['slug']); continue; }
	/*
	 * BOTH HALVES TO THE SAME PERSON. A tethered weapon and its strap
	 * have to arrive together, or the cascade hands two lapsed players
	 * half a weapon each and recreates the exact bug the pairing exists
	 * to prevent -- on somebody who did nothing to deserve it.
	 */
	$slugs = isset($p['slugs']) ? $p['slugs'] : array($p['slug']);
	foreach ($slugs as $i => $sl) {
		$info = isset($rarity[$p['category']][$sl]) ? $rarity[$p['category']][$sl] : null;
		if (!$info) continue;
		/* The second row carries the same mark dhcf_award() uses, so a
		   paired half is never counted as a drop in its own right. */
		$detail = $i === 0 ? 'jettisoned by another player'
		                   : DHCF_PAIRED_MARK . 'jettisoned by another player';
		if (dhcf_commit_drop($conn, $to, $p['category'], $sl, $info, 'portalgift', $detail)
		    && $i === 0) $sent++;
	}
}

/* Logged rather than returned: nobody is listening any more. */
if ($sent !== count($pieces)) {
	error_log('dhcportal: ' . $sent . ' of ' . count($pieces) . ' pieces placed for user ' . $uid);
}

/*
 * AND NOW THE PLAYER'S OWN, LAST. Deferred from the commit above so it
 * lands at the bottom of the channel, under the pile of gift messages
 * this jettison just generated, instead of being the first thing posted
 * and the hardest to find.
 *
 * Buffered around the require as well as the call, the same as
 * dhcf_commit_drop() does it: loading the notifier pulls in webhooks.php
 * and its credentials file, display_errors is on platform-wide, and a
 * warning from that chain would otherwise be written into a response
 * that has already declared its Content-Length.
 */
if (!(defined('DHCF_NO_NOTIFY') && DHCF_NO_NOTIFY)
    && is_file(__DIR__ . '/../dhcfighters-notify.php')) {
	ob_start();
	require_once __DIR__ . '/../dhcfighters-notify.php';
	dhcf_notify_drop($conn, $uid, $drop, 'portal');
	ob_end_clean();
}
exit;
