<?php
/**
 * lib/week-start.php — when the platform's week turns.
 *
 * THURSDAY, 4PM, AMERICA/CHICAGO. Every weekly game on this platform runs
 * on that boundary: Skull Swap, Boss Battles, Gauntlets, Crypt Crawl,
 * Obscura, Skull Racer and Drop Ship all lock their week there.
 *
 * MOST OF THEM NEVER COMPUTE IT. They select `WHERE reward = 0` and a cron
 * at 4pm Thursday pays whatever is unpaid, so for those boards the boundary
 * IS the crontab line and there is nothing in the code to get wrong.
 *
 * THIS EXISTS FOR THE ONES THAT DO NEED THE DATE. Gauntlets needs it to
 * window its own table, and DHC Bosses needs it twice over: the crew is
 * keyed on the week, and the trait cache counts days WITHIN the week, so
 * both have to agree on where the week starts. Two copies of this
 * arithmetic would eventually disagree by an hour or a day, and a boss
 * crew that stood up on the wrong boundary would be invisible until
 * somebody noticed the cache had eight slots in it.
 *
 * CHICAGO, NOT A FIXED OFFSET. 4pm CST and 4pm CDT are different UTC
 * instants and the switch happens twice a year; naming the zone lets PHP
 * handle it. Returned in UTC because that is what comparisons against
 * stored timestamps want.
 */

/**
 * THE SAME RULE, TWICE, AND ON PURPOSE.
 *
 * skl_week_start() answers it in PHP. skl_week_start_sql() answers it in
 * MySQL. Both live here so the rule has one home even though it has two
 * engines, and neither can be changed without the other being in front of
 * you.
 *
 * WHY NOT JUST THE PHP ONE: every timestamp this platform compares against
 * is written by MySQL's NOW(), and MySQL's clock is not php.ini's on this
 * host. The Arena lost a whole class of cooldown bug to exactly that --
 * see dhc_arena_fighters.benched_until, which is written and read entirely
 * in SQL for the same reason. Anything comparing started_at to a boundary
 * has to compute that boundary in the clock that wrote started_at.
 */

/**
 * The start of the week that is currently running, as a UTC
 * 'Y-m-d H:i:s' string.
 */
function skl_week_start($now = null) {
	$tz  = new DateTimeZone('America/Chicago');
	$now = ($now instanceof DateTime) ? (clone $now)->setTimezone($tz) : new DateTime('now', $tz);
	$dow = (int)$now->format('N');          // 1 = Monday ... 4 = Thursday ... 7 = Sunday
	$back = ($dow - 4 + 7) % 7;
	$start = clone $now;
	if ($back > 0) $start->modify("-{$back} days");
	$start->setTime(16, 0, 0);
	/* Before 4pm on a Thursday still belongs to the week that began the
	   Thursday before. */
	if ($start > $now) $start->modify('-7 days');
	$start->setTimezone(new DateTimeZone('UTC'));
	return $start->format('Y-m-d H:i:s');
}

/**
 * The same instant as a date, for use as a key.
 *
 * THE CHICAGO DATE OF THAT THURSDAY, not the UTC one. 4pm Chicago is 21:00
 * or 22:00 UTC depending on the season, so both land on the same calendar
 * day in UTC today -- but naming the zone means a future change to the
 * hour cannot silently roll the key onto the next day.
 */
function skl_week_key($now = null) {
	$s = new DateTime(skl_week_start($now), new DateTimeZone('UTC'));
	$s->setTimezone(new DateTimeZone('America/Chicago'));
	return $s->format('Y-m-d');
}

/**
 * Which day of the week we are in, 0 to 6.
 *
 * COUNTED IN 24-HOUR BLOCKS FROM THE BOUNDARY, not in calendar days. The
 * week runs 4pm Thursday to 4pm Thursday, which spans EIGHT calendar dates
 * -- so a cache counting DATE(started_at) would offer eight slots in a
 * seven-day week, and a player could bank one more trait than the week is
 * supposed to hold.
 */
function skl_week_day_index($when = null, $now = null) {
	$start = new DateTime(skl_week_start($now), new DateTimeZone('UTC'));
	$at    = ($when === null) ? new DateTime('now', new DateTimeZone('UTC'))
	                          : new DateTime($when, new DateTimeZone('UTC'));
	$d = (int)floor(($at->getTimestamp() - $start->getTimestamp()) / 86400);
	return max(0, min(6, $d));
}

