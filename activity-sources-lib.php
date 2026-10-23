<?php
/*
 * activity-sources-lib.php -- what the Activity LEADERBOARD counts.
 *
 * NOT TO BE CONFUSED WITH activity-lib.php, which is the activity FEED --
 * logging, channels, the daily digest. This file is only the leaderboard's
 * definition of a play. They were nearly the same file: the first version
 * of this was written straight over activity-lib.php with `cat >`, which
 * destroyed 799 lines of the feed and was caught by its harness rather
 * than by me.
 *
 * WHY IT EXISTS. checkActivityLeaderboard() in db.php owns the definition
 * of a play -- which tables count, what each is worth -- and it ECHOES a
 * leaderboard rather than returning anything, so nothing else can ask it
 * a question. By the time the admin Activity page was wanted, the same
 * map had already been copied into ajax/analytics-trends.php and
 * ajax/analytics-content.php, and skullpaper/MAINTENANCE.md records the
 * rule that keeps them honest: change one, change all three.
 *
 * A fourth copy was the obvious way to build this page and the wrong one.
 * The queries and weights now live here, the leaderboard reads them from
 * here, and the admin page reads them from here.
 *
 * THE DATE FILTER IS A PARAMETER, NOT THREE BRANCHES. db.php had the
 * whole map spelled out once per period -- monthly, weekly, all-time --
 * which is why the column names drifted: skull_racer_runs uses
 * created_at, gauntlets uses ge.resolved_date, raids uses r.created_date,
 * and each of those had to be right in three places. One cutoff string
 * is threaded through one copy now.
 *
 * COLUMN NAMES ARE NOT GUESSABLE AND NOT UNIFORM. Each is taken from that
 * table's own CREATE, the same way the analytics endpoints take theirs.
 * Guessing wrong gives a silent zero for that source rather than an
 * error, because a failed query is skipped.
 */

/**
 * The per-source queries and their weights.
 *
 * $since is a 'Y-m-d' date or '' for all time. A source whose rows have
 * no usable date column simply sees the cutoff applied to the column it
 * does have -- there is no source here without one.
 */
