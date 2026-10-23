<?php
/**
 * dropship-share.php -- what a finished run can honestly say about itself.
 *
 * THE POST HAS TO BE TRUE, and most of what makes a result worth posting is
 * relative: a 34 is a boast or a shrug depending on the board. So this reads
 * the board rather than the score, and every claim it can make is one the
 * standings support at the moment the run ended.
 *
 * WHO GETS NAMED, per the owner: name whoever this run PASSED, and if it
 * passed nobody, name the leader being chased. Both are claims the sharer
 * comes out of well -- one is an overtake, the other is a challenge upward.
 * Nobody is ever named as having beaten the sharer, and no label describes
 * another player. That rule is the same one the Arena share follows, where
 * 'sitting duck' is computed and deliberately never printed.
 *
 * THE PREVIOUS SCORE IS THE ONLY THING THAT CANNOT BE READ LATER.
 * logScore() deletes a player's standing row before inserting the new one,
 * so by the time the page renders, the score they beat is gone. It is
 * already captured a few lines earlier for the trait rule
 * (dropshipDropContext()'s 'prev'), so the run carries it forward in the
 * session rather than this file re-deriving something that no longer exists.
 *
 * NO SESSION READS IN HERE. Everything arrives as an argument so the
 * harness can drive the real functions over a board it builds itself.
 */

require_once __DIR__ . '/../lib/share-x.php';

/**
 * Where a shared run lands. PUBLIC, or X's crawler sees a login redirect.
 *
 * KEYED ON THE THEME, NOT THE RUN. ?run=<result_id> was the obvious shape
 * and it is the wrong one: it would let anyone read any player's score by
 * changing a number in a URL. The theme is all the page needs -- it decides
 * which art the card shows -- and the placement, the round and the name all
 * live in the post text, which the player writes in their own composer.
 */
function dropshipSharePage($theme_slug) {
	$slug = strtolower(trim((string)$theme_slug));
	if ($slug === '' || !preg_match('/^[a-z0-9-]{1,48}$/', $slug)) $slug = 'drop-ship';
	return 'dropshipgame.php?theme=' . rawurlencode($slug);
}

/**
 * The standings, as rows of array('user_id','username','score'), score DESC.
 *
 * Its own query rather than checkLeaderboard(), which echoes formatted
 * markup and pulls in replay buttons and XP levels. Same table, same order.
 */
