<?php
/*
 * REALM GUARDIANS -- persistence, scores and announcements.
 *
 * A SEPARATE FILE, not part of db.php, for the same reason obscura-lib.php is:
 * this is a prototype and can be rewritten wholesale without touching a file
 * the whole platform depends on. db.php holds only the leaderboard entry
 * points, because the boards registry lives there.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS GAME CANNOT BE SERVER-AUTHORITATIVE (and what follows from it)
 * ---------------------------------------------------------------------------
 * Crypt Conquest keeps its run in the database and mutates it one AJAX call
 * per turn, so the server is the only thing that decides anything. Guardians
 * cannot work that way: it is a real-time simulation stepping ten times a
 * second, and round-tripping every tick is neither possible nor sane.
 *
 * So the client owns the simulation and the server stores what it is told.
 * That is a trust boundary, and with CARBON on the leaderboard it has to be
 * named rather than glossed over:
 *
 *   - A saved SNAPSHOT is convenience only. It is never scored. The worst a
 *     forged snapshot buys is a nicer position to resume from, and resuming
 *     is not itself worth anything.
 *   - A submitted SCORE is bounded by wall-clock time. The run row records
 *     started_at server-side at Begin, and a wave cannot be reached faster
 *     than GUARDIANS_MIN_WAVE_SECONDS. That figure is deliberately generous:
 *     the modelled floor is ~28s for wave 1 (a wave cannot end until the last
 *     attacker has walked the length of the field) and ~47s at wave 50+, so
 *     15s rejects nothing a real player can do while making "wave 9000 in a
 *     minute" impossible.
 *   - Full verification is possible later WITHOUT changing the client. The
 *     simulation is deterministic and seeded, and guardians.php already keeps
 *     an action log of [tick, action] pairs, so a PHP replay could reproduce a
 *     run exactly. That is a port of the combat loop, which is why it is not
 *     here yet -- the bound above is the cheap 90%.
 *
 * ---------------------------------------------------------------------------
 * REQUIRES A MIGRATION (the page says so plainly rather than fataling):
 *
 *   CREATE TABLE guardians_runs (
 *     user_id      INT NOT NULL PRIMARY KEY,
 *     state        LONGTEXT NULL,
 *     wave         INT NOT NULL DEFAULT 0,
 *     start_wave   INT NOT NULL DEFAULT 1,
 *     scratch      TINYINT NOT NULL DEFAULT 0,
 *     started_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 *   CREATE TABLE guardians_scores (
 *     id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *     user_id      INT NOT NULL,
 *     wave         INT NOT NULL DEFAULT 0,
 *     start_wave   INT NOT NULL DEFAULT 1,
 *     held         INT NOT NULL DEFAULT 0,
 *     lost         INT NOT NULL DEFAULT 0,
 *     seconds      INT NOT NULL DEFAULT 0,
 *     scratch      TINYINT NOT NULL DEFAULT 0,
 *     reward       TINYINT NOT NULL DEFAULT 0,
 *     breacher_id  VARCHAR(25) NOT NULL DEFAULT '',
 *     breach_carbon INT NOT NULL DEFAULT 0,
 *     date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     INDEX (user_id), INDEX (reward)
 *   );
 *
 * MIGRATION for an existing install (the two breacher columns are new):
 *
 *   ALTER TABLE guardians_scores
 *     ADD COLUMN breacher_id VARCHAR(25) NOT NULL DEFAULT '',
 *     ADD COLUMN breach_carbon INT NOT NULL DEFAULT 0;
 *
 * `held` is waves survived (wave - start_wave), which is what the board ranks
 * by. It is stored rather than computed so the board does not have to trust
 * two columns agreeing, and so a later change to how a run starts cannot
 * silently rewrite history.
 */

define('GUARDIANS_MIN_WAVE_SECONDS', 15);

