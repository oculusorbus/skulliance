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

/** 1st, 2nd, 3rd, 4th. */
function dropshipOrdinal($n) {
	$n = (int)$n;
	$s = array('th','st','nd','rd');
	$v = $n % 100;
	return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
}

/**
 * The post, as the player would write it if they were bothered.
 *
 * Short declaratives, the theme named because five games share this engine
 * and "Drop Ship" alone would be wrong for four of them, and no sentence
 * that comments on itself. shareOnXUrl() handles the 280 budget and the
 * @skulliance tail, so length is its problem and not this function's --
 * but the interesting half is put FIRST so a trim never eats it.
 */
function dropshipShareText($facts, $theme, $rounds_word = 'Round') {
	$f = $facts;
	if (!$f['rank']) return 'Just played ' . $theme . ' on Skulliance.';

	$score = (int)$f['score'];
	$lines = array();
	/* A PERSONAL BEST IS ONLY NEWS WHEN THE OVERTAKE ISN'T. Beating your own
	   score and going past somebody are usually the same run, and leading
	   with the smaller of the two claims wastes the first line. */
	$lead = ($f['personal_best'] && $f['passed'] === '') ? 'New personal best: survived' : 'Survived';
	$lines[] = $lead . ' to ' . strtolower($rounds_word) . ' ' . $score . ' in ' . $theme . '.';

	if ($f['passed'] !== '') {
		$also = $f['passed_n'] > 1 ? ' and ' . ($f['passed_n'] - 1) . ' more' : '';
		$lines[] = 'Passed ' . $f['passed'] . $also . ' into '
		         . dropshipOrdinal($f['rank']) . ' of ' . $f['field'] . '.';
	} elseif ($f['first']) {
		$lines[] = $f['clear'] > 0
			? 'Top of the board, ' . $f['clear'] . ' clear of second.'
			: 'Top of the board.';
	} else {
		$lines[] = dropshipOrdinal($f['rank']) . ' of ' . $f['field']
		         . ', ' . $f['behind'] . ' behind ' . $f['leader'] . '.';
	}
	return implode(' ', $lines);
}
