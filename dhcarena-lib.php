<?php
/* ============================================================================
   dhcarena-lib.php — persistence and economy for DHC Arena.

   The engine (dhcarena-engine.php) knows the rules and nothing else. This knows
   the database, the Crew, the allowance, the bench and the rewards. Keeping the
   two apart is what lets the rules be tested without either.

   Schema: dhcarena-schema.md. Design: dhcarena.md.
   ============================================================================ */

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
define('DHCA_STALE_H',        6);     // an untouched battle is forfeited after this

function dhca_season() { return date('Y-m'); }

/* ---------- the Crew -------------------------------------------------------- */

/**
 * A player's Fighters with their Arena state attached, newest first.
 * `available` is the whole entry condition: not benched, right now.
 */
function dhca_crew($conn, $user_id) {
	$user_id = (int)$user_id;
	$rows = array();
	$sql = "SELECT f.id, f.serial, f.name, f.traits, f.rarity_score,
	               a.benched_until, a.ko, a.wins, a.losses, a.season_wins, a.season_losses
	        FROM dhc_fighters f
	        LEFT JOIN dhc_arena_fighters a ON a.fighter_id = f.id
	        WHERE f.user_id = $user_id
	        ORDER BY f.rarity_score DESC, f.id DESC";
	$res = $conn->query($sql);
	if (!$res) return $rows;
	$now = time();
	while ($r = $res->fetch_assoc()) {
		$until = $r['benched_until'] ? strtotime($r['benched_until']) : 0;
		$r['traits']    = json_decode($r['traits'], true) ?: array();
		$r['available'] = ($until <= $now);
		$r['bench_left']= max(0, $until - $now);
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
		return 'That is all '.DHCA_DAILY_BATTLES.' battles for today. Back tomorrow.';
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
	if (dhca_open_battle($conn, $user_id))
		return 'You are in the middle of a battle. Finish it and the Arena reopens.';
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
 * A battle nobody has touched in DHCA_STALE_H hours is FORFEITED -- resolved as
 * a defeat, with the bench and the record that a defeat carries.
 *
 * Not deleted, and not left open. Deleting it would hand back the free
 * abandonment that dhca_entry_block() exists to prevent; leaving it open would
 * mean one dropped connection locks a player out of the Arena permanently. A
 * forfeit is what actually happened: they walked away from a battle, so they
 * lost it -- and they waited six hours to find that out, which is worse than
 * simply losing and strictly better than being stuck.
 */
function dhca_sweep_stale($conn, $user_id) {
	$open = dhca_open_battle($conn, $user_id);
	if (!$open || $open['age'] < DHCA_STALE_H * 3600) return false;
	$b = dhca_load($conn, $open['battle_id'], $user_id);
	if (!$b) {
		// state gone but the ledger row still open: close it without a payout
		$conn->query("UPDATE dhc_arena_battles SET outcome = 2, ended_at = NOW()
		              WHERE id = ".$open['battle_id']." AND outcome = 0");
		return true;
	}
	$b['over']    = 'foes';
	$b['forfeit'] = true;          // no Discord post: see dhca_finish()
	$b['log'][]   = 'Abandoned — forfeited after '.DHCA_STALE_H.' hours.';
	dhca_finish($conn, $b);
	return true;
}

function dhca_hms($secs) {
	$secs = max(0, (int)$secs);
	$h = intdiv($secs, 3600); $m = intdiv($secs % 3600, 60);
	if ($h > 0) return $h.'h '.$m.'m';
	return max(1, $m).'m';
}

/* ---------- opponents ------------------------------------------------------- */

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

/** The three a defender fields: their best available, by rarity score. */
function dhca_defending_crew($conn, $user_id) {
	$crew = dhca_crew($conn, $user_id);
	return array_slice($crew, 0, DHCA_CREW_SIZE);
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

	$b['meta'] = array(
		'attacker'=>$user_id, 'defender'=>$defender_id,
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

	$rewarded = 0;
	if ($won && !dhca_already_rewarded($conn, $att, $def)) {
		$rewarded = 1;
		dhca_pay($conn, $att, $b);
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

/** Longer bench for a deeper Crew, so depth buys resilience and not immunity. */
function dhca_bench_loss_hours($depth) {
	$span = DHCA_BENCH_LOSS_MAX - DHCA_BENCH_LOSS_MIN;
	$t    = max(0, min(1, ($depth - DHCA_CREW_SIZE) / 9));   // 3 Fighters -> 0, 12+ -> 1
	return DHCA_BENCH_LOSS_MIN + $span * $t;
}

function dhca_bench($conn, $user_id, $fighter_id, $hours, $won) {
	$conn->query(sprintf(
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
	return (int)round(($foes - $mine) / 100);
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
		$out['avatar'] = 'https://cdn.discordapp.com/avatars/'.$u['discord_id'].'/'.$u['avatar'].'.jpg';
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

		ob_start();
		discordmsg($won ? '⚔️ Arena — Challenger Wins' : '🛡️ Arena — Defence Holds',
			$desc, $img, 'https://skulliance.io/staking/dhcarena.php', 'dhcarena', $thumb,
			$won ? '00C8A0' : 'E0466B', $author, null, $ping);
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
function dhca_ladder($conn, $season = null, $limit = 25) {
	$season = $season ?: dhca_season();
	$limit  = (int)$limit;
	$out = array();
	$sql = "SELECT u.id AS user_id, u.username, u.discord_id, u.avatar, u.visibility,
	               SUM(b.outcome = 1) AS wins,
	               SUM(b.outcome = 2) AS losses,
	               MAX(b.best_chain)  AS best_chain
	        FROM dhc_arena_battles b
	        INNER JOIN users u ON u.id = b.attacker_id
	        WHERE b.season = '".$conn->real_escape_string($season)."' AND b.outcome <> 0
	        GROUP BY u.id
	        ORDER BY wins DESC, losses ASC, best_chain DESC
	        LIMIT $limit";
	$res = $conn->query($sql);
	if (!$res) return $out;
	while ($r = $res->fetch_assoc()) $out[] = $r;
	return $out;
}
