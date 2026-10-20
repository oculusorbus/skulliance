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

echo "\nthe drop announces in DHC Fighters without colliding\n";
/*
 * THE SHARPEST EDGE IN THIS CHANGE, and the reason the platform sender was
 * split in two.
 *
 * dashboard.php includes db.php, then dropship.php (where the score is
 * written, so where the award fires), THEN its own webhooks.php. discordmsg()
 * is the ONLY name that collides between the two apps -- Drop Ship's takes a
 * project id where the platform's takes a link -- and neither declaration was
 * guarded. Announcing from inside the award means loading the platform's
 * webhooks.php there, which used to declare discordmsg() and make Drop Ship's
 * next include a fatal, taking the dashboard down rather than just the drop.
 *
 * Guarding both declarations would have been worse: Drop Ship's own calls
 * would land on the platform function and post a project id as a title link.
 * So webhooks.php defines skl_discordmsg() and only aliases the bare name
 * when SKL_WEBHOOKS_NO_ALIAS is unset.
 */
ok(strpos($src, "define('SKL_WEBHOOKS_NO_ALIAS', true)") !== false,
   'the award does not suppress the alias; announcing would fatal the dashboard');
$defAt = strpos($src, "define('SKL_WEBHOOKS_NO_ALIAS', true)");
$reqAt = strpos($src, "require_once __DIR__ . '/../dhcfighters-lib.php'");
ok($defAt !== false && $reqAt !== false && $defAt < $reqAt,
   'SKL_WEBHOOKS_NO_ALIAS is defined after the library loads, which is too late');
/* And it must no longer suppress the announcement itself. */
ok(strpos($src, 'DHCF_NO_NOTIFY') === false,
   'the award still suppresses the notifier, so the drop is silent in DHC Fighters');
$lib = file_get_contents(__DIR__ . '/dhcfighters-lib.php');
ok(preg_match("/if \(is_file\(__DIR__ \. '\/dhcfighters-notify\.php'\)\) \{/", $lib) === 1,
   'dhcf_award() no longer announces unconditionally');

/*
 * RUN IT. The collision is a fatal, and no amount of reading the source
 * proves its absence -- so this loads the notifier exactly as the award does
 * and then loads Drop Ship's webhooks.php exactly as dashboard.php does, in a
 * subprocess because a fatal would take this harness with it.
 */
$probe = tempnam(sys_get_temp_dir(), 'dsx') . '.php';
file_put_contents($probe, '<?php $R = ' . var_export(__DIR__, true) . ";\n"
  /* A checkout has no webhook credentials and display_errors is on, so the
     include warns; that noise is not what is being measured here. */
  . "ini_set('display_errors','0'); error_reporting(0);\n"
  . "define('SKL_WEBHOOKS_NO_ALIAS', true);\n"
  . "require_once \$R . '/dhcfighters-notify.php';\n"
  . "\$a = function_exists('skl_discordmsg') ? 1 : 0;\n"
  . "\$b = function_exists('discordmsg') ? 1 : 0;\n"
  . "include \$R . '/dropship/webhooks.php';\n"
  . "\$c = (new ReflectionFunction('discordmsg'))->getNumberOfParameters();\n"
  . "echo \"OK \$a \$b \$c\";\n");
$out = trim((string)shell_exec('php ' . escapeshellarg($probe) . ' 2>/dev/null'));
@unlink($probe);
$parts = explode(' ', $out);
ok(isset($parts[0]) && $parts[0] === 'OK',
   'loading the notifier then Drop Ship\'s webhooks.php is still fatal: ' . $out);
ok(isset($parts[1]) && $parts[1] === '1', 'the platform sender did not load under the alias guard');
ok(isset($parts[2]) && $parts[2] === '0',
   'the platform still declared the bare discordmsg(), which is the fatal');
ok(isset($parts[3]) && $parts[3] === '5',
   "Drop Ship's own discordmsg() is not the one in scope afterwards; its calls would misroute");

echo "\nand the platform itself is unchanged\n";
/* 150-odd call sites use the bare name. The alias has to still be there for
   every ordinary request, or every announcement on the platform goes silent. */
$probe = tempnam(sys_get_temp_dir(), 'dsy') . '.php';
file_put_contents($probe, '<?php $R = ' . var_export(__DIR__, true) . ";\n"
  . "ini_set('display_errors','0'); error_reporting(0);\n"
  . "require_once \$R . '/dhcfighters-notify.php';\n"
  . "\$r = new ReflectionFunction('discordmsg');\n"
  . "echo 'OK ' . (int)\$r->isVariadic() . ' '\n"
  . "   . (new ReflectionFunction('skl_discordmsg'))->getNumberOfParameters();\n");
$out = trim((string)shell_exec('php ' . escapeshellarg($probe) . ' 2>/dev/null'));
@unlink($probe);
$parts = explode(' ', $out);
ok(isset($parts[0]) && $parts[0] === 'OK',
   'an ordinary platform request can no longer load the notifier: ' . $out);
