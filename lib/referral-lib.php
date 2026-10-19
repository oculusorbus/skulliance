<?php
/**
 * lib/referral-lib.php -- invite a friend, and both of you get traits.
 *
 * A player shares their daily digest on X and the link carries ?r=<code>.
 * Anyone who signs in through it and is either BRAND NEW or has not been
 * here for a month pays out three traits to each of them, drawn from the
 * placement_3 table rather than the ordinary run table.
 *
 * AN OPEN POST, NOT A DM. This started as a scheme for players to message
 * dormant friends, which was the wrong shape: a DM from an account with no
 * history with you is what Discord bans people for, and the platform cannot
 * see it happen anyway. A public post with an open offer recruits strangers
 * too, costs the sharer nothing, and is attributable by construction -- the
 * link is the proof.
 *
 * placement_3 is 25/30/27/15/3 against the ordinary run table's
 * 55/28/13/3.5/0.5. That gap IS the pitch; three draws from the run table
 * would be three commons and nobody would bother twice.
 *
 * NO NEW TABLE, AND THAT IS NOT A SHORTCUT. dhc_trait_drops already records
 * (user_id, source, source_detail, awarded_at) for every trait ever granted,
 * which is exactly who-invited-whom and when. The two rules this needs are
 * reads of it:
 *
 *   once per friend  ->  EXISTS source='referral' AND source_detail='invited:<id>'
 *   the daily wall   ->  dhcf_award()'s own per-source cap, already enforced
 *
 * Avoiding a CREATE TABLE matters here specifically: nothing in this codebase
 * creates tables, and when monstrocity_theme_collections never got created,
 * every write failed silently for weeks with the page still saying "Saved."
 */

define('REF_COOKIE',      'skl_ref');
define('REF_COOKIE_DAYS', 60);
define('REF_SOURCE',      'referral');
/* Dormant means what it already means on this platform: gallery.php and
   image-cache.php both treat a month since last_login as inactive. */
define('REF_DORMANT_DAYS', 30);
/*
 * THREE EACH, and the arrival's three are not generosity -- they are the
 * offer. The invite is a PUBLIC post now, not a private DM, so the only
 * thing recruiting anybody is the line "click this and get three traits
 * with a real shot at a rare one". If the arrival got one, the post would
 * be a lie, and the first person to notice would say so in the replies.
 */
define('REF_INVITER_DRAWS', 3);
define('REF_ARRIVAL_DRAWS', 3);

/**
 * A player's own code. Derived, so there is no column and nothing to migrate.
 *
 * base36 of the user id with two check characters, which is not security --
 * the link is meant to be handed around -- it only stops a typo resolving to
 * somebody else's account and paying the wrong person.
 */
function ref_code($user_id) {
	$user_id = (int) $user_id;
	if ($user_id <= 0) return '';
	$b = base_convert((string) $user_id, 10, 36);
	return $b . substr(sha1('skulliance-ref|' . $user_id), 0, 2);
}

/** The user id behind a code, or 0 if it is not one of ours. */
function ref_decode($code) {
	$code = strtolower(trim((string) $code));
	if (!preg_match('/^[0-9a-z]{3,12}$/', $code)) return 0;
	$body  = substr($code, 0, -2);
	$check = substr($code, -2);
	if ($body === '') return 0;
	$id = (int) base_convert($body, 36, 10);
	if ($id <= 0) return 0;
	return hash_equals(substr(sha1('skulliance-ref|' . $id), 0, 2), $check) ? $id : 0;
}

/**
 * Remember a ?r= on the way in.
 *
 * A COOKIE, NOT THE QUERY STRING, because signing in leaves for Discord and
 * comes back through process-oauth.php -- a parameter on the landing page
 * does not survive that round trip, and the credit would simply never happen
 * with nothing to show it had failed.
 *
 * Safe to call from anywhere; it does nothing without a ?r=.
 */
function ref_capture() {
	if (empty($_GET['r'])) return;
	if (headers_sent()) return;
	$id = ref_decode($_GET['r']);
	if ($id <= 0) return;
	/* Already signed in as that person: a self-referral, so do not even
	   store it. */
	if (isset($_SESSION['userData']['user_id'])
	    && (int) $_SESSION['userData']['user_id'] === $id) return;
	setcookie(REF_COOKIE, ref_code($id), array(
		'expires'  => time() + REF_COOKIE_DAYS * 86400,
		'path'     => '/',
		'samesite' => 'Lax',   /* Lax, not Strict: the arrival is a click from X */
		'secure'   => !empty($_SERVER['HTTPS']),
		'httponly' => true,
	));
}

/** Clear it once it has been spent, or once it cannot be. */
function ref_forget() {
	if (headers_sent()) return;
	setcookie(REF_COOKIE, '', array('expires' => time() - 3600, 'path' => '/'));
}

/**
 * Pay out an invite, if this arrival is one.
 *
 * $prev_login is last_login AS IT WAS BEFORE THIS REQUEST UPDATED IT. The
 * caller must read it first: skulliance.php writes last_login on the way in,
 * so asking afterwards makes every returning player look active and no
 * dormant invite ever pays -- a failure with no error anywhere.
 *
 * Returns the number of traits given to the inviter, 0 for anything that was
 * not an invite.
 */