/**
 * The same boundary as a SQL expression, in MySQL's own clock.
 *
 * WEEKDAY() is 0 for Monday and 3 for Thursday, and is NOT locale
 * dependent -- DAYOFWEEK() is, and would move the boundary on a server
 * whose first day of the week is Sunday.
 *
 * Four cases, which is one more than it looks:
 *   Thursday at or after 16:00  -> today
 *   Thursday before 16:00       -> last Thursday, seven days back
 *   Friday to Sunday            -> back to this week's Thursday
 *   Monday to Wednesday         -> back to last week's Thursday
 */
function skl_week_start_sql() {
	return "(CASE
	           WHEN WEEKDAY(NOW()) = 3 AND TIME(NOW()) >= '16:00:00'
	             THEN TIMESTAMP(DATE(NOW()), '16:00:00')
	           WHEN WEEKDAY(NOW()) = 3
	             THEN TIMESTAMP(DATE_SUB(DATE(NOW()), INTERVAL 7 DAY), '16:00:00')
	           WHEN WEEKDAY(NOW()) > 3
	             THEN TIMESTAMP(DATE_SUB(DATE(NOW()), INTERVAL (WEEKDAY(NOW()) - 3) DAY), '16:00:00')
	           ELSE TIMESTAMP(DATE_SUB(DATE(NOW()), INTERVAL (WEEKDAY(NOW()) + 4) DAY), '16:00:00')
	         END)";
}

/** The key the week is stored under: the DATE of that Thursday. */
function skl_week_key_sql() { return 'DATE(' . skl_week_start_sql() . ')'; }

/**
 * Which 24-hour block of the week a timestamp falls in, 0 to 6.
 *
 * BLOCKS FROM THE BOUNDARY, NOT CALENDAR DAYS. The week runs 4pm Thursday
 * to 4pm Thursday and therefore touches EIGHT calendar dates, so a cache
 * counting DATE(started_at) would offer eight slots in a seven-day week
 * and a player could bank one more trait than the week holds.
 */
function skl_week_day_sql($col = 'started_at') {
	return 'LEAST(6, GREATEST(0, FLOOR(TIMESTAMPDIFF(SECOND, '
	     . skl_week_start_sql() . ', ' . $col . ') / 86400)))';
}

/**
 * The same two, anchored on a GIVEN week rather than on the clock.
 *
 * THIS EXISTS BECAUSE THE SETTLE PAID EVERYBODY THREE TRAITS.
 *
 * skl_week_day_sql() measures from "the week NOW() is in", which is right
 * while a week is running and wrong at the only moment the weekly settle
 * executes: the cron fires after 4pm Thursday, so NOW() is already inside
 * the NEW week while the rows being settled belong to the old one. Every
 * TIMESTAMPDIFF came out negative, GREATEST(0, ...) clamped all of them to
 * block 0, and a player's five separate rounds collapsed into one group --
 * paying LEAST(DHCB_WAVE_TRAITS_MAX, MAX(wave)) once instead of per round.
 *
 * Three traits for everyone who took part, whether they turned up once or
 * seven times. It looked entirely normal in the announcement.
 *
 * Anything that groups rows by round for a week it is not currently inside
 * must use these.
 */
function skl_week_start_sql_for($weekKey) {
	/* A key is the DATE of a Thursday. Anything else is a bug upstream and
	   falling back to the clock would reintroduce the fault quietly, so it
	   is refused loudly instead. */
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$weekKey))
		throw new InvalidArgumentException('week key must be Y-m-d, got ' . var_export($weekKey, true));
	return "TIMESTAMP('" . $weekKey . "', '16:00:00')";
}

function skl_week_day_sql_for($weekKey, $col = 'started_at') {
	return 'LEAST(6, GREATEST(0, FLOOR(TIMESTAMPDIFF(SECOND, '
	     . skl_week_start_sql_for($weekKey) . ', ' . $col . ') / 86400)))';
}

/**
 * And anchored on each ROW's own week, for a query spanning several.
 *
 * A single key cannot serve a range: the Siege map counts rounds across
 * thirteen weeks at once, and measuring all of them from one Thursday puts
 * twelve weeks' worth of rows outside the 0-6 window. `day` already holds
 * the Thursday each row belongs to, so it is the anchor.
 */
function skl_week_day_sql_col($weekCol = 'day', $col = 'started_at') {
	return 'LEAST(6, GREATEST(0, FLOOR(TIMESTAMPDIFF(SECOND, '
	     . "TIMESTAMP($weekCol, '16:00:00'), " . $col . ') / 86400)))';
}
