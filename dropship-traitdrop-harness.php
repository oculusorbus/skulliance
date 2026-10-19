<?php
/**
 * dropship-traitdrop-harness.php — trait drops for Drop Ship / Oculus Lounge.
 *
 * Run: php dropship-traitdrop-harness.php
 *
 * The rule is driven for real: dropshipDropContext() against a stub
 * connection, dropshipDropEarned() directly. What cannot be executed here --
 * the ORDER of the two calls inside logScore(), and the notifier suppression
 * that stops a fatal -- is read from source, and each of those says why.
 */
require_once __DIR__ . '/dropship/dropship-traitdrop.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

/* A connection that answers the two aggregate reads and nothing else. It does
   NOT filter by user itself -- the point is to check the library's own WHERE
   clauses, so each query is answered from what it asked for. */
class DSRes {
	private $rows; public $num_rows;
	public function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return array_shift($this->rows); }
}
class DSConn {
	public $rows;            /* array of array(user_id, score) */
	public $queries = array();
	public $throw = false;
	public function __construct($rows) { $this->rows = $rows; }
	public function query($sql) {
		$this->queries[] = preg_replace('/\s+/', ' ', $sql);
		if ($this->throw) throw new RuntimeException('database went away');
		$self = 7;
		$mine = ($sql !== '' && strpos($sql, "user_id = $self") !== false);
		$best = null;
		foreach ($this->rows as $r) {
			$isSelf = ((int)$r['user_id'] === $self);
			if ($mine ? !$isSelf : $isSelf) continue;
			if ($best === null || $r['score'] > $best) $best = $r['score'];
		}
		return new DSRes(array(array('s' => $best === null ? null : (string)$best)));
	}
}
function session_for($score) {
	$_SESSION['userData'] = array(
		'game_id' => 12, 'dropship_user_id' => 7, 'dropship_project_id' => 1,
		'user_id' => 4242, 'score' => $score);
}
function ctx_from($rows) {
	session_for(0);
	return dropshipDropContext(new DSConn($rows));
}

echo "\nthe context reads the right two numbers\n";
/* Mine is MY best in this game; rival is the best of EVERYONE ELSE. Getting
   the second one wrong by including the player would mean their own standing
   score blocks them from ever leading again. */
$ctx = ctx_from(array(array('user_id' => 7, 'score' => 20), array('user_id' => 9, 'score' => 50)));
ok($ctx['ok'] === true, 'the context read did not complete');
ok($ctx['prev'] === 20, 'the player\'s own previous score is wrong: ' . var_export($ctx['prev'], true));
ok($ctx['rival'] === 50, 'the rival best is wrong: ' . var_export($ctx['rival'], true));
/* An empty game gives nulls, not zeroes -- 0 is a real score somebody could
   post, and "nobody has played" has to be distinguishable from "somebody
   scored nothing". */
$ctx = ctx_from(array());
ok($ctx['prev'] === null && $ctx['rival'] === null,
   'an empty game does not report null for both scores');
/* The rival query must exclude the player by id, in SQL. */
$c = new DSConn(array()); session_for(0); dropshipDropContext($c);
ok(strpos($c->queries[1], 'user_id <> 7') !== false,
   'the rival query does not exclude the player, so their own score blocks them');
ok(strpos($c->queries[0], 'user_id = 7') !== false,
   'the own-score query is not scoped to the player');
foreach ($c->queries as $i => $q) {
	ok(strpos($q, 'game_id = 12') !== false,
	   "query $i is not scoped to the current game, so the lead is not the weekly one");
	ok(strpos($q, 'project_id = 1') !== false,
	   "query $i is not scoped to the project, so Drop Ship and Oculus Lounge share a board");
}

echo "\nthe rule: take or tie the lead, and beat your own\n";
$case = function ($prev, $rival) { return array('ok' => true, 'prev' => $prev, 'rival' => $rival); };

/* First into a fresh game. Nothing to beat either way -- rewarding whoever
   turns up first is the whole point of the mechanic. */
ok(dropshipDropEarned($case(null, null), 20) === true,
   'the first player into a new game earns nothing, which removes the reason to be early');

/* THE OWNER'S WORKED EXAMPLE. 20 pays; the 10 that replaces it does not;
   the 25 after that does. Two traits for well/badly/well, accepted on the
   grounds that the bad run risked a real position. */
ok(dropshipDropEarned($case(20, null), 10) === false,
   'a worse run still pays, so three quick runs farm three traits');
ok(dropshipDropEarned($case(10, null), 25) === true,
   'recovering past your own replaced score does not pay');

