<?php
include 'db.php';
include 'webhooks.php';

if(isset($argv)){
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
// Validate cron job execution
if(isset($_GET['automate'])){
	/*
	 * ONE PROJECT ON REQUEST, all of them by default.
	 *
	 *   php automate.php automate=1                # the weekly cron
	 *   php automate.php automate=1 project_id=3   # just this one
	 *
	 * Switching a theme on mid-week needs it to be given a game, and the
	 * only way to do that was to run the whole cron -- which would also
	 * END the games the other themes were in the middle of. That is a
	 * destructive way to ask for one small thing.
	 */
	/* THE SAME LIST THE CURRENCIES COME FROM. This was array(1,2,3,4,5)
	   typed out, a fourth copy of "which themes are there" that a sixth
	   theme would have had to be added to by hand. */
	$all = array_keys(dropshipRewardTokens());
	$only = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
	if ($only && !in_array($only, $all, true)) {
		echo "project_id must be one of: " . implode(', ', $all) . "\n";
		exit(1);
	}
	foreach ($only ? array($only) : $all as $ds_pid) automate($conn, $ds_pid);
}

function automate($conn, $project_id){
	/*
	 * $prizes IS THE GAME'S NAME, not a payout. games.prizes is the NUMBER
	 * of winning places -- checkLeaderboard() cuts the board at
	 * `$counter > $row["prizes"]` and dashboard.php's own form calls the
	 * field "Number of Prizes" -- so createGame($conn, $prizes, 3) below
	 * reads oddly but is right: this string is the name, 3 is the count.
	 *
	 * DEFAULTED, because an unlisted project used to leave both undefined:
	 * the new game would be created nameless and the announcement would
	 * point at an image path built from an empty prefix.
	 */
	/*
	 * THE NAME IS BUILT FROM WHAT THE THEME PAYS, not from a ladder.
	 *
	 * This was an if/else chain over project ids with no branch for theme
	 * 5, so a DHC2 Fighters game would have been created called
	 * "1K, 500, 250 DREAD" and announced that way to a channel whose
	 * players are credited MAXI. The sixth theme would have done the same.
	 *
	 * $prefix went with it: it was assigned in all four branches and read
	 * in none, dead since dropshipThemeImageUrl() replaced the hand-built
	 * image path below.
	 */
	$prizes = dropshipRewardPrizeName($project_id);
	if ($prizes === '') {
		/* REFUSE RATHER THAN GUESS. A theme with no currency would other-
		   wise get a game named after nothing and a Discord post to match;
		   the old default quietly named it after DREAD instead. */
		echo "project " . (int)$project_id . " has no reward currency. "
		   . "Add it to dropshipRewardTokens() in dropship-themes.php first.\n";
		return;
	}
	$_SESSION['userData']['dropship_project_id'] = $project_id;
	
	/*
	 * END THE CURRENT GAME -- ONLY IF THERE IS ONE.
	 *
	 * checkGame() unsets game_id when the project has no active game, and
	 * everything below assumed there always was one: deactivateGame()
	 * would UPDATE games SET active=0 WHERE id='' (matching nothing, after
	 * pointlessly logging balances and resetting soldiers), and Discord
	 * would get a "Game Over" post with an empty leaderboard for a game
	 * that never existed. That is the state a theme is in the first time
	 * it is switched on, which is exactly when this runs.
	 */
	checkGame($conn);
	if (isset($_SESSION['userData']['game_id'])) {
	$title = "Game Over";
	/* Captured WITHOUT its warnings. display_errors is on platform-wide, so
	   a notice raised while the buffer is open lands in the embed: a real
	   Game Over post went out carrying "Undefined array key \"game_id\" in
	   /home/.../db.php on line 2220", absolute server path and all, into a
	   channel players read. */
	$description = dropshipCapture(function () use ($conn) { checkLeaderboard($conn, "true"); });
	/* The theme's own copy, resolved from its theme row and checked on
	   disk -- one rule, shared with the dashboard's posts and with the
	   embed thumbnail. The hand-built path here was right, but it was the
	   third copy of the flat-vs-nested rule in the file. */
	$imageurl = dropshipThemeImageUrl($conn, $project_id, 'images/dropship.jpg');
	discordmsg($title, evaluateText($description), $imageurl, $_SESSION['userData']['dropship_project_id']);
	deactivateGame($conn);
	}

	// Automatically create new game
	createGame($conn, $prizes, dropshipRewardPlaces());
	$title = "New Game ".$prizes;
	$description = "A new game of ".getProjectName($conn)." has been created.";
	/* The theme's own copy, resolved from its theme row and checked on
	   disk -- one rule, shared with the dashboard's posts and with the
	   embed thumbnail. The hand-built path here was right, but it was the
	   third copy of the flat-vs-nested rule in the file. */
	$imageurl = dropshipThemeImageUrl($conn, $project_id, 'images/dropship.jpg');
	discordmsg($title, $description, $imageurl, $_SESSION['userData']['dropship_project_id']);
}
?>