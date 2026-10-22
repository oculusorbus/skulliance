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
/* dropshipThemeSlug() is the rule dropshipDropAward() derives a source key
   with, so the key check below has to call the real one rather than
   reimplement it. Harmless here: the file declares functions and nothing
   else, and it is already loaded on any real request. */
require_once __DIR__ . '/dropship/dropship-themes.php';

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
/*
 * AND THE ANNOUNCEMENT IS SUPPRESSED AGAIN -- CONDITIONALLY, which is the
 * opposite of what this used to assert and for a reason that only showed
 * up in production. SKL_WEBHOOKS_NO_ALIAS fixed the discordmsg() half of
 * the collision, and I took the notify skip out believing that was all of
 * it. Both credentials files ALSO declare a getWebhook(), with different
 * signatures, so loading the notifier here made Drop Ship's own later
 * include a fatal and took the play page down for every project the first
 * time anybody earned a drop. See the section at the bottom of this file.
 */
ok(strpos($src, 'DHCF_NO_NOTIFY') !== false,
   'nothing stops the notifier loading the platform webhooks into a Drop Ship request');
$lib = file_get_contents(__DIR__ . '/dhcfighters-lib.php');
ok(strpos($lib, "elseif (is_file(__DIR__ . '/dhcfighters-notify.php')) {") !== false,
   'dhcf_award() announces unconditionally again, which fatals a foreign caller');

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
/* ALL FOUR, not the two that existed when this was written. Dread City and
   Filthy Mermaid were registered later and were checked by nothing, which a
   mutation test found: capping Dread City again survived. */
foreach (array('dropship'      => 'Drop Ship',
               'oculuslounge'  => 'Oculus Lounge',
               'filthymermaid' => 'Filthy Mermaid',
               'dreadcity'     => 'Dread City',
               'dhc2fighters'  => 'DHC2 Fighters') as $k => $label) {
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
	/*
	 * ONE ROW FOR THE FOUR. They are themes of one game, switched between
	 * from inside it, and all four being 'wildcard' meant the rows rendered
	 * IDENTICALLY -- same "any trait", same count out of the whole
	 * collection, same trigger, same limit note, four times in a sixteen-row
	 * list of games to go and play.
	 *
	 * The three reskins are unlisted and DROP SHIP IS NOT, which is the half
	 * worth pinning: unlisting all four would take the only row that leads
	 * anywhere, and unlisting none is what shipped.
	 */
	if ($k === 'dropship') {
		ok(empty($g['unlisted']), 'Drop Ship itself is hidden from where traits drop');
		ok(strpos($g['trigger'], 'any theme') !== false,
		   "Drop Ship's row no longer says the other themes pay it too, and nothing else does");
	} else {
		ok(!empty($g['unlisted']), "$k is listed separately again; it is a Drop Ship theme, not a game");
	}
	/* Unlisted is a DISPLAY flag and must stay one: hiding the row must not
	   touch what the drop pays, what it is called or where it is credited.
	   Everything else in this loop is asserted for all four either way. */
	ok(!empty($g['url']) && !empty($g['x_tag']),
	   "$k lost its link or its credit along with its row");
	/*
	 * NO DAILY CEILING, ON PURPOSE. The games run weekly and a player with
	 * the NFTs to battle all day was left with nothing after the third
	 * lead. The rule above is the limit now: every drop needs a score that
	 * beats both the board and your own last one.
	 *
	 * ZERO HAS TO BE READ AS "NO CEILING" BY EVERY CALLER, because
	 * `$used >= 0` is true of any count at all -- a caller that compares
	 * without guarding refuses every draw instead of allowing them, which
	 * is how an uncapped game would silently become an unplayable one.
	 */
	ok(dhcf_cap($k) === 0, "$k is capped at " . dhcf_cap($k) . " a day again");
	/*
	 * THE KEY IS THE SLUG WITH THE HYPHENS OUT, which is the one thing that
	 * fails SILENTLY: dropshipDropAward() falls back to 'dropship' for an
	 * unregistered key, so a mistyped one still pays and simply credits the
	 * wrong game. Derived here through the same call the award uses rather
	 * than written out, so a change to the slug rule breaks this and not
	 * the live credit.
	 */
	$slug = array('dropship' => 'drop-ship', 'oculuslounge' => 'oculus-lounge',
	              'filthymermaid' => 'filthy-mermaid', 'dreadcity' => 'dread-city',
	              'dhc2fighters' => 'dhc2-fighters');
	ok(str_replace('-', '', dropshipThemeSlug($slug[$k])) === $k,
	   "$k is not what dropshipDropAward() derives from the slug {$slug[$k]}");
	/* AND THE LINK GOES TO THAT GAME. The reveal and the Collection both
	   send a player to $g['url']; pointing it at another theme is a dead
	   end that looks like a working link. */
	$proj = array('dropship' => 1, 'dreadcity' => 2, 'filthymermaid' => 3,
	              'oculuslounge' => 4, 'dhc2fighters' => 5);
	ok(strpos($g['url'], 'project_id=' . $proj[$k]) !== false,
	   "$k links to {$g['url']}, which is not project {$proj[$k]}");
	/*
	 * THE X TAG IS A REAL HANDLE SHAPE. A drop shared on X puts this in the
	 * post, so a malformed or invented one @-tags whoever does hold it --
	 * the same hazard dhc-fighter-modal.php names about Discord usernames.
	 * Shape only: nothing here can tell whether an account exists.
	 */
	ok(preg_match('/^@[A-Za-z0-9_]{1,15}$/', $g['x_tag']) === 1,
	   "$k has an x_tag that is not a valid handle: {$g['x_tag']}");
}
/*
 * BOTH ENFORCERS, each in its own shape. dhcf_award() reads the cap into
 * $cap and wraps the whole count in `if ($cap > 0)`; the claim endpoint
 * skips the query instead. What matters in both is that the comparison
 * `count >= cap` is UNREACHABLE when the cap is zero -- reached, it is
 * true of every count, and the uncapped game becomes the unplayable one.
 */
