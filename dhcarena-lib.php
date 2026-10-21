<?php
/* ============================================================================
   dhcarena-lib.php — persistence and economy for DHC Arena.

   The engine (dhcarena-engine.php) knows the rules and nothing else. This knows
   the database, the Crew, the allowance, the bench and the rewards. Keeping the
   two apart is what lets the rules be tested without either.

   Schema: dhcarena-schema.md. Design: dhcarena.md.
   ============================================================================ */

/* avatar_url() -- required here rather than relied on from a caller: these are
   libraries, and a page that includes one without db.php would fatal on an
   undefined function at render time. require_once, so the usual path where
   db.php already pulled it in costs nothing. */
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/dhcarena-engine.php';
require_once __DIR__ . '/dhcfighters-lib.php';      // pulls dhcfighters-config.php

define('DHCA_CREW_SIZE',      3);     // Fighters needed to enter
define('DHCA_DAILY_BATTLES',  6);     // equal for everyone -- see dhcarena.md §5
/* VOCABULARY. The code says bench -- DHCA_BENCH_*, dhca_bench(), benched_until
   -- and every word a player reads says RECOVERING. Benching is a sports term
   for being left out of a game; what is happening to a Fighter here is that it
   is hurt. The internal names are not worth a column rename, but nothing new
   that a player will read should use them. */
define('DHCA_BENCH_BASE_H',   4);     // flat, after any battle
define('DHCA_BENCH_LOSS_MIN', 4);     // extra hours on a loss at the 3-Fighter floor
define('DHCA_BENCH_LOSS_MAX', 12);    // ...rising to this for a deep Crew
/* DHCA_STALE_H is gone: nothing is forfeited on a clock. An open battle waits
   for the player to resume or forfeit it. See dhca_sweep_stale() below. */

function dhca_season() { return date('Y-m'); }

/* ---------- the Crew -------------------------------------------------------- */

/**
 * A player's Fighters with their Arena state attached, newest first.
 * `available` is the whole entry condition: not benched, right now.
 */
function dhca_crew($conn, $user_id) {
	$user_id = (int)$user_id;
	$rows = array();
	/*
	 * HOW LONG IS LEFT IS ASKED OF MYSQL, and it is the whole of a reported
	 * bug: a Fighter that won and SURVIVED was available again immediately,
	 * while a Fighter that fell was correctly unavailable for hours. Every
	 * overlay in the Crew picker said resurrecting; none ever said recovering.
	 *
	 * TWO CLOCKS. dhca_bench() writes benched_until with MySQL's
	 * DATE_ADD(NOW(), ...). This read it back with PHP's strtotime() and
	 * compared it against PHP's time() -- and the two do not have to agree.
	 * Nothing on this platform calls date_default_timezone_set(), so PHP takes
	 * php.ini's zone (UTC on this host) while MySQL takes the host's, which is
	 * BEHIND it: the newest dhc_fighters.created_at, written by MySQL NOW(),
	 * reads four and a half hours in the past against the web server's own
	 * clock on a Fighter assembled minutes earlier.
	 *
	 * So benched_until came back hours earlier than it was written, and
	 * DHCA_BENCH_BASE_H is 4: a survivor's bench expired the moment it was
	 * stored, while a fallen Fighter's 8-to-16 hours outlasted the shortfall.
	 *
	 * Same clock writes it, same clock reads it, and the offset cannot matter
	 * -- which is also how the rest of the platform does a cooldown. See the
	 * soldier training and resurrection sweeps in db.php: the whole comparison
	 * happens in SQL.
	 */
	$sql = "SELECT f.id, f.serial, f.name, f.traits, f.rarity_score,
	               a.benched_until, a.ko, a.wins, a.losses, a.season_wins, a.season_losses,
	               GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), a.benched_until), 0)) AS bench_left
	        FROM dhc_fighters f
	        LEFT JOIN dhc_arena_fighters a ON a.fighter_id = f.id
	        WHERE f.user_id = $user_id
	        ORDER BY f.rarity_score DESC, f.id DESC";
	$res = $conn->query($sql);
	/*
	 * THE `ko` COLUMN IS OPTIONAL AT RUNTIME, and that is deliberate.
	 *
	 * It was added after release, and this platform is updated by pulling the
	 * code and then running the migration by hand -- so there is always a
	 * window, however short, in which the code is ahead of the database.
	 * Naming a column that does not exist does not degrade this query, it FAILS
	 * it, and a failed crew query returns nobody: the Arena then tells a player
	 * with twelve Fighters that they have none and cannot enter, and the only
	 * way back into a battle is resume, which deliberately skips the entrance.
	 *
	 * A missing label is worth a retry. A missing Arena is not.
	 */
	if (!$res) $res = $conn->query(str_replace('a.ko, ', '', $sql));
	if (!$res) return $rows;
	while ($r = $res->fetch_assoc()) {
		$left = (int)$r['bench_left'];
		$r['traits']    = json_decode($r['traits'], true) ?: array();
		$r['available'] = ($left <= 0);
		$r['bench_left']= $left;
		/* FELL, not merely fought. A Fighter that was knocked out is being
		   brought back; one that walked off the board is only catching its
		   breath. The player sees two different words for it, so the two states
		   have to be distinguishable and cannot be inferred from the clock --
		   the remaining time depends on roster depth as well. */
		$r['fell']      = !empty($r['ko']);
		$r['display']   = ($r['name'] !== null && $r['name'] !== '')
		                  ? $r['name'] : dhcf_default_name($r['serial']);
		$rows[] = $r;
	}
	/* Decorated here so every caller gets it, and so the two lookups happen
	   once for the whole roster rather than once per card. */
	$profiles = dhca_crew_profiles($conn, $user_id);
	foreach ($rows as $i => $r) {
		$rows[$i]['profile'] = isset($profiles[(int)$r['id']])
			? $profiles[(int)$r['id']]
			: dhca_fighter_profile(array('all' => array('battles'=>0,'wins'=>0,'losses'=>0,
			    'survived_wins'=>0,'fell_in_wins'=>0,'dealt'=>0)), null);
	}
	return $rows;
}

function dhca_available(&$crew) {
	$out = array();
	foreach ($crew as $f) if ($f['available']) $out[] = $f;
	return $out;
}

