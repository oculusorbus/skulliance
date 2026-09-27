<?php
/**
 * dhcarena-live.php — DHC Arena, human against human, in real time.
 *
 * Two players who are both online fight each other directly, with no AI on
 * either side unless somebody stalls. See dhcarena.md §8d.
 *
 * IT IS A SPORT AND NOTHING ELSE. No ladder, no traits, no CARBON, no effect on
 * any Fighter's win/loss record, no bench, and no touch on the daily allowance
 * in either direction. You cannot win anything here and you cannot lose
 * anything. The reason to play is that the other person is real.
 *
 * THAT IS ENFORCED BY THIS FILE'S ISOLATION, NOT BY A RULE. Everything here
 * reads and writes ONE table, dhc_arena_live. It never touches
 * dhc_arena_battles, dhc_arena_state or dhc_arena_fighters, and never calls
 * dhca_finish(), dhca_bench(), dhca_record() or dhca_pay(). "Live never pays"
 * is therefore not a condition somebody can later add an exception to -- there
 * is no code path from here to the economy at all. A flag on the ranked table
 * would have been an `if` waiting for an `unless`.
 *
 * It also has to stay out of the ranked rules' way: dhca_open_battle() and
 * dhca_sweep_stale() key on dhc_arena_state.user_id with outcome = 0, so a live
 * battle stored there would block the player's real battle AND be force-
 * forfeited as a DEFEAT after six hours, benching Fighters over a match that
 * carried no stake.
 *
 * Reads from the ranked lib (crews, identities, the fiercest Fighter, the
 * announcement plumbing) are fine and deliberate -- those are lookups, not
 * writes.
 */
require_once __DIR__ . '/dhcarena-lib.php';

/* How long a player has to take their turn before the AI takes it for them.
   A live game can be stalled and an async one cannot, which is the only reason
   this exists. Generous: the point is to stop a walk-away, not to add time
   pressure to a game that has none. */
if (!defined('DHCAL_TURN_S'))   define('DHCAL_TURN_S', 90);
/* How long a challenge stands. Short, because it is issued to somebody you are
   already talking to and a stale invite is worse than no invite. */
if (!defined('DHCAL_INVITE_S')) define('DHCAL_INVITE_S', 600);
/* A match nobody has touched at all in this long is closed with no winner --
   both players walked away, which with no stake is allowed to just end. */
if (!defined('DHCAL_IDLE_S'))   define('DHCAL_IDLE_S', 1800);
/* How many past move timelines to keep so a poller can animate what it missed.
   A client further behind than this resyncs without animation instead, which is
   bounded rather than letting the state JSON grow with the battle. */
if (!defined('DHCAL_TL_KEEP'))  define('DHCAL_TL_KEEP', 8);

/* ---------- reading ---------------------------------------------------------- */

function dhcal_row($conn, $id) {
	$r = $conn->query("SELECT * FROM dhc_arena_live WHERE id = ".(int)$id." LIMIT 1");
	return ($r && $r->num_rows) ? $r->fetch_assoc() : null;
}

/** Is this player in this match at all, and on which side? */
function dhcal_seat($row, $user_id) {
	$user_id = (int)$user_id;
	if ((int)$row['host_id']  === $user_id) return 'host';
	if ((int)$row['guest_id'] === $user_id) return 'guest';
	return '';
}

/**
 * The live match this player is in, invited to, or waiting on.
 * One row, because a player is in at most one live match at a time -- the same
 * call the ranked side makes, for the same reason: two boards at once is two
 * boards you are losing on.
 */
