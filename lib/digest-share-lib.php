<?php
/**
 * lib/digest-share-lib.php -- where a shared day poster lives.
 *
 * Shared by the endpoint that stores one (ajax/digest-share.php) and the
 * page that shows it (digest.php), so the token rule and the directory are
 * written once.
 */

/* Under images/, which is already web-served and already where generated
   pictures go. Not committed -- images reach this server by FTP and the
   directory is created on first use. */
define('DSH_DIR',  'images/digests');
define('DSH_BASE', 'https://www.skulliance.io/staking/');

/* Keep a shared poster for this long. Long enough that a link posted to X
   keeps working for months; short enough that the directory does not grow
   without bound. */
define('DSH_KEEP_DAYS', 120);

/**
 * The filename for one player's one day.
 *
 * DERIVED, NEVER SUPPLIED. A client-chosen name is a file write wherever
 * they like; this is a hash of the user and the day, so the endpoint
 * decides the path and a second share of the same day overwrites rather
 * than accumulating.
 *
 * NOT A SECRET, AND IT DOES NOT NEED TO BE. The token is guessable from a
 * user id and a date, and that is fine: a file only exists because its
 * owner pressed Share, which published it to X. There is nothing here that
 * was not already posted in public. Treating it as a capability would be
 * security theatre -- and would need a secret stored somewhere, which on
 * this host means a manual step, which is the failure mode that cost
 * Monstrocity weeks.
 */
function dsh_token($user_id, $day) {
	return substr(sha1('skulliance-digest|' . (int) $user_id . '|' . $day), 0, 20);
}

/**
 * Does this poster belong to this player?
 *
 * ?d= and ?r= ARE TWO INDEPENDENT PARAMETERS. Nothing stops a hand-made
 * url pairing one person's poster with another person's referral code, so
 * anything that puts a NAME on a shared poster has to establish that the
 * name belongs to it -- otherwise a stranger's day gets captioned with
 * whoever the url says, from a link anybody can type.
 *
 * dsh_token() is a plain hash of the user id and the day, so the claim is
 * checkable without storing anything: recompute it for every day a poster
 * could still exist for and see whether any of them match. That is
 * DSH_KEEP_DAYS hashes, about 0.07ms, and it needs no table.
 *
 * A couple of days of slack past the keep window covers a server timezone
 * either side of the day the poster was drawn for.
 */
function dsh_token_belongs_to($token, $user_id) {
	if (!dsh_valid_token($token) || (int) $user_id <= 0) return false;
	for ($i = 0; $i <= DSH_KEEP_DAYS + 2; $i++) {
		if (dsh_token($user_id, date('Y-m-d', strtotime('-' . $i . ' day'))) === $token) return true;
	}
	return false;
}

/** Is this a token shape, rather than something walking out of the directory? */
function dsh_valid_token($t) {
	return is_string($t) && preg_match('/^[0-9a-f]{20}$/', $t) === 1;
}

/**
 * Drop posters older than DSH_KEEP_DAYS.
 *
 * Opportunistic and BOUNDED: it runs on a share, which is rare, and stops
 * after a fixed number of deletions so one unlucky request never pays for
 * a year of backlog. There is no cron for this and deliberately so -- a
 * cron is another thing to install, and the directory only grows when
 * somebody shares.
 */
function dsh_prune($dir, $limit = 40) {
	$cut = time() - DSH_KEEP_DAYS * 86400;
	$n = 0;
	foreach (glob($dir . '/*.jpg') ?: array() as $f) {
		if ($n >= $limit) break;
		$mt = @filemtime($f);
		if ($mt !== false && $mt < $cut) { @unlink($f); $n++; }
	}
	return $n;
}
