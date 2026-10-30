<?php
/**
 * lib/outreach-log.php — who has already been written to.
 *
 * WHY THIS EXISTS. Three hundred and forty four personal invites is
 * not one sitting, and without a record of who has had one the work
 * is not resumable: the position counter in the modal is an index
 * into the current sort, so it moves the moment the sort changes or
 * somebody signs back in and drops off the list. Marking them turns
 * an open-ended grind into a list that gets shorter.
 *
 * A FILE, NOT A TABLE, and not the nudges table either. This is the
 * owner's own working notes about his own outreach, not player state:
 * nothing in the game reads it and nothing breaks if it is lost.
 *
 * BUT NOT IN dhcrenders/, WHICH IS WHERE THIS STARTED. That directory
 * is the render cache, and the obvious thing anybody does when a
 * picture looks wrong is empty it -- which would silently delete a
 * record of three hundred messages with no way to tell which had been
 * sent. Caches are things you are allowed to throw away; this is not
 * one, so it lives in its own directory that says so.
 *
 * Still gitignored: it is operational state belonging to one install,
 * not something to carry in the repo and merge on every pull.
 *
 * KEYED BY DISCORD ID, NOT USER ID, because the id is the thing that
 * cannot change -- a display name can, and a user row could in
 * principle be rebuilt.
 */

if (!defined('OUTREACH_PATH_NAME')) define('OUTREACH_PATH_NAME', 'outreach.json');

/* The follow-up log: who has been asked why they came back and then did
   nothing. A second message, so a second file -- see outreach_path(). */
if (!defined('OUTREACH_LIST_ASK')) define('OUTREACH_LIST_ASK', 'ask');

/* And the newcomer log: who has been written to about being stuck at the
   rules gate, never staking, or leaving. Third message, third file. */
if (!defined('OUTREACH_LIST_WELCOME')) define('OUTREACH_LIST_WELCOME', 'welcome');

/*
 * TWO LOGS, NOT ONE FILE WITH PREFIXED KEYS.
 *
 * The invite and the follow-up are different messages to the same
 * person, and marking one must never make the other look sent. Keys
 * here are stripped to digits (see outreach_mark), so a namespace
 * prefix would be sanitised straight back off -- it has to be a
 * separate file.
 *
 * $which is a plain suffix, allow-listed to letters and hyphens
 * because it lands in a path.
 */
function outreach_path($which = '') {
	$which = preg_replace('/[^a-z-]/', '', strtolower((string)$which));
	$name  = $which === '' ? OUTREACH_PATH_NAME : 'outreach-' . $which . '.json';
	return __DIR__ . '/../data/' . $name;
}

/** discord_id => unix time contacted. Always an array. */
function outreach_load($which = '') {
	$p = outreach_path($which);
	if (!is_file($p)) return array();
	$raw = @file_get_contents($p);
	if ($raw === false || $raw === '') return array();
	$d = json_decode($raw, true);
	return is_array($d) ? $d : array();
}

/** Has this person been written to? */
function outreach_done($log, $discord_id) {
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	return $id !== '' && !empty($log[$id]);
}

/**
 * Record, or clear, many people in ONE write.
 *
 * NOT A LOOP OVER outreach_mark(). Marking seventy eight people that
 * way is seventy eight read-modify-write cycles on the same file, and
 * any two that overlap lose each other's changes -- the whole point
 * of a batch is that it is one read and one write.
 *
 * Returns the number of ids actually applied, or -1 if the write
 * failed, so a caller can tell "nothing selected" from "nothing
 * saved".
 */
function outreach_mark_many($ids, $on = true, $which = '') {
	if (!is_array($ids)) return 0;
	$log = outreach_load($which);
	$n = 0;
	foreach ($ids as $raw) {
		$id = preg_replace('/[^0-9]/', '', (string)$raw);
		if ($id === '') continue;
		if ($on) $log[$id] = time();
		else     unset($log[$id]);
		$n++;
	}
	if ($n === 0) return 0;
	return outreach_write($log, $which) ? $n : -1;
}

/** The single write both paths go through. */
function outreach_write($log, $which = '') {
	$path = outreach_path($which);
	$dir  = dirname($path);
	if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return false;
	/* Temp then rename, so a reader never sees half a log and concludes
	   nobody has been contacted. */
	$tmp = $path . '.' . getmypid() . '.tmp';
	if (@file_put_contents($tmp, json_encode($log)) === false) { @unlink($tmp); return false; }
	if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
	return true;
}

/**
 * Record, or clear, one person. Returns true on a successful write.
 *
 * READ-MODIFY-WRITE EVERY TIME rather than holding the log in memory:
 * the admin may have the panel open in two tabs, and the cost of
 * re-reading a few hundred keys is nothing against losing half of
 * them.
 */
function outreach_mark($discord_id, $on = true, $which = '') {
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	if ($id === '') return false;
	$log = outreach_load($which);
	if ($on) $log[$id] = time();
	else     unset($log[$id]);
	return outreach_write($log, $which);
}
