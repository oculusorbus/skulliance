<?php
/*
 * OBSCURA -- endless NFT art recognition run.
 *
 * You are shown a small crop of an NFT and pick which collection it came
 * from. Wrong answers widen the crop. Solve it and your streak grows and the
 * noose tightens: fewer attempts, more options, smaller crops. One failed
 * puzzle ends the run. Runs persist, so leaving mid-puzzle costs nothing.
 *
 * A SEPARATE FILE, not part of db.php, because this is a prototype -- it can
 * be deleted or rewritten wholesale without touching a file the whole
 * platform depends on.
 *
 * ---------------------------------------------------------------------------
 * THE IMAGE IS THE GAME, SO A BROKEN IMAGE MUST NEVER COST A PLAYER ANYTHING
 * ---------------------------------------------------------------------------
 * At streak 31+ a player gets ONE attempt. If the artwork failed to load,
 * they would lose a 31-run streak to a 404 they could do nothing about. That
 * is the fastest way to make a game feel rigged, so there are two guarantees:
 *
 *   1. A puzzle is only ever created from art VERIFIED on disk here, by
 *      stat-ing the file. getIPFS() falls back to a public IPFS gateway when
 *      nothing is cached -- cache-crypties-art.php's own header says that
 *      fallback is "slow and often fails outright". Obscura never uses it.
 *   2. If an image fails to render anyway, the client asks for a replacement
 *      and the server issues one WITHOUT spending an attempt or touching the
 *      streak (see obscuraReroll).
 *
 * ---------------------------------------------------------------------------
 * REQUIRES A MIGRATION (the page says so plainly rather than fataling):
 *
 *   CREATE TABLE obscura_runs (
 *     user_id       INT NOT NULL PRIMARY KEY,
 *     streak        INT NOT NULL DEFAULT 0,
 *     best_streak   INT NOT NULL DEFAULT 0,
 *     nft_id        INT NULL,
 *     answer_id     INT NULL,
 *     options       TEXT NULL,
 *     crop_x        FLOAT NOT NULL DEFAULT 50,
 *     crop_y        FLOAT NOT NULL DEFAULT 50,
 *     attempts_used INT NOT NULL DEFAULT 0,
 *     wrong         TEXT NULL,
 *     seen_collections TEXT NULL,
 *     updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 *   );
 *
 * If obscura_runs already exists without the no-repeat tracking:
 *
 *   ALTER TABLE obscura_runs ADD COLUMN seen_collections TEXT NULL;
 *
 * AND, for the leaderboards (run this one if the table above already exists):
 *
 *   CREATE TABLE obscura_scores (
 *     id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *     user_id      INT NOT NULL,
 *     streak       INT NOT NULL DEFAULT 0,
 *     solves       INT NOT NULL DEFAULT 0,
 *     active       TINYINT NOT NULL DEFAULT 1,
 *     reward       TINYINT NOT NULL DEFAULT 0,
 *     date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *     KEY user_reward (user_id, reward),
 *     KEY reward_active (reward, active)
 *   );
 *
 * ONE ROW PER RUN, updated live as the streak grows -- not written at the end.
 * A player sitting on a 40-streak they have not lost yet is exactly who the
 * board should be showing, and a write-on-death design would leave them off it
 * until they failed. `active = 1` means the run is still going.
 *
 * `reward = 0` means "not yet paid out", which is how every other game here
 * closes a week (see skull_racer_runs.reward). It is deliberately a flag rather
 * than a date comparison: the payout cron can then run at any time, pay
 * whatever is outstanding, and mark it paid, instead of depending on firing
 * inside a particular window.
 *
 * date_created carries a default because this table is NEW -- there are no
 * existing rows for that default to backfill and stamp with the migration
 * moment. Do NOT copy that pattern when adding a date column to a table that
 * already has history; see the note in checkActivityLeaderboard().
 */

// The noose. Deliberately a plain table rather than a formula: every tier is
// visible at a glance and tunable without arithmetic.
function obscuraDifficulty($streak) {
	if ($streak >= 31) return array('attempts'=>1, 'options'=>12, 'zooms'=>array(8));
	if ($streak >= 16) return array('attempts'=>2, 'options'=>10, 'zooms'=>array(10, 25));
	if ($streak >= 6)  return array('attempts'=>3, 'options'=>8,  'zooms'=>array(12, 25, 45));
	return                     array('attempts'=>3, 'options'=>6,  'zooms'=>array(15, 35, 60));
}