/** Battles started today. The allowance is counted, never stored. */
function dhca_battles_today($conn, $user_id) {
	$res = $conn->query("SELECT COUNT(*) AS c FROM dhc_arena_battles
	                     WHERE attacker_id = ".(int)$user_id."
	                       AND DATE(started_at) = CURDATE()");
	if (!$res) return DHCA_DAILY_BATTLES;      // fail closed
	$r = $res->fetch_assoc();
	return $r ? (int)$r['c'] : 0;
}

/**
 * What a won battle did: array(rewarded flag, why-no-trait).
 *
 * Pulled out of dhca_settle() so the one rule that decides whether a pairing
 * is spent can be tested on its own -- dhca_settle() needs a live battle, a
 * board and four tables, and the rule underneath it is four lines.
 *
 * THE FLAG MEANS "A TRAIT CAME OUT OF THIS", nothing looser. dhc_arena_battles
 * .rewarded is what dhca_already_rewarded() reads to decide whether the
 * pairing may pay again, and what dhca_paid_today() reads to mark the rival
 * list -- so setting it for a win that awarded nothing costs the player the
 * trait AND the chance to win it off that rival later.
 */
function dhca_payout_outcome($already_paid, $got_drop) {
	if ($already_paid) return array(0, 'opponent');
	if (!$got_drop)    return array(0, 'cap');
	return array(1, '');
}

/** Has this pairing already paid out today? One rewarded battle per opponent. */
function dhca_already_rewarded($conn, $attacker, $defender) {
	$res = $conn->query("SELECT COUNT(*) AS c FROM dhc_arena_battles
	                     WHERE attacker_id = ".(int)$attacker."
	                       AND defender_id = ".(int)$defender."
	                       AND DATE(started_at) = CURDATE() AND rewarded = 1");
	if (!$res) return true;                     // fail closed
	$r = $res->fetch_assoc();
	return $r && (int)$r['c'] > 0;
}

/**
 * Rivals this player has ALREADY been paid for today, as a uid => true map.
 *
 * The same fact dhca_already_rewarded() checks, asked once for the whole
 * rival list instead of per battle. It exists because the rule was invisible:
 * beating someone twice in a day is a perfectly ordinary thing to do, the
 * second win pays nothing, and nothing anywhere said so -- reported as "I won
 * and got no trait, is that a bug?", which is exactly what it looks like.
 */
function dhca_paid_today($conn, $user_id) {
	$out = array();
	$res = $conn->query("SELECT DISTINCT defender_id FROM dhc_arena_battles
	                     WHERE attacker_id = ".(int)$user_id."
	                       AND DATE(started_at) = CURDATE() AND rewarded = 1");
	if (!$res) return $out;          // a failed read hides the marker, never invents one
	while ($r = $res->fetch_assoc()) $out[(int)$r['defender_id']] = true;
	return $out;
}

/** Why this player cannot start a battle right now, or '' if they can. */
function dhca_entry_block($conn, $user_id) {
	dhca_sweep_stale($conn, $user_id);
	$crew = dhca_crew($conn, $user_id);
	if (count($crew) < DHCA_CREW_SIZE)
		return 'Arena needs a Crew of '.DHCA_CREW_SIZE.' Fighters. You have '.count($crew).'.';
	if (count(dhca_available($crew)) < DHCA_CREW_SIZE) {
		$soon = null;
		foreach ($crew as $f) if (!$f['available'])
			if ($soon === null || $f['bench_left'] < $soon) $soon = $f['bench_left'];
		return 'Not enough Fighters standing — '.count(dhca_available($crew)).' of '
		     . DHCA_CREW_SIZE.' available. Next one back in '.dhca_hms($soon).'.';
	}
	if (dhca_battles_today($conn, $user_id) >= DHCA_DAILY_BATTLES)
		return 'That is all '.DHCA_DAILY_BATTLES.' battles for today. The Arena reopens in '
		     . dhca_hms(dhca_day_resets_in($conn)) . '.';
	/*
	 * ONE BATTLE AT A TIME, and this is not tidiness.
	 *
	 * Without it, a player losing a battle simply starts another: the one they
	 * walked away from never reaches dhca_finish(), so nobody is benched, no
	 * loss is recorded and the Fighters who were about to be knocked out are
	 * back on the board immediately. Abandoning would be strictly better than
	 * losing, which makes the bench -- the only real cost in the game --
	 * optional.
	 *
	 * dhca_sweep_stale() above has already forfeited anything genuinely stuck,
	 * so this can never lock somebody out of the Arena for good.
	 */
	/* Resumable for as long as they like -- the board is waiting exactly as
	   they left it, so there is nothing to gain by stalling and nothing to
	   lose by stopping. The page turns this into a resume-or-forfeit choice. */
	if (dhca_open_battle($conn, $user_id))
		return 'You have a battle in progress. Finish it or forfeit it, and the Arena reopens.';
	return '';
}

/** The open battle this player owns, as array(battle_id, age in seconds). */
function dhca_open_battle($conn, $user_id) {
	$res = $conn->query("SELECT s.battle_id, TIMESTAMPDIFF(SECOND, s.updated_at, NOW()) AS age
	                     FROM dhc_arena_state s
	                     INNER JOIN dhc_arena_battles b ON b.id = s.battle_id
	                     WHERE s.user_id = ".(int)$user_id." AND b.outcome = 0
	                     ORDER BY s.battle_id DESC LIMIT 1");
	if (!$res || !$res->num_rows) return null;
	$r = $res->fetch_assoc();
	return array('battle_id' => (int)$r['battle_id'], 'age' => (int)$r['age']);
}

/**
 * NOTHING IS FORFEITED ON A CLOCK ANY MORE.
 *
 * A battle used to be resolved as a defeat after six hours untouched.
 * That rule was doing almost no work. The thing that prevents free abandonment
 * is ONE BATTLE AT A TIME (see dhca_entry_block): the board lives in
 * dhc_arena_state, so coming back a week later puts you on the same damaged
 * Crew in the same losing position. Waiting buys nothing.
 *
 * What the timer uniquely did was decide FOR the player, silently, after an
 * arbitrary interval -- and because the sweep only ran when that same player
 * next opened the Arena, they discovered a loss they had no say in, whenever
 * they happened to return. The old comment here conceded as much.
 *
 * An open battle is now resumed or forfeited BY A PERSON. dhca_forfeit() is
 * the deliberate act, and it records what actually happened.
 *
 * This still exists for ONE case: a ledger row open with no state behind it.
 * That cannot be resumed and cannot be judged -- there is no board to judge --
 * and leaving it would lock the player out for good, which is the one outcome
 * worth avoiding automatically. It is not reachable in normal play; state is
 * deleted only when a battle resolves.
 */
function dhca_sweep_stale($conn, $user_id) {
	$open = dhca_open_battle($conn, $user_id);
	if (!$open) return false;
	$b = dhca_load($conn, $open['battle_id'], $user_id);
	if ($b) return false;          // resumable: leave it for the player to decide
	$conn->query("UPDATE dhc_arena_battles SET outcome = 2, ended_at = NOW()
	              WHERE id = ".$open['battle_id']." AND outcome = 0");
	return true;
}

/**
 * Give up an open battle, deliberately.
 *
 * EVERY FIGHTER ON THE FORFEITING SIDE IS MARKED AS FALLEN, which the old
 * automatic forfeit did not do: it ran the normal finish, so any Fighter still
 * standing banked a SURVIVAL in a battle the code itself called a defeat. A
 * Crew that gives up has not held the line, and a survival record that counts
 * walking away as surviving is not measuring anything.
 *
 * That also restores the invariant the per-Fighter numbers depend on -- a
 * defeat means every Fighter fell -- which is what makes "fell during a win"
 * derivable at all.
 */
function dhca_forfeit($conn, $user_id) {
	$open = dhca_open_battle($conn, $user_id);
	if (!$open) return array(false, 'Nothing in progress.');
	$b = dhca_load($conn, $open['battle_id'], $user_id);
	if (!$b) {
		/* No board to resolve. Close the row rather than strand the player. */
		$conn->query("UPDATE dhc_arena_battles SET outcome = 2, ended_at = NOW()
		              WHERE id = ".(int)$open['battle_id']." AND outcome = 0");
		return array(true, 'That battle could not be restored, so it has been closed.');
	}
	/* Giving up IS falling. Marked before dhca_finish() reads the ko flags. */
	foreach ($b['mine'] as $i => $f) $b['mine'][$i]['ko'] = true;
	$b['over']    = 'foes';
	$b['forfeit'] = true;          // no Discord post: see dhca_finish()
	$b['log'][]   = 'Forfeited.';
	dhca_finish($conn, $b);
	return array(true, 'Battle forfeited.');
}

/**
 * Seconds until the daily allowance resets.
 *
 * ASKED OF THE DATABASE, not of PHP. The allowance is counted with
 * DATE(started_at) = CURDATE(), so the boundary that matters is MySQL's
 * midnight -- and the two clocks agree only as long as nothing moves one of
 * their timezones. A countdown that disagrees with the rule it is describing is
 * worse than no countdown, because a player watches it reach zero and finds the
 * Arena still shut.
 *
 * PHP's own midnight is the fallback, for the case where the query fails and
 * an approximate answer beats none.
 */
function dhca_day_resets_in($conn) {
	$res = $conn->query("SELECT TIMESTAMPDIFF(SECOND, NOW(), CURDATE() + INTERVAL 1 DAY) AS s");
	if ($res && $res->num_rows) {
		$r = $res->fetch_assoc();
		if ($r && (int)$r['s'] > 0) return (int)$r['s'];
	}
	return max(60, strtotime('tomorrow') - time());
}

function dhca_hms($secs) {
	$secs = max(0, (int)$secs);
	$h = intdiv($secs, 3600); $m = intdiv($secs % 3600, 60);
	if ($h > 0) return $h.'h '.$m.'m';
	return max(1, $m).'m';
}

/* ---------- opponents ------------------------------------------------------- */

/**
 * The three opponents worth flagging in the picker, from ONE query.
 *
 *   nemesis    who you fight most -- volume, and the closest of the
 *              high-volume pairings, which is dhca_nemesis()'s ordering.
 *   tormentor  who beats you. A WARNING: the point of it is to make
 *              somebody pause before attacking.
 *   duck       who you beat. The opposite.
 *
 * A RECORD HAS TO BE ONE-SIDED TO EARN EITHER WORD. tormentor requires
 * losses > wins and duck requires wins > losses, so a 6-5 pairing is
 * neither -- without that the "worst" of a set of winning records gets
 * called a tormentor, which is the same trap the rivalry panel hit when
 * 'best against' was taken as the max of (won - lost) and a 5-6 record
 * came back as a best. It also makes the two mutually exclusive by
 * construction: nobody can be above and below you at once.
 *
 * The nemesis CAN also be one of the other two, and usually is -- the
 * person you fight most is the person you have the most record against.
 * The caller shows both chips rather than picking one.
 *
 * Returns array('nemesis'=>row|null, 'tormentor'=>row|null, 'duck'=>row|null)
 * where a row is the shape dhca_nemesis() returns, so the picker renders
 * them with the template it already has.
 */
function dhca_standouts($conn, $user_id) {
	$out = array('nemesis' => null, 'tormentor' => null, 'duck' => null);
	$uid = (int)$user_id;
	if ($uid <= 0) return $out;
	$min = (int)DHCA_NEMESIS_MIN;

	/* The same aggregate dhca_nemesis() runs, without the LIMIT, so three
	   flags cost one query rather than three. */
	$sql = "SELECT t.other AS user_id, u.username, u.discord_id, u.avatar,
	               SUM(t.n)          AS battles,
	               SUM(t.mine)       AS my_wins,
	               SUM(t.theirs)     AS their_wins,
	               SUM(t.they_came)  AS they_attacked,
	               MAX(t.last_at)    AS last_at
	        FROM (
	              SELECT defender_id AS other, 1 AS n,
	                     (outcome = 1) AS mine, (outcome = 2) AS theirs,
	                     0 AS they_came, started_at AS last_at
	              FROM dhc_arena_battles
	              WHERE attacker_id = $uid AND defender_id <> $uid AND outcome <> 0
	              UNION ALL
	              SELECT attacker_id AS other, 1 AS n,
	                     (outcome = 2) AS mine, (outcome = 1) AS theirs,
	                     1 AS they_came, started_at AS last_at
	              FROM dhc_arena_battles
	              WHERE defender_id = $uid AND attacker_id <> $uid AND outcome <> 0
	             ) t
	        INNER JOIN users u ON u.id = t.other
	        GROUP BY t.other, u.username, u.discord_id, u.avatar
	        HAVING battles >= $min";
	$res = $conn->query($sql);
	if (!$res) return $out;                       // table not there yet

	$rows = array();
	while ($r = $res->fetch_assoc()) {
		$r['battles']    = (int)$r['battles'];
		$r['my_wins']    = (int)$r['my_wins'];
		$r['their_wins'] = (int)$r['their_wins'];
		$r['net']        = $r['my_wins'] - $r['their_wins'];
		$rows[] = $r;
	}
	if (!$rows) return $out;

	foreach ($rows as $r) {
		/* Nemesis: the ORDER BY dhca_nemesis() uses, in php. */
		$score = $r['battles'] - abs($r['net']);
		$cur   = $out['nemesis'];
		if (!$cur || $score > $cur['_score']
		    || ($score === $cur['_score'] && $r['battles'] > $cur['battles'])) {
			$out['nemesis'] = $r + array('_score' => $score);
			$out['nemesis']['_score'] = $score;
		}
		/* Tormentor: the deepest losing record. Ties broken by volume, so
		   the one you have lost to most often wins the word. */
		if ($r['net'] < 0 && (!$out['tormentor']
		    || $r['net'] < $out['tormentor']['net']
		    || ($r['net'] === $out['tormentor']['net']
		        && $r['battles'] > $out['tormentor']['battles']))) {
			$out['tormentor'] = $r;
		}
		/* Sitting duck: the deepest winning record. */
		if ($r['net'] > 0 && (!$out['duck']
		    || $r['net'] > $out['duck']['net']
		    || ($r['net'] === $out['duck']['net']
		        && $r['battles'] > $out['duck']['battles']))) {
			$out['duck'] = $r;
		}
	}

	/* Fighters and best rarity, for the card template -- and the same bar
	   dhca_opponents() applies: fewer Fighters than a Crew needs and there
	   is nothing to pin, because they cannot be fielded. One query for all
	   three, which are usually two distinct people. */
	$ids = array();
	foreach ($out as $r) if ($r) $ids[(int)$r['user_id']] = true;
	if (!$ids) return $out;
	$crew = array();
	$c = $conn->query("SELECT user_id, COUNT(*) AS fighters, MAX(rarity_score) AS best
	                     FROM dhc_fighters WHERE user_id IN (" . implode(',', array_keys($ids)) . ")
	                    GROUP BY user_id");
	if ($c) while ($cr = $c->fetch_assoc()) $crew[(int)$cr['user_id']] = $cr;

	foreach ($out as $k => $r) {
		if (!$r) continue;
		$id = (int)$r['user_id'];
		$f  = isset($crew[$id]) ? (int)$crew[$id]['fighters'] : 0;
		if ($f < DHCA_CREW_SIZE) { $out[$k] = null; continue; }
		$out[$k] = array(
			'user_id'       => $id,
			'username'      => $r['username'],
			'discord_id'    => $r['discord_id'],
			'avatar'        => $r['avatar'],
			'fighters'      => $f,
			'best'          => (int)$crew[$id]['best'],
			'battles'       => $r['battles'],
			'my_wins'       => $r['my_wins'],
			'their_wins'    => $r['their_wins'],
			'they_attacked' => (int)$r['they_attacked'],
		);
	}
	return $out;
}

/**
 * Who can be challenged: anyone else with at least a full Crew saved. Their
 * Fighters are NOT checked for availability -- a defender is never benched by
 * someone else's attack, and defending costs them nothing.
 */
function dhca_opponents($conn, $user_id, $limit = 24) {
	$user_id = (int)$user_id; $limit = (int)$limit;
	$out = array();
	$sql = "SELECT u.id AS user_id, u.username, u.discord_id, u.avatar,
	               COUNT(f.id) AS fighters, MAX(f.rarity_score) AS best
	        FROM users u
	        INNER JOIN dhc_fighters f ON f.user_id = u.id
	        WHERE u.id <> $user_id
	        GROUP BY u.id
	        HAVING fighters >= ".DHCA_CREW_SIZE."
	        ORDER BY best DESC
	        LIMIT $limit";
	$res = $conn->query($sql);
	if (!$res) return $out;
	while ($r = $res->fetch_assoc()) $out[] = $r;
	return $out;
}

/* An arch nemesis needs a history, not an incident. */
define('DHCA_NEMESIS_MIN', 3);

/*
 * THE PLAYER YOU ARE LOCKED WITH.
 *
 * NOT "who beats you most" -- that is a wall, and pointing somebody at an
 * opponent who always wins is discouraging rather than motivating. A nemesis
 * is the one nothing is settled with, so the score rewards VOLUME and
 * punishes a one-sided record:
 *
 *     score = battles - |my wins - their wins|
 *
 * Ten battles at 5-5 scores 10. Ten at 10-0 scores 0. Six at 4-2 scores 4.
 *
 * BOTH DIRECTIONS. Half of this history is battles they started against you,
 * which you were never told about -- dhca_finish() benches only the attacker
 * and dhca_ladder() counts only the attacker, so a defender is given no sign
 * any of it happened. That half is the part worth surfacing.
 *
 * ALL-TIME, NOT SEASONAL. The ladder resets monthly; a grudge that resets
 * with it is not a grudge.
 *
 * The ledger needs no filtering: practice is never stored at all, and live
 * matches never touch this table ("Live never pays"), so every row here is a
 * ranked battle between two real players.
 */
function dhca_nemesis($conn, $user_id) {
	$uid = (int)$user_id;
	if ($uid <= 0) return null;
	$min = (int)DHCA_NEMESIS_MIN;

	/* outcome: 1 = the ATTACKER won, 2 = the defender won. So which column is
	   "my win" flips with which side of the row I am on. */
	$sql = "SELECT t.other AS user_id, u.username, u.discord_id, u.avatar,
	               SUM(t.n)          AS battles,
	               SUM(t.mine)       AS my_wins,
	               SUM(t.theirs)     AS their_wins,
	               SUM(t.they_came)  AS they_attacked,
	               MAX(t.last_at)    AS last_at
	        FROM (
	              SELECT defender_id AS other, 1 AS n,
	                     (outcome = 1) AS mine, (outcome = 2) AS theirs,
	                     0 AS they_came, started_at AS last_at
	              FROM dhc_arena_battles
	              WHERE attacker_id = $uid AND defender_id <> $uid AND outcome <> 0
	              UNION ALL
	              SELECT attacker_id AS other, 1 AS n,
	                     (outcome = 2) AS mine, (outcome = 1) AS theirs,
	                     1 AS they_came, started_at AS last_at
	              FROM dhc_arena_battles
	              WHERE defender_id = $uid AND attacker_id <> $uid AND outcome <> 0
	             ) t
	        INNER JOIN users u ON u.id = t.other
	        GROUP BY t.other, u.username, u.discord_id, u.avatar
	        HAVING battles >= $min
	        ORDER BY (SUM(t.n) - ABS(SUM(t.mine) - SUM(t.theirs))) DESC,
	                 battles DESC, last_at DESC
	        LIMIT 1";
	$res = $conn->query($sql);
	if (!$res || !$res->num_rows) return null;
	$r = $res->fetch_assoc();

	/* The picker renders every rival from the same shape, so the nemesis has
	   to arrive wearing it -- otherwise the card needs a second template and
	   the two drift. fighters/best come from the same place dhca_opponents()
	   reads them. */
	$c = $conn->query("SELECT COUNT(*) AS fighters, MAX(rarity_score) AS best
	                   FROM dhc_fighters WHERE user_id = ".(int)$r['user_id']);
	$cr = ($c && $c->num_rows) ? $c->fetch_assoc() : array('fighters' => 0, 'best' => 0);

	/* Fewer Fighters than a Crew needs and they cannot be fielded, so there is
	   nothing to pin -- the same bar dhca_opponents() applies. */
	if ((int)$cr['fighters'] < DHCA_CREW_SIZE) return null;

	return array(
		'user_id'       => (int)$r['user_id'],
		'username'      => $r['username'],
		'discord_id'    => $r['discord_id'],
		'avatar'        => $r['avatar'],
		'fighters'      => (int)$cr['fighters'],
		'best'          => (int)$cr['best'],
		'battles'       => (int)$r['battles'],
		'my_wins'       => (int)$r['my_wins'],
		'their_wins'    => (int)$r['their_wins'],
		'they_attacked' => (int)$r['they_attacked'],
	);
}

/**
 * The three a defender fields, and in what order.
 *
 * BY STRENGTH, NOT BY RARITY SCORE. This used to take the top three of
 * dhca_crew(), which is ordered by rarity_score -- the one number the Arena
 * deliberately refuses to let decide a fight. §4 makes the spread between two
 * traits of a tier wider than the gap between tiers, so rarity score and combat
 * strength are close to unrelated: measured across 600 generated Fighters the
 * correlation is +0.04, and from a roster of eight the top three by rarity are
 * the strongest three about one time in a hundred, fielding a Crew averaging
 * 14% weaker than the one that was available.
 *
 * Worse, the same ordering set the formation, so the highest-rarity Fighter
 * stood at the FRONT -- the rank every match of three can reach. A defender's
 * most valuable Fighter was also their most attacked.
 *
 * Now: the best three by what the engine will actually give them, arranged the
 * way anybody would arrange them -- toughest at the front to absorb the threes,
 * hardest hitter at the back where only a five reaches.
 *
 * Costed before shipping: attacker win rate falls from 64.7% to 56.7% over 300
 * battles, which is a fairer fight and still favours the side that chose it.
 * The trait economy does not move -- 3.4 expected wins a day against 3.9, and
 * the cap of three binds either way.
 *
 * Availability is still not checked, deliberately. A defender is never made
 * unavailable by someone else's attack, so a Fighter recovering from the
 * owner's OWN attack still defends for them. Nobody is left undefended for
 * having played.
 */
function dhca_defending_crew($conn, $user_id) {
	$crew = dhca_crew($conn, $user_id);
	if (count($crew) <= DHCA_CREW_SIZE) return $crew;   // no choice to make

	$rarity = dhcf_rarity();
	foreach ($crew as $i => $f) {
		$built = dhca_build_fighter(is_array($f['traits']) ? $f['traits'] : array(),
		                            '', 'd'.$i, $rarity);
		/* EFFECTIVE health and EFFECTIVE damage, not the raw numbers. Armour is
		   health you do not lose, and crit and a companion's assist are damage
		   you do not have to roll for -- ignoring them would field a Crew chosen
		   on half of what the engine is about to use. */
		$crew[$i]['_hp']  = (int)round($built['maxHp'] / max(0.5, 1 - $built['resist']));
		$crew[$i]['_pow'] = (int)round($built['power']
			* (1 + $built['critC'] * (DHCA_CRIT_MULT - 1))
			* (1 + $built['assist'] * DHCA_ASSIST_SHARE));
	}
	usort($crew, function($a, $b) {
		return ($b['_hp'] * $b['_pow']) <=> ($a['_hp'] * $a['_pow']);
	});
	$three = array_slice($crew, 0, DHCA_CREW_SIZE);

	// front takes every match of three, so the front is the one that can wear it
	usort($three, function($a, $b) { return $b['_hp'] <=> $a['_hp']; });
	$front = array_shift($three);
	// and the back, which only a five reaches, is where the damage belongs
	usort($three, function($a, $b) { return $a['_pow'] <=> $b['_pow']; });
	return array_merge(array($front), $three);
}

/* ---------- starting, saving, loading --------------------------------------- */

function dhca_start($conn, $user_id, $defender_id, $fighter_ids) {
	$user_id = (int)$user_id; $defender_id = (int)$defender_id;
	$block = dhca_entry_block($conn, $user_id);
	if ($block) return array(false, $block, null);
	if ($defender_id === $user_id) return array(false, 'Pick somebody else.', null);

	$crew = dhca_crew($conn, $user_id);
	$byId = array(); foreach ($crew as $f) $byId[(int)$f['id']] = $f;
	$mine = array();
	foreach ($fighter_ids as $fid) {
		$fid = (int)$fid;
		if (!isset($byId[$fid]))            return array(false, 'That is not your Fighter.', null);
		if (!$byId[$fid]['available'])      return array(false, $byId[$fid]['display'].' is still '
				.(!empty($byId[$fid]['fell']) ? 'resurrecting' : 'recovering').'.', null);
		$mine[] = $byId[$fid];
	}
	if (count($mine) !== DHCA_CREW_SIZE) return array(false, 'Pick '.DHCA_CREW_SIZE.' Fighters.', null);

	$foes = dhca_defending_crew($conn, $defender_id);
	if (count($foes) < DHCA_CREW_SIZE) return array(false, 'They have no Crew to field.', null);

	$rarity = dhcf_rarity();
	$seed   = random_int(1, 0x7FFFFFFE);
	$names  = array(
		'mine' => array_map(function($f){ return $f['display']; }, $mine),
		'foes' => array_map(function($f){ return $f['display']; }, $foes),
	);
	$b = dhca_new_battle(
		array_map(function($f){ return $f['traits']; }, $mine),
		array_map(function($f){ return $f['traits']; }, $foes),
		$names, $rarity, $seed);

	/*
	 * WHO YOU FOUGHT, carried in the state rather than looked up again.
	 *
	 * The share text named nobody -- "Swept the DHC Arena" reads the same
	 * whoever was on the other side, which is most of what made it dull.
	 * The client knows the rival it clicked, but not after a resume, so the
	 * name belongs in the battle rather than in the page that started it.
	 *
	 * The USERNAME, which is a Discord username. The platform stores no X
	 * handle for anybody, so this is named in words and never @-tagged --
	 * an @ in front of it would tag whichever stranger holds that name on
	 * X. Same rule dhc-fighter-modal.php follows for a Fighter's builder.
	 */
	$dname = '';
	$dq = $conn->query("SELECT username FROM users WHERE id = " . (int)$defender_id . " LIMIT 1");
	if ($dq && ($drow = $dq->fetch_assoc())) $dname = (string)$drow['username'];

	$b['meta'] = array(
		'attacker'=>$user_id, 'defender'=>$defender_id,
		'defenderName'=>$dname,
		'mineIds'=>array_map(function($f){ return (int)$f['id']; }, $mine),
		'foeIds' =>array_map(function($f){ return (int)$f['id']; }, $foes),
		'moves'=>array(),
	);

	$conn->query(sprintf(
		"INSERT INTO dhc_arena_battles (attacker_id,defender_id,seed,season,started_at)
		 VALUES (%d,%d,%d,'%s',NOW())",
		$user_id, $defender_id, $seed, $conn->real_escape_string(dhca_season())));
	$bid = (int)$conn->insert_id;
	if (!$bid) return array(false, 'Could not start the battle.', null);
	$b['meta']['battle_id'] = $bid;
	dhca_save($conn, $bid, $user_id, $b);
	return array(true, '', $b);
}

function dhca_save($conn, $battle_id, $user_id, $b) {
	$json = $conn->real_escape_string(json_encode($b));
	$conn->query("INSERT INTO dhc_arena_state (battle_id,user_id,state,updated_at)
	              VALUES (".(int)$battle_id.",".(int)$user_id.",'$json',NOW())
	              ON DUPLICATE KEY UPDATE state=VALUES(state), updated_at=NOW()");
}

function dhca_load($conn, $battle_id, $user_id) {
	$res = $conn->query("SELECT state FROM dhc_arena_state
	                     WHERE battle_id = ".(int)$battle_id."
	                       AND user_id = ".(int)$user_id." LIMIT 1");
	if (!$res || !$res->num_rows) return null;
	$r = $res->fetch_assoc();
	$b = json_decode($r['state'], true);
	return is_array($b) ? $b : null;
}

/** The battle this player is in the middle of, if any. */
function dhca_active($conn, $user_id) {
	$res = $conn->query("SELECT s.battle_id FROM dhc_arena_state s
	                     INNER JOIN dhc_arena_battles b ON b.id = s.battle_id
	                     WHERE s.user_id = ".(int)$user_id." AND b.outcome = 0
	                     ORDER BY s.battle_id DESC LIMIT 1");
	if (!$res || !$res->num_rows) return null;
	$r = $res->fetch_assoc();
	return dhca_load($conn, (int)$r['battle_id'], $user_id);
}

/* ---------- taking a turn ---------------------------------------------------- */

/**
 * One player move, then the AI's replies until it is the player's turn again.
 * Everything is decided here; the client only says which gem it slid.
 *
 * Returns array(ok, message, battle). A refusal is never explained in detail --
 * an illegal move is either a stale page or somebody poking the endpoint.
 */
function dhca_move($conn, $user_id, $battle_id, $a, $z) {
	$b = dhca_load($conn, $battle_id, $user_id);
	if (!$b)                  return array(false, 'No battle in progress.', null);
	if ($b['over'] !== null)  return array(false, 'That battle is over.', $b);
	if ($b['turn'] !== 'mine')return array(false, 'Not your turn.', $b);

	if (!dhca_play($b, 'mine', (int)$a, (int)$z))
		return array(false, 'That slide makes no match.', $b);
	$b['meta']['moves'][] = array((int)$a, (int)$z);

	// the defending Crew answers, and keeps answering while it earns extra turns
	$guard = 0;
	while ($b['over'] === null && $b['turn'] === 'foes' && $guard++ < 12) {
		$mv = dhca_ai_move($b);
		if (!$mv) { dhca_fill_board($b); $mv = dhca_ai_move($b); }
		if (!$mv) { $b['turn'] = 'mine'; break; }
		$before = $b['fx']; $beforeLog = $b['log'];
		if (!dhca_play($b, 'foes', $mv[0], $mv[1])) { $b['turn'] = 'mine'; break; }
		// one timeline AND one log for the whole exchange: dhca_play() clears both
		// at the top of every move, so without this the player only ever saw the
		// last defending reply and never their own turn.
		$b['fx']  = array_merge($before, $b['fx']);
		$b['log'] = array_merge($beforeLog, $b['log']);
		$b['meta']['moves'][] = array($mv[0], $mv[1]);
	}

	if ($b['over'] !== null) dhca_finish($conn, $b);
	else                     dhca_save($conn, $battle_id, $user_id, $b);
	return array(true, '', $b);
}

/* ---------- the end of a battle ---------------------------------------------- */

/**
 * Bench the fallen, write the record, and pay out -- once, and only for a
 * battle that earned it. Idempotent: a second call finds outcome already set
 * and does nothing, because a retried request must not pay twice.
 */
function dhca_finish($conn, &$b) {
	$m   = $b['meta'];
	$bid = (int)$m['battle_id'];

	$res = $conn->query("SELECT outcome FROM dhc_arena_battles WHERE id = $bid LIMIT 1");
	if (!$res || !$res->num_rows) return;
	$row = $res->fetch_assoc();
	if ((int)$row['outcome'] !== 0) return;          // already settled

	$won     = ($b['over'] === 'mine');
	$outcome = $won ? 1 : 2;
	$att     = (int)$m['attacker'];
	$def     = (int)$m['defender'];

	// Bench only the ATTACKER's Fighters. A defender is played by the AI and
	// never chose to be here; benching them would let anyone lock a rival out.
	$crew     = dhca_crew($conn, $att);
	$depth    = count($crew);
	$lossHrs  = dhca_bench_loss_hours($depth);
	foreach ($m['mineIds'] as $i => $fid) {
		$fell  = isset($b['mine'][$i]) ? !empty($b['mine'][$i]['ko']) : false;
		$hours = DHCA_BENCH_BASE_H + ($fell ? $lossHrs : 0);
		dhca_bench($conn, $att, (int)$fid, $hours, $fell ? false : true);
	}
	// the defender's Fighters get a record, but never a bench
	foreach ($m['foeIds'] as $i => $fid) {
		$fell = isset($b['foes'][$i]) ? !empty($b['foes'][$i]['ko']) : false;
		dhca_record($conn, $def, (int)$fid, !$fell);
	}

	/*
	 * THE LEDGER. Six rows saying who was in this battle and what happened to
	 * each of them.
	 *
	 * Nothing recorded Crew composition before this. dhc_arena_battles keeps
	 * the seed, the moves and the outcome; dhc_arena_state is deleted the
	 * moment a battle resolves. That is why one corrupted forfeit could not be
	 * repaired -- there was no record of whose record it was.
	 *
	 * The counters above stay exactly as they are, so nothing reading them
	 * breaks. This accrues alongside and becomes the thing worth trusting,
	 * because a ledger can be recomputed and a counter cannot.
	 */
	dhca_log_participants($conn, $bid, $b, $m);

	/*
	 * WHY THERE WAS NO TRAIT, not just that there wasn't one.
	 *
	 *   'opponent'  this pairing already paid today. One rewarded battle per
	 *               rival, so the second win off the same Crew is for the
	 *               ladder only.
	 *   'cap'       the win qualified and dhcf_award() still declined -- the
	 *               three Arena drops for today are spent, or the cap check
	 *               itself failed (it fails closed).
	 *
	 * THE FLAG FOLLOWS THE TRAIT, and it used to be set before the trait was
	 * known. $rewarded = 1 was written, THEN dhca_pay() ran, and the UPDATE
	 * stored 1 whether or not anything was awarded -- so a win that hit the
	 * cap paid nothing AND burned the pairing's one payout for the day.
	 * Reported as "I lost a bunch, finally beat a player, got no trait, and
	 * now the list says I already beat them today". The rival marker was
	 * telling the truth about the flag; the flag was wrong.
	 */
	$rewarded = 0;
	if ($won) {
		$alreadyPaid = dhca_already_rewarded($conn, $att, $def);
		if (!$alreadyPaid) dhca_pay($conn, $att, $b);
		list($rewarded, $why) = dhca_payout_outcome($alreadyPaid, !empty($b['drop']));
		if ($why !== '') $b['nodrop'] = $why;
	}

	$conn->query(sprintf(
		"UPDATE dhc_arena_battles
		 SET outcome=%d, rounds=%d, bombs=%d, blasts=%d, best_chain=%d,
		     rewarded=%d, moves='%s', ended_at=NOW()
		 WHERE id=%d AND outcome=0",
		$outcome, (int)$b['round'], (int)$b['stats']['bombs'], (int)$b['stats']['blasts'],
		(int)$b['stats']['best'], $rewarded,
		$conn->real_escape_string(json_encode($m['moves'])), $bid));

	$conn->query("DELETE FROM dhc_arena_state WHERE battle_id = $bid");
	$b['rewarded'] = $rewarded;
	/*
	 * A FORFEIT IS NOT ANNOUNCED, and this is about latency, not tact.
	 *
	 * dhca_announce() makes a blocking HTTP call to Discord. dhca_finish() is
	 * normally reached on the move that ends a battle, where that cost is paid
	 * once and expected -- but dhca_sweep_stale() also calls it, and the sweep
	 * runs from dhca_entry_block(), which runs on every page load and on every
	 * attempt to ENTER the Arena. That put a third-party network call on the
	 * critical path of the button a player presses to start playing.
	 *
	 * Nobody played the battle being closed here, so there is nothing to
	 * report. The ledger still records the defeat.
	 */
	if (empty($b['forfeit'])) dhca_announce($conn, $b, $won, $rewarded);
}

/**
 * Write the six participation rows for a finished battle.
 *
 * INSERT IGNORE on the (battle_id, fighter_id) key: dhca_finish() already
 * refuses to settle a battle twice, but a ledger that can double-count is
 * worse than no ledger, and the guarantee should not rest on a check made
 * somewhere else.
 *
 * The crew outcome is deliberately NOT stored. It is outcome joined against
 * side, and a second copy of a fact is a fact that can disagree with itself.
 */
function dhca_log_participants($conn, $battle_id, $b, $m) {
	$rows = array();
	foreach (array(array('mineIds', 'mine', 1), array('foeIds', 'foes', 2)) as $sideSpec) {
		list($idsKey, $stateKey, $side) = $sideSpec;
		if (empty($m[$idsKey]) || !is_array($m[$idsKey])) continue;
		foreach ($m[$idsKey] as $i => $fid) {
			$f = isset($b[$stateKey][$i]) ? $b[$stateKey][$i] : null;
			if (!$f) continue;
			$rows[] = sprintf('(%d,%d,%d,%d,%d,%d)',
				(int)$battle_id, (int)$fid, $side,
				isset($f['rank']) ? (int)$f['rank'] : $i,
				!empty($f['ko']) ? 1 : 0,
				isset($f['dealt']) ? (int)$f['dealt'] : 0);
		}
	}
	if (!$rows) return;
	$conn->query('INSERT IGNORE INTO dhc_arena_battle_fighters
	              (battle_id, fighter_id, side, `rank`, fell, dealt) VALUES ' . implode(',', $rows));
}

/**
 * Every per-Fighter number, from the ledger.
 *
 * SIX FIGURES, THREE SCOPES. Overall, battles you initiated, and battles the
 * game made it defend. The split matters because dhca_defenders() fields your
 * best three automatically -- a strong Fighter's record is mostly matchups
 * you never saw, and blending them is why the old counter was hard to act on.
 *
 * "Survived a win" is the one worth reading. A Fighter can be on the winning
 * side without having carried it, and a Crew only loses when every Fighter
 * has fallen -- so survival carries no information in a defeat and all of it
 * in a victory.
 *
 * Returns zeroes for a Fighter with no history, which is a true answer.
 */
function dhca_fighter_stats($conn, $fighter_id) {
	$fid = (int)$fighter_id;
	$z = array('battles'=>0,'wins'=>0,'losses'=>0,'survived_wins'=>0,'fell_in_wins'=>0,'dealt'=>0);
	$out = array('all'=>$z, 'attack'=>$z, 'defend'=>$z);
	$res = $conn->query(
		"SELECT p.side,
		        COUNT(*)                                            AS battles,
		        SUM(b.outcome = p.side)                             AS wins,
		        SUM(b.outcome <> p.side)                            AS losses,
		        SUM(b.outcome = p.side AND p.fell = 0)              AS survived_wins,
		        SUM(b.outcome = p.side AND p.fell = 1)              AS fell_in_wins,
		        COALESCE(SUM(p.dealt), 0)                           AS dealt
		   FROM dhc_arena_battle_fighters p
		   INNER JOIN dhc_arena_battles b ON b.id = p.battle_id
		  WHERE p.fighter_id = $fid AND b.outcome IN (1,2)
		  GROUP BY p.side");
	if (!$res) return $out;
	/* outcome 1 is an attacker win and side 1 is the attacker, so `outcome =
	   side` is "this Fighter's Crew won" for both sides at once. */
	while ($r = $res->fetch_assoc()) {
		$k = ((int)$r['side'] === 1) ? 'attack' : 'defend';
		foreach ($z as $f => $_) $out[$k][$f] = (int)$r[$f];
		foreach ($z as $f => $_) $out['all'][$f] += (int)$r[$f];
	}
	return $out;
}

/*
 * HOW MANY BATTLES BEFORE A RATE MEANS ANYTHING. Three is not a sample, it is
 * an anecdote: one unlucky front-rank battle takes a Fighter from 100% to 50%.
 * Below this it says "Unproven" and shows the count, which is the honest
 * answer and also the useful one -- it tells the player to field it more.
 */
define('DHCA_PROFILE_MIN', 5);
define('DHCA_RELIABLE_PCT', 70);   // survives this share of its wins, or better
define('DHCA_RISKY_PCT',    40);   // falls in more than half of them
define('DHCA_EDGE_PCT', 15);   // win-rate gap that makes a Fighter a specialist

/**
 * A Fighter at a glance: is it reliable, or is it risky?
 *
 * ONE LABEL AND THE FACT UNDER IT. The label is for the Crew picker, where the
 * question is "do I field this", and a label alone is a judgement a player
 * cannot check. The fact is what makes it trustworthy, and it is the thing
 * that stays true if the thresholds are ever retuned.
 *
 * RANK IS SHOWN, NOT CORRECTED FOR. A match of 3 reaches the FRONT rank, so a
 * front Fighter is hit far more often than a back one and 60% survival at the
 * front is a better Fighter than 60% at the back. Quietly normalising that
 * away would invent a number nobody can verify; printing the position it
 * actually played lets the player read the rate properly.
 *
 * Takes the output of dhca_fighter_stats() plus the rank it plays most.
 */
function dhca_fighter_profile($stats, $rank_mode = null) {
	$all  = $stats['all'];
	$b    = (int)$all['battles'];
	$wins = (int)$all['wins'];
	$pos  = ($rank_mode === null) ? '' : array('front', 'mid', 'back')[max(0, min(2, (int)$rank_mode))];

	$out = array('key' => 'unproven', 'label' => 'Unproven', 'fact' => '',
	             'pos' => $pos, 'pct' => null, 'dpb' => 0);
	$out['dpb'] = $b > 0 ? (int)round((int)$all['dealt'] / $b) : 0;

	if ($b < DHCA_PROFILE_MIN) {
		$out['fact'] = $b === 0 ? 'Not fought yet'
		             : $b . ' battle' . ($b === 1 ? '' : 's') . ' so far';
		return $out;
	}
	if ($wins < 1) {
		/* Fought enough, never won. Survival says nothing -- a Crew only
		   loses when every Fighter has fallen, so there is no win to have
		   survived. The record is the fact. */
		$out['key'] = 'winless'; $out['label'] = 'Winless';
		$out['fact'] = '0 of ' . $b . ' battles won';
		return $out;
	}

	$pct = round((int)$all['survived_wins'] / $wins * 100);
	$out['pct']  = $pct;
	$out['fact'] = 'survived ' . (int)$all['survived_wins'] . ' of ' . $wins . ' wins'
	             . ($pos !== '' ? ', mostly at ' . $pos : '');
	if      ($pct >= DHCA_RELIABLE_PCT) { $out['key'] = 'reliable'; $out['label'] = 'Reliable'; }
	elseif  ($pct <  DHCA_RISKY_PCT)    { $out['key'] = 'risky';    $out['label'] = 'Risky'; }
	else                                { $out['key'] = 'even';     $out['label'] = 'Even'; }
	return $out;
}

/**
 * Every Fighter of this player's, profiled, in two queries.
 *
 * NOT ONE QUERY PER FIGHTER. A roster runs to a dozen or more and this is
 * called on the Arena page load; per-Fighter lookups would be a dozen round
 * trips to decorate a list.
 *
 * TOLERANT OF THE TABLE NOT EXISTING, for the same reason dhca_crew() is
 * tolerant of a missing column: this platform is updated by pulling the code
 * and running the migration by hand, so there is always a window where the
 * code is ahead of the database. A missing label is worth nothing; a missing
 * Arena is worth a great deal. On failure every Fighter is simply Unproven.
 *
 * Returns fighter_id => profile.
 */
function dhca_crew_profiles($conn, $user_id) {
	$uid = (int)$user_id;
	$out = array();
	$agg = $conn->query(
		"SELECT p.fighter_id,
		        COUNT(*)                                 AS battles,
		        SUM(b.outcome = p.side)                  AS wins,
		        SUM(b.outcome <> p.side)                 AS losses,
		        SUM(b.outcome = p.side AND p.fell = 0)   AS survived_wins,
		        SUM(b.outcome = p.side AND p.fell = 1)   AS fell_in_wins,
		        COALESCE(SUM(p.dealt), 0)                AS dealt
		   FROM dhc_arena_battle_fighters p
		   INNER JOIN dhc_arena_battles b ON b.id = p.battle_id
		   INNER JOIN dhc_fighters f      ON f.id = p.fighter_id
		  WHERE f.user_id = $uid AND b.outcome IN (1,2)
		  GROUP BY p.fighter_id");
	if (!$agg) return $out;                       // table not there yet: all Unproven

	$stats = array();
	while ($r = $agg->fetch_assoc()) {
		$one = array('battles'=>(int)$r['battles'], 'wins'=>(int)$r['wins'],
		             'losses'=>(int)$r['losses'], 'survived_wins'=>(int)$r['survived_wins'],
		             'fell_in_wins'=>(int)$r['fell_in_wins'], 'dealt'=>(int)$r['dealt']);
		$stats[(int)$r['fighter_id']] = array('all'=>$one, 'attack'=>$one, 'defend'=>$one);
	}
	/* The position each one plays most, so a rate can be read properly. */
	$modes = array();
	$rk = $conn->query(
		"SELECT p.fighter_id, p.`rank`, COUNT(*) AS n
		   FROM dhc_arena_battle_fighters p
		   INNER JOIN dhc_fighters f ON f.id = p.fighter_id
		  WHERE f.user_id = $uid
		  GROUP BY p.fighter_id, p.`rank`
		  ORDER BY p.fighter_id ASC, n DESC, p.`rank` ASC");
	if ($rk) while ($r = $rk->fetch_assoc()) {
		$fid = (int)$r['fighter_id'];
		if (!isset($modes[$fid])) $modes[$fid] = (int)$r['rank'];   // ordered, so first wins
	}
	foreach ($stats as $fid => $st)
		$out[$fid] = dhca_fighter_profile($st, isset($modes[$fid]) ? $modes[$fid] : null);
	return $out;
}

/** The rank a Fighter has stood in most often, or null if it has not fought. */
function dhca_fighter_rank_mode($conn, $fighter_id) {
	$res = $conn->query("SELECT `rank`, COUNT(*) AS n FROM dhc_arena_battle_fighters
	                      WHERE fighter_id = " . (int)$fighter_id . "
	                      GROUP BY `rank` ORDER BY n DESC, `rank` ASC LIMIT 1");
	if (!$res) return null;
	$r = $res->fetch_assoc();
	return $r ? (int)$r['rank'] : null;
}

/** Survived-a-win as a percentage, or null when there is nothing to divide. */
function dhca_survivability($scope) {
	$wins = (int)$scope['wins'];
	if ($wins < 1) return null;
	return round($scope['survived_wins'] / $wins * 100, 1);
}

/**
 * How often this player's Crew has held when attacked.
 *
 * Derivable with no new table at all -- dhc_arena_battles has defender_id and
 * outcome -- and shown nowhere until now. dhca_ladder() joins on attacker_id
 * only, so no player has ever been able to see how their defence performs,
 * and for picking an opponent it is the better measure: how often attackers
 * bounce off a Crew says how hard it is to beat, where someone's attacking
 * rank says nothing about attacking them.
 */
function dhca_defence_record($conn, $user_id) {
	$uid = (int)$user_id;
	$out = array('defences' => 0, 'held' => 0, 'lost' => 0);
	$res = $conn->query(
		"SELECT COUNT(*) AS defences,
		        SUM(outcome = 2) AS held,
		        SUM(outcome = 1) AS lost
		   FROM dhc_arena_battles
		  WHERE defender_id = $uid AND outcome IN (1,2)");
	if ($res && ($r = $res->fetch_assoc())) {
		$out['defences'] = (int)$r['defences'];
		$out['held']     = (int)$r['held'];
		$out['lost']     = (int)$r['lost'];
	}
	return $out;
}

/** Longer bench for a deeper Crew, so depth buys resilience and not immunity. */
function dhca_bench_loss_hours($depth) {
	$span = DHCA_BENCH_LOSS_MAX - DHCA_BENCH_LOSS_MIN;
	$t    = max(0, min(1, ($depth - DHCA_CREW_SIZE) / 9));   // 3 Fighters -> 0, 12+ -> 1
	return DHCA_BENCH_LOSS_MIN + $span * $t;
}

function dhca_bench($conn, $user_id, $fighter_id, $hours, $won) {
	$ok = $conn->query(sprintf(
		"INSERT INTO dhc_arena_fighters
		   (fighter_id,user_id,benched_until,ko,wins,losses,season_wins,season_losses,season)
		 VALUES (%d,%d,DATE_ADD(NOW(), INTERVAL %d MINUTE),%d,%d,%d,%d,%d,'%s')
		 ON DUPLICATE KEY UPDATE
		   benched_until=VALUES(benched_until), ko=VALUES(ko),
		   wins=wins+%d, losses=losses+%d,
		   season_wins  = IF(season=VALUES(season), season_wins+%d,  %d),
		   season_losses= IF(season=VALUES(season), season_losses+%d,%d),
		   season=VALUES(season)",
		(int)$fighter_id, (int)$user_id, (int)round($hours*60),
		$won?0:1,                                  // ko: $won is "this Fighter survived"
		$won?1:0, $won?0:1, $won?1:0, $won?0:1,
		$conn->real_escape_string(dhca_season()),
		$won?1:0, $won?0:1, $won?1:0, $won?1:0, $won?0:1, $won?0:1));
	if ($ok) return;
	/* Same reason as dhca_crew(): before the ALTER has run, a battle must still
	   be able to END. Losing the ko flag costs a label; losing this write costs
	   the whole result -- the Fighters would never be marked unavailable.

	   ONLY WHEN THE FIRST ONE FAILED. $ok was never assigned, so this ran after
	   every single bench -- a second ON DUPLICATE KEY UPDATE that incremented
	   wins and losses again. Every Fighter's record was double-counted from the
	   day the fallback was added: Manta Ray Pilot's one battle read as 2 wins.
	   The ledger in dhc_arena_battle_fighters is unaffected and still says 1,
	   which is exactly why it exists. */
	$conn->query(sprintf(
		"INSERT INTO dhc_arena_fighters
		   (fighter_id,user_id,benched_until,wins,losses,season_wins,season_losses,season)
		 VALUES (%d,%d,DATE_ADD(NOW(), INTERVAL %d MINUTE),%d,%d,%d,%d,'%s')
		 ON DUPLICATE KEY UPDATE
		   benched_until=VALUES(benched_until),
		   wins=wins+%d, losses=losses+%d,
		   season_wins  = IF(season=VALUES(season), season_wins+%d,  %d),
		   season_losses= IF(season=VALUES(season), season_losses+%d,%d),
		   season=VALUES(season)",
		(int)$fighter_id, (int)$user_id, (int)round($hours*60),
		$won?1:0, $won?0:1, $won?1:0, $won?0:1,
		$conn->real_escape_string(dhca_season()),
		$won?1:0, $won?0:1, $won?1:0, $won?1:0, $won?0:1, $won?0:1));
}
/** A record with no bench -- what a defender gets. */
function dhca_record($conn, $user_id, $fighter_id, $won) {
	$conn->query(sprintf(
		"INSERT INTO dhc_arena_fighters
		   (fighter_id,user_id,wins,losses,season_wins,season_losses,season)
		 VALUES (%d,%d,%d,%d,%d,%d,'%s')
		 ON DUPLICATE KEY UPDATE
		   wins=wins+%d, losses=losses+%d,
		   season_wins  = IF(season=VALUES(season), season_wins+%d,  %d),
		   season_losses= IF(season=VALUES(season), season_losses+%d,%d),
		   season=VALUES(season)",
		(int)$fighter_id, (int)$user_id, $won?1:0, $won?0:1, $won?1:0, $won?0:1,
		$conn->real_escape_string(dhca_season()),
		$won?1:0, $won?0:1, $won?1:0, $won?1:0, $won?0:1, $won?0:1));
}

/**
 * The trait. Gated wildcard through the one function every source goes through,
 * so the 3/day cap and the ledger apply here exactly as everywhere else.
 * Banded on how far UP the winner punched, using the Crews' best rarity scores.
 */
function dhca_pay($conn, $user_id, &$b) {
	if (!function_exists('dhcf_award')) return;
	$gap = dhca_rating_gap($conn, $b);
	ob_start();
	$drop = dhcf_award($conn, $user_id, 'wildcard', 'arena',
		'beat '.dhca_username($conn, (int)$b['meta']['defender']),
		dhcf_table_for('arena', $gap));
	ob_end_clean();
	$b['drop'] = $drop;
}

/**
 * THE GAP, FROM TWO SCORES. Split out so the opponent-select screen can
 * show what a fight pays using the same arithmetic that pays it. The
 * screen and the payout disagreeing would be worse than not showing it:
 * a player picks the hard fight for odds that never existed.
 */
function dhca_gap_steps($mine_best, $foes_best) {
	return (int)round(((int)$foes_best - (int)$mine_best) / 100);
}

/**
 * THE REWARD LADDER, FOR DISPLAY, PROBED FROM THE FUNCTION THAT PAYS IT.
 *
 * dhcf_table_for('arena', $steps) is what dhca_pay() calls. Rather than
 * restate its thresholds here -- which is how two numbers drift apart --
 * this asks it for every plausible gap and collapses the runs where the
 * answer does not change. Re-band Arena in dhcfighters-config.php and
 * this follows with nothing edited.
 *
 * Returns ordered array(from, legendary, mythic, best), where `from` is
 * the smallest step count that reaches that table.
 */
function dhca_reward_bands($max_steps = 12) {
	if (!function_exists('dhcf_table_for')) return array();
	$out = array(); $prev = null;
	for ($i = 0; $i <= (int)$max_steps; $i++) {
		$t = dhcf_table_for('arena', $i);
		$sig = isset($t['legendary']) ? $t['legendary'] . '/' . $t['mythic'] : '';
		if ($sig === $prev) continue;
		$prev = $sig;
		$out[] = array('from' => $i,
		               'legendary' => (float)($t['legendary'] ?? 0),
		               'mythic'    => (float)($t['mythic'] ?? 0));
	}
	/* The top band is the one to beat, so the UI can say so. */
	$n = count($out);
	foreach ($out as $i => &$row) $row['best'] = ($i === $n - 1);
	return $out;
}

/** Defender's best score minus the attacker's, in rough 100-point steps. */
function dhca_rating_gap($conn, $b) {
	$best = function($ids) use ($conn) {
		if (!$ids) return 0;
		$in = implode(',', array_map('intval', $ids));
		$r = $conn->query("SELECT MAX(rarity_score) AS m FROM dhc_fighters WHERE id IN ($in)");
		if (!$r) return 0;
		$x = $r->fetch_assoc();
		return $x ? (int)$x['m'] : 0;
	};
	$mine = $best($b['meta']['mineIds']);
	$foes = $best($b['meta']['foeIds']);
	return dhca_gap_steps($mine, $foes);
}

function dhca_username($conn, $user_id) {
	$r = $conn->query("SELECT username FROM users WHERE id = ".(int)$user_id." LIMIT 1");
	if (!$r || !$r->num_rows) return 'a rival Crew';
	$x = $r->fetch_assoc();
	return $x['username'] !== '' ? $x['username'] : 'a rival Crew';
}

/* ---------- announcing ------------------------------------------------------- */

/** The Fighter on the winning side that actually did the work, with the row
 *  from dhc_fighters it came from -- the announce needs its traits and serial
 *  to draw it. Falls back to the strongest on paper if nothing landed a hit,
 *  which only happens to a Crew that won without swinging. */
function dhca_fiercest($conn, $b, $won) {
	$side = $won ? 'mine' : 'foes';
	$ids  = $won ? $b['meta']['mineIds'] : $b['meta']['foeIds'];
	$best = -1; $bestAt = -1;
	foreach ($b[$side] as $i => $f) {
		$score = isset($f['dealt']) ? (int)$f['dealt'] : 0;
		if ($score > $best) { $best = $score; $bestAt = $i; }
	}
	if ($bestAt < 0) return null;
	if ($best <= 0) {
		$bestAt = 0; $bp = -1;
		foreach ($b[$side] as $i => $f) if ((int)$f['power'] > $bp) { $bp = (int)$f['power']; $bestAt = $i; }
	}
	$fid = isset($ids[$bestAt]) ? (int)$ids[$bestAt] : 0;
	$row = null;
	if ($fid) {
		$r = $conn->query("SELECT serial, traits FROM dhc_fighters WHERE id = $fid LIMIT 1");
		if ($r && $r->num_rows) $row = $r->fetch_assoc();
	}
	return array('f' => $b[$side][$bestAt], 'serial' => $row ? (int)$row['serial'] : 0,
	             'traits' => $row ? (json_decode($row['traits'], true) ?: array()) : array(),
	             'dealt' => max(0, $best));
}

/** Username, Discord avatar URL and mention for one staker. */
function dhca_identity($conn, $user_id) {
	$out = array('name' => 'a rival Crew', 'avatar' => '', 'mention' => '');
	$r = $conn->query("SELECT username, discord_id, avatar FROM users WHERE id = "
	                  .(int)$user_id." LIMIT 1");
	if (!$r || !$r->num_rows) return $out;
	$u = $r->fetch_assoc();
	if ($u['username'] !== '') $out['name'] = $u['username'];
	if (!empty($u['discord_id']) && !empty($u['avatar']))
		$out['avatar'] = avatar_url($u['discord_id'], $u['avatar']);
	if (!empty($u['discord_id'])) $out['mention'] = '<@'.$u['discord_id'].'>';
	return $out;
}

/**
 * Fire and forget, like every other announcement on the platform. Buffered and
 * caught: a Discord outage must never cost somebody their battle result.
 *
 * THE DEFENDER IS PINGED, not the attacker. An attack happens to a Crew whose
 * owner did not choose the fight and was not at the keyboard for it; the
 * attacker watched the whole thing and needs no telling. The mention goes in
 * discordmsg()'s top-level $content, because a mention written into an embed
 * renders as a link and notifies nobody -- see the note on that parameter in
 * webhooks.php.
 */
function dhca_announce($conn, $b, $won, $rewarded) {
	if (!function_exists('discordmsg')) {
		if (is_file(__DIR__ . '/webhooks.php')) { ob_start(); include_once __DIR__ . '/webhooks.php'; ob_end_clean(); }
		if (!function_exists('discordmsg')) return;
	}
	try {
		$attId = (int)$b['meta']['attacker'];
		$defId = (int)$b['meta']['defender'];
		$attU  = dhca_identity($conn, $attId);
		$defU  = dhca_identity($conn, $defId);
		$att   = $attU['name'];
		$def   = $defU['name'];
		$winU  = $won ? $attU : $defU;
		$standing = 0;
		foreach ($b[$won ? 'mine' : 'foes'] as $f) if (empty($f['ko'])) $standing++;

		$desc  = $won ? "**$att** takes the Arena.\n\n" : "**$def**'s Crew holds the Arena.\n\n";
		$desc .= "⚔️ **Challenger:** $att\n🛡️ **Defender:** $def\n";
		$desc .= "🏁 **Result:** ".($won ? 'challenger wins' : 'defender holds')
		       . " · ".$standing." still standing after ".(int)$b['round']." rounds\n";

		/* The image is the winning Crew's fiercest Fighter rather than the
		   Skulliance icon. Every one of these is a different character, built
		   by the person being announced, so the generic mark was the least
		   informative thing that could have gone there. */
		$hero = dhca_fiercest($conn, $b, $won);
		$img  = '';
		if ($hero && $hero['traits']) {
			if (!function_exists('dhcf_render_fighter') && is_file(__DIR__ . '/dhcfighters-notify.php')) {
				ob_start(); include_once __DIR__ . '/dhcfighters-notify.php'; ob_end_clean();
			}
			if (function_exists('dhcf_render_fighter')) {
				ob_start();
				$img = dhcf_render_fighter($hero['traits'], $hero['serial']);
				ob_end_clean();
			}
			// It returns '' for a missing art directory or an unwritable
			// dhcrenders/ and says nothing, which is indistinguishable from
			// "the feature was never deployed" when you are looking at Discord.
			if ($img === '') error_log('dhca_announce: no render for fighter serial '
			                           . (int)$hero['serial']);
			$desc .= "🔥 **Fiercest:** ".$hero['f']['name']
			       . ($hero['dealt'] > 0 ? " — ".number_format($hero['dealt'])." damage" : "")
			       . " · ".$hero['f']['kit']['name']."\n";
		}

		if ((int)$b['stats']['bombs'] > 0)
			$desc .= "💣 **Bombs:** ".(int)$b['stats']['bombs']." armed, "
			       . (int)$b['stats']['blasts']." detonated\n";
		if ((int)$b['stats']['best'] > 2)
			$desc .= "✦ **Best chain:** x".(int)$b['stats']['best']."\n";
		if ($rewarded && !empty($b['drop']))
			$desc .= "🎁 **Trait:** ".$b['drop']['name']." (".$b['drop']['tier'].")\n";

		// Not every staker has linked Discord, so this is empty as often as not
		// and the post simply goes out without a ping.
		$ping = $defU['mention']
		      ? $defU['mention'].' '.($won ? 'your Crew was beaten in the Arena.'
		                                   : 'your Crew held the Arena.')
		      : '';

		/*
		 * THE THUMBNAIL IS NOT THE PLATFORM SKULL. discordmsg() substitutes it
		 * for any empty $thumbnail, so passing '' was the reason these posts
		 * still carried the generic mark next to a battle between two named
		 * Crews. The winner's own Discord avatar goes there instead, and the
		 * Fighter render only stands in when they have not linked Discord --
		 * using it for both slots would print the same art twice.
		 */
		$thumb = $winU['avatar'] !== '' ? $winU['avatar'] : $img;
		$author = array('name' => $won ? $att.' takes the Arena' : $def.' holds the Arena');
		if ($winU['avatar'] !== '') $author['icon_url'] = $winU['avatar'];

		/*
		 * THE LAST TWO ARGS ARE FOR THE ACTIVITY FEED, not for Discord.
		 *
		 * This is the platform's only two-sided announce, and the only place
		 * that knows both players. discordmsg() otherwise infers the actor
		 * from the session, which here would always be the attacker -- the
		 * defender is not making a request, they are being attacked, often
		 * while offline. Without $defId the battle would be invisible in
		 * their own feed. Attacker first so it reads as THEIR action under
		 * "My activity", and lands in the defender's "Involving me".
		 */
		ob_start();
		discordmsg($won ? '⚔️ Arena — Challenger Wins' : '🛡️ Arena — Defense Holds',
			$desc, $img, 'https://skulliance.io/staking/dhcarena.php', 'dhcarena', $thumb,
			$won ? '00C8A0' : 'E0466B', $author, null, $ping, $attId, $defId);
		ob_end_clean();
	} catch (Throwable $e) {
		// never reaches the player
	}
}

/* ---------- the ladder -------------------------------------------------------- */

/**
 * Standings for a season, derived from the battle ledger rather than stored.
 * RANKED ON WINS, not win rate -- see dhcarena.md §8c. With an equal daily
 * allowance for everyone, ranking on total wins IS ranking on win rate, while
 * still making an unspent battle a wasted one.
 */
/* Pass this as the season for a ladder with no month boundary at all. A
   sentinel rather than '' or null, because dhca_ladder() reads both of those
   as "the current season" and a typo must not silently publish all-time
   standings under a monthly heading. */
define('DHCA_LADDER_ALL_TIME', '*');

function dhca_ladder($conn, $season = null, $limit = 25) {
	$season = $season ?: dhca_season();
	$limit  = (int)$limit;
	$out = array();
	/*
	 * ONE QUERY, TWO WINDOWS. The all-time board is the monthly board without
	 * the season clause -- same columns, same grouping, same order -- so it is
	 * the same SQL with one predicate swapped rather than a second function
	 * that can drift from this one about what a win is.
	 *
	 * `outcome IN (1,2)` not `outcome <> 0`: identical result (0, 1 and 2 are
	 * the only values) and an IN list is something MySQL can use an index for,
	 * which the all-time branch needs because it has no season to narrow on.
	 * See the index note in dhcarena-schema.md.
	 */
	$window = ($season === DHCA_LADDER_ALL_TIME)
		? ""
		: "b.season = '" . $conn->real_escape_string($season) . "' AND ";
	$sql = "SELECT u.id AS user_id, u.username, u.discord_id, u.avatar, u.visibility,
	               SUM(b.outcome = 1) AS wins,
	               SUM(b.outcome = 2) AS losses,
	               MAX(b.best_chain)  AS best_chain
	        FROM dhc_arena_battles b
	        INNER JOIN users u ON u.id = b.attacker_id
	        WHERE {$window}b.outcome IN (1,2)
	        GROUP BY u.id
	        ORDER BY wins DESC, losses ASC, best_chain DESC
	        LIMIT $limit";
	$res = $conn->query($sql);
	if (!$res) return $out;
	while ($r = $res->fetch_assoc()) $out[] = $r;
	return $out;
}

/* ===================================================================
   WHAT THE LEDGER DEMONSTRATES, for the whole collection at once.
   =================================================================== */

/**
 * Every Fighter's Arena performance, keyed by fighter id, in ONE read.
 *
 * dhca_fighter_stats() answers this for a single Fighter and is right for a
 * detail panel. The Collection ranks a few hundred Fighters against each
 * other on one page, so asking per row would be a few hundred queries to
 * produce one sorted list. This is the same aggregation GROUPed by fighter as
 * well as side.
 *
 * THE THREE PROVEN AXES MIRROR THE THREE CLAIMED ONES, deliberately, because
 * the point of having them is to put the claim next to the evidence:
 *
 *   claimed (dhcf_rank_values)        proven (here)
 *   power  = weapon's power           dmg    = damage actually dealt per battle
 *   tough  = torso's health           live   = share of battles walked off alive
 *   might  = tough * power            real   = dmg * live
 *
 * `might` multiplies staying power by hitting power, so its evidence
 * multiplies the evidence for each: output, discounted by how often the
 * Fighter is taken off the board before it can deliver. A glass cannon with
 * one huge battle and four early exits does not out-rank a Fighter that is
 * still swinging in round six, which is the whole claim `might` makes.
 *
 * Rates need a denominator worth dividing by, so a Fighter under
 * DHCA_PROFILE_MIN battles gets its totals and `proven => false`. It is not
 * scored and it is not ranked -- see dhca_performance_pool().
 */
function dhca_performance($conn) {
	$out = array();
	/*
	 * THE SAME COLUMNS dhca_fighter_stats() READS, and that is the point.
	 *
	 * This one built a SHORTER row -- battles, wins, survived, dealt --
	 * and the single-Fighter query next to it built a longer one. Both
	 * are "a Fighter's tallies", so anything written against one of them
	 * works against the other right up until it touches a column only
	 * the long shape has, and then it emits an undefined-key warning on
	 * a live page with display_errors on. That is exactly what happened
	 * the moment the Collection started calling dhca_fighter_profile(),
	 * which needs survived_wins.
	 *
	 * losses and fell_in_wins come along for the same reason: one shape,
	 * so the next thing written against either function cannot find a
	 * hole. The cost is two more SUMs over rows already being grouped.
	 */
	$res = $conn->query(
		"SELECT p.fighter_id, p.side,
		        COUNT(*)                                  AS battles,
		        SUM(b.outcome = p.side)                   AS wins,
		        SUM(b.outcome <> p.side)                  AS losses,
		        SUM(p.fell = 0)                           AS survived,
		        SUM(b.outcome = p.side AND p.fell = 0)    AS survived_wins,
		        SUM(b.outcome = p.side AND p.fell = 1)    AS fell_in_wins,
		        COALESCE(SUM(p.dealt), 0)                 AS dealt
		   FROM dhc_arena_battle_fighters p
		   INNER JOIN dhc_arena_battles b ON b.id = p.battle_id
		  WHERE b.outcome IN (1,2)
		  GROUP BY p.fighter_id, p.side");
	/* Tolerant of the table not existing yet, exactly as dhca_crew_profiles()
	   is: the Collection is a public page and must render before the first
	   battle is ever logged. */
	if (!$res) return $out;

	$z = array('battles'=>0,'wins'=>0,'losses'=>0,'survived'=>0,
	           'survived_wins'=>0,'fell_in_wins'=>0,'dealt'=>0);
	while ($r = $res->fetch_assoc()) {
		$fid = (int)$r['fighter_id'];
		if (!isset($out[$fid])) $out[$fid] = array('all'=>$z, 'attack'=>$z, 'defend'=>$z);
		$k = ((int)$r['side'] === 1) ? 'attack' : 'defend';
		foreach ($z as $f => $_) {
			$out[$fid][$k][$f]   = (int)$r[$f];
			$out[$fid]['all'][$f] += (int)$r[$f];
		}
	}
	foreach ($out as $fid => $s) $out[$fid] = dhca_performance_score($s);
	return $out;
}

/**
 * The derived numbers for one Fighter's raw tallies. Split out so a harness
 * can drive the arithmetic without a database.
 */
function dhca_performance_score($s) {
	foreach (array('all','attack','defend') as $k) {
		$b = (int)$s[$k]['battles'];
		$s[$k]['proven'] = ($b >= DHCA_PROFILE_MIN);
		/* (int) on every one: round() returns a FLOAT, and these travel into
		   json_encode() for the client and into === comparisons here. An
		   un-cast percentage ships as 63.0 and matches nothing. */
		$s[$k]['winpct'] = $b ? (int)round(100 * $s[$k]['wins']     / $b) : 0;
		$s[$k]['live']   = $b ? (int)round(100 * $s[$k]['survived'] / $b) : 0;
		$s[$k]['dmg']    = $b ? (int)round($s[$k]['dealt'] / $b)          : 0;
		/* dmg * live, carried as damage-per-battle so the number means
		   something on its own: "the damage this Fighter is still around to
		   deal". A 300-damage Fighter that survives half its battles reads
		   150, level with a 150-damage Fighter that always walks off. */
		$s[$k]['real']   = (int)round($s[$k]['dmg'] * $s[$k]['live'] / 100);
	}
	/* Which side of the board it is actually better on. Needs BOTH records to
	   be worth comparing -- a Fighter with nine attacks and one defence has
	   not shown anything about defending. */
	$s['edge'] = '';
	if ($s['attack']['proven'] && $s['defend']['proven']) {
		$d = $s['attack']['winpct'] - $s['defend']['winpct'];
		if      ($d >=  DHCA_EDGE_PCT) $s['edge'] = 'attack';
		else if ($d <= -DHCA_EDGE_PCT) $s['edge'] = 'defend';
		else                           $s['edge'] = 'even';
	}
	return $s;
}

/**
 * The sorted pools the Collection ranks against, built from dhca_performance().
 *
 * UNPROVEN FIGHTERS ARE ABSENT FROM THE POOLS, not zero in them. A Fighter
 * with two battles is not the worst in the collection at anything; it is
 * unmeasured, and ranking it last would be a claim the ledger does not
 * support. So `_n` is how many Fighters have actually fought enough to be
 * ranked, and a rank reads "#4 of 31" rather than "#4 of 108" -- the
 * denominator has to be the measured population or the rank overstates
 * itself.
 */
function dhca_performance_pool($perf) {
	$pool = array('dmg'=>array(), 'live'=>array(), 'real'=>array(), 'winpct'=>array());
	foreach ($perf as $s) {
		if (empty($s['all']['proven'])) continue;
		foreach ($pool as $axis => $_) $pool[$axis][] = $s['all'][$axis];
	}
	foreach ($pool as $axis => $vals) { rsort($pool[$axis]); }
	$pool['_n'] = count($pool['dmg']);
	return $pool;
}

/** The three proven axes and the claim each one tests. */
function dhca_proven_axes() {
	return array(
		'real' => array('label'=>'Deadliest',       'claim'=>'might', 'unit'=>'dmg/battle held'),
		'live' => array('label'=>'Toughest',        'claim'=>'tough', 'unit'=>'% survived'),
		'dmg'  => array('label'=>'Hardest hitting', 'claim'=>'power', 'unit'=>'dmg/battle'),
	);
}

/**
 * The CLAIMED axes, ranked over only the Fighters that have a record.
 *
 * WHY THIS EXISTS AT ALL. A proven rank is out of the few dozen Fighters with
 * five or more battles; a claimed rank, as dhc-ranks.php computes it, is out
 * of every Fighter ever assembled. Setting "#4 proven" beside "#19 claimed"
 * would read as a disagreement when #19 of 108 and #4 of 31 are nearly the
 * same standing -- the gap would be an artefact of two denominators and
 * nothing else. So the claim is re-ranked over exactly the population the
 * evidence covers, and a gap becomes a finding.
 *
 * $known lets a caller that has already built every Fighter -- dhcgallery.php
 * builds all of them for its kit and role readout -- hand over
 * fighter_id => array('tough'=>, 'power'=>, 'might'=>) and skip the query
 * entirely. Everything else (the single-Fighter AJAX endpoint) passes null and
 * pays for one SELECT over just the proven ids, which is a few dozen rows.
 */
function dhca_claim_pool($conn, $perf, $known = null) {
	$pool = array('tough' => array(), 'power' => array(), 'might' => array(), '_n' => 0);
	$ids = array();
	foreach ($perf as $fid => $s) if (!empty($s['all']['proven'])) $ids[] = (int)$fid;
	if (!$ids) return $pool;

	if (is_array($known)) {
		foreach ($ids as $fid) {
			if (!isset($known[$fid])) continue;
			foreach (array('tough','power','might') as $k) $pool[$k][] = $known[$fid][$k];
		}
	} else {
		/* Only the proven ids, and only the two columns a rank needs. */
		$res = $conn->query('SELECT id, traits, rarity_score FROM dhc_fighters
		                      WHERE disassembled_at IS NULL AND id IN (' . implode(',', $ids) . ')');
		if ($res) {
			$rarity = dhcf_rarity();
			while ($r = $res->fetch_assoc()) {
				$tr = json_decode($r['traits'], true) ?: array();
				$rv = dhcf_rank_values($tr, (int)$r['rarity_score'],
				                       dhca_build_fighter($tr, '', 'g' . (int)$r['id'], $rarity));
				foreach (array('tough','power','might') as $k) $pool[$k][] = $rv[$k];
			}
		}
	}
	foreach (array('tough','power','might') as $k) rsort($pool[$k]);
	$pool['_n'] = count($pool['might']);
	return $pool;
}

/**
 * One Fighter's claimed rank on each tested axis, against dhca_claim_pool().
 * Empty for a Fighter with no record -- it has nothing to be compared to.
 */
function dhca_claim_ranks($cpool, $rv, $proven) {
	$out = array();
	if (!$proven || empty($cpool['_n'])) return $out;
	foreach (array('tough','power','might') as $k) {
		$out[$k] = dhcf_rank_of($cpool[$k], $rv[$k]);
	}
	return $out;
}

/* ===================================================================
   RIVALRIES -- who has been fighting whom, and with what.
   =================================================================== */

define('DHCA_RIVALRY_DAYS', 30);   // the window: current events, not all history

/**
 * The rivalry graph for the last $days: players, the attacks between them, and
 * each player's Fighters.
 *
 * THREE READS, EACH AGGREGATED IN SQL. The graph is small -- a busy month is
 * tens of players and maybe a few hundred pairs -- but the tables behind it
 * are not, so nothing here pulls battle rows into PHP to count them.
 *
 * WHAT IS AVAILABLE TODAY IS NOT WHAT IS AVAILABLE PER FIGHTER.
 * dhc_arena_battles has recorded attacker, defender and outcome since the
 * Arena opened, so the players and the lines between them are months deep.
 * dhc_arena_battle_fighters is new, so the Fighter satellites only exist for
 * battles logged since it landed, and a player's cluster can legitimately
 * come back with a hub and no satellites. The renderer must survive that
 * rather than treat it as missing data.
 *
 * ONLY SETTLED BATTLES COUNT. outcome 0 is a battle still in progress, and a
 * game somebody walked away from mid-match is not a rivalry -- it is an
 * unfinished sentence. dhca_forfeit() closes those with a real outcome, so
 * they arrive here once they are actually over.
 */
function dhca_rivalries($conn, $days = DHCA_RIVALRY_DAYS) {
	$d   = max(1, (int)$days);
	$out = array('days' => $d, 'players' => array(), 'edges' => array(),
	             'fighters' => array(), 'battles' => 0);

	/* ---- 1. the edges, and every player who appears on one ---- */
	$res = $conn->query(
		"SELECT attacker_id, defender_id,
		        COUNT(*)            AS n,
		        SUM(outcome = 1)    AS aw
		   FROM dhc_arena_battles
		  WHERE outcome IN (1,2)
		    AND started_at >= NOW() - INTERVAL $d DAY
		  GROUP BY attacker_id, defender_id");
	if (!$res) return $out;

	$ids = array();
	while ($r = $res->fetch_assoc()) {
		$a = (int)$r['attacker_id']; $v = (int)$r['defender_id'];
		/* A battle against yourself is not a rivalry. It should not exist, but
		   a self-edge would render as a line from a node to itself and read as
		   a glitch, so it is dropped here rather than in the renderer. */
		if ($a === $v || !$a || !$v) continue;
		$n  = (int)$r['n'];
		$aw = (int)$r['aw'];
		$out['edges'][] = array('a' => $a, 'd' => $v, 'n' => $n, 'aw' => $aw);
		$out['battles'] += $n;
		$ids[$a] = true; $ids[$v] = true;
	}
	if (!$ids) return $out;

	/* ---- 2. who those people are ---- */
	$in  = implode(',', array_map('intval', array_keys($ids)));
	$res = $conn->query("SELECT id, username, discord_id, avatar FROM users WHERE id IN ($in)");
	if ($res) {
		while ($r = $res->fetch_assoc()) {
			$out['players'][(int)$r['id']] = array(
				'id'      => (int)$r['id'],
				'name'    => (string)$r['username'],
				'avatar'  => avatar_url($r['discord_id'], $r['avatar']),
				'att'     => 0, 'def' => 0, 'wins' => 0, 'losses' => 0, 'battles' => 0,
			);
		}
	}
	/* Tallied from the edges rather than re-queried: the edge list already
	   carries every battle in the window, split by direction and by who won,
	   so a second GROUP BY over the same rows could only disagree with it. */
	foreach ($out['edges'] as $e) {
		foreach (array($e['a'], $e['d']) as $p) {
			if (!isset($out['players'][$p])) continue;
			$out['players'][$p]['battles'] += $e['n'];
		}
		if (isset($out['players'][$e['a']])) {
			$out['players'][$e['a']]['att']    += $e['n'];
			$out['players'][$e['a']]['wins']   += $e['aw'];
			$out['players'][$e['a']]['losses'] += $e['n'] - $e['aw'];
		}
		if (isset($out['players'][$e['d']])) {
			$out['players'][$e['d']]['def']    += $e['n'];
			$out['players'][$e['d']]['wins']   += $e['n'] - $e['aw'];
			$out['players'][$e['d']]['losses'] += $e['aw'];
		}
	}

	/* ---- 3. the Fighters each of them fielded ---- */
	$res = $conn->query(
		"SELECT p.fighter_id, f.user_id, f.name, f.serial, f.traits,
		        COUNT(*)                   AS n,
		        SUM(p.side = 1)            AS att,
		        SUM(p.side = 2)            AS def,
		        SUM(p.fell)                AS fell,
		        COALESCE(SUM(p.dealt), 0)  AS dealt
		   FROM dhc_arena_battle_fighters p
		   INNER JOIN dhc_arena_battles b ON b.id = p.battle_id
		   INNER JOIN dhc_fighters      f ON f.id = p.fighter_id
		  WHERE b.outcome IN (1,2)
		    AND b.started_at >= NOW() - INTERVAL $d DAY
		  GROUP BY p.fighter_id, f.user_id, f.name, f.serial, f.traits");
	/* Missing or empty is the normal early state, not a failure. */
	if ($res) {
		while ($r = $res->fetch_assoc()) {
			$uid = (int)$r['user_id'];
			/* f.user_id is the CURRENT owner, which is not necessarily who
			   fielded it -- a Fighter can change hands. Clustering by current
			   owner is the honest choice for a "who is active now" map, but a
			   Fighter whose owner is not on the graph has no cluster to sit
			   in and is dropped rather than floated loose. */
			if (!isset($out['players'][$uid])) continue;
			if (!isset($out['fighters'][$uid])) $out['fighters'][$uid] = array();
			$out['fighters'][$uid][] = array(
				'id'      => (int)$r['fighter_id'],
				/* The SERIAL, not just the id: ajax/dhc-fighter.php is keyed on
				   it, and that endpoint is how a click here opens the very same
				   panel the Collection opens. */
				'serial'  => (int)$r['serial'],
				'name'    => dhcf_display_name($r),
				/* The trait set, so a caller can draw the Fighter rather than a
				   dot. Decoded here and not in the renderer: it is a JSON
				   column and every consumer would otherwise repeat the decode
				   and the empty-array fallback. dhca_rivalry_art() turns it
				   into layers; nothing sends raw traits to the client. */
				/* COALESCED TO A STRING BEFORE DECODING. dhc_fighters.traits is
				   NOT NULL today, but this row comes through a LEFT-joined
				   ledger and the harness produced a null on the first run
				   under 8.5 -- passing null to json_decode() is deprecated,
				   and with display_errors on platform-wide a deprecation is
				   a notice printed into the Rivalries page. */
				'traits'  => json_decode((string)($r['traits'] ?? ''), true) ?: array(),
				'battles' => (int)$r['n'],
				'att'     => (int)$r['att'],
				'def'     => (int)$r['def'],
				'fell'    => (int)$r['fell'],
				'dealt'   => (int)$r['dealt'],
			);
		}
		foreach ($out['fighters'] as $uid => $list) {
			usort($out['fighters'][$uid], function ($x, $y) {
				return $y['battles'] <=> $x['battles'] ?: strcmp($x['name'], $y['name']);
			});
		}
	}
	return $out;
}

/**
 * The graph arranged for drawing: one node per player with a radius, and one
 * edge per ORDERED pair, folded so a mutual grudge is a single entry that
 * knows it runs both ways.
 *
 * Split from the query so the layout can be driven without a database, and
 * because the arithmetic here is the part worth testing: a radius scale that
 * collapses or a fold that loses a direction is invisible in a screenshot.
 */
function dhca_rivalry_layout($riv, $rmin = 16, $rmax = 46) {
	$nodes = array();
	$busiest = 0;
	foreach ($riv['players'] as $p) $busiest = max($busiest, $p['battles']);

	foreach ($riv['players'] as $id => $p) {
		/* SQUARE ROOT, NOT LINEAR. Area is what the eye reads on a circle, so
		   scaling the radius linearly with battles makes a 10-battle player
		   look ten times the player with one, which is a lie about a tenfold
		   difference in activity. sqrt puts AREA in proportion instead.
		   A lone player with every battle in the window still gets rmax. */
		$share = $busiest > 0 ? sqrt($p['battles'] / $busiest) : 0;
		$node = $p;
		$node['r']  = (int)round($rmin + ($rmax - $rmin) * $share);
		$node['fighters'] = isset($riv['fighters'][$id]) ? $riv['fighters'][$id] : array();
		/* Which side of the board this player lives on, for the hub's ring.
		   Deliberately not dhca_performance()'s `edge`, which is about win
		   RATE -- this is about where the activity is. */
		$node['lean'] = '';
		if ($p['battles'] >= 3) {
			if      ($p['att'] >= $p['def'] * 2) $node['lean'] = 'aggressor';
			else if ($p['def'] >= $p['att'] * 2) $node['lean'] = 'target';
		}
		$nodes[$id] = $node;
	}

	/* Fold A->B and B->A into one undirected pair that remembers both counts.
	   Two arrowheads on one line reads as a feud; two separate lines between
	   the same pair of avatars reads as a rendering bug. */
	$pairs = array();
	foreach ($riv['edges'] as $e) {
		$lo = min($e['a'], $e['d']); $hi = max($e['a'], $e['d']);
		$k  = $lo . ':' . $hi;
		if (!isset($pairs[$k])) {
			$pairs[$k] = array('lo' => $lo, 'hi' => $hi, 'n' => 0,
			                   'lohi' => 0, 'hilo' => 0, 'lowins' => 0, 'hiwins' => 0);
		}
		$pairs[$k]['n'] += $e['n'];
		if ($e['a'] === $lo) {
			$pairs[$k]['lohi']   += $e['n'];          // lo attacked hi this often
			$pairs[$k]['lowins'] += $e['aw'];
			$pairs[$k]['hiwins'] += $e['n'] - $e['aw'];
		} else {
			$pairs[$k]['hilo']   += $e['n'];
			$pairs[$k]['hiwins'] += $e['aw'];
			$pairs[$k]['lowins'] += $e['n'] - $e['aw'];
		}
	}
	/* Heaviest grudge first: it is the one worth reading, and drawing it first
	   puts it under the lighter lines rather than over them. */
	$pairs = array_values($pairs);
	usort($pairs, function ($x, $y) { return $y['n'] <=> $x['n']; });
	foreach ($pairs as $i => $p) {
		$pairs[$i]['mutual'] = ($p['lohi'] > 0 && $p['hilo'] > 0);
	}

	return array('nodes' => $nodes, 'pairs' => $pairs,
	             'days' => $riv['days'], 'battles' => $riv['battles'],
	             'busiest' => $busiest);
}

/**
 * Swap each Fighter's trait set for the image layers that draw it.
 *
 * SEPARATE FROM dhca_rivalries() because it needs the art directory, which is
 * a property of the page and not of the data -- and because the traits must
 * not reach the client. They are the Fighter's recipe; the map needs pictures.
 *
 * ONE DECODE, ONE LAYER PASS, AND THE TRAITS ARE DROPPED. dhcf_layers() is the
 * same call the Collection and the Discord render use, so a Fighter cannot be
 * drawn one way here and another way there -- it decides the armless-torso
 * swap and the single-sided-arm restore, which no caller should be
 * reimplementing.
 */
function dhca_rivalry_art(&$riv, $webroot, $base) {
	foreach ($riv['fighters'] as $uid => $list) {
		foreach ($list as $i => $f) {
			$layers = array();
			foreach (dhcf_layers($f['traits'], $webroot) as $L) {
				$layers[] = array(
					/* The 250px masters: a satellite is tens of pixels across,
					   so the 1000px art would be a hundred needless megabytes
					   on a busy map. */
					'src'  => $base . '/250/' . $L['cat'] . '/' . $L['slug'] . '.png',
					'n'    => (float)$L['nudge'],
					'clip' => $L['clip'],
				);
			}
			$riv['fighters'][$uid][$i]['layers'] = $layers;
			unset($riv['fighters'][$uid][$i]['traits']);
		}
	}
}
