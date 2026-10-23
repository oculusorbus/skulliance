<?php
/**
 * lib/nudge-lib.php -- one suggestion a day, for players doing one thing.
 *
 * THE TARGET IS NARROWNESS, NOT INACTIVITY, and that distinction is the
 * whole feature. Reading the Activity wall: bigredcrypto is ninth on the
 * platform by weighted score and has touched three things. scorba is on
 * 7,057 and has touched four. These are not lapsed players to win back,
 * they are some of the most active people here, running one loop. A nudge
 * aimed at low scores would miss every one of them and pester the people
 * who have already tried everything.
 *
 * So the test is how many SOURCES somebody has touched, never how much.
 *
 * ONE SUGGESTION, AND IT HAS TO BE ONE THEY CAN ACT ON NOW. A list of
 * everything they are missing is a chore; "you have 31 traits and nothing
 * built with them" is a thing to go and do. Every candidate below carries
 * its own availability test, and a candidate that cannot answer "why you,
 * today" is not offered.
 *
 * COSTS NOTHING ON MOST PAGE LOADS. The shown/snoozed check is one indexed
 * read, and the fifteen activity queries only run when that read says
 * there is a suggestion to make -- so a player sees the cost once a day,
 * and a snoozed player never.
 *
 * DEGRADES TO SILENCE. No table, no database, no activity library: no
 * nudge. This is a suggestion box on a game platform and nothing about it
 * is worth a broken page.
 */

require_once __DIR__ . '/../activity-sources-lib.php';

/*
 * FOUR OF FIFTEEN. Everyone the owner marked on the Activity wall sits at
 * four or fewer, and everyone he left alone sits at six or more -- the
 * gap is real, not a round number picked to look tidy. Named so it can be
 * moved in one place when the platform grows past fifteen sources.
 */
if (!defined('NUDGE_NARROW_MAX'))  define('NUDGE_NARROW_MAX', 4);
/* How far back "what do they actually do" looks. Long enough that a quiet
   fortnight does not make a broad player look narrow. */
if (!defined('NUDGE_WINDOW_DAYS')) define('NUDGE_WINDOW_DAYS', 60);
/* "Not now" means not for a week. */
if (!defined('NUDGE_SNOOZE_DAYS')) define('NUDGE_SNOOZE_DAYS', 7);
/*
 * DAYS BETWEEN SUGGESTIONS, and this is not 1 on purpose.
 *
 * At one a day the rotation is real -- a different card every morning,
 * never a repeat -- and it is still a card every morning for ten days
 * running, then twenty days of nothing, then the same burst again. A
 * simulated fortnight made that obvious in a way the code did not: the
 * two suggestions with a personal reason behind them ("you have 31
 * unused traits") are spent inside 48 hours and the rest arrives as a
 * checklist of games to try.
 *
 * Three days gives a suggestion room to be acted on before the next one
 * lands, and it is deliberately NUDGE_REPEAT_DAYS / 10 -- ten candidates
 * at three days each is thirty, so the rotation comes round exactly as
 * the repeat guard expires. One nudge every few days, indefinitely,
 * instead of a burst and a drought.
 */
if (!defined('NUDGE_GAP_DAYS')) define('NUDGE_GAP_DAYS', 3);

/*
 * WHEN TO ACCEPT THE ANSWER IS NO.
 *
 * Nothing here ever stopped on its own. A player who stays narrow gets a
 * card every three days forever, and "forever" is the correct answer
 * only while they might still say yes. Somebody who has been offered ten
 * different things over two months and clicked none of them has
 * answered, and asking an eleventh time is not a nudge any more.
 *
 * Shown without a single click inside the window means rest for a
 * quarter. One click of any kind resets it -- the point is distinguishing
 * "has not got round to it" from "is not interested", and a click is the
 * only evidence of the first.
 *
 * This is why nudge_log() records 'clicked' at all. It was built for
 * measurement; it turns out to be the off switch.
 */
if (!defined('NUDGE_FATIGUE_DAYS'))  define('NUDGE_FATIGUE_DAYS', 60);
if (!defined('NUDGE_FATIGUE_SHOWN')) define('NUDGE_FATIGUE_SHOWN', 10);
if (!defined('NUDGE_REST_DAYS'))     define('NUDGE_REST_DAYS', 90);
/* A suggestion they clicked is not repeated for a month -- they have seen
   it, and repeating it is how a nudge becomes nagging. */