/*
 * How many columns the option buttons sit in, per option count.
 *
 * Fixed per count rather than auto-fitting, so the board has a deliberate
 * shape at each tier instead of reflowing into whatever the viewport allows.
 * Twelve gets 3 rows of 4 rather than 2 of 6, which would be cramped.
 *
 *   6 -> 3x2    8 -> 4x2    10 -> 5x2    12 -> 4x3
 *
 * Anything unexpected falls back to 3 across, which never looks broken.
 */
function obscuraColumns($option_count) {
	switch (intval($option_count)) {
		case 6:  return 3;
		case 8:  return 4;
		case 10: return 5;
		case 12: return 4;
	}
	return 3;
}

/*
 * The terms you are playing under RIGHT NOW, for the standing line above the
 * board. Built from obscuraDifficulty rather than written out, so it cannot
 * drift from the numbers actually in force.
 *
 * Deliberately separate from obscuraTierLabel below. One describes a state and
 * has to stay true for as long as it is on screen; the other announces a change
 * and is only true at the instant it fires. Conflating them is what left "ONE
 * attempt. 12 collections." sitting above a fresh streak-0 board.
 */
function obscuraTierTerms($streak) {
	$d = obscuraDifficulty($streak);
	return $d['options'] . ' collections, '
	     . $d['attempts'] . ($d['attempts'] === 1 ? ' attempt, ' : ' attempts, ')
	     . $d['zooms'][0] . '% of the artwork';
}

// Human-readable note shown ONLY when the difficulty changes, so the tightening
// reads as earned pressure rather than the game misbehaving. Transient: it goes
// in the result message, never in the standing line.
function obscuraTierLabel($streak) {
	if ($streak >= 31) return 'ONE attempt. 12 collections. 8% of the artwork.';
	if ($streak >= 16) return 'Two attempts now, and ten collections to choose from.';
	if ($streak >= 6)  return 'Tighter: eight collections, and the crop just shrank.';
	return '';
}
function obscuraTierIndex($streak) {
	if ($streak >= 31) return 3; if ($streak >= 16) return 2; if ($streak >= 6) return 1; return 0;
}

/*
 * Resolve an NFT's artwork to a LOCAL url, or null.
 *
 * Deliberately re-implements the local-cache lookup instead of calling
 * getIPFS(), because getIPFS() returns an ipfs.io url when nothing is cached
 * and there is no way to tell that from a real hit without string-matching
 * its output. Here, "no local file" is an explicit null.
 */
function obscuraLocalArt($ipfs, $collection_id, $project_id) {
	if ($ipfs === '' || $project_id <= 0) return null;
	if (strpos($ipfs, 'data:image/svg+xml;base64') === 0) return null; // not croppable
	$dir = __DIR__ . '/images/nfts/' . intval($project_id) . '/' . intval($collection_id) . '/';
	$matches = glob($dir . md5($ipfs) . '.*');
	if (empty($matches)) return null;
	// A truncated or zero-byte cache file renders as a broken image, which for
	// this game is the same as no image at all.
	if (@filesize($matches[0]) < 1024) return null;
	$ext = pathinfo($matches[0], PATHINFO_EXTENSION);
	$bust = ($m = @filemtime($matches[0])) ? '?v=' . $m : '';
	return '/staking/images/nfts/' . intval($project_id) . '/' . intval($collection_id) . '/' . md5($ipfs) . '.' . $ext . $bust;
}

/*
 * Resolve an NFT id straight to its local artwork url, or null.
 *
 * Safe to hand to the client ONLY once the puzzle it belongs to is over --
 * while a puzzle is live the url IS the answer, which is the whole reason
 * ajax/obscura-crop.php exists.
 */
