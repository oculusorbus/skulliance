<?php
/**
 * lib/discord-interaction-log.php -- every interaction, and where its time went.
 *
 * WHAT THIS ANSWERS, AND WHY NOTHING ELSE DID. A member presses the rules
 * button and Discord tells them the application did not respond in time.
 * There are two completely different causes with the same symptom:
 *
 *   THE REQUEST NEVER ARRIVED. The button belongs to another application
 *     (an old BotGhost rules message still sitting in the channel), or the
 *     Interactions Endpoint URL is not saved. Nothing reaches this server,
 *     so there is nothing to find in any log.
 *
 *   THE REQUEST ARRIVED AND WAS LATE. The host queued it, or a PHP worker
 *     started cold, or a Discord API call hung.
 *
 * The endpoint used to log only when it was SLOW, which cannot tell those
 * apart: a silent log means either "the gate is healthy" or "the gate is
 * never called", and for most of a day it was read as the first while it
 * was the second. So every interaction gets a line now, healthy or not.
 *
 * TWO CLOCKS, AND THE GAP BETWEEN THEM IS THE ANSWER.
 *
 *   lag  -- Discord created the interaction, to PHP receiving the request.
 *           An interaction id is a snowflake, so the creation moment is in
 *           the id itself, to the millisecond, with no clock of ours in it.
 *           A big lag is the network or the host queueing; nothing in our
 *           code can shorten it.
 *   work -- PHP receiving the request, to PHP finishing. This is ours.
 *
 *   lag + work against Discord's 3000ms is the whole budget, and which half
 *   is eating it decides who can fix it.
 *
 * A FILE, NOT A TABLE. The endpoint deliberately does not include db.php --
 * it is public, must not load the login gate, and a database connection is
 * dead weight on a path with a 3-second budget. Named .log so the .htaccess
 * in the repository root already refuses to serve it.
 *
 * NEVER FATAL, NEVER BLOCKING. Every write is suppressed and its result
 * ignored: a full disk must cost a line of diagnostics, not somebody's
 * entry to the server.
 */

/* data/ is the same place lib/outreach-log.php keeps the owner's own
   operational notes, and is gitignored for the same reason. */
if (!defined('SK_ILOG_FILE')) define('SK_ILOG_FILE', __DIR__ . '/../data/discord-interactions.log');

/*
 * Capped, and trimmed from the FRONT. A few interactions a day makes this
 * tiny, but the endpoint is a public URL and a scanner could find it -- a
 * log that can grow without bound is a way to fill a disk. 256KB is a few
 * thousand lines, which is weeks of real traffic.
 */
if (!defined('SK_ILOG_MAX'))  define('SK_ILOG_MAX',  262144);
if (!defined('SK_ILOG_KEEP')) define('SK_ILOG_KEEP', 131072);

/* Discord's epoch, 2015-01-01, and the 22-bit shift. Same constants
   lib/welcome-channel.php uses for account age; duplicated rather than
   required, because that file pulls in the guild roster and this one runs
   on a path that must stay light. */
if (!defined('SK_ILOG_EPOCH')) define('SK_ILOG_EPOCH', 1420070400000);

/**
 * The millisecond a snowflake was created, or 0 when it cannot be read.
 *
 * Needs 64-bit PHP. A 32-bit build would silently produce nonsense rather
 * than fail, which would put a confident wrong lag on every line, so it is
 * checked rather than assumed.
 */
function sk_ilog_snowflake_ms($id) {
	if (PHP_INT_SIZE < 8) return 0;
	$id = preg_replace('/[^0-9]/', '', (string)$id);
	if ($id === '' || strlen($id) < 17) return 0;
	$ms = intdiv((int)$id, 4194304) + SK_ILOG_EPOCH;
	/* Before Discord existed, or in the future, is a bad read -- and a
	   negative lag printed next to a timeout is worse than no lag. */
	if ($ms < SK_ILOG_EPOCH || $ms > (time() + 86400) * 1000) return 0;
	return $ms;
}

/**
 * Append one line. $fields is an ordered map; values are flattened to a
 * single line each so a line is always a line.
 */
function sk_ilog($fields) {
	if (!is_array($fields) || !$fields) return false;
	$bits = array();
	foreach ($fields as $k => $v) {
		if ($v === null || $v === '') continue;
		$v = str_replace(array("\r", "\n", "\t"), ' ', (string)$v);
		$bits[] = preg_replace('/[^a-z_]/', '', (string)$k) . '=' . $v;
	}
	if (!$bits) return false;
	$line = date('Y-m-d H:i:s') . "\t" . implode("\t", $bits) . "\n";

	$f = SK_ILOG_FILE;
	$dir = dirname($f);
	if (!is_dir($dir)) @mkdir($dir, 0775, true);
	$ok = @file_put_contents($f, $line, FILE_APPEND | LOCK_EX);

	/*
	 * TRIMMED AFTER THE WRITE, NOT BEFORE. The line is the thing that
	 * matters; losing it because the rotation failed would be the wrong way
	 * round. clearstatcache() because filesize() is cached per request and
	 * this runs in the same request that just appended.
	 */
	clearstatcache(true, $f);
	if ($ok !== false && @filesize($f) > SK_ILOG_MAX) sk_ilog_trim();
	return $ok !== false;
}

/** Keep the newest SK_ILOG_KEEP bytes, cut at a line boundary. */
function sk_ilog_trim() {
	$f = SK_ILOG_FILE;
	$raw = @file_get_contents($f);
	if ($raw === false) return;
	$tail = substr($raw, -SK_ILOG_KEEP);
	/* Cut at the first newline so the oldest surviving line is whole -- half
	   a line at the top of a log is the kind of thing that gets misread. */
	$nl = strpos($tail, "\n");
	if ($nl !== false) $tail = substr($tail, $nl + 1);
	@file_put_contents($f, $tail, LOCK_EX);
}

/**
 * The newest $limit lines, newest first, each parsed into a map.
 *
 * 'at' is the timestamp; everything else is whatever the writer put there,
 * so a reader must not assume a key exists -- the fields have changed once
 * already and will again.
 */
function sk_ilog_read($limit = 60) {
	$f = SK_ILOG_FILE;
	if (!is_file($f)) return array();
	$raw = @file_get_contents($f);
	if ($raw === false || $raw === '') return array();
	$lines = array_values(array_filter(explode("\n", $raw), function ($l) { return trim($l) !== ''; }));
	$lines = array_slice($lines, -max(1, (int)$limit));
	$out = array();
	foreach (array_reverse($lines) as $l) {
		$bits = explode("\t", $l);
		$row  = array('at' => array_shift($bits));
		foreach ($bits as $b) {
			$eq = strpos($b, '=');
			if ($eq === false) continue;
			$row[substr($b, 0, $eq)] = substr($b, $eq + 1);
		}
		$out[] = $row;
	}
	return $out;
}