function dropshipShareBoard($conn, $game_id, $project_id) {
	$game = (int)$game_id; $project = (int)$project_id;
	if ($game <= 0) return array();
	$res = @$conn->query(
		"SELECT r.user_id, r.score, u.username
		   FROM results r INNER JOIN users u ON u.id = r.user_id
		  WHERE r.game_id = $game AND r.project_id = $project
		  ORDER BY r.score DESC, r.id ASC");
	$out = array();
	if ($res) while ($row = $res->fetch_assoc()) {
		$out[] = array('user_id'  => (int)$row['user_id'],
		               'username' => (string)$row['username'],
		               'score'    => (int)$row['score']);
	}
	return $out;
}

/**
 * Everything the post may claim, from the board and the score it replaced.
 *
 * $prev is this player's score for this same game before the run, or null
 * when they had none. The difference matters: entering the board for the
 * first time passes nobody, however high the score lands, and claiming
 * otherwise would be the one kind of lie that is easy to catch.
 */
function dropshipShareFacts($board, $user_id, $prev = null) {
	$me = (int)$user_id;
	$f = array('rank' => 0, 'field' => count($board), 'score' => 0,
	           'passed' => '', 'passed_n' => 0, 'leader' => '', 'behind' => 0,
	           'clear' => 0, 'first' => false, 'personal_best' => false);
	if (!$board || $me <= 0) return $f;

	foreach ($board as $i => $row) {
		if ($row['user_id'] === $me) { $f['rank'] = $i + 1; $f['score'] = $row['score']; }
	}
	if ($f['rank'] === 0) return $f;
	$f['first'] = ($f['rank'] === 1);

	/*
	 * PASSED = below the new score, at or above the old one. Those are the
	 * players this run went over the top of, and nobody else: somebody who
	 * was already behind was not overtaken by anything that happened today.
	 * The one NAMED is the highest of them, which is the best true claim.
	 */
	if ($prev !== null) {
		$p = (int)$prev;
		$f['personal_best'] = $f['score'] > $p;
		foreach ($board as $row) {
			if ($row['user_id'] === $me) continue;
			if ($row['score'] < $f['score'] && $row['score'] >= $p) {
				$f['passed_n']++;
				if ($f['passed'] === '') $f['passed'] = $row['username'];
			}
		}
	}

	/* The leader, and the gap to them -- only meaningful when it is not you. */
	if (!$f['first']) {
		$f['leader'] = $board[0]['username'];
		$f['behind'] = $board[0]['score'] - $f['score'];
	} elseif (count($board) > 1) {
		/* Leading: how much daylight there is, which is the boast up there. */
		$f['clear'] = $f['score'] - $board[1]['score'];
	}
	return $f;
}

/**
 * The X account behind a theme, where it has one of its own.
 *
 * REAL ACCOUNTS, AND A WRONG ONE TAGS A STRANGER IN PUBLIC from a
 * player's own timeline -- so these are copied from the handles the owner
 * already supplied for trait-drop shares (the x_tag entries in
 * dhcfighters-config.php), never guessed from a theme name.
 *
 * ONLY OCULUS LOUNGE, DELIBERATELY. The registry also holds @ohh_meed and
 * @MMAXI404, and those are PEOPLE. A trait drop is occasional; a run
 * share fires every time anybody finishes a game, and pointing that
 * volume at a person's mentions is not a thing to switch on without being
 * asked for it. @oculuslounge is the game's own account and was asked
 * for. Adding the others is one line each when the owner says so.
 *
 * Not required from the platform's copy because Drop Ship cannot include
 * dhcfighters-config.php -- different database, different db.php. Kept in
 * step by hand, and there is exactly one value in it.
 */
function dropshipThemeHandle($slug) {
	$map = array('oculus-lounge' => '@oculuslounge');
	$slug = strtolower(trim((string)$slug));
	if (!isset($map[$slug])) return '';
	/* Shape-checked on the way out, because this string becomes a public
	   mention: letters, digits and underscore after the @, as X allows. */
	return preg_match('/^@[A-Za-z0-9_]{1,15}$/', $map[$slug]) ? $map[$slug] : '';
}

/** 1st, 2nd, 3rd, 4th. */
function dropshipOrdinal($n) {
	$n = (int)$n;
	$s = array('th','st','nd','rd');
	$v = $n % 100;
	return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
}

/**
 * THE POST IS AN ADVERT THAT HAPPENS TO CONTAIN A SCORE.
 *
 * The first version read "Survived to round 28 in DHC2 Fighters. Top of
 * the board, 13 clear of second." -- true, and a report card. It tells a
 * stranger scrolling past nothing about what the game is, that it costs
 * nothing, or why they would click. A share is reach we are given for
 * free and that one spent it on a number.
 *
 * So the post is three parts and only the first is about the player:
 *
 *   THE RESULT   what they did, and who they went past -- the reason they
 *                pressed the button, so it leads and it never gets cut.
 *   THE PITCH    what the game IS, to somebody who has never heard of it.
 *                Free, four soldiers, a board that wipes weekly.
 *   THE CALL     one line that invites a reply. Different when you are
 *                defending the top than when you are climbing.
 *
 * THE BUDGET IS SPENT DELIBERATELY, NOT TRIMMED. shareOnXUrl() cuts from
 * the END, which on a long theme name would eat the call to action and
 * then the pitch -- exactly the half this rewrite exists to add. So the
 * pitch has a short form, and the text is assembled against the real
 * limit: 280, minus 23 for the link (X counts every URL as 23 whatever it
 * is), minus one for the separator, minus the @skulliance tail.
 */

/**
 * The limit shareOnXUrl() will apply, worked out the same way it does.
 *
 * 280, less 23 for the link (X counts every URL as 23 whatever it is) and
 * one for the space in front of it. NO TAIL ALLOWANCE: every post this
 * file builds names @skulliance inside its call to action, and
 * shareOnXUrl() appends nothing when the body already carries the
 * mention. Budgeting for a tail that never arrives cost 13 characters and
 * pushed the pitch down a rung for no reason.
 */
function dropshipShareLimit() {
	return 280 - 24;
}

function dropshipShareText($facts, $theme, $rounds_word = 'Round', $slug = '') {
	$f = $facts;
	if (!$f['rank']) {
		return 'Playing ' . $theme . ' on Skulliance. A squad of four soldiers, one run, and a '
		     . 'leaderboard that resets every week. Free to play, and your first squad is free too.';
	}

	$score = (int)$f['score'];
	$round = strtolower($rounds_word);

	/* --- the result ------------------------------------------------- */
	/* A PERSONAL BEST IS ONLY NEWS WHEN THE OVERTAKE ISN'T. Beating your
	   own score and going past somebody are usually the same run, and
	   leading with the smaller of the two claims wastes the first line. */
	/* "New personal best" said New twice over; a personal best is new by
	   definition. Four characters, and this budget is counted in them. */
	$lead = ($f['personal_best'] && $f['passed'] === '') ? 'Personal best: survived' : 'Survived';
	$result = $lead . ' to ' . $round . ' ' . $score . ' in ' . $theme . '.';

	if ($f['passed'] !== '') {
		$also = $f['passed_n'] > 1 ? ' and ' . ($f['passed_n'] - 1) . ' more' : '';
		$result .= ' Passed ' . $f['passed'] . $also . ' into '
		         . dropshipOrdinal($f['rank']) . ' of ' . $f['field'] . '.';
	} elseif ($f['first']) {
		$result .= $f['clear'] > 0
			/* "clear of second" -- of second what else, on a board? */
			? ' Top of the board, ' . $f['clear'] . ' clear.'
			: ' Top of the board.';
	} else {
		$result .= ' ' . dropshipOrdinal($f['rank']) . ' of ' . $f['field']
		         . ', ' . $f['behind'] . ' behind ' . $f['leader'] . '.';
	}

	/* --- the call ----------------------------------------------------- */
	/*
	 * ONE LINE, AND IT RECRUITS. This had three situational taunts --
	 * "Come take it off me", "Who's next?", "Going back for the top spot"
	 * -- which are written at the board. The board is already here. The
	 * person worth writing to is the stranger who has never played, and
	 * what they need is an instruction, so the owner replaced all three
	 * with this.
	 *
	 * THE MENTION LIVES HERE, which is why shareOnXUrl() stops appending
	 * its own: one "@skulliance" that reads as a sentence beats a handle
	 * dangling under the post, and it buys back 13 characters.
	 */
	/* A theme with its own account is NAMED by it -- "play @oculuslounge"
	   reads as the game's name and tags it in the same breath, where the
	   name plus a trailing handle would cost more and read as two. */
	$handle = dropshipThemeHandle($slug);
	$call = 'Join ' . (defined('SKULLIANCE_X_HANDLE') ? SKULLIANCE_X_HANDLE : '@skulliance')
	      . ' and play ' . ($handle !== '' ? $handle : $theme) . '.';

	/* --- the pitch ---------------------------------------------------- */
	/*
	 * THE OWNER'S WORDING, AND THEN THE SAME THING SHORTER.
	 *
	 * His sentence is the first rung. It does not always fit: with a
	 * personal best, a long theme name and a full standing it came to 288
	 * against a 243 budget, so a ladder is not optional -- the question is
	 * only what gets dropped, and it must never be the meaning.
	 *
	 * The second rung drops the theme name, which costs nothing: the
	 * result line already says which game this was and the call says it
	 * again, so naming it here is the third mention in three sentences.
	 * That is the rung most posts will actually use.
	 *
	 * VERIFIED, NOT SOLD. Every player really is given four soldiers and
	 * the four really do make one complete squad -- Hagar, Delay, Blood
	 * Bag and Sektr, Heavy through Base, see dropship/dropship-grunts.php.
	 * A claim in a public post has to be one somebody can turn up and
	 * check.
	 */
	$free  = 'Free to play, and your first squad is free too.';
	$rungs = array(
		$theme . ' entails a squad of four soldiers, one run, and a leaderboard that '
		       . 'resets every week. ' . $free,
		'A squad of four soldiers, one run, and a leaderboard that resets every week. ' . $free,
		'A squad of four, one run, and a leaderboard that resets every week. Free to play.',
		$free,
		'',
	);

	$limit = dropshipShareLimit();
	foreach ($rungs as $pitch) {
		$parts = array($result);
		if ($pitch !== '') $parts[] = $pitch;
		$parts[] = $call;
		$text = implode("\n\n", $parts);
		if (mb_strlen($text) <= $limit) return $text;
	}
	/* Even the result alone is over budget -- a theme with an absurd name
	   and a four-digit score. Hand it over and let shareOnXUrl() cut it;
	   there is nothing left here worth protecting. */
	return $result;
}