function dhcal_current($conn, $user_id) {
	$uid = (int)$user_id;
	$r = $conn->query("SELECT * FROM dhc_arena_live
	                   WHERE (host_id = $uid OR guest_id = $uid) AND status IN (0,1)
	                   ORDER BY id DESC LIMIT 1");
	if (!$r || !$r->num_rows) return null;
	$row = $r->fetch_assoc();
	// An invite nobody answered, or a board both players abandoned, is not
	// "current" -- it is litter, and it would block the next challenge.
	if (dhcal_expired($row)) { dhcal_close($conn, $row); return null; }
	return $row;
}

function dhcal_expired($row) {
	$age = time() - strtotime($row['updated_at']);
	if ((int)$row['status'] === 0) return $age > DHCAL_INVITE_S;
	if ((int)$row['status'] === 1) return $age > DHCAL_IDLE_S;
	return false;
}

function dhcal_close($conn, $row) {
	$conn->query("UPDATE dhc_arena_live SET status = 3, ended_at = NOW()
	              WHERE id = ".(int)$row['id']." AND status IN (0,1)");
}

/** Who this player could challenge: anyone with a Crew, same list the ranked
    rival picker uses. Nothing about live changes who exists. */
function dhcal_opponents($conn, $user_id, $limit = 24) {
	return dhca_opponents($conn, $user_id, $limit);
}

/* ---------- perspective ------------------------------------------------------ */

/**
 * THE ENGINE HAS ONE 'mine' AND ONE 'foes'. Both players are 'mine' to
 * themselves, so the battle is stored host-as-mine and flipped on the way out
 * to the guest. The client is unchanged by this and never learns which seat it
 * is in -- it still decides nothing, and now it does not even know its own
 * perspective.
 *
 * The BOARD needs no transformation. dhca_fighter_for_gem() resolves a gem to a
 * RANK within the asking side, so the 7x7 is genuinely symmetric and a gem
 * means "your front rank" to whoever matched it.
 */
function dhcal_view($pub, $seat) {
	if ($seat === 'host') return $pub;
	$out = $pub;
	$out['mine'] = $pub['foes'];
	$out['foes'] = $pub['mine'];
	$out['turn'] = ($pub['turn'] === 'mine') ? 'foes' : 'mine';
	if ($pub['over'] !== null && $pub['over'] !== '')
		$out['over'] = ($pub['over'] === 'mine') ? 'foes' : 'mine';
	$out['fx'] = dhcal_flip_fx(isset($pub['fx']) ? $pub['fx'] : array());
	return $out;
}

function dhcal_flip_fx($fx) {
	$out = array();
	foreach ($fx as $e) {
		if (isset($e['side'])) $e['side'] = ($e['side'] === 'mine') ? 'foes' : 'mine';
		$out[] = $e;
	}
	return $out;
}

/** Which engine side this seat plays. */
function dhcal_side($seat) { return $seat === 'host' ? 'mine' : 'foes'; }

/* ---------- starting --------------------------------------------------------- */

/**
 * Both players pick a Crew, which is the one genuinely new thing about live: in
 * a ranked battle only the attacker picks and the defender's Crew is whatever
 * dhca_defending_crew() says they have standing.
 *
 * RECOVERING FIGHTERS ARE ALLOWED. The bench exists to price ranked battles and
 * a live battle has no price, so gating the sport on the economy it is
 * deliberately outside of would be the wrong rule. Availability is not checked
 * here on purpose -- see dhcarena.md §8d.
 */
function dhcal_pick($conn, $user_id, $fighter_ids) {
	$crew = dhca_crew($conn, $user_id);
	$byId = array(); foreach ($crew as $f) $byId[(int)$f['id']] = $f;
	$out = array();
	foreach ($fighter_ids as $fid) {
		$fid = (int)$fid;
		if (!isset($byId[$fid])) return array(null, 'That is not your Fighter.');
		$out[] = $byId[$fid];
	}
	if (count($out) !== DHCA_CREW_SIZE) return array(null, 'Pick '.DHCA_CREW_SIZE.' Fighters.');
	return array($out, '');
}

function dhcal_challenge($conn, $host_id, $guest_id, $fighter_ids) {
	$host_id = (int)$host_id; $guest_id = (int)$guest_id;
	if ($guest_id === $host_id) return array(false, 'Pick somebody else.', null);
	if ($guest_id <= 0)         return array(false, 'Pick somebody to challenge.', null);

	if (dhcal_current($conn, $host_id))
		return array(false, 'You already have a live match open.', null);
	if (dhcal_current($conn, $guest_id))
		return array(false, 'They are already in a live match.', null);

	list($mine, $err) = dhcal_pick($conn, $host_id, $fighter_ids);
	if (!$mine) return array(false, $err, null);

	$ids = array_map(function($f){ return (int)$f['id']; }, $mine);
	$conn->query(sprintf(
		"INSERT INTO dhc_arena_live (host_id,guest_id,status,seed,host_crew,created_at,updated_at)
		 VALUES (%d,%d,0,%d,'%s',NOW(),NOW())",
		$host_id, $guest_id, random_int(1, 0x7FFFFFFE),
		$conn->real_escape_string(json_encode($ids))));
	$id = (int)$conn->insert_id;
	if (!$id) return array(false, 'Could not send the challenge.', null);
	return array(true, '', dhcal_row($conn, $id));
}

function dhcal_decline($conn, $user_id, $id) {
	$row = dhcal_row($conn, $id);
	if (!$row || !dhcal_seat($row, $user_id)) return array(false, 'No such match.');
	if ((int)$row['status'] !== 0)            return array(false, 'Too late.');
	dhcal_close($conn, $row);
	return array(true, '');
}

/** The guest picks a Crew and the board is dealt. */
function dhcal_accept($conn, $user_id, $id, $fighter_ids) {
	$row = dhcal_row($conn, $id);
	if (!$row)                                     return array(false, 'No such match.', null);
	if (dhcal_seat($row, $user_id) !== 'guest')    return array(false, 'That is not your challenge.', null);
	if ((int)$row['status'] !== 0)                 return array(false, 'That challenge is closed.', null);
	if (dhcal_expired($row)) { dhcal_close($conn, $row); return array(false, 'That challenge expired.', null); }

	list($theirs, $err) = dhcal_pick($conn, $user_id, $fighter_ids);
	if (!$theirs) return array(false, $err, null);

	$hostIds = json_decode($row['host_crew'], true);
	if (!is_array($hostIds)) return array(false, 'That challenge is broken.', null);
	list($hostCrew, $err2) = dhcal_pick($conn, (int)$row['host_id'], $hostIds);
	// The host may have deleted a Fighter between challenging and this moment.
	if (!$hostCrew) { dhcal_close($conn, $row); return array(false, 'Their Crew is no longer available.', null); }

	$rarity = dhcf_rarity();
	$names  = array(
		'mine' => array_map(function($f){ return $f['display']; }, $hostCrew),
		'foes' => array_map(function($f){ return $f['display']; }, $theirs),
	);
	$b = dhca_new_battle(
		array_map(function($f){ return $f['traits']; }, $hostCrew),
		array_map(function($f){ return $f['traits']; }, $theirs),
		$names, $rarity, (int)$row['seed']);

	/* Same meta shape the ranked battle uses, so dhca_fiercest() works on it
	   unchanged -- it reads meta.mineIds / meta.foeIds. */
	$b['meta'] = array(
		'live'     => (int)$row['id'],
		'attacker' => (int)$row['host_id'],
		'defender' => (int)$row['guest_id'],
		'mineIds'  => array_map(function($f){ return (int)$f['id']; }, $hostCrew),
		'foeIds'   => array_map(function($f){ return (int)$f['id']; }, $theirs),
		'moves'    => array(),
		'tl'       => array(),   // seq => that move's fx, for a poller to animate
	);

	$guestIds = array_map(function($f){ return (int)$f['id']; }, $theirs);
	$conn->query(sprintf(
		"UPDATE dhc_arena_live SET status = 1, guest_crew = '%s', state = '%s',
		        turn_at = NOW(), updated_at = NOW()
		 WHERE id = %d AND status = 0",
		$conn->real_escape_string(json_encode($guestIds)),
		$conn->real_escape_string(json_encode($b)), (int)$row['id']));
	if (!$conn->affected_rows) return array(false, 'That challenge is closed.', null);
	return array(true, '', dhcal_row($conn, (int)$row['id']));
}

/* ---------- playing ---------------------------------------------------------- */

function dhcal_state($row) {
	if ($row['state'] === null || $row['state'] === '') return null;
	$b = json_decode($row['state'], true);
	return is_array($b) ? $b : null;
}

function dhcal_save($conn, $id, $b) {
	$conn->query(sprintf("UPDATE dhc_arena_live SET state = '%s', moves = '%s', updated_at = NOW()
	                      WHERE id = %d",
		$conn->real_escape_string(json_encode($b)),
		$conn->real_escape_string(json_encode($b['meta']['moves'])), (int)$id));
}

/** Record one applied move and its timeline, trimming the tail we keep. */
function dhcal_record_move(&$b, $a, $z) {
	$b['meta']['moves'][] = array((int)$a, (int)$z);
	$seq = count($b['meta']['moves']);
	$b['meta']['tl'][(string)$seq] = $b['fx'];
	if (count($b['meta']['tl']) > DHCAL_TL_KEEP) {
		$keys = array_keys($b['meta']['tl']);
		sort($keys, SORT_NUMERIC);
		while (count($keys) > DHCAL_TL_KEEP) { unset($b['meta']['tl'][array_shift($keys)]); }
	}
}

/**
 * THE TURN CLOCK. On expiry the AI takes that turn -- it already exists, and it
 * is the same answer for a stall and for a dropped connection. Nobody should
 * lose to their wifi, and with no stake there is nothing to lose to it anyway.
 *
 * Returns true if it played anything.
 */
function dhcal_clock($conn, &$row, &$b) {
	if ((int)$row['status'] !== 1 || $b['over'] !== null) return false;
	$began = $row['turn_at'] ? strtotime($row['turn_at']) : 0;
	if (!$began || time() - $began < DHCAL_TURN_S) return false;

	$side = $b['turn'];
	$played = false; $guard = 0;
	// keeps answering while it earns extra turns, the same way dhca_move() does
	while ($b['over'] === null && $b['turn'] === $side && $guard++ < 12) {
		$mv = dhca_ai_move($b);
		if (!$mv) { dhca_fill_board($b); $mv = dhca_ai_move($b); }
		if (!$mv) break;
		if (!dhca_play($b, $side, $mv[0], $mv[1])) break;
		$b['log'][] = 'Turn ran out — the Arena played it.';
		dhcal_record_move($b, $mv[0], $mv[1]);
		$played = true;
	}
	if (!$played) return false;
	$conn->query("UPDATE dhc_arena_live SET turn_at = NOW() WHERE id = ".(int)$row['id']);
	$row['turn_at'] = date('Y-m-d H:i:s');
	if ($b['over'] !== null) dhcal_finish($conn, $row, $b);
	else                     dhcal_save($conn, (int)$row['id'], $b);
	return true;
}

function dhcal_move($conn, $user_id, $id, $a, $z) {
	$row = dhcal_row($conn, $id);
	if (!$row)                     return array(false, 'No such match.', null, null);
	$seat = dhcal_seat($row, $user_id);
	if (!$seat)                    return array(false, 'Not your match.', null, null);
	if ((int)$row['status'] !== 1) return array(false, 'That match is not running.', null, null);

	$b = dhcal_state($row);
	if (!$b) return array(false, 'That match is broken.', null, null);

	// the clock may have handed this very turn away while the page sat idle
	dhcal_clock($conn, $row, $b);

	$side = dhcal_side($seat);
	if ($b['over'] !== null)   return array(false, 'That match is over.', $row, $b);
	if ($b['turn'] !== $side)  return array(false, 'Not your turn.', $row, $b);

	if (!dhca_play($b, $side, (int)$a, (int)$z))
		return array(false, 'That slide makes no match.', $row, $b);
	dhcal_record_move($b, $a, $z);

	$conn->query("UPDATE dhc_arena_live SET turn_at = NOW() WHERE id = ".(int)$row['id']);
	$row['turn_at'] = date('Y-m-d H:i:s');
	if ($b['over'] !== null) dhcal_finish($conn, $row, $b);
	else                     dhcal_save($conn, (int)$row['id'], $b);
	return array(true, '', $row, $b);
}

/**
 * What a polling client gets. `since` is the last move sequence it has already
 * animated; anything newer comes back as one timeline to play.
 */
function dhcal_poll($conn, $user_id, $id, $since) {
	$row = dhcal_row($conn, $id);
	if (!$row) return array(false, 'No such match.', null);
	$seat = dhcal_seat($row, $user_id);
	if (!$seat) return array(false, 'Not your match.', null);

	$b = dhcal_state($row);
	if ($b) {
		if (dhcal_expired($row)) { dhcal_close($conn, $row); $row = dhcal_row($conn, $id); }
		else dhcal_clock($conn, $row, $b);
	}
	return array(true, '', dhcal_payload($conn, $row, $b, $seat, (int)$since));
}

/**
 * One shape for every live reply, so the client has one thing to handle whether
 * it just moved, just polled, or just accepted.
 */
function dhcal_payload($conn, $row, $b, $seat, $since) {
	$status = (int)$row['status'];
	$other  = ($seat === 'host') ? (int)$row['guest_id'] : (int)$row['host_id'];
	$them   = dhca_identity($conn, $other);
	$out = array(
		'live'    => (int)$row['id'],
		'seat'    => $seat,
		'status'  => $status,
		'them'    => $them['name'],
		'turnFor' => DHCAL_TURN_S,
	);
	if ($status === 0) {
		$out['left'] = max(0, DHCAL_INVITE_S - (time() - strtotime($row['updated_at'])));
		return $out;
	}
	if (!$b) { $out['status'] = 3; return $out; }

	$seq = count($b['meta']['moves']);
	$out['seq'] = $seq;
	$pub = dhca_public($b);

	/* Only the moves this client has not seen carry a timeline. Further behind
	   than the tail we keep -- a tab asleep for a while -- and it resyncs to the
	   current board with no animation, which is bounded and honest. */
	$fx = array(); $resync = false;
	if ($since >= 0 && $since < $seq) {
		for ($n = $since + 1; $n <= $seq; $n++) {
			if (!isset($b['meta']['tl'][(string)$n])) { $resync = true; $fx = array(); break; }
			$fx = array_merge($fx, $b['meta']['tl'][(string)$n]);
		}
	}
	$pub['fx'] = $fx;
	$out['resync'] = $resync;
	$out['state']  = dhcal_view($pub, $seat);
	$out['yours']  = ($b['over'] === null) && ($b['turn'] === dhcal_side($seat));
	$out['clock']  = $row['turn_at']
		? max(0, DHCAL_TURN_S - (time() - strtotime($row['turn_at']))) : DHCAL_TURN_S;
	if ($b['over'] !== null) {
		$out['over'] = $out['state']['over'];       // already flipped for this seat
		$out['won']  = ($b['over'] === dhcal_side($seat));
	}
	return $out;
}

/* ---------- the end ---------------------------------------------------------- */

/**
 * Mark it finished and tell Discord. NOTHING ELSE HAPPENS: no bench, no record,
 * no ladder, no trait. Bragging rights are the entire payout, which is exactly
 * why the announcement is the feature rather than decoration.
 */
function dhcal_finish($conn, $row, &$b) {
	$hostWon = ($b['over'] === 'mine');
	$conn->query(sprintf("UPDATE dhc_arena_live SET status = 2, winner = %d, state = '%s',
	                             moves = '%s', ended_at = NOW(), updated_at = NOW()
	                      WHERE id = %d AND status = 1",
		$hostWon ? 1 : 2,
		$conn->real_escape_string(json_encode($b)),
		$conn->real_escape_string(json_encode($b['meta']['moves'])), (int)$row['id']));
	// Only the request that actually closed it announces, so two clients
	// finishing the same battle at once cannot post twice.
	if ($conn->affected_rows) dhcal_announce($conn, $row, $b, $hostWon);
}

function dhcal_announce($conn, $row, $b, $hostWon) {
	if (!function_exists('discordmsg')) {
		if (is_file(__DIR__ . '/webhooks.php')) { ob_start(); include_once __DIR__ . '/webhooks.php'; ob_end_clean(); }
		if (!function_exists('discordmsg')) return;
	}
	try {
		$hostU = dhca_identity($conn, (int)$row['host_id']);
		$guestU = dhca_identity($conn, (int)$row['guest_id']);
		$winU  = $hostWon ? $hostU : $guestU;
		$loseU = $hostWon ? $guestU : $hostU;

		$standing = 0;
		foreach ($b[$hostWon ? 'mine' : 'foes'] as $f) if (empty($f['ko'])) $standing++;

		/* Said plainly, because the whole point is that this was two people and
		   not an AI -- and because it must never be mistaken for a ladder
		   result by somebody scrolling the channel. */
		$desc  = "**".$winU['name']."** beats **".$loseU['name']."** in a live match.\n\n";
		$desc .= "🎮 **Live** — both Crews played by their owners, in real time\n";
		$desc .= "🏁 **Result:** ".$standing." still standing after ".(int)$b['round']." rounds\n";
		if ((int)$b['stats']['bombs'] > 0)
			$desc .= "💣 **Bombs:** ".(int)$b['stats']['bombs']." armed, "
			       . (int)$b['stats']['blasts']." detonated\n";
		if ((int)$b['stats']['best'] > 2)
			$desc .= "✦ **Best chain:** x".(int)$b['stats']['best']."\n";
		$desc .= "\n*A friendly. Nothing was staked and nothing was won "
		       . "— except this.*";

		// dhca_fiercest() reads meta.mineIds / meta.foeIds and 'mine'/'foes',
		// all of which this battle has in the ranked shape, so it works as-is.
		$hero = dhca_fiercest($conn, $b, $hostWon);
		$img  = '';
		if ($hero && $hero['traits']) {
			if (!function_exists('dhcf_render_fighter') && is_file(__DIR__ . '/dhcfighters-notify.php')) {
				ob_start(); include_once __DIR__ . '/dhcfighters-notify.php'; ob_end_clean();
			}
			if (function_exists('dhcf_render_fighter')) {
				ob_start(); $img = dhcf_render_fighter($hero['traits'], $hero['serial']); ob_end_clean();
			}
			$desc .= "\n🔥 **Fiercest:** ".$hero['f']['name']
			       . ($hero['dealt'] > 0 ? " — ".number_format($hero['dealt'])." damage" : "")
			       . " · ".$hero['f']['kit']['name'];
		}

		// Both get pinged: unlike a ranked attack, both of them chose this and
		// both want to see it said out loud.
		$ping = trim($hostU['mention'].' '.$guestU['mention']);
		$thumb  = $winU['avatar'] !== '' ? $winU['avatar'] : $img;
		$author = array('name' => $winU['name'].' wins a live match');
		if ($winU['avatar'] !== '') $author['icon_url'] = $winU['avatar'];

		ob_start();
		discordmsg('🎮 Arena — Live Match', $desc, $img,
			'https://skulliance.io/staking/dhcarena.php', 'dhcarena', $thumb,
			'F5A623', $author, null, $ping);
		ob_end_clean();
	} catch (Throwable $e) {
		// never reaches the players
	}
}