$lib_src = (string)@file_get_contents(__DIR__ . '/dhcfighters-lib.php');
ok($lib_src !== '', 'dhcfighters-lib.php could not be read');
$lib_at = strpos($lib_src, '$cap = dhcf_cap($source);');
ok($lib_at !== false, 'dhcf_award() no longer reads the cap where this expects it');
$lib_guard = $lib_at === false ? '' : substr($lib_src, $lib_at, 1400);
$lib_g = strpos($lib_guard, 'if ($cap > 0) {');
$lib_c = strpos($lib_guard, "(int)\$row['c'] >= \$cap");
ok($lib_g !== false,
   'dhcf_award() counts against the cap without guarding zero, so an uncapped game refuses every draw');
ok($lib_g !== false && $lib_c !== false && $lib_c > $lib_g,
   'the cap comparison in dhcf_award() sits outside the zero guard');

$claim_src = (string)@file_get_contents(__DIR__ . '/ajax/dhc-claim-drop.php');
ok($claim_src !== '', 'ajax/dhc-claim-drop.php could not be read');
ok(strpos($claim_src, 'dhcf_cap($key) > 0 ? $conn->query($sql) : null') !== false,
   'the claim endpoint runs its cap count even when there is no cap, and 0 refuses everything');
/* And the table says so rather than "none left today", which is what the
   plain arithmetic produces for a cap of zero. */
$dhcf_page = (string)@file_get_contents(__DIR__ . '/dhcfighters.php');
ok(strpos($dhcf_page, '$uncapped = ($cap <= 0);') !== false,
   'the drops table reads a cap of zero as a game with nothing left today');
/* The capped games must be untouched: this was a change to four entries,
   not to the ceiling itself. */
ok(DHCF_DAILY_CAP === 3, 'the platform-wide daily cap moved; only the four themes were meant to');
/* NAMED KEYS THAT REALLY EXIST. Written as `if (dhcf_game($k))` around the
   assertion, a typo in the key skips the check instead of failing it --
   'realmguardians' is not the key, 'guardians' is, so this proved nothing
   until a mutation uncapping it survived. */
foreach (array('guardians', 'skullswap', 'cryptcrawl') as $k) {
	ok(dhcf_game($k) !== null, "$k is not a registry key, so the check below is skipped");
	ok(dhcf_cap($k) > 0, "$k lost its daily cap too; only the four themes were meant to");
}
/* The two must not share a key, or one board's leads pay out of the other's
   daily allowance. */
ok(dhcf_game('dropship') !== dhcf_game('oculuslounge'),
   'the two specialty games share one registry entry');


echo "\ntwo apps, two getWebhook()s, one fatal\n";
/*
 * BOTH credentials files declare a getWebhook(): Skulliance's takes no
 * arguments, Drop Ship's takes a project id. Awarding a drop loads the
 * platform's webhooks.php, and that happens during the dropship.php
 * include -- BEFORE dashboard.php includes Drop Ship's own webhooks.php --
 * so that later include became "Cannot redeclare getWebhook()" and took
 * the play page down for every project the moment anybody earned a drop.
 *
 * SKL_WEBHOOKS_NO_ALIAS fixed the discordmsg() half of this and was
 * assumed to have fixed all of it. It had not.
 */
/* This harness has no shared stripper, and the comments below quote the
   very names being asserted on -- so strip them here rather than let a
   paragraph satisfy a check about code. */
$nocomments = function ($t) {
	return preg_replace(array('#/\*.*?\*/#s', '#^\s*//.*$#m'), '', (string)$t);
};
$td2 = $nocomments(file_get_contents(__DIR__ . '/dropship/dropship-traitdrop.php'));
ok(strpos($td2, "if (!function_exists('dropshipGetWebhook') && !defined('DHCF_NO_NOTIFY'))") !== false,
   'the award still loads the platform notifier while getWebhook() is shared');
