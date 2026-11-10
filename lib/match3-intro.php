<?php
/*
 * lib/match3-intro.php — teaching the one rule nobody guesses.
 *
 * THE PROBLEM, REPORTED. New players do not know that a tile can be
 * dragged the WHOLE WAY along its row or column. They arrive from every
 * other match-3 ever made, where you swap with a neighbour, play that
 * way, score badly, and some of them quit. It is not in the Skull
 * Paper, and worse, skullswap.php's own help said "drag a tile to swap
 * it with a neighbor" -- the game was teaching the misconception.
 *
 * WHY A DEMONSTRATION AND NOT A PARAGRAPH. The thing being taught is a
 * gesture. A sentence about dragging competes with every other sentence
 * on a games page and loses; a board that performs the move in front of
 * you cannot be skimmed past, because the move IS the content. So this
 * is a looping animation of one real slide, with the text under it as
 * the caption rather than the lesson.
 *
 * THE DEMO IS THE REAL RULE, NOT A SIMPLIFICATION. slideTiles() rotates
 * the span you drag across: the tile you grabbed lands where you let go
 * and everything between shifts one step back toward where it started.
 * The demo drags a tile TWO cells for that reason -- at one cell a slide
 * and a neighbour swap look identical, so a one-cell demo would teach
 * nothing and quietly confirm what the player already believed.
 *
 * WHO SEES IT. Anybody with no score on record for that game, which is
 * one indexed read. The score IS the record: nothing is written when
 * this is dismissed, so a player who closes it and wanders off meets it
 * again next time, and a player who finishes one run never does. There
 * is no flag to go stale and nothing to clean up.
 */

/**
 * Has this account ever played one of these?
 *
 * EACH GAME KEEPS ITS OWN RECORD, so the gate cannot assume one table.
 * Skull Swap and Monstrocity write a row in `scores`; the Arena writes
 * a battle and DHC Bosses a run, in tables of their own. A caller hands
 * over the places its own game would have left a mark and ANY of them
 * counts, because the Arena and the Bosses are the same board in two
 * modes and learning it in one is learning it in both.
 *
 * ANY ROW COUNTS, not a good one. The question is "have they played",
 * and a run that scored nothing still taught them how the board moves.
 *
 * @param mysqli $conn
 * @param array  $marks  list of array('from' => table, 'user' => column,
 *                                     'and' => extra SQL or '')
 */
function match3_intro_played($conn, $marks) {
	static $seen = array();
	$uid = (int) ($_SESSION['userData']['user_id'] ?? 0);
	if ($uid <= 0) return true;          /* logged out: never interrupt */
	$key = $uid . '|' . json_encode($marks);
	if (isset($seen[$key])) return $seen[$key];

	/*
	 * DEFAULTS TO PLAYED, and each read can only move it toward "yes".
	 * A failed query must not put a dialog in front of a returning
	 * player's board: missing the intro once costs nothing, nagging a
	 * veteran costs more than it ever saves.
	 */
	$seen[$key] = true;
	$any = false; $read = false;
	foreach ($marks as $m) {
		$from = preg_replace('~[^a-z0-9_]~i', '', (string) ($m['from'] ?? ''));
		$user = preg_replace('~[^a-z0-9_]~i', '', (string) ($m['user'] ?? ''));
		if ($from === '' || $user === '') continue;
		$r = @$conn->query("SELECT 1 FROM `" . $from . "` WHERE `" . $user . "` = '"
		                 . $uid . "'" . (string) ($m['and'] ?? '') . " LIMIT 1");
		if (!$r) continue;               /* unreadable: say nothing either way */
		$read = true;
		if ($r->num_rows > 0) { $any = true; break; }
	}
	if ($read) $seen[$key] = $any;
	return $seen[$key];
}

/**
 * Render the overlay. Call once, near the end of the body.
 *
 * @param array $o  game   display name, used in the copy
 *                  played the marks above; omit with force
 *                  force  true to render regardless (the admin preview)
 */
function match3_intro($o) {
	$game  = isset($o['game']) ? (string) $o['game'] : 'this game';
	$force = !empty($o['force']);
	if (!$force) {
		if (empty($o['played']) || !is_array($o['played'])) return;
		if (!isset($GLOBALS['conn'])) return;
		if (match3_intro_played($GLOBALS['conn'], $o['played'])) return;
	}
	require __DIR__ . '/match3-intro-view.php';
}

/**
 * The marks for each game, in one place so three pages and the harness
 * cannot disagree about what "has played" means.
 */
function match3_intro_marks($which) {
	$all = array(
		/* project_id 0 is Skull Swap, 36 is Monstrocity. */
		'skullswap'   => array(array('from' => 'scores', 'user' => 'user_id',
		                             'and'  => " AND project_id = '0'")),
		'monstrocity' => array(array('from' => 'scores', 'user' => 'user_id',
		                             'and'  => " AND project_id = '36'")),
		/* ONE SET FOR BOTH ARENA MODES. dhcbosses.php is `require
		   dhcarena.php` -- the same board, the same slide -- so a player
		   who has done either has met the mechanic. */
		'dhcarena'    => array(array('from' => 'dhc_arena_battles', 'user' => 'attacker_id', 'and' => ''),
		                       array('from' => 'dhc_boss_runs',     'user' => 'user_id',     'and' => '')),
	);
	return isset($all[$which]) ? $all[$which] : array();
}
