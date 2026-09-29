<?php
/*
 * profile-lib.php — what a player has actually done, across everything.
 *
 * profile.php was written when the platform had Missions, Realms, Raids,
 * Boss Battles, Monstrocity and Skull Swap, and it still shows exactly
 * those. Nine games have shipped since and none of them appears on a
 * player's own profile, which is the one page meant to say who they are
 * here.
 *
 * ONE FUNCTION, NOT NINE SECTIONS. Each of these already has a working
 * leaderboard query in db.php; the columns below are taken from those
 * rather than guessed, so they match the live schema by construction.
 * What a profile needs is the same numbers narrowed to one user, which
 * is a headline and a couple of supporting figures each -- not another
 * nine image strips down an already long page.
 *
 * EVERY QUERY IS GUARDED. mysqli_report(MYSQLI_REPORT_OFF) is set
 * platform-wide (db.php:49), so a missing table returns false rather
 * than throwing -- and a game that has not been deployed, or a table
 * renamed later, must not be able to blank a public page. A row that
 * cannot be read is simply a game the player has not played.
 */

/* One row per game. `stat` is the headline; `sub` are the supporting
   figures, already formatted. A game with no plays returns null and is
   dropped, so the grid shows what someone HAS done rather than a wall of
   zeroes. */
function profile_game_record($conn, $user_id) {
	$uid = (int)$user_id;
	if ($uid <= 0) return array();

	$one = function ($sql) use ($conn) {
		$r = $conn->query($sql);
		if (!$r || !$r->num_rows) return null;
		return $r->fetch_assoc();
	};
	$n = function ($v) { return number_format((float)$v); };

	$out = array();

	/* ---- DHC Fighters: a collection, so the headline is how many ---- */
	$r = $one("SELECT COUNT(*) AS fighters, MAX(rarity_score) AS best, SUM(rarity_score) AS total
	           FROM dhc_fighters WHERE user_id = '$uid'");
	if ($r && (int)$r['fighters'] > 0) $out[] = array(
		'key' => 'dhcfighters', 'name' => 'DHC Fighters', 'url' => 'dhcfighters.php',
		'stat' => $n($r['fighters']), 'label' => (int)$r['fighters'] === 1 ? 'Fighter' : 'Fighters',
		'sub' => array('Best ' . $n($r['best']), $n($r['total']) . ' total'));

	/* ---- DHC Arena ----
	   attacker_id, NOT user_id: dhc_arena_battles records two sides and the
	   leaderboard counts the attacker's, so this counts the same battles the
	   board does. Getting that wrong is what made this tile vanish -- the
	   query failed, the guard below dropped the row, and a game with a real
	   record looked like one never played. `outcome <> 0` excludes battles
	   still in progress, same as checkDHCArenaLeaderboard(). */
	$r = $one("SELECT SUM(outcome = 1) AS wins, SUM(outcome = 2) AS losses,
	                  MAX(best_chain) AS chain
	           FROM dhc_arena_battles WHERE attacker_id = '$uid' AND outcome <> 0");
	if ($r && ((int)$r['wins'] + (int)$r['losses']) > 0) $out[] = array(
		'key' => 'dhcarena', 'name' => 'DHC Arena', 'url' => 'dhcarena.php',
		'stat' => $n($r['wins']), 'label' => 'Wins',
		'sub' => array($n($r['losses']) . ' losses', 'Best chain ' . $n($r['chain'])));

	/* ---- Realm Guardians ---- */
	$r = $one("SELECT COUNT(*) AS sieges, MAX(wave) AS wave, MAX(held) AS held
	           FROM guardians_scores WHERE user_id = '$uid'");
	if ($r && (int)$r['sieges'] > 0) $out[] = array(
		'key' => 'guardians', 'name' => 'Realm Guardians', 'url' => 'guardians.php',
		'stat' => $n($r['wave']), 'label' => 'Best wave',
		'sub' => array($n($r['sieges']) . ' sieges', $n($r['held']) . ' held'));

	/* ---- Crypt Crawl ---- */
	$r = $one("SELECT SUM(status = 'won') AS wins, SUM(status = 'lost') AS losses,
	                  MAX(rooms_cleared) AS depth
	           FROM cryptcrawls WHERE user_id = '$uid' AND status IN ('won','lost')");
	if ($r && ((int)$r['wins'] + (int)$r['losses']) > 0) $out[] = array(
		'key' => 'cryptcrawl', 'name' => 'Crypt Crawl', 'url' => 'cryptcrawl.php',
		'stat' => $n($r['wins']), 'label' => 'Runs won',
		'sub' => array($n($r['losses']) . ' lost', 'Deepest ' . $n($r['depth'])));

	/* ---- Crypt Conquest ---- */
	$r = $one("SELECT SUM(status = 'won') AS wins, SUM(status = 'lost') AS losses,
	                  MAX(enemies_defeated) AS best
	           FROM cryptconquests WHERE user_id = '$uid' AND status IN ('won','lost')");
	if ($r && ((int)$r['wins'] + (int)$r['losses']) > 0) $out[] = array(
		'key' => 'cryptconquest', 'name' => 'Crypt Conquest', 'url' => 'cryptconquest.php',
		'stat' => $n($r['wins']), 'label' => 'Runs won',
		'sub' => array($n($r['losses']) . ' lost', $n($r['best']) . ' best clear'));

	/* ---- Gauntlets: wins and losses live on the encounters ----
	   run_id, not gauntlet_id, and outcome is a STRING -- 'win' / 'loss' /
	   'pending' -- not the integer the other games use. Both taken from
	   checkGauntletsLeaderboard(); both were wrong here first time and the
	   tile silently disappeared. */
	$r = $one("SELECT COUNT(DISTINCT g.id) AS runs,
	                  SUM(ge.outcome = 'win')  AS wins,
	                  SUM(ge.outcome = 'loss') AS losses
	           FROM gauntlets g
	           LEFT JOIN gauntlets_encounters ge
	                  ON ge.run_id = g.id AND ge.outcome != 'pending'
	           WHERE g.user_id = '$uid'");
	if ($r && (int)$r['runs'] > 0) $out[] = array(
		'key' => 'gauntlets', 'name' => 'Gauntlets', 'url' => 'gauntlets.php',
		'stat' => $n($r['runs']), 'label' => (int)$r['runs'] === 1 ? 'Gauntlet' : 'Gauntlets',
		'sub' => array($n($r['wins']) . ' wins', $n($r['losses']) . ' losses'));

	/* ---- Obscura ---- */
	$r = $one("SELECT COUNT(*) AS runs, MAX(streak) AS streak, SUM(solves) AS solves
	           FROM obscura_scores WHERE user_id = '$uid'");
	if ($r && (int)$r['runs'] > 0) $out[] = array(
		'key' => 'obscura', 'name' => 'Obscura', 'url' => 'obscura.php',
		'stat' => $n($r['streak']), 'label' => 'Best streak',
		'sub' => array($n($r['solves']) . ' solved', $n($r['runs']) . ' runs'));

	/* ---- Skull Racer ---- */
	$r = $one("SELECT COUNT(*) AS races FROM skull_racer_runs WHERE user_id = '$uid'");
	if ($r && (int)$r['races'] > 0) $out[] = array(
		'key' => 'skullracer', 'name' => 'Skull Racer', 'url' => 'skullracer.php',
		'stat' => $n($r['races']), 'label' => (int)$r['races'] === 1 ? 'Race' : 'Races',
		'sub' => array());

	return $out;
}