ok(isset($parts[1]) && $parts[1] === '1',
   'discordmsg() is not the variadic alias, so arguments may not reach the sender intact');
ok(isset($parts[2]) && $parts[2] === '12',
   'skl_discordmsg() lost parameters; the alias forwards positionally and would shift them');
/* The notifier must ask for the prefixed name, or from Drop Ship it finds
   Drop Ship's and posts a trait into the wrong channel with the wrong args. */
$nt = file_get_contents(__DIR__ . '/dhcfighters-notify.php');
ok(strpos($nt, "function_exists('skl_discordmsg')") !== false,
   'the notifier still guards on the bare name, which Drop Ship also defines');
ok(preg_match('/\n\t\tdiscordmsg\(/', $nt) !== 1,
   'the notifier still calls the bare discordmsg(), which may be another app\'s');

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

echo "\nevery run reaches Discord, not just the patient ones\n";
/*
 * THE OLD BEHAVIOUR: the announcement sat inside the game-over panel, which
 * only renders once `current_score` is set -- and checkScore() sets that at
 * the BOTTOM of dashboard.php, so on the request that logs the run it still
 * holds the previous value. The post therefore waited for a LATER request,
 * and the thing that produced one was a button labelled "Send Results to
 * Discord" at the end of the replay. Anyone who closed the tab posted
 * nothing.
 */
$dash = file_get_contents(__DIR__ . '/dropship/dashboard.php');
$js   = file_get_contents(__DIR__ . '/dropship/dropship.js');
ok(strpos($ds, "\$_SESSION['userData']['run_logged'] = \"true\";") !== false,
   'logScore() does not flag the run, so the announcement still waits for a later request');
ok(strpos($dash, "\$ds_just_ran") !== false,
   'dashboard.php never checks whether a run was just logged');
/*
 * IT MUST RUN AFTER THE WHOLE PAGE IS ON THE WIRE, which is a stronger rule
 * than "outside the game-over panel" and replaces it.
 *
 * The first version put it just above <div id="results">, which is above
 * #hidden-results where the replay frames live -- and discordmsg() had no
 * timeout, so a slow webhook stalled the response part-way and players got
 * a page with no animation and then an error, with the score already saved.
 * Reported live on mobile. Anywhere above the last byte of the page can do
 * that again, so the assertion is positional, not structural.
 */
$annAt    = strpos($dash, '$ds_just_ran  = isset(');
$replayAt = strpos($dash, 'id="hidden-results"');
$panelAt  = strpos($dash, "<?php if(isset(\$_SESSION['userData']['current_score']) && checkSquadCount(\$conn) < 1) { ?>");
ok($annAt !== false && $panelAt !== false && $annAt > $panelAt,
   'the announcement is back inside the game-over panel, where it waits on current_score');
ok($annAt !== false && $replayAt !== false && $annAt > $replayAt,
   'the announcement runs before the replay markup; a slow Discord post truncates the page');
/* ONCE. run_logged is consumed before the send, and 'notification' still
   guards the game-over path, so a reload cannot repeat the post. */
ok(strpos($dash, "unset(\$_SESSION['userData']['run_logged']);") !== false,
   'run_logged is never cleared, so every later request re-announces the same run');
$unsetAt = strpos($dash, "unset(\$_SESSION['userData']['run_logged']);");
$ifAt    = strpos($dash, 'if(($ds_just_ran || $ds_game_over)');
ok($unsetAt !== false && $ifAt !== false && $unsetAt < $ifAt,
   'run_logged is cleared after the send rather than before, so an early return leaves it set');
ok(strpos($dash, "!isset(\$_SESSION['userData']['notification'])") !== false,
   'the notification guard is gone, so one run can post twice');
ok(strpos($ds, "unset(\$_SESSION['userData']['notification']);") !== false,
   'logScore() does not clear the notification guard, so only the first run of a session posts');
/* discordmsg() is Drop Ship's and loads AFTER dropship.php, so the send must
   be guarded rather than assumed. */
ok(strpos($dash, "function_exists('discordmsg')") !== false,
   'the announcement assumes discordmsg() is loaded; webhooks.php is included after dropship.php');
/* AND IT MUST NEVER HOLD THE PAGE OPEN. There was no timeout on the Discord
   post at all, which is what turned a slow webhook into a broken page. */
$wh = file_get_contents(__DIR__ . '/dropship/webhooks.php');
ok(strpos($wh, 'CURLOPT_TIMEOUT') !== false,
   'the Discord post has no timeout, so a slow webhook can stall a page again');
ok(strpos($wh, 'CURLOPT_CONNECTTIMEOUT') !== false,
   'the Discord post has no connect timeout');
/* And the button must stop claiming to do the thing it no longer does. */
ok(strpos($js, 'Send Results to Discord') === false,
   'the button still says "Send Results to Discord", which is no longer what it does');
ok(strpos($js, '"Refresh"') !== false, 'the end-of-replay button lost its label entirely');

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