function obscuraArtForNft($conn, $nft_id) {
	$nft_id = intval($nft_id);
	if ($nft_id <= 0) return null;
	$r = $conn->query("SELECT nfts.ipfs, nfts.collection_id, collections.project_id
	                   FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id
	                   WHERE nfts.id = $nft_id LIMIT 1");
	if (!$r || $r->num_rows === 0) return null;
	$a = $r->fetch_assoc();
	return obscuraLocalArt($a['ipfs'], $a['collection_id'], $a['project_id']);
}

/*
 * Everything the reveal screen shows: the artwork, what the piece is called, and
 * who holds it. Only ever called once a puzzle is judged.
 *
 * THE OWNER IS NAMED ONLY WITH THEIR CONSENT. Saying "held by X" publishes part
 * of X's collection to whoever is playing, and this platform already has a
 * control for that: visibility == 2 is what profile.php:40 and gallery.php:33
 * require before showing anyone's NFTs. Obscura uses the same gate rather than
 * inventing a looser one. Anything else -- unowned (user_id 0, which is most of
 * the table), or a holder who has not opted in -- and the holding is simply not
 * mentioned. Not "private", not "anonymous": mentioning it at all would leak
 * that somebody here holds it.
 */
function obscuraRevealDetails($conn, $nft_id) {
	$nft_id = intval($nft_id);
	if ($nft_id <= 0) return null;
	// LEFT JOIN, not INNER: an NFT with no Skulliance holder still has a name and
	// still deserves a reveal.
	$r = $conn->query("SELECT nfts.ipfs, nfts.name, nfts.collection_id, nfts.user_id,
	                          collections.project_id, users.username, users.visibility,
	                          users.avatar, users.discord_id
	                   FROM nfts
	                   INNER JOIN collections ON collections.id = nfts.collection_id
	                   LEFT JOIN users ON users.id = nfts.user_id
	                   WHERE nfts.id = $nft_id LIMIT 1");
	if (!$r || $r->num_rows === 0) return null;
	$a = $r->fetch_assoc();

	$art = obscuraLocalArt($a['ipfs'], $a['collection_id'], $a['project_id']);
	if ($art === null) return null;

	$out = array('art' => $art, 'name' => (string)($a['name'] ?? ''),
	             'owner' => '', 'owner_url' => '', 'owner_avatar' => '');
	if (intval($a['user_id']) > 0 && intval($a['visibility']) === 2 && !empty($a['username'])) {
		$out['owner']     = $a['username'];
		$out['owner_url'] = 'profile.php?username=' . urlencode($a['username']);
		// Same construction and same fallback as the leaderboard podium
		// (leaderboards.php:302-304). The avatar is gated with the username, not
		// separately: a face is every bit as identifying as a name.
		$out['owner_avatar'] = ($a['avatar'] && $a['discord_id'])
			? 'https://cdn.discordapp.com/avatars/' . $a['discord_id'] . '/' . $a['avatar'] . '.png'
			: 'icons/skull.png';
	}
	return $out;
}

/*
 * Find one NFT with verified local art, optionally avoiding collections the
 * player has already been asked about this run.
 *
 * Random-offset sampling rather than ORDER BY RAND(), which would sort the
 * whole table for one row. Several passes because a random id may land on a
 * gap, on art that was never cached, or -- once exclusions are in play -- on a
 * stretch of the table belonging entirely to collections already used.
 */
function obscuraSampleNft($conn, $max, $exclude = array()) {
	$not_in = '';
	if (!empty($exclude)) {
		$ids = array_filter(array_map('intval', $exclude));
		if (!empty($ids)) $not_in = " AND nfts.collection_id NOT IN (" . implode(',', $ids) . ")";
	}
	for ($pass = 0; $pass < 12; $pass++) {
		$from = random_int(1, $max);
		$res = $conn->query("
			SELECT nfts.id, nfts.ipfs, nfts.collection_id,
			       collections.name AS collection_name, collections.project_id,
			       projects.name AS project_name
			FROM nfts
			INNER JOIN collections ON collections.id = nfts.collection_id
			INNER JOIN projects ON projects.id = collections.project_id
			WHERE nfts.id >= $from AND nfts.ipfs != ''$not_in
			ORDER BY nfts.id ASC
			LIMIT 25");
		if (!$res) continue;
		while ($row = $res->fetch_assoc()) {
			$url = obscuraLocalArt($row['ipfs'], $row['collection_id'], $row['project_id']);
			if ($url !== null) { $row['art'] = $url; return $row; }
		}
	}
	return null;
}

/*
 * Pick a puzzle: one NFT with verified art, plus N-1 decoy collections.
 *
 * $seen holds the collections already used this run, so a run never asks about
 * the same collection twice -- being shown three crops from the same set in one
 * run makes the answer a gimme and the variety is the point.
 *
 * That exclusion is a PREFERENCE, never a blocker. A long enough streak will
 * eventually use up every collection on the platform, and at that point the
 * choice is between recycling one and ending the run for a reason the player
 * did nothing to deserve. So if nothing unseen can be found, it samples again
 * with no exclusion at all -- the cycle simply starts over.
 *
 * Returns null only if there is genuinely no usable art anywhere, which the
 * caller surfaces rather than pretending a puzzle exists.
 */
function obscuraPickPuzzle($conn, $option_count, $seen = array()) {
	$max = 0;
	if ($r = $conn->query("SELECT MAX(id) AS m FROM nfts")) { $max = intval($r->fetch_assoc()['m']); }
	if ($max <= 0) return null;

	$found = obscuraSampleNft($conn, $max, $seen);
	if (!$found && !empty($seen)) $found = obscuraSampleNft($conn, $max);   // cycle restarts
	if (!$found) return null;

	// Decoys: other collections that actually hold NFTs, so a player is never
	// offered a collection nothing could have come from.
	// Each option carries its PROJECT as well as the collection: collection
	// names alone are frequently useless on their own ("Season 1" tells you
	// nothing), and several projects run collections with similar names.
	$answer_id = intval($found['collection_id']);
	$opts = array(array('id'=>$answer_id, 'name'=>$found['collection_name'], 'project'=>$found['project_name']));
	$dr = $conn->query("
		SELECT collections.id, collections.name, projects.name AS project_name
		FROM collections
		INNER JOIN nfts ON nfts.collection_id = collections.id
		INNER JOIN projects ON projects.id = collections.project_id
		WHERE collections.id != $answer_id
		GROUP BY collections.id
		ORDER BY RAND()
		LIMIT " . (intval($option_count) - 1));
	if ($dr) while ($d = $dr->fetch_assoc()) {
		$opts[] = array('id'=>intval($d['id']), 'name'=>$d['name'], 'project'=>$d['project_name']);
	}
	shuffle($opts);

	return array(
		'nft_id'    => intval($found['id']),
		'answer_id' => $answer_id,
		'art'       => $found['art'],
		'options'   => $opts,
		// Crop centre, kept away from the extreme edges where NFT art is most
		// often flat background -- an unanswerable crop is unfair, not hard.
		'crop_x'    => random_int(25, 75),
		'crop_y'    => random_int(25, 75),
	);
}

function obscuraGetRun($conn, $user_id) {
	$user_id = intval($user_id);
	$r = @$conn->query("SELECT * FROM obscura_runs WHERE user_id = $user_id LIMIT 1");
	if ($r === false) return false;                 // table not migrated yet
	if ($r->num_rows > 0) return $r->fetch_assoc();
	$conn->query("INSERT INTO obscura_runs (user_id) VALUES ($user_id)");
	$r2 = $conn->query("SELECT * FROM obscura_runs WHERE user_id = $user_id LIMIT 1");
	return ($r2 && $r2->num_rows > 0) ? $r2->fetch_assoc() : false;
}

// Collections already used this run. Kept on the run row so it survives a
// reload, and cleared when the run ends.
function obscuraSeenCollections($run) {
	$seen = json_decode($run['seen_collections'] ?? '[]', true);
	return is_array($seen) ? $seen : array();
}

// Store a freshly picked puzzle against the run. Shared by "need a puzzle",
// "solved, next one" and "that image was broken, swap it".
function obscuraSetPuzzle($conn, $user_id, $puzzle, $seen = array()) {
	$user_id = intval($user_id);
	// Remember the answer's collection so the rest of this run avoids it. Capped
	// because the column is a TEXT blob read on every request, and a very long
	// streak would otherwise grow it without limit; the tail is also the least
	// useful part, since those puzzles are furthest back.
	$seen[] = intval($puzzle['answer_id']);
	$seen = array_values(array_unique(array_map('intval', $seen)));
	if (count($seen) > 200) $seen = array_slice($seen, -200);
	$seen_sql = "'" . $conn->real_escape_string(json_encode($seen)) . "'";
	$conn->query("UPDATE obscura_runs SET
		nft_id = " . intval($puzzle['nft_id']) . ",
		answer_id = " . intval($puzzle['answer_id']) . ",
		options = '" . $conn->real_escape_string(json_encode($puzzle['options'])) . "',
		crop_x = " . floatval($puzzle['crop_x']) . ",
		crop_y = " . floatval($puzzle['crop_y']) . ",
		attempts_used = 0, wrong = NULL, seen_collections = $seen_sql, updated_at = NOW()
		WHERE user_id = $user_id");
}

function obscuraEnsurePuzzle($conn, $user_id, $run) {
	if (!empty($run['nft_id'])) return $run;
	$diff = obscuraDifficulty(intval($run['streak']));
	$seen = obscuraSeenCollections($run);
	$p = obscuraPickPuzzle($conn, $diff['options'], $seen);
	if (!$p) return $run;                            // caller reports it
	obscuraSetPuzzle($conn, $user_id, $p, $seen);
	return obscuraGetRun($conn, $user_id);
}

/*
 * Score keeping. One obscura_scores row per RUN, updated on every solve, so an
 * unbeaten streak is on the board while it is still being built.
 *
 * A run that loses its very first puzzle never solved anything and so never
 * creates a row -- there is nothing to rank, and an empty row would just dilute
 * the "runs" count that Activity reads.
 */
function obscuraRecordSolve($conn, $user_id, $streak) {
	$user_id = intval($user_id);
	$streak  = intval($streak);
	$r = $conn->query("SELECT id FROM obscura_scores
	                   WHERE user_id = $user_id AND active = 1 ORDER BY id DESC LIMIT 1");
	if ($r && $r->num_rows > 0) {
		$id = intval($r->fetch_assoc()['id']);
		$conn->query("UPDATE obscura_scores SET streak = $streak, solves = solves + 1 WHERE id = $id");
	} else {
		$conn->query("INSERT INTO obscura_scores (user_id, streak, solves) VALUES ($user_id, $streak, 1)");
	}
}

// The run is over. Closing it is what makes the NEXT solve start a new row.
// Returns how many puzzles that run solved, for the announcement.
function obscuraCloseRun($conn, $user_id) {
	$user_id = intval($user_id);
	$solves  = 0;
	$r = $conn->query("SELECT solves FROM obscura_scores
	                   WHERE user_id = $user_id AND active = 1 ORDER BY id DESC LIMIT 1");
	if ($r && $r->num_rows > 0) $solves = intval($r->fetch_assoc()['solves']);
	$conn->query("UPDATE obscura_scores SET active = 0 WHERE user_id = $user_id AND active = 1");
	return $solves;
}

// Who is currently topping the unpaid (i.e. this week's) board.
function obscuraWeeklyLeaderUserId($conn) {
	$r = $conn->query("SELECT user_id FROM obscura_scores WHERE reward = 0
	                   GROUP BY user_id ORDER BY MAX(streak) DESC, SUM(solves) DESC LIMIT 1");
	return ($r && $r->num_rows > 0) ? intval($r->fetch_assoc()['user_id']) : 0;
}

/*
 * A run ended -- announce it, with the artwork that beat them.
 *
 * Showing the NFT is the point: the interesting part of a loss is which piece
 * was unrecognisable from a sliver, and that is worth seeing in the channel.
 *
 * Deliberately NOT every run. Obscura runs end far more often than a Crypt
 * Crawl delve does -- failing the first puzzle of a fresh run is routine and
 * not news -- so there is a floor. Drop OBSCURA_ANNOUNCE_MIN_STREAK to 1 to
 * post every ended run.
 */
define('OBSCURA_ANNOUNCE_MIN_STREAK', 3);

function obscuraAnnounceRunEnd($conn, $user_id, $streak, $solves, $best_streak, $reveal, $collection_name) {
	if (intval($streak) < OBSCURA_ANNOUNCE_MIN_STREAK) return;
	// webhooks.php may not be loaded by whatever included us. A missing Discord
	// post is cosmetic; a fatal here would break the guess response itself and
	// the player would lose their run AND see a connection error.
	if (!function_exists('discordmsg')) return;

	$user_id = intval($user_id);
	$ur = $conn->query("SELECT username, discord_id, avatar FROM users WHERE id = $user_id LIMIT 1");
	if (!$ur || !$ur->num_rows) return;
	$u = $ur->fetch_assoc();
	if (empty($u['discord_id'])) return;   // nothing to mention

	$username   = !empty($u['username']) ? $u['username'] : 'Unknown';
	$avatar_url = ($u['discord_id'] && $u['avatar'])
		? "https://cdn.discordapp.com/avatars/" . $u['discord_id'] . "/" . $u['avatar'] . ".png" : "";
	$author  = array("name" => $username, "icon_url" => $avatar_url,
	                 "url"  => "https://skulliance.io/staking/profile.php?username=" . urlencode($username));

	// obscuraLocalArt returns a site-absolute path; Discord needs a full url.
	$art = (!empty($reveal['art'])) ? "https://skulliance.io" . $reveal['art'] : "";

	$badges = array();
	if (intval($streak) >= intval($best_streak))            $badges[] = "🏅 **New personal best!**";
	if (obscuraWeeklyLeaderUserId($conn) === $user_id)      $badges[] = "🔥 **#1 This Week!**";
	$badge_text = $badges ? ("\n\n" . implode("\n", $badges)) : "";

	$piece = trim((string)($reveal['name'] ?? ''));
	$desc  = "<@" . $u['discord_id'] . "> ran out of attempts at a streak of **" . number_format($streak) . "**.\n\n"
	       . "🔍 **Stumped by:** " . ($piece !== '' ? $piece : 'an unnamed piece') . "\n"
	       . "🗂️ **Collection:** " . $collection_name . "\n"
	       . "✅ **Solved this run:** " . number_format($solves)
	       . $badge_text;

	$footer = array("text" => "Obscura - name the collection from a sliver",
	                "icon_url" => "https://skulliance.io/staking/icons/skulliance.png");

	discordmsg("🔍 Obscura Run Ended", $desc, $art,
		"https://skulliance.io/staking/obscura.php", "obscura", $avatar_url, "FF4444", $author, $footer);
}

// A player's view of the run -- never includes answer_id.
function obscuraState($conn, $user_id) {
	$run = obscuraGetRun($conn, $user_id);
	if ($run === false) return array('error' => 'not_migrated');
	$run = obscuraEnsurePuzzle($conn, $user_id, $run);
	if (empty($run['nft_id'])) return array('error' => 'no_art');

	$streak   = intval($run['streak']);
	$diff     = obscuraDifficulty($streak);
	$used     = intval($run['attempts_used']);
	// Resolved but NOT returned: this only confirms the art is really there, so
	// a puzzle with a missing file surfaces now instead of as a broken crop.
	if (obscuraArtForNft($conn, $run['nft_id']) === null) {
		return array('error' => 'art_gone');   // client will reroll
	}

	/*
	 * The artwork url is DELIBERATELY not in this payload, and neither are the
	 * crop coordinates. Cropping in CSS meant shipping the whole image and
	 * asking the browser to look away from most of it -- devtools, or View
	 * Source, and the answer was there. ajax/obscura-crop.php now renders only
	 * the visible region server-side, so the full art never reaches a browser
	 * while the puzzle is live.
	 *
	 * 'crop_v' only busts the image cache: the same url must re-fetch when a
	 * wrong guess widens the view. It reveals nothing -- the server derives
	 * the zoom from the run row, so asking for a wider crop than you have
	 * earned is not possible.
	 */
	return array(
		'streak'      => $streak,
		'best'        => intval($run['best_streak']),
		'options'     => json_decode($run['options'], true) ?: array(),
		'crop_v'      => intval($run['nft_id']) . '-' . $used,
		'attempts'    => $diff['attempts'],
		'columns'     => obscuraColumns($diff['options']),
		'used'        => $used,
		'wrong'       => json_decode($run['wrong'] ?? '[]', true) ?: array(),
		'tier'        => obscuraTierIndex($streak),
		'tier_terms'  => obscuraTierTerms($streak),
		'tier_label'  => obscuraTierLabel($streak),
	);
}

// Swap the current puzzle for a new one at NO cost -- used when the browser
// could not render the artwork. Attempts and streak are untouched.
function obscuraReroll($conn, $user_id, $run = null) {
	$run = $run ?: obscuraGetRun($conn, $user_id);
	if ($run === false) return false;
	$diff = obscuraDifficulty(intval($run['streak']));
	// A rerolled puzzle's collection still counts as used: the player is being
	// given a replacement, not a second go at the same set.
	$seen = obscuraSeenCollections($run);
	$p = obscuraPickPuzzle($conn, $diff['options'], $seen);
	if (!$p) return false;
	obscuraSetPuzzle($conn, $user_id, $p, $seen);
	return true;
}

/*
 * Judge a guess. Returns what the client needs and nothing it shouldn't have.
 * The answer is only ever disclosed once the puzzle is over.
 */
function obscuraGuess($conn, $user_id, $collection_id) {
	$user_id = intval($user_id);
	$run = obscuraGetRun($conn, $user_id);
	if ($run === false) return array('error' => 'not_migrated');
	if (empty($run['nft_id'])) return array('error' => 'no_puzzle');

	$streak = intval($run['streak']);
	$diff   = obscuraDifficulty($streak);
	$used   = intval($run['attempts_used']);
	$wrong  = json_decode($run['wrong'] ?? '[]', true) ?: array();
	$answer = intval($run['answer_id']);
	$guess  = intval($collection_id);

	// Only options actually offered can be guessed, and only once each --
	// otherwise a client could burn attempts or replay a known-wrong answer.
	$offered = array_column(json_decode($run['options'], true) ?: array(), 'id');
	if (!in_array($guess, $offered, true) || in_array($guess, $wrong, true)) {
		return array('error' => 'bad_guess');
	}

	/*
	 * The puzzle is over the moment it is judged, so the full artwork can go to
	 * the client -- and should: seeing what the sliver was cut from is the
	 * payoff. Resolved BEFORE the row is cleared, since that drops nft_id.
	 * By the time the player has this url the run already points at a new NFT,
	 * so it cannot be turned around on a live puzzle.
	 */
	$reveal = obscuraRevealDetails($conn, $run['nft_id']);

	if ($guess === $answer) {
		$streak++;
		$best = max(intval($run['best_streak']), $streak);
		$conn->query("UPDATE obscura_runs SET streak = $streak, best_streak = $best,
			nft_id = NULL, answer_id = NULL, options = NULL, attempts_used = 0, wrong = NULL,
			updated_at = NOW() WHERE user_id = $user_id");
		obscuraRecordSolve($conn, $user_id, $streak);
		return array('result'=>'correct', 'streak'=>$streak, 'best'=>$best, 'reveal'=>$reveal,
		             'tier_changed'=>obscuraTierIndex($streak) !== obscuraTierIndex($streak - 1),
		             'tier_label'=>obscuraTierLabel($streak));
	}

	$wrong[] = $guess;
	$used++;
	if ($used >= $diff['attempts']) {
		// Run over. Streak resets; best_streak is the consolation and survives.
		$name = '';
		$nr = $conn->query("SELECT name FROM collections WHERE id = $answer LIMIT 1");
		if ($nr && $nr->num_rows > 0) $name = $nr->fetch_assoc()['name'];
		// seen_collections clears with the run: the no-repeat rule is per run,
		// so a fresh run starts with the whole catalogue available again.
		$conn->query("UPDATE obscura_runs SET streak = 0, nft_id = NULL, answer_id = NULL,
			options = NULL, attempts_used = 0, wrong = NULL, seen_collections = NULL,
			updated_at = NOW() WHERE user_id = $user_id");
		$run_solves = obscuraCloseRun($conn, $user_id);
		// $streak is this run's peak, read before the reset above.
		obscuraAnnounceRunEnd($conn, $user_id, $streak, $run_solves,
			intval($run['best_streak']), $reveal, $name);
		return array('result'=>'failed', 'answer_id'=>$answer, 'answer_name'=>$name,
		             'reveal'=>$reveal, 'streak'=>0, 'best'=>intval($run['best_streak']));
	}

	$conn->query("UPDATE obscura_runs SET attempts_used = $used,
		wrong = '" . $conn->real_escape_string(json_encode($wrong)) . "', updated_at = NOW()
		WHERE user_id = $user_id");
	// crop_v changes with attempts_used, which is what makes the browser
	// re-fetch and see the wider view. The zoom itself stays server-side.
	return array('result'=>'wrong', 'used'=>$used, 'attempts'=>$diff['attempts'],
	             'crop_v'=>intval($run['nft_id']) . '-' . $used, 'wrong'=>$wrong);
}
