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
	$all = array(1, 3, 4);
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
	$prefix = "drop-ship";
	$prizes = "1K, 500, 250 DREAD";
	if($project_id == 3){
		$prefix = "filthy-mermaid";
	}else if($project_id == 4){
		$prefix = "oculus-lounge";
		$prizes = "1K, 500, 250 MOON";
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
	ob_start(); // Start output buffering
	checkLeaderboard($conn, "true");
	$list = ob_get_contents(); // Store buffer in variable
	ob_end_clean(); // End buffering and clean up
	$description = $list;
	// Drop Ship's own assets (prefix "drop-ship") sit flat under
	// dropship/images/; every other reskin nests under its own
	// dropship/<prefix>/images/ -- confirmed live via curl, see
	// dropship.php's own $prefix comment for the full story.
	$imageurl = ($prefix === "drop-ship")
		? "https://skulliance.io/staking/dropship/images/dropship.jpg"
		: "https://skulliance.io/staking/dropship/".$prefix."/images/dropship.jpg";
	discordmsg($title, evaluateText($description), $imageurl, $_SESSION['userData']['dropship_project_id']);
	deactivateGame($conn);
	}

	// Automatically create new game
	createGame($conn, $prizes, 3);
	$title = "New Game ".$prizes;
	$description = "A new game of ".getProjectName($conn)." has been created.";
	// Drop Ship's own assets (prefix "drop-ship") sit flat under
	// dropship/images/; every other reskin nests under its own
	// dropship/<prefix>/images/ -- confirmed live via curl, see
	// dropship.php's own $prefix comment for the full story.
	$imageurl = ($prefix === "drop-ship")
		? "https://skulliance.io/staking/dropship/images/dropship.jpg"
		: "https://skulliance.io/staking/dropship/".$prefix."/images/dropship.jpg";
	discordmsg($title, $description, $imageurl, $_SESSION['userData']['dropship_project_id']);
}
?>