function ref_credit($conn, $arrival_id, $prev_login) {
	$arrival_id = (int) $arrival_id;
	if (!($conn instanceof mysqli) || $arrival_id <= 0) return 0;
	if (empty($_COOKIE[REF_COOKIE])) return 0;

	$inviter_id = ref_decode($_COOKIE[REF_COOKIE]);
	if ($inviter_id <= 0 || $inviter_id === $arrival_id) { ref_forget(); return 0; }

	/* NEW, OR BACK AFTER A MONTH. Anyone else is just a player following a
	   link, and paying for that would make the whole thing farmable by
	   people who already live here. */
	$isNew     = ($prev_login === null || $prev_login === '' || $prev_login === '0000-00-00 00:00:00');
	$isDormant = !$isNew && (strtotime($prev_login) < time() - REF_DORMANT_DAYS * 86400);
	if (!$isNew && !$isDormant) { ref_forget(); return 0; }

	/* The inviter has to be real. */
	$r = $conn->query("SELECT id, discord_id, username FROM users WHERE id = $inviter_id LIMIT 1");
	$inviter = $r ? $r->fetch_assoc() : null;
	if (!$inviter) { ref_forget(); return 0; }

	/* ONCE PER FRIEND, EVER. Without this one returning friend logging in
	   every month is an annuity. The ledger is the record -- see the note at
	   the top of this file for why there is no table. */
	$seen = $conn->query("SELECT 1 FROM dhc_trait_drops
	                       WHERE source = '" . $conn->real_escape_string(REF_SOURCE) . "'
	                         AND source_detail = 'invited:" . $arrival_id . "' LIMIT 1");
	if ($seen && $seen->num_rows) { ref_forget(); return 0; }

	if (!function_exists('dhcf_award')) {
		if (!is_file(__DIR__ . '/../dhcfighters-lib.php')) { ref_forget(); return 0; }
		require_once __DIR__ . '/../dhcfighters-lib.php';
	}

	/*
	 * THREE FOR THE INVITER, ONE FOR THE ARRIVAL, all on placement_3.
	 *
	 * dhcf_award() grants one trait per call and enforces the per-source
	 * daily cap itself, so a draw that would exceed it returns null and the
	 * rest simply do not happen -- which is the right behaviour and needs no
	 * handling here.
	 */
	$table = dhcf_table_for(REF_SOURCE);
	$given = 0;
	for ($i = 0; $i < REF_INVITER_DRAWS; $i++) {
		if (dhcf_award($conn, $inviter_id, 'wildcard', REF_SOURCE,
		               'invited:' . $arrival_id, $table)) $given++;
	}
	/* The arrival's own, marked the other way round so the two halves of a
	   referral are told apart in the ledger. */
	for ($i = 0; $i < REF_ARRIVAL_DRAWS; $i++) {
		dhcf_award($conn, $arrival_id, 'wildcard', REF_SOURCE, 'joined:' . $inviter_id, $table);
	}

	/*
	 * THE INVITER IS NOT HERE. dhcf_award() queues its reveal only when the
	 * awarded user is the session user -- deliberately, so a raid resolved by
	 * the defender does not show them the attacker's trait -- so the inviter
	 * gets no modal from this. They find out two ways: a DM now, and the
	 * reveal replayed on their next login by ref_replay().
	 */
	if ($given > 0 && !empty($inviter['discord_id'])) {
		if (!function_exists('sendDM') && is_file(__DIR__ . '/../message.php'))
			@include_once __DIR__ . '/../message.php';
		if (function_exists('sendDM')) {
			@sendDM($inviter['discord_id'],
				"\xF0\x9F\x92\x80 **Your invite worked.**\n\n"
				. "Someone you invited just turned up on Skulliance, and "
				. $given . " trait" . ($given === 1 ? ' is' : 's are')
				. " waiting for you.\n\nhttps://skulliance.io/staking/dhcfighters.php");
		}
	}

	ref_forget();
	return $given;
}

/**
 * Show an inviter the traits they earned while they were away.
 *
 * The ledger IS the queue -- dhcf_award()'s own comment says as much ("a
 * reveal queue is a nicety, not a ledger. The ledger is the database"). So
 * rather than persisting a reveal, this reads back the referral rows granted
 * since they were last here and hands them to the modal that already exists.
 *
 * $prev_login is again the value from BEFORE this request updated it.
 */
function ref_replay($conn, $user_id, $prev_login) {
	$user_id = (int) $user_id;
	if (!($conn instanceof mysqli) || $user_id <= 0) return array();
	if (session_status() !== PHP_SESSION_ACTIVE) return array();
	if ($prev_login === null || $prev_login === '') return array();

	$since = $conn->real_escape_string($prev_login);
	$r = $conn->query("SELECT category, slug, tier, drop_rate FROM dhc_trait_drops
	                    WHERE user_id = $user_id
	                      AND source = '" . $conn->real_escape_string(REF_SOURCE) . "'
	                      AND source_detail LIKE 'invited:%'
	                      AND awarded_at > '$since'
	                 ORDER BY awarded_at ASC LIMIT 9");
	if (!$r) return array();
	if (!function_exists('dhcf_drop_payload')) {
		if (!is_file(__DIR__ . '/../dhcfighters-lib.php')) return array();
		require_once __DIR__ . '/../dhcfighters-lib.php';
	}

	$out = array();
	while ($row = $r->fetch_assoc()) {
		/* Shaped exactly as the drop modal expects, through the one function
		   that builds that payload, so this cannot drift from a real drop. */
		$d = dhcf_drop_payload($conn, $user_id, $row['category'], $row['slug']);
		if ($d) { $d['why'] = 'invite'; $out[] = $d; }
	}
	return $out;
}