/* Second place pays nothing. This is the rule that makes the whole thing
   different from every other game on the platform. */
ok(dropshipDropEarned($case(null, 50), 49) === false, 'a run that does not reach the lead still pays');
ok(dropshipDropEarned($case(null, 50), 50) === true, 'tying the lead does not pay, though the rule says it should');
ok(dropshipDropEarned($case(null, 50), 51) === true, 'beating the lead does not pay');

/* Equalling your own score is not beating it -- otherwise a replay of the
   same run pays forever. */
ok(dropshipDropEarned($case(50, 40), 50) === false,
   'matching your own previous score pays, so one good run can be repeated for traits');

/* Both gates, together. Leading but not improving, and improving but not
   leading, each pay nothing. */
ok(dropshipDropEarned($case(60, 50), 55) === false,
   'a run that leads the board but not your own best pays');
ok(dropshipDropEarned($case(10, 90), 20) === false,
   'a run that beats your own but not the board pays');

echo "\nnothing is earned when the read failed\n";
/* A context that did not complete must never pay: its nulls would read as
   "empty board, no previous score", which is the one case that always pays. */
ok(dropshipDropEarned(array('ok' => false, 'prev' => null, 'rival' => null), 999) === false,
   'a failed context read pays out as though the board were empty');
$c = new DSConn(array()); $c->throw = true; session_for(0);
$bad = dropshipDropContext($c);
ok($bad['ok'] === false, 'a database error during the read is reported as a good context');
/* Missing session keys -- a CLI/cron path, or a game that was deactivated. */
$_SESSION['userData'] = array();
ok(dropshipDropContext(new DSConn(array()))['ok'] === false,
   'the context reads as good with no game in the session');

echo "\nit cannot disturb a score save\n";
$src = file_get_contents(__DIR__ . '/dropship/dropship-traitdrop.php');
/* Every path out is quiet. A throw reaching logScore() would abort the rest
   of the save (inventory, soldiers) after the row was already written. */
ok(substr_count($src, 'catch (Throwable') >= 2,
   'not every path through this file catches, so a failure can surface mid-game');
ok(strpos($src, 'error_log(') !== false, 'failures are swallowed without a trace in the log');

echo "\nthe award prints nothing into the game page\n";
/*
 * THIS RUNS MID-RENDER inside dropship.php, and display_errors is ON
 * platform-wide. A warning from the credentials include, the mysqli connect,
 * or anything the library emits would land in the middle of the page the
 * player is looking at. Measured, not assumed: before the buffering went in,
 * a missing credentials file printed a two-line warning into the output.
 *
 * Driven for real with an EARNING context, so it takes the full award path.
 *
 * WHAT THIS CHECK CANNOT PROVE, stated so nobody reads more into it: removing
 * the ob_start()/ob_end_clean() pair entirely still passes here. A checkout
 * has no credentials file, so the is_file() guard returns before the only
 * warning this environment can produce, and silence comes from the guard
 * rather than from the buffer. The buffer is there for the warnings a
 * checkout CANNOT produce -- a mysqli connect notice, anything the library
 * emits on a live box -- and the only honest way to see it work was to delete
 * the credentials check and watch two lines of warning land in the output,
 * which is how it was found. Mutation-tested and confirmed: buffering removed
 * is not caught; ONE BRANCH leaving the buffer open is.
 *
 * Never make this testable by writing a credentials file from a harness --
 * run on the live server that would overwrite the real one.
 */
session_for(99);
$depth = ob_get_level();
ob_start();
dropshipDropAward(array('ok' => true, 'prev' => null, 'rival' => null));
$noise = ob_get_clean();
ok($noise === '', 'the award printed into the page: ' . substr(str_replace("\n", ' ', $noise), 0, 120));
/* AND THE BUFFER STACK IS BALANCED. An ob_start() without its matching end on
   some branch would swallow the rest of the game page instead of a warning --
   a worse bug than the one the buffering fixes. */
ok(ob_get_level() === $depth,
   'the award left an output buffer open, which would swallow the rest of the page');
/* Every branch out of the buffered section has to close it. */
$awardSrc = substr($src, strpos($src, 'function dropshipDropAward'));
ok(substr_count($awardSrc, 'ob_start()') === substr_count($awardSrc, 'ob_end_clean()')
   || substr_count($awardSrc, 'ob_end_clean()') > substr_count($awardSrc, 'ob_start()'),
   'the award opens more buffers than it closes');

