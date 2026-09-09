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
 *     updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
 *   );
 */

// The noose. Deliberately a plain table rather than a formula: every tier is
// visible at a glance and tunable without arithmetic.
function obscuraDifficulty($streak) {
	if ($streak >= 31) return array('attempts'=>1, 'options'=>12, 'zooms'=>array(8));
	if ($streak >= 16) return array('attempts'=>2, 'options'=>10, 'zooms'=>array(10, 25));
	if ($streak >= 6)  return array('attempts'=>3, 'options'=>8,  'zooms'=>array(12, 25, 45));
	return                     array('attempts'=>3, 'options'=>6,  'zooms'=>array(15, 35, 60));
}

// Human-readable note shown when the difficulty changes, so the tightening
// reads as earned pressure rather than the game misbehaving.
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

// Pick a puzzle: one NFT with verified art, plus N-1 decoy collections.
// Returns null if nothing suitable could be found, which the caller surfaces
// rather than pretending a puzzle exists.
function obscuraPickPuzzle($conn, $option_count) {
	$max = 0;
	if ($r = $conn->query("SELECT MAX(id) AS m FROM nfts")) { $max = intval($r->fetch_assoc()['m']); }
	if ($max <= 0) return null;

	// Random-offset sampling rather than ORDER BY RAND(), which would sort the
	// whole table for one row. Several passes because a random id may land on
	// a gap or on art that was never cached.
	$found = null;
	for ($pass = 0; $pass < 12 && !$found; $pass++) {
		$from = random_int(1, $max);
		$res = $conn->query("
			SELECT nfts.id, nfts.ipfs, nfts.collection_id,
			       collections.name AS collection_name, collections.project_id
			FROM nfts
			INNER JOIN collections ON collections.id = nfts.collection_id
			WHERE nfts.id >= $from AND nfts.ipfs != ''
			ORDER BY nfts.id ASC
			LIMIT 25");
		if (!$res) continue;
		while ($row = $res->fetch_assoc()) {
			$url = obscuraLocalArt($row['ipfs'], $row['collection_id'], $row['project_id']);
			if ($url !== null) { $found = $row; $found['art'] = $url; break; }
		}
	}
	if (!$found) return null;

	// Decoys: other collections that actually hold NFTs, so a player is never
	// offered a collection nothing could have come from.
	$answer_id = intval($found['collection_id']);
	$opts = array(array('id'=>$answer_id, 'name'=>$found['collection_name']));
	$dr = $conn->query("
		SELECT collections.id, collections.name
		FROM collections
		INNER JOIN nfts ON nfts.collection_id = collections.id
		WHERE collections.id != $answer_id
		GROUP BY collections.id
		ORDER BY RAND()
		LIMIT " . (intval($option_count) - 1));
	if ($dr) while ($d = $dr->fetch_assoc()) $opts[] = array('id'=>intval($d['id']), 'name'=>$d['name']);
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

// Store a freshly picked puzzle against the run. Shared by "need a puzzle",
// "solved, next one" and "that image was broken, swap it".
function obscuraSetPuzzle($conn, $user_id, $puzzle) {
	$user_id = intval($user_id);
	$conn->query("UPDATE obscura_runs SET
		nft_id = " . intval($puzzle['nft_id']) . ",
		answer_id = " . intval($puzzle['answer_id']) . ",
		options = '" . $conn->real_escape_string(json_encode($puzzle['options'])) . "',
		crop_x = " . floatval($puzzle['crop_x']) . ",
		crop_y = " . floatval($puzzle['crop_y']) . ",
		attempts_used = 0, wrong = NULL, updated_at = NOW()
		WHERE user_id = $user_id");
}

function obscuraEnsurePuzzle($conn, $user_id, $run) {
	if (!empty($run['nft_id'])) return $run;
	$diff = obscuraDifficulty(intval($run['streak']));
	$p = obscuraPickPuzzle($conn, $diff['options']);
	if (!$p) return $run;                            // caller reports it
	obscuraSetPuzzle($conn, $user_id, $p);
	return obscuraGetRun($conn, $user_id);
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
	$art      = null;
	$ar = $conn->query("SELECT nfts.ipfs, nfts.collection_id, collections.project_id
	                    FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id
	                    WHERE nfts.id = " . intval($run['nft_id']) . " LIMIT 1");
	if ($ar && $ar->num_rows > 0) {
		$a = $ar->fetch_assoc();
		$art = obscuraLocalArt($a['ipfs'], $a['collection_id'], $a['project_id']);
	}
	if ($art === null) return array('error' => 'art_gone');   // client will reroll

	return array(
		'streak'      => $streak,
		'best'        => intval($run['best_streak']),
		'options'     => json_decode($run['options'], true) ?: array(),
		'art'         => $art,
		'crop_x'      => floatval($run['crop_x']),
		'crop_y'      => floatval($run['crop_y']),
		'zoom'        => $diff['zooms'][min($used, count($diff['zooms']) - 1)],
		'attempts'    => $diff['attempts'],
		'used'        => $used,
		'wrong'       => json_decode($run['wrong'] ?? '[]', true) ?: array(),
		'tier'        => obscuraTierIndex($streak),
		'tier_label'  => obscuraTierLabel($streak),
	);
}

// Swap the current puzzle for a new one at NO cost -- used when the browser
// could not render the artwork. Attempts and streak are untouched.
function obscuraReroll($conn, $user_id, $run = null) {
	$run = $run ?: obscuraGetRun($conn, $user_id);
	if ($run === false) return false;
	$diff = obscuraDifficulty(intval($run['streak']));
	$p = obscuraPickPuzzle($conn, $diff['options']);
	if (!$p) return false;
	obscuraSetPuzzle($conn, $user_id, $p);
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

	if ($guess === $answer) {
		$streak++;
		$best = max(intval($run['best_streak']), $streak);
		$conn->query("UPDATE obscura_runs SET streak = $streak, best_streak = $best,
			nft_id = NULL, answer_id = NULL, options = NULL, attempts_used = 0, wrong = NULL,
			updated_at = NOW() WHERE user_id = $user_id");
		return array('result'=>'correct', 'streak'=>$streak, 'best'=>$best,
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
		$conn->query("UPDATE obscura_runs SET streak = 0, nft_id = NULL, answer_id = NULL,
			options = NULL, attempts_used = 0, wrong = NULL, updated_at = NOW()
			WHERE user_id = $user_id");
		return array('result'=>'failed', 'answer_id'=>$answer, 'answer_name'=>$name,
		             'streak'=>0, 'best'=>intval($run['best_streak']));
	}

	$conn->query("UPDATE obscura_runs SET attempts_used = $used,
		wrong = '" . $conn->real_escape_string(json_encode($wrong)) . "', updated_at = NOW()
		WHERE user_id = $user_id");
	return array('result'=>'wrong', 'used'=>$used, 'attempts'=>$diff['attempts'],
	             'zoom'=>$diff['zooms'][min($used, count($diff['zooms']) - 1)], 'wrong'=>$wrong);
}
