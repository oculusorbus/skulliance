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
 * Has this account ever logged a score in this game?
 *
 * ANY ROW COUNTS, not a good one. The question is "have they played",
 * and a run that scored zero still taught them how the board moves.
 *
 * @param mysqli $conn
 * @param int    $project_id  scores.project_id: 0 Skull Swap, 36 Monstrocity
 */
function match3_intro_played($conn, $project_id) {
	static $seen = array();
	$pid = (int) $project_id;
	$uid = (int) ($_SESSION['userData']['user_id'] ?? 0);
	if ($uid <= 0) return true;          /* logged out: never interrupt */
	$key = $uid . ':' . $pid;
	if (isset($seen[$key])) return $seen[$key];

	/* @-wrapped and defaulting to PLAYED. A failed read must not put a
	   dialog in front of a returning player's board; the cost of missing
	   the intro once is nothing, the cost of nagging a veteran is real. */
	$seen[$key] = true;
	$r = @$conn->query("SELECT id FROM scores WHERE user_id = '" . $uid
	                 . "' AND project_id = '" . $pid . "' LIMIT 1");
	if ($r) $seen[$key] = ($r->num_rows > 0);
	return $seen[$key];
}

/**
 * Render the overlay. Call once, near the end of the body.
 *
 * @param array $o  game    display name, used in the copy
 *                  project scores.project_id for the played check
 *                  force   true to render regardless (the admin preview)
 */
function match3_intro($o) {
	$game    = isset($o['game']) ? (string) $o['game'] : 'this game';
	$project = isset($o['project']) ? (int) $o['project'] : -1;
	$force   = !empty($o['force']);
	if (!$force) {
		if ($project < 0) return;
		if (!isset($GLOBALS['conn'])) return;
		if (match3_intro_played($GLOBALS['conn'], $project)) return;
	}
	require __DIR__ . '/match3-intro-view.php';
}