echo "\nthe notifier is suppressed, and that is a fatal if it is not\n";
/*
 * THE SHARPEST EDGE IN THIS CHANGE. dashboard.php includes db.php, then
 * dropship.php (where the score is written, so where the award happens), THEN
 * its own webhooks.php. Neither webhooks.php guards its discordmsg()
 * declaration. If the award loads the platform notifier it defines the
 * platform's discordmsg() first, and Drop Ship's include on the next line is
 * a fatal "cannot redeclare" -- the dashboard, not just the drop.
 */
ok(strpos($src, "define('DHCF_NO_NOTIFY', true)") !== false,
   'the award does not suppress the notifier; loading it would fatal the dashboard');
$defAt = strpos($src, "define('DHCF_NO_NOTIFY', true)");
$reqAt = strpos($src, "require_once __DIR__ . '/../dhcfighters-lib.php'");
ok($defAt !== false && $reqAt !== false && $defAt < $reqAt,
   'DHCF_NO_NOTIFY is defined after the library loads, which is too late');
$lib = file_get_contents(__DIR__ . '/dhcfighters-lib.php');
ok(strpos($lib, "if (!defined('DHCF_NO_NOTIFY') && is_file(__DIR__ . '/dhcfighters-notify.php'))") !== false,
   'dhcf_award() ignores DHCF_NO_NOTIFY, so suppressing it does nothing');
/* And the suppression must be opt-in, or every other caller goes silent. */
ok(strpos($lib, "define('DHCF_NO_NOTIFY'") === false,
   'the platform defines DHCF_NO_NOTIFY itself, which would silence all nine games');

echo "\nlogScore reads before the delete and awards after the insert\n";
/*
 * ORDER IS THE WHOLE INTEGRATION. deleteResult() destroys the player's
 * previous score, which is half the rule, so the read has to precede it. And
 * the award has to follow a successful INSERT, or a score that failed to save
 * still pays a trait.
 */
$ds = file_get_contents(__DIR__ . '/dropship/db.php');
$ctxAt = strpos($ds, 'dropshipDropContext($conn)');
$delAt = strpos($ds, 'deleteResult($conn);', $ctxAt === false ? 0 : $ctxAt);
$insAt = strpos($ds, 'INSERT INTO results (game_id');
$awdAt = strpos($ds, 'dropshipDropAward($ds_drop_ctx)');
ok($ctxAt !== false, 'logScore() never reads the drop context');
ok($awdAt !== false, 'logScore() never awards the drop');
ok($ctxAt !== false && $delAt !== false && $ctxAt < $delAt,
   'the context is read after deleteResult(), by which point the previous score is gone');
ok($awdAt !== false && $insAt !== false && $awdAt > $insAt,
   'the award happens before the score is inserted');
/* Inside the success branch, not after it: the else branch is the "game was
   deactivated, score not saved" path. */
$elseAt = strpos($ds, "echo \"<script type='text/javascript'>alert('The game was already deactivated");
ok($awdAt !== false && $elseAt !== false && $awdAt < $elseAt,
   'the award is outside the branch that confirms the insert succeeded');
/* logScore() MUST NOT have changed its replace-on-any-score behaviour: that
   is deliberate, the risk of losing your position is what deters spam. */
ok(strpos($ds, '// Delete previous result for current game, if it exists') !== false
   && $delAt !== false,
   'logScore() no longer replaces the previous result unconditionally');

echo "\nboth games are registered\n";
require_once __DIR__ . '/dhcfighters-config.php';
foreach (array('dropship' => 'Drop Ship', 'oculuslounge' => 'Oculus Lounge') as $k => $label) {
	$g = dhcf_game($k);
	ok(is_array($g), "$k is not in DHCF_GAMES, so dhcf_award() will not resolve it");
	if (!is_array($g)) continue;
	ok($g['label'] === $label, "$k has the wrong label");
	/* FLOOR 0: the lead is the gate. A score floor would only lock out the
	   first player in a fresh game, which is who the mechanic is for. */
	ok(dhcf_floor($k) === 0, "$k has a score floor, which shuts out the first player in a new game");
	/* Topping a board is what this pays for, and placement_1 is the table
	   reserved for exactly that. */
	ok($g['base'] === 'placement_1', "$k does not pay the first-place table");
	ok($g['category'] === 'wildcard', "$k claims a trait slot; all eight belong to the other games");
	ok(empty($g['unlisted']), "$k is hidden from the list of where traits drop");
	ok(dhcf_cap($k) >= 1, "$k has no daily cap, so a see-saw is unbounded");
}
/* The two must not share a key, or one board's leads pay out of the other's
   daily allowance. */
ok(dhcf_game('dropship') !== dhcf_game('oculuslounge'),
   'the two specialty games share one registry entry');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