function activity_sources($since = '') {
	/*
	 * THE CUTOFF IS A DATE OR IT IS NOTHING.
	 *
	 * These strings are concatenated straight into SQL, and db.php used to
	 * wrap its week start in real_escape_string() -- which this function
	 * has no connection to call. Every caller passes a date built by PHP
	 * today, so nothing is reachable; a shape check costs nothing and
	 * means the next caller cannot make it reachable either. Anything
	 * that is not YYYY-MM-DD, optionally with a time, is treated as no
	 * cutoff rather than being quoted and hoped for.
	 *
	 * THE TIME HALF IS LOAD-BEARING. gauntletGetWeekStart() returns a full
	 * 'Y-m-d H:i:s' because the platform's week turns at 4pm Thursday, not
	 * midnight. A date-only pattern here looked tidy and silently rejected
	 * it -- which would have quietly turned the public WEEKLY leaderboard
	 * into an all-time one, with no error anywhere.
	 */
	if (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', (string)$since)) $since = '';
	$d = $since !== '' ? "'" . $since . "'" : '';
	/* Each entry names the column the cutoff applies to, because they are
	   all different and all easy to get wrong. */
	$f = function ($col) use ($d) { return $d === '' ? '' : "AND $col >= $d"; };

	$w_t  = $f('date_created');       // transactions
	$w_m  = $f('created_date');       // missions
	$w_ge = $f('ge.resolved_date');   // gauntlets_encounters
	$w_r  = $f('r.created_date');     // raids
	$w_e  = $f('date_created');       // encounters
	$w_ss = $f('date_created');       // scores (skull swap)
	$w_s  = $f('date_created');       // scores (monstrocity)
	$w_cc = $f('date_created');       // cryptcrawls
	$w_cq = $f('date_created');       // cryptconquests
	$w_sr = $f('created_at');         // skull_racer_runs -- NOT date_created
	$w_ob = $f('date_created');       // obscura_scores
	$w_gd = $f('date_created');       // guardians_scores

	/*
	 * ONE DEFINITION OF A PLAY. Completed runs only for the two card
	 * games, resolved encounters only for Gauntlets, SUM(attempts) rather
	 * than COUNT(*) for the two match-3s, finished runs for Obscura.
	 * These are the same rules db.php applied; they are simply written
	 * once now.
	 */
	return array(
		'daily'       => array("SELECT user_id, COUNT(*) AS cnt FROM transactions WHERE bonus = 1 AND amount = 30 $w_t GROUP BY user_id", 1),
		'mission'     => array("SELECT user_id, COUNT(*) AS cnt FROM missions WHERE status IN (1,2) $w_m GROUP BY user_id", 5),
		'skullswap'   => array("SELECT user_id, SUM(attempts) AS cnt FROM scores WHERE project_id = 0 $w_ss GROUP BY user_id", 5),
		'gauntlet'    => array("SELECT g.user_id, COUNT(*) AS cnt FROM gauntlets_encounters ge INNER JOIN gauntlets g ON g.id = ge.run_id WHERE ge.outcome != 'pending' $w_ge GROUP BY g.user_id", 5),
		'crawl'       => array("SELECT user_id, COUNT(*) AS cnt FROM cryptcrawls WHERE status IN ('won','lost') $w_cc GROUP BY user_id", 5),
		'conquest'    => array("SELECT user_id, COUNT(*) AS cnt FROM cryptconquests WHERE status IN ('won','lost') $w_cq GROUP BY user_id", 5),
		'racer'       => array("SELECT user_id, COUNT(*) AS cnt FROM skull_racer_runs WHERE 1=1 $w_sr GROUP BY user_id", 5),
		'obscura'     => array("SELECT user_id, COUNT(*) AS cnt FROM obscura_scores WHERE active = 0 $w_ob GROUP BY user_id", 5),
		'guardians'   => array("SELECT user_id, COUNT(*) AS cnt FROM guardians_scores WHERE 1=1 $w_gd GROUP BY user_id", 5),
		'raid'        => array("SELECT re.user_id, COUNT(*) AS cnt FROM raids r INNER JOIN realms re ON re.id = r.offense_id WHERE r.outcome IN (1,2) $w_r GROUP BY re.user_id", 15),
		'boss'        => array("SELECT user_id, COUNT(*) AS cnt FROM encounters WHERE 1=1 $w_e GROUP BY user_id", 25),
		'monstrocity' => array("SELECT user_id, SUM(attempts) AS cnt FROM scores WHERE project_id = 36 $w_s GROUP BY user_id", 50),
	);
}

/** Human labels for the sources, for a legend or a tooltip. */
function activity_labels() {
	return array(
		'daily' => 'Daily claims', 'mission' => 'Missions', 'skullswap' => 'Skull Swap',
		'gauntlet' => 'Gauntlets', 'crawl' => 'Crypt Crawl', 'conquest' => 'Crypt Conquest',
		'racer' => 'Skull Racer', 'obscura' => 'Obscura', 'guardians' => 'Realm Guardians',
		'raid' => 'Raids', 'boss' => 'Boss Battles', 'monstrocity' => 'Monstrocity',
	);
}

/**
 * Everybody's activity over the last $days, heaviest first.
 *
 * Returns array of rows: user_id, username, discord_id, avatar, total,
 * and a 'by' map of source => count. Users with no rows do not appear.
 *
 * A FAILED QUERY IS SKIPPED, not fatal -- the same bargain db.php makes.
 * A source missing its date column reports zero for a windowed view while
 * all-time looks fine, which is worth knowing when a number looks wrong.
 */
function activity_totals($conn, $days = 30) {
	$days  = max(1, (int)$days);
	$since = date('Y-m-d', strtotime('-' . $days . ' day'));
	$src   = activity_sources($since);

	$blank = array_fill_keys(array_keys($src), 0);
	$rows  = array();
	foreach ($src as $key => $q) {
		$res = @$conn->query($q[0]);
		if (!$res) continue;
		while ($r = $res->fetch_assoc()) {
			$uid = (int)$r['user_id'];
			if ($uid <= 0) continue;
			if (!isset($rows[$uid])) $rows[$uid] = array('by' => $blank, 'total' => 0);
			$n = (int)$r['cnt'];
			$rows[$uid]['by'][$key] += $n;
			$rows[$uid]['total']    += $n * $q[1];
		}
	}
	if (!$rows) return array();

	$ids = implode(',', array_map('intval', array_keys($rows)));
	$ur  = @$conn->query("SELECT id, username, discord_id, avatar FROM users WHERE id IN ($ids)");
	$out = array();
	if ($ur) while ($u = $ur->fetch_assoc()) {
		$uid = (int)$u['id'];
		if (!isset($rows[$uid])) continue;
		$out[] = array(
			'user_id'    => $uid,
			'username'   => (string)$u['username'],
			'discord_id' => (string)$u['discord_id'],
			'avatar'     => (string)$u['avatar'],
			'total'      => $rows[$uid]['total'],
			'by'         => $rows[$uid]['by'],
		);
	}
	usort($out, function ($a, $b) {
		if ($b['total'] !== $a['total']) return $b['total'] - $a['total'];
		return strcasecmp($a['username'], $b['username']);
	});
	return $out;
}
