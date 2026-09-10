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
 *     date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     INDEX (user_id), INDEX (reward)
 *   );
 *
 * `held` is waves survived (wave - start_wave), which is what the board ranks
 * by. It is stored rather than computed so the board does not have to trust
 * two columns agreeing, and so a later change to how a run starts cannot
 * silently rewrite history.
 */

define('GUARDIANS_MIN_WAVE_SECONDS', 15);

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
function guardiansRecordDefeat($conn, $user_id, $wave, $lost) {
	$user_id = intval($user_id);
	$wave    = max(0, intval($wave));
	$lost    = max(0, intval($lost));
	if ($user_id <= 0) return null;

	$r = $conn->query("SELECT start_wave, scratch, TIMESTAMPDIFF(SECOND, started_at, NOW()) AS secs
	                   FROM guardians_runs WHERE user_id = $user_id LIMIT 1");
	if (!$r || $r->num_rows === 0) return null;
	$run        = $r->fetch_assoc();
	$start_wave = max(1, intval($run['start_wave']));
	$scratch    = intval($run['scratch']) ? 1 : 0;
	$secs       = max(0, intval($run['secs']));

	$held = max(0, $wave - $start_wave);
	// The bound. Generous on purpose: see GUARDIANS_MIN_WAVE_SECONDS.
	if ($held > 0 && $secs < $held * GUARDIANS_MIN_WAVE_SECONDS) {
		guardiansClearRun($conn, $user_id);
		error_log("guardians: rejected implausible score user=$user_id held=$held secs=$secs");
		return null;
	}

	$conn->query("
		INSERT INTO guardians_scores (user_id, wave, start_wave, held, lost, seconds, scratch, reward)
		VALUES ($user_id, $wave, $start_wave, $held, $lost, $secs, $scratch, 0)
	");
	guardiansClearRun($conn, $user_id);
	return array('wave' => $wave, 'start_wave' => $start_wave, 'held' => $held,
	             'lost' => $lost, 'seconds' => $secs, 'scratch' => $scratch);
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

	$name = 'A guardian';
	$ur = $conn->query("SELECT username FROM users WHERE id = $user_id LIMIT 1");
	if ($ur && $ur->num_rows) $name = (string)$ur->fetch_assoc()['username'];

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

	$mins  = intval(floor($result['seconds'] / 60));
	$title = 'The realm falls at wave ' . $result['wave'];
	$desc  = '**' . $name . '** ' . $where . ' and survived **' . $result['held'] .
	         '** wave' . ($result['held'] === 1 ? '' : 's') .
	         ' past where they began (wave ' . $result['start_wave'] . ').' .
	         "\n" . $result['lost'] . ' guardian' . ($result['lost'] === 1 ? '' : 's') .
	         ' lost over ' . ($mins > 0 ? $mins . ' minute' . ($mins === 1 ? '' : 's') : 'under a minute') . '.';

	discordmsg(
		$title, $desc, $image,
		'https://skulliance.io/staking/guardians.php',
		'guardians',
		'https://skulliance.io/staking/icons/locations/tower.png',
		'c0392b'
	);
}