ok(strpos($td2, "define('DHCF_NO_NOTIFY', true)") !== false, 'the notify skip is never set');
/* Set BEFORE the library is required, or the library has already run. */
ok(strpos($td2, "define('DHCF_NO_NOTIFY', true)") < strpos($td2, "require_once __DIR__ . '/../dhcfighters-lib.php'"),
   'the skip is defined after the library loads, which is too late');
/* It must be conditional: the moment the credentials rename lands, the
   announcement has to come back by itself. */
ok(strpos($td2, "!function_exists('dropshipGetWebhook')") !== false,
   'the skip is unconditional, so announcements never return after the rename');

$lib2 = $nocomments(file_get_contents(__DIR__ . '/dhcfighters-lib.php'));
ok(substr_count($lib2, 'DHCF_NO_NOTIFY') >= 3,
   'the library ignores the skip, so setting it changes nothing');
/* THE AWARD ITSELF MUST STILL HAPPEN. Skipping the announcement is the
   point; skipping the drop would be a silent loss of a prize. */
/*
 * dhcf_commit_drop(), NOT dhcf_award(). The announcement lives in the
 * commit, and slicing the award -- which is what the first version of
 * this did, and what the code comment beside the guard claimed -- found
 * neither the notifier nor the guard and failed for the right reason.
 * Bounded by the NEXT top-level function, because the first bare `}`
 * lands partway through.
 */
$award = substr($lib2, strpos($lib2, 'function dhcf_commit_drop'));
$nextfn = strpos($award, "\nfunction ", 1);
if ($nextfn !== false) $award = substr($award, 0, $nextfn);
ok(strlen($award) > 900 && strpos($award, 'dhcf_notify_drop($conn') !== false,
   'the dhcf_commit_drop() slice (' . strlen($award) . ' chars) does not reach the announcement');
ok(strpos($award, 'DHCF_NO_NOTIFY') !== false, 'the commit has no skip at all');
ok(strpos($award, 'DHCF_NO_NOTIFY') < strpos($award, 'dhcf_notify_drop($conn'),
   'the skip sits after the announcement, so it never prevents anything');
ok(strpos($award, 'dhcf_notify_drop($conn') !== false,
   'the announcement was removed outright rather than made skippable');

/* Drop Ship asks for its own accessor by the unshared name first, so the
   rename can land on the server without the code having to go first. */
$wh2 = $nocomments(file_get_contents(__DIR__ . '/dropship/webhooks.php'));
ok(strpos($wh2, "function_exists('dropshipGetWebhook')") !== false,
   'Drop Ship does not prefer the unshared accessor name');
ok(strpos($wh2, 'dropshipGetWebhook($project_id)') !== false,
   'the renamed accessor is not called with the project id');
ok(strpos($wh2, 'getWebhook($project_id)') !== false,
   'the fallback to the old name is gone, so this breaks before the rename');


echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
/*
 * EXIT CODE, which this file never had. It printed its failures and then
 * returned 0 regardless, so every automated run of the suite reported it
 * as passing however many checks were red -- found by mutation testing the
 * daily-cap change, when eight mutants in a row "survived" because nothing
 * here could ever fail.
 */
/*
 * AND THE SENDER EXISTS BY THE TIME THE DROP ASKS FOR IT.
 *
 * dropship-traitdrop.php decides whether to announce by testing
 * function_exists('dropshipGetWebhook'), and that function comes from
 * dropship/webhooks.php. A run EXECUTES while dropship.php is being
 * included -- the loop is top level -- so if webhooks.php is included
 * after it, the test is false on every run whatever the credentials
 * hold, DHCF_NO_NOTIFY goes up, and every Drop Ship trait drop commits
 * in silence while every other game announces.
 *
 * That is exactly what shipped, for months, with a comment in
 * dropship-traitdrop.php claiming the opposite. It is an ORDERING bug and
 * nothing in the file itself can show it, so it is pinned here.
 */
echo "\nthe sender is loaded before a run can award a drop\n";
$dash = (string)@file_get_contents(__DIR__ . '/dropship/dashboard.php');
$at_hooks = strpos($dash, "include 'webhooks.php'");
$at_run   = strpos($dash, "include 'dropship.php'");
ok($at_hooks !== false, 'dashboard.php no longer includes webhooks.php at all');
ok($at_run   !== false, 'dashboard.php no longer includes dropship.php at all');
ok($at_hooks !== false && $at_run !== false && $at_hooks < $at_run,
   'webhooks.php is included AFTER dropship.php again, so dropshipGetWebhook() does not '
 . 'exist when a run awards a drop and every Drop Ship trait drop goes unannounced');
/* The constraint the old ordering comment named, still true. */
$at_role = strpos($dash, "require_once 'role.php'");
ok($at_role === false || $at_hooks < $at_role,
   'webhooks.php no longer precedes role.php, which reads $bot_token from it');

exit($fail ? 1 : 0);