/*
 * Whether the staker whose avatar broke the wall gets an actual Discord PING,
 * or just a highlighted name in the embed. See guardiansAnnounceDefeat() --
 * the horde is drawn at random, so a ping notifies someone for something they
 * had no part in, once per fallen siege across the whole membership. One
 * constant, so turning the noise off is a one-line change and not a rewrite.
 */
define('GUARDIANS_PING_BREACHER', true);

/* ---------------------------------------------------------------------------
 * THE LIVE RUN
 * ------------------------------------------------------------------------- */

// One row per player. A new Begin overwrites whatever was there -- a player
// only ever has one siege in progress, and starting a fresh one is an explicit
// abandonment of the old.
function guardiansBeginRun($conn, $user_id, $start_wave, $scratch) {
	$user_id    = intval($user_id);
	$start_wave = max(1, intval($start_wave));
	$scratch    = $scratch ? 1 : 0;
	if ($user_id <= 0) return false;
	$conn->query("
		INSERT INTO guardians_runs (user_id, state, wave, start_wave, scratch, started_at, updated_at)
		VALUES ($user_id, NULL, $start_wave, $start_wave, $scratch, NOW(), NOW())
		ON DUPLICATE KEY UPDATE
			state = NULL, wave = $start_wave, start_wave = $start_wave,
			scratch = $scratch, started_at = NOW(), updated_at = NOW()
	");
	return true;
}

// A snapshot, stored verbatim. Never scored, never trusted for anything but
// putting the player back where they were -- see the header.
function guardiansSaveRun($conn, $user_id, $state_json, $wave) {
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	// A snapshot is a few KB of plain JSON. Anything wildly larger is not a
	// board state, so refuse it rather than let a row grow without bound.
	if (!is_string($state_json) || strlen($state_json) > 262144) return false;
	if (json_decode($state_json) === null) return false;   // must be real JSON
	$state = $conn->real_escape_string($state_json);
	$wave  = max(0, intval($wave));
	$conn->query("
		UPDATE guardians_runs SET state = '$state', wave = $wave, updated_at = NOW()
		WHERE user_id = $user_id
	");
	return true;
}

function guardiansLoadRun($conn, $user_id) {
	$user_id = intval($user_id);
	if ($user_id <= 0) return null;
	$r = $conn->query("SELECT * FROM guardians_runs WHERE user_id = $user_id LIMIT 1");
	if (!$r || $r->num_rows === 0) return null;
	$row = $r->fetch_assoc();
	if (empty($row['state'])) return null;   // begun but never snapshotted
	return $row;
}

function guardiansClearRun($conn, $user_id) {
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	$conn->query("DELETE FROM guardians_runs WHERE user_id = $user_id");
	return true;
}

/*
 * THE BREACH PAYOUT: whatever CARBON the realm was still holding when the wall
 * came down goes to the member whose avatar broke it. Deliberately uncapped --
 * a player who hoards simply makes some stranger rich, which is the lottery
 * working as intended, not a bug to design around.
 *
 * This constant is NOT that cap. It is an INTEGRITY bound, the same kind
 * GUARDIANS_MIN_WAVE_SECONDS is: the carbon figure arrives from the browser,
 * and without a ceiling the defeat endpoint is an unbounded "mint CARBON"
 * call. It is set orders of magnitude above anything reachable in play --
 * measured, a real run banked ~1.5 CARBON/sec of leftover and the game's own
 * theoretical maximum (a Mine-30 realm that upgrades nothing and hoards
 * everything) is 50/sec. So 200/sec refuses only claims the simulation could
 * not have produced, and never trims an honest one.
 *
 * Tighten it here if that headroom ever looks too generous; nothing else has
 * to change.
 */
define('GUARDIANS_MAX_CARBON_RATE', 200);

/* ---------------------------------------------------------------------------
 * DEFEAT
 * ------------------------------------------------------------------------- */

/*
 * Records the run and clears the live snapshot. Returns the row it wrote, or
 * null when the claim failed the time bound.
 *
 * The wave is taken from the CLIENT and the elapsed time from the SERVER, so
 * the two cannot be forged together. A run with no row (a guest, or a run
 * begun before this shipped) is simply not scored -- refusing to record is
 * always safer than recording something unbounded.
 */
function guardiansRecordDefeat($conn, $user_id, $wave, $lost, $played = 0,
                               $carbon = 0, $breacher_id = '') {
	$user_id = intval($user_id);
	$wave    = max(0, intval($wave));
	$lost    = max(0, intval($lost));
	$played  = max(0, intval($played));
	$carbon  = max(0, intval($carbon));
	// Digits only before it goes anywhere near a query or a mention.
	$breacher_id = substr(preg_replace('/[^0-9]/', '', (string)$breacher_id), 0, 25);
	if ($user_id <= 0) return null;

	$r = $conn->query("SELECT start_wave, scratch, TIMESTAMPDIFF(SECOND, started_at, NOW()) AS secs
	                   FROM guardians_runs WHERE user_id = $user_id LIMIT 1");
	if (!$r || $r->num_rows === 0) return null;
	$run        = $r->fetch_assoc();
	$start_wave = max(1, intval($run['start_wave']));
	$scratch    = intval($run['scratch']) ? 1 : 0;
	$secs       = max(0, intval($run['secs']));

	$held = max(0, $wave - $start_wave);
	// The bound, measured against WALL CLOCK. This must keep using $secs and
	// never the client's figure: a forged play time could otherwise buy back
	// exactly the seconds the check demands.
	if ($held > 0 && $secs < $held * GUARDIANS_MIN_WAVE_SECONDS) {
		guardiansClearRun($conn, $user_id);
		error_log("guardians: rejected implausible score user=$user_id held=$held secs=$secs");
		return null;
	}

	/*
	 * TWO DIFFERENT TIMES, and `seconds` stores the one a human would recognise.
	 *
	 * $secs is wall clock since started_at. It is the right number for the
	 * bound above -- it cannot be forged -- but it is the WRONG number to show
	 * anyone, because a run can now be paused and resumed across sessions, so
	 * it counts hours the player was not at the keyboard. Reported: a modal
	 * reading "45m 49s" against a Discord post reading "264 minutes" for the
	 * same siege.
	 *
	 * $played is the client's own S.tick/10, which is exactly what the defeat
	 * modal printed and stops while paused. It is untrusted, so it is CLAMPED
	 * to the wall clock: you cannot have played longer than the run existed.
	 * That makes it unforgeable upward, which is all that matters for a number
	 * that is displayed and never scored. A run from before this shipped sends
	 * nothing, and falls back to the old wall-clock figure.
	 *
	 * NOTE rows written before this change hold wall clock in this column.
	 * The leaderboard does not read it -- checkGuardiansLeaderboard() ranks on
	 * held/wave/lost -- so the mixed history only ever affected these posts.
	 */
	$shown = ($played > 0) ? min($played, $secs) : $secs;

	/*
	 * THE BREACH PAYOUT. Two things have to be true before a single CARBON
	 * moves, because BOTH the amount and the recipient arrive from the client.
	 *
	 * 1. The amount is bounded by GUARDIANS_MAX_CARBON_RATE * elapsed. Not an
	 *    economic cap -- see that constant -- but the difference between a
	 *    payout and an unbounded mint.
	 * 2. The recipient must be a real member who was ELIGIBLE for the horde:
	 *    a non-empty discord_id and avatar, and not the player themselves.
	 *    That is the same WHERE the horde roster is drawn with in
	 *    guardians.php, re-checked here rather than trusted, because a forged
	 *    breacher used to cost a wrong @mention and would now cost CARBON.
	 *
	 * What this does NOT prevent: a modified client naming a SPECIFIC eligible
	 * member instead of the one who actually broke through. Preventing that
	 * needs the horde roster persisted at Begin, which it is not. The exposure
	 * is bounded by (1) and by the fact that the carbon was the player's own.
	 */
	$paid = 0;
	if ($breacher_id !== '' && $carbon > 0) {
		$ceiling = $secs * GUARDIANS_MAX_CARBON_RATE;
		$amount  = min($carbon, $ceiling);
		$esc = $conn->real_escape_string($breacher_id);
		$br  = $conn->query("SELECT id FROM users
		                     WHERE discord_id = '$esc' AND discord_id != '' AND avatar != ''
		                       AND id != $user_id LIMIT 1");
		if ($br && $br->num_rows && $amount > 0) {
			$bid  = intval($br->fetch_assoc()['id']);
			$paid = intval($amount);
			// Currency 15, the same CARBON the boards pay into.
			updateBalance($conn, $bid, 15, $paid);
			logCredit($conn, $bid, $paid, 15);
		}
	}

	$esc_breacher = $conn->real_escape_string($breacher_id);
	$conn->query("
		INSERT INTO guardians_scores (user_id, wave, start_wave, held, lost, seconds, scratch, reward,
		                              breacher_id, breach_carbon)
		VALUES ($user_id, $wave, $start_wave, $held, $lost, $shown, $scratch, 0,
		        '$esc_breacher', $paid)
	");
	guardiansClearRun($conn, $user_id);
	return array('wave' => $wave, 'start_wave' => $start_wave, 'held' => $held,
	             'lost' => $lost, 'seconds' => $shown, 'elapsed' => $secs,
	             'scratch' => $scratch, 'breach_carbon' => $paid);
}

/* ---------------------------------------------------------------------------
 * THE ANNOUNCEMENT
 * ------------------------------------------------------------------------- */

/*
 * The realm's own theme art carries the post, because that is the thing the
 * run was about -- a wall of identical game icons says nothing about whose
 * realm just fell. Falls back to the game icon when a player has no realm
 * (a conscript run), which is the honest picture in that case too.
 */
function guardiansAnnounceDefeat($conn, $user_id, $result) {
	if (!$result || !function_exists('discordmsg')) return;
	$user_id = intval($user_id);

	/*
	 * THE THUMBNAIL IS THE PLAYER'S OWN AVATAR, not the Tower icon.
	 *
	 * Every siege posted the same tower, so a channel of these was a wall of
	 * identical icons and you had to read the text to see whose run it was. The
	 * avatar makes the player identifiable at a glance, which is the whole job
	 * of that corner of the embed. Same cdn.discordapp.com URL shape the rest
	 * of the platform builds (obscura-lib.php, realms.php, launchpad.php).
	 *
	 * Falls back to the Tower when a player has no Discord avatar -- the field
	 * must not be empty, or the embed renders with a hole where the icon was.
	 */
	$name   = 'A guardian';
	$avatar = '';
	$ur = $conn->query("SELECT username, discord_id, avatar FROM users WHERE id = $user_id LIMIT 1");
	if ($ur && $ur->num_rows) {
		$u    = $ur->fetch_assoc();
		$name = (string)($u['username'] ?? 'A guardian');
		if (!empty($u['discord_id']) && !empty($u['avatar'])) {
			$avatar = 'https://cdn.discordapp.com/avatars/' . $u['discord_id'] . '/' . $u['avatar'] . '.png';
		}
	}
	$thumb = $avatar !== '' ? $avatar : 'https://skulliance.io/staking/icons/locations/tower.png';

	$realm_name = '';
	$theme_id   = 0;
	$rr = $conn->query("SELECT name, theme_id FROM realms WHERE user_id = $user_id LIMIT 1");
	if ($rr && $rr->num_rows) {
		$row        = $rr->fetch_assoc();
		$realm_name = (string)($row['name'] ?? '');
		$theme_id   = intval($row['theme_id'] ?? 0);
	}

	$image = ($theme_id > 0 && !$result['scratch'])
		? "https://skulliance.io/staking/images/themes/" . $theme_id . ".jpg"
		: "";

	$where = $result['scratch']
		? 'held the wall with conscripts'
		: ($realm_name !== '' ? 'defended ' . $realm_name : 'held the wall');

	/*
	 * Same shape as the defeat modal -- "45m 49s", not "45 minutes" -- because
	 * the two describe the same run and a player comparing them should not have
	 * to do arithmetic to see that they agree.
	 */
	$tsecs = max(0, intval($result['seconds']));
	$mins  = intval(floor($tsecs / 60));
	$held_for = $mins > 0 ? $mins . 'm ' . ($tsecs % 60) . 's' : $tsecs . 's';
	$title = 'The realm falls at wave ' . $result['wave'];
	$desc  = '**' . $name . '** ' . $where . ' and survived **' . $result['held'] .
	         '** wave' . ($result['held'] === 1 ? '' : 's') .
	         ' past where they began (wave ' . $result['start_wave'] . ').' .
	         "\n" . $result['lost'] . ' guardian' . ($result['lost'] === 1 ? '' : 's') .
	         ' lost over ' . $held_for . '.';

	/*
	 * WHO BROKE THE WALL. The horde is other stakers' avatars, so the attacker
	 * that landed the killing blow is a real member, and naming them is the
	 * whole charm of the horde being real people.
	 *
	 * Both fields are re-filtered here even though the endpoint already
	 * sanitised them, because this is the function that BUILDS the message and
	 * it should not depend on a caller having been careful. A mention string
	 * assembled from unfiltered input is how "@everyone" ends up in a webhook.
	 */
	$breacher    = preg_replace('/[^A-Za-z0-9 _.\-]/', '', (string)($result['breacher'] ?? ''));
	$breacher_id = preg_replace('/[^0-9]/', '', (string)($result['breacher_id'] ?? ''));
	/*
	 * WHAT THEY CARRIED OFF. The breacher takes whatever CARBON the realm was
	 * still holding, so the post says so -- and says the opposite when there
	 * was nothing left, which is the more satisfying line and the one a player
	 * who spent well has earned.
	 */
	$took = max(0, intval($result['breach_carbon'] ?? 0));
	$spoils = $took > 0
		? ' and carried off **' . number_format($took) . ' CARBON** from the vaults.'
		: ' and found the coffers bare.';
	$content     = '';
	if ($breacher_id !== '') {
		$desc .= "\nThe wall was broken by <@" . $breacher_id . ">" . $spoils;
		/*
		 * GUARDIANS_PING_BREACHER decides whether that is an actual
		 * notification or just a highlighted name. Discord only pings for a
		 * mention in the top-level content field, never one inside an embed.
		 *
		 * It is ON because it was asked for, but it is one constant because
		 * the case against is real: the horde is drawn at RANDOM from the
		 * membership, so this pings somebody for something they did not do,
		 * and it fires once per fallen siege across every player. Flip it to
		 * false and the name still renders and still links -- it just stops
		 * buzzing someone's phone.
		 */
		if (defined('GUARDIANS_PING_BREACHER') && GUARDIANS_PING_BREACHER) {
			// The ping now carries good news, which is most of the answer to the
			// objection above: it buzzes someone about CARBON they just received
			// rather than about a wall they did not really break.
			$content = $took > 0
				? '<@' . $breacher_id . '> broke through and took ' . number_format($took) . ' CARBON.'
				: '<@' . $breacher_id . '> broke through.';
		}
	} elseif ($breacher !== '') {
		$desc .= "\nThe wall was broken by " . $breacher . $spoils;
	}

	discordmsg(
		$title, $desc, $image,
		'https://skulliance.io/staking/guardians.php',
		'guardians',
		$thumb,
		'c0392b',
		null, null, $content
	);
}