if (!defined('NUDGE_REPEAT_DAYS')) define('NUDGE_REPEAT_DAYS', 30);

/**
 * Is the log table there? Returns false rather than throwing, and the
 * whole feature switches off.
 *
 * Checked once per request and cached, because this is asked on every
 * page load through header.php's include chain.
 */
function nudge_table($conn) {
	static $ok = null;
	if ($ok !== null) return $ok;
	$ok = false;
	if (!is_object($conn)) return $ok;
	$r = @$conn->query("SHOW TABLES LIKE 'nudges'");
	$ok = ($r && $r->num_rows > 0);
	return $ok;
}

/**
 * Everything the gate needs, in one read.
 *
 * One query rather than four, because this runs on every Launchpad load
 * for every signed-in player and three of the four answers are usually
 * "no". Counts rather than rows, so it is an index scan and a sum.
 */
function nudge_state($conn, $user_id) {
	$out = array('snoozed' => false, 'resting' => false, 'recent' => false,
	             'shown' => 0, 'clicked' => 0);
	if (!nudge_table($conn)) return $out;
	$uid = (int)$user_id;
	if ($uid <= 0) return $out;
	$gap = max(1, (int)NUDGE_GAP_DAYS);
	$fat = max(1, (int)NUDGE_FATIGUE_DAYS);
	$r = @$conn->query(
		"SELECT
		   SUM(action = 'snoozed' AND until IS NOT NULL AND until > NOW()) AS snoozed,
		   SUM(action = 'rested'  AND until IS NOT NULL AND until > NOW()) AS resting,
		   SUM(action IN ('shown','none') AND created_at > DATE_SUB(NOW(), INTERVAL $gap DAY)) AS recent,
		   SUM(action = 'shown'   AND created_at > DATE_SUB(NOW(), INTERVAL $fat DAY)) AS shown_n,
		   SUM(action = 'clicked' AND created_at > DATE_SUB(NOW(), INTERVAL $fat DAY)) AS clicked_n
		 FROM nudges WHERE user_id = $uid");
	if ($r && ($row = $r->fetch_assoc())) {
		$out['snoozed'] = !empty($row['snoozed']);
		$out['resting'] = !empty($row['resting']);
		$out['recent']  = !empty($row['recent']);
		$out['shown']   = (int)$row['shown_n'];
		$out['clicked'] = (int)$row['clicked_n'];
	}
	return $out;
}

/**
 * Offered plenty and never once taken up? Then stop asking.
 *
 * Pure, so the rule can be read and tested without a database.
 */
function nudge_fatigued($state) {
	return empty($state['clicked'])
	    && (int)$state['shown'] >= NUDGE_FATIGUE_SHOWN;
}

/**
 * Should this player be left alone right now?
 *
 * True when they have snoozed, are resting after ignoring a run of
 * suggestions, or have been shown one inside the last NUDGE_GAP_DAYS.
 * The gate in front of everything expensive.
 */
function nudge_quiet($conn, $user_id) {
	if (!nudge_table($conn)) return true;
	$st = nudge_state($conn, $user_id);
	return $st['snoozed'] || $st['resting'] || $st['recent'];
}

/** Suggestions this player has already been sent, and when. */
function nudge_history($conn, $user_id) {
	$out = array();
	if (!nudge_table($conn)) return $out;
	$uid = (int)$user_id;
	$r = @$conn->query(
		"SELECT nudge_key, MAX(created_at) AS last_at
		   FROM nudges
		  WHERE user_id = $uid AND action IN ('shown','clicked')
		  GROUP BY nudge_key");
	if ($r) while ($row = $r->fetch_assoc()) $out[$row['nudge_key']] = $row['last_at'];
	return $out;
}

/** Record something. Fail-quiet by contract. */
function nudge_log($conn, $user_id, $key, $action, $until = null) {
	if (!nudge_table($conn)) return false;
	$uid = (int)$user_id;
	if ($uid <= 0) return false;
	$k = $conn->real_escape_string(substr((string)$key, 0, 32));
	$a = $conn->real_escape_string(substr((string)$action, 0, 16));
	$u = ($until === null) ? 'NULL' : "'" . $conn->real_escape_string($until) . "'";
	return (bool)@$conn->query(
		"INSERT INTO nudges (user_id, nudge_key, action, until, created_at)
		 VALUES ($uid, '$k', '$a', $u, NOW())");
}

/**
 * What this player has actually been doing, as source => count.
 *
 * Runs the same fifteen queries the Activity page runs and keeps one
 * player's row out of each. Not filtered per user in SQL because the
 * queries live in activity-sources-lib.php and are shared with the
 * leaderboard and two analytics endpoints -- rewriting them here is how a
 * fifth copy of the definition of a play gets created, which that file
 * exists to prevent. Gated behind nudge_quiet() so it runs once a day.
 */
function nudge_footprint($conn, $user_id, $days = NUDGE_WINDOW_DAYS) {
	$uid   = (int)$user_id;
	$since = date('Y-m-d', strtotime('-' . max(1, (int)$days) . ' days'));
	$out   = array();
	foreach (activity_sources($since) as $key => $src) {
		$r = @$conn->query($src[0]);
		if (!$r) continue;                 /* a failed source is a zero, not a fatal */
		while ($row = $r->fetch_assoc()) {
			if ((int)$row['user_id'] === $uid) { $out[$key] = (int)$row['cnt']; break; }
		}
	}
	return $out;
}

/** How many different things they do. */
function nudge_breadth($footprint) {
	$n = 0;
	foreach ($footprint as $c) if ((int)$c > 0) $n++;
	return $n;
}

/**
 * Everything this player could be pointed at, best first.
 *
 * ORDERED BY HOW LITTLE STANDS BETWEEN THEM AND DOING IT. A player
 * holding unused traits can assemble a Fighter in one click with what
 * they already own, so that outranks a game they would be starting cold.
 * Each entry carries the sentence that makes it personal; a candidate
 * whose test fails is simply not in the list.
 *
 * $facts is supplied by the caller so this stays testable: it has no
 * database of its own and no session.
 */
function nudge_candidates($footprint, $facts) {
	$did = function ($k) use ($footprint) { return !empty($footprint[$k]); };
	$out = array();

	/*
	 * 1. SOMETHING THEY ALREADY OWN AND HAVE NOT USED. The strongest
	 *    nudge on the platform: no cost, no learning, and the reason is a
	 *    number from their own account.
	 *
	 *    !$did('fighter') IS NOT OPTIONAL, and this shipped without it.
	 *    Every other candidate here is guarded; this one checked whether
	 *    they had the PARTS and forgot to check whether they already do
	 *    it. bigredcrypto -- one of the accounts this feature was built
	 *    for -- has built two Fighters and holds spare traits, so the
	 *    card headed "Something else to try" offered them the thing they
	 *    already do. The whole point is widening a footprint, and a
	 *    suggestion inside it widens nothing.
	 */
	if (!$did('fighter') && !empty($facts['free_traits']) && $facts['free_traits'] >= 3) {
		$out[] = array(
			'key'    => 'fighter',
			'title'  => 'Assemble a Fighter',
			'why'    => 'You have ' . (int)$facts['free_traits'] . ' traits sitting unused. '
			          . 'Three is enough to build with.',
			'cta'    => 'Open the assembler',
			'url'    => 'dhcfighters.php',
		);
	}

	/* 2. A REALM IS THE GATEWAY TO THREE OTHER THINGS -- raids, guardians
	      and the location board -- so a player without one is locked out
	      of more than they realise. */
	if (empty($facts['has_realm'])) {
		$out[] = array(
			'key'    => 'realm',
			'title'  => 'Establish your realm',
			'why'    => 'You do not have one yet. A realm unlocks raids and Realm Guardians, '
			          . 'and it is built from NFTs you already hold.',
			'cta'    => 'Build a realm',
			'url'    => 'realms.php',
		);
	}

	/* 3..5 Things they are already equipped for and have not touched. */
	if (!empty($facts['fighters']) && $facts['fighters'] >= 3 && !$did('arena')) {
		$out[] = array(
			'key'   => 'arena',
			'title' => 'Send a Crew into DHC Arena',
			'why'   => 'You have ' . (int)$facts['fighters'] . ' Fighters built and have never fielded them. '
			         . 'Three is a Crew.',
			'cta'   => 'Enter the Arena',
			'url'   => 'dhcarena.php',
		);
	}
	if (!empty($facts['has_realm']) && !$did('guardians')) {
		$out[] = array(
			'key'   => 'guardians',
			'title' => 'Defend your realm',
			'why'   => 'Realm Guardians is played on the realm you already built, with the NFTs '
			         . 'already standing in it.',
			'cta'   => 'Hold the wall',
			'url'   => 'guardians.php',
		);
	}
	if (!empty($facts['has_realm']) && !$did('raid')) {
		$out[] = array(
			'key'   => 'raid',
			'title' => 'Raid another realm',
			'why'   => 'You have a realm and have never sent anyone out of it.',
			'cta'   => 'Pick a target',
			'url'   => 'realms.php',
		);
	}

	/*
	 * 6. DROP SHIP NEEDS NOTHING AT ALL. Four free soldiers, and they make
	 *    one complete squad -- the only game here that hands a player a
	 *    working team on arrival, which makes it the safest thing to send
	 *    somebody who owns nothing.
	 */
	if (!$did('dropship')) {
		$out[] = array(
			'key'   => 'dropship',
			'title' => 'Take a run in Drop Ship',
			'why'   => 'Four free soldiers are already waiting in your barracks, and the four of them '
			         . 'make a complete squad.',
			'cta'   => 'Deploy',
			'url'   => 'dropship/dashboard.php?project_id=1',
		);
	}

	/*
	 * 7. AND THEN THE GAMES THAT NEED NOTHING BUT A BROWSER, in the order
	 *    they take the least explaining. These have no personal fact to
	 *    offer, which is exactly why they are last.
	 */
	$open = array(
		'obscura'   => array('Try Obscura', 'Name the collection from a sliver of the art. One screen, no setup.', 'Play a round', 'obscura.php'),
		'skullswap' => array('Try Skull Swap', 'A match 3 score chase. Nothing to own, nothing to lose.', 'Play a round', 'skullswap.php'),
		'crawl'     => array('Try Crypt Crawl', 'A solo card game against the deck. One sitting, no setup.', 'Start a run', 'cryptcrawl.php'),
		'racer'     => array('Try Skull Racer', 'An arcade racer in the browser. Nothing to own.', 'Take a lap', 'skullracer.php'),
		'conquest'  => array('Try Crypt Conquest', 'Beat twelve court cards with one hand. Solo, no setup.', 'Start a run', 'cryptconquest.php'),
		'gauntlet'  => array('Run a Gauntlet', 'A roguelike run with the NFTs you already hold.', 'Enter', 'gauntlets.php'),
		'monstrocity' => array('Try Monstrocity', 'A match 3 campaign, 28 levels deep.', 'Start', 'monstrocity.php'),
		'boss'      => array('Join a Boss Battle', 'The whole community against one boss. Every hit counts.', 'Take a swing', 'monstrocity.php#boss'),
	);
	foreach ($open as $k => $o) {
		if (!$did($k)) {
			$out[] = array('key' => $k, 'title' => $o[0], 'why' => $o[1], 'cta' => $o[2], 'url' => $o[3]);
		}
	}
	return $out;
}

/**
 * The one suggestion to make, or null.
 *
 * Skips anything suggested inside NUDGE_REPEAT_DAYS, so a player who
 * ignores Obscura is offered something else next time rather than the
 * same card forever.
 */
function nudge_pick($candidates, $history, $now = null) {
	$now = $now === null ? time() : $now;
	foreach ($candidates as $c) {
		if (isset($history[$c['key']])) {
			$last = strtotime($history[$c['key']]);
			if ($last && ($now - $last) < NUDGE_REPEAT_DAYS * 86400) continue;
		}
		return $c;
	}
	/* Everything has been offered recently. Say nothing rather than
	   repeating the top of the list. */
	return null;
}

/**
 * The line that explains why they are seeing this at all.
 *
 * Names what they DO, not what they are missing -- "you have been deep in
 * Missions" reads as being noticed, where "you have not played 12 games"
 * reads as a telling off, and one of those makes somebody click.
 */
function nudge_headline($footprint) {
	$labels = activity_labels();
	arsort($footprint);
	$top = array();
	foreach ($footprint as $k => $n) {
		if ((int)$n <= 0) continue;
		$top[] = isset($labels[$k]) ? $labels[$k] : $k;
		if (count($top) === 2) break;
	}
	if (!$top)            return 'There is more here than the leaderboards show.';
	if (count($top) === 1) return 'You have been deep in ' . $top[0] . '.';
	return 'You have been deep in ' . $top[0] . ' and ' . $top[1] . '.';
}
