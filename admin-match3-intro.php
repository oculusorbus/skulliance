<?php
/*
 * admin-match3-intro.php — the match-3 slide intro, on demand.
 *
 * The overlay a new player meets on skullswap.php and monstrocity.php
 * before their first run. It is gated on having no score on record for
 * that game, so once anybody has played once it is unreachable to them
 * -- which makes it unreviewable without this page.
 *
 * It renders the REAL partial with force, exactly as admin-chest.php
 * plays the real reward reveal. Nothing here reimplements the overlay,
 * so what is reviewed is what ships. It reads no scores and writes
 * nothing at all.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

/*
 * FOUR NAMES, THREE PAGES. DHC Bosses is `require dhcarena.php` and
 * Boss Battles is monstrocity.php#boss, so two of these are modes of a
 * page already listed. They are named separately anyway because the
 * copy names the game and a player on the Bosses page has never heard
 * of the Arena.
 */
$ami_all = array(
	'skullswap'   => array('Skull Swap',  'skullswap.php',   'scores, project_id 0'),
	'monstrocity' => array('Monstrocity', 'monstrocity.php', 'scores, project_id 36'),
	'bossbattles' => array('Monstrocity', 'monstrocity.php#boss', 'scores, project_id 36'),
	'dhcarena'    => array('DHC Arena',   'dhcarena.php',    'dhc_arena_battles or dhc_boss_runs'),
	'dhcbosses'   => array('DHC Bosses',  'dhcbosses.php',   'dhc_arena_battles or dhc_boss_runs'),
);
$ami_key  = (isset($_GET['game']) && isset($ami_all[$_GET['game']])) ? $_GET['game'] : 'skullswap';
$ami_game = $ami_all[$ami_key];

include 'header.php';
admin_chrome('match3-intro');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Match-3 intro</h3>
	<small>
		The overlay a player meets before their first run of a match-3 game. It
		explains the one rule nobody guesses: a tile can be dragged the whole way
		along its row or column, not just swapped with a neighbour.
	</small>

	<h4>Who sees it</h4>
	<small>
		Any account with no row in the table that game already writes when it is
		played: <code>scores</code> for Skull Swap and Monstrocity, a battle or a
		Boss run for the Arena. That row IS the record, so nothing is written when
		the overlay is dismissed: a player who closes it without playing meets it
		again next time, and a player who finishes one run never does. There is no
		flag to reset and nothing to clean up, which is also why this page exists.
		Once you have played, you cannot reach it.
	</small>

	<h4>Which game</h4>
	<div class="adm-pick">
		<?php foreach ($ami_all as $k => $row): ?>
		<a class="small-button" href="admin-match3-intro.php?game=<?php echo $k; ?>"><?php
			echo htmlspecialchars($k === 'bossbattles' ? 'Boss Battles' : $row[0]); ?></a>
		<?php endforeach; ?>
	</div>
	<small>
		Showing <strong><?php echo htmlspecialchars($ami_game[0]); ?></strong>, served by
		<code><?php echo htmlspecialchars($ami_game[1]); ?></code>, gated on
		<code><?php echo htmlspecialchars($ami_game[2]); ?></code>. The copy names the
		game, which is the only thing that differs between them.
	</small>

	<h4>Reopen</h4>
	<div class="adm-pick">
		<a class="small-button" href="admin-match3-intro.php?game=<?php
			echo htmlspecialchars($ami_key, ENT_QUOTES); ?>">Play it again</a>
	</div>
	<small>The demo loops on its own; this is for after you have dismissed it.</small>
</div></div>

<?php /* THE REAL PARTIAL, forced. Not a copy -- see the header. */ ?>
<?php require_once __DIR__ . '/lib/match3-intro.php';
      match3_intro(array('game' => $ami_game[0], 'force' => true)); ?>
</html>
