<?php
/**
 * monstrocity-boss-collections-harness.php — which collections a boss accepts.
 *
 * Run: php monstrocity-boss-collections-harness.php
 *
 * The library functions are driven for real against a stub connection. The
 * behaviour that matters here is what happens when the table is MISSING, and
 * a stub is the only way to have it missing on demand.
 */
require_once __DIR__ . '/lib/monstrocity-boss-lib.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

class BRes {
	private $rows;
	public $num_rows;
	public function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return array_shift($this->rows); }
}
class BConn {
	public $error = '';
	public $have_table;
	public $links;      /* rows of boss_id/collection_id/policy */
	public $queries = array();
	public function __construct($have_table, $links) {
		$this->have_table = $have_table; $this->links = $links;
	}
	public function query($sql) {
		$this->queries[] = $sql;
		if (strpos($sql, 'SHOW TABLES LIKE') !== false)
			return new BRes($this->have_table ? array(array('t' => 'boss_collections')) : array());
		/* A MISSING TABLE RETURNS false, IT DOES NOT THROW. That is the whole
		   reason the guard exists, so the stub has to behave that way. */
		if (!$this->have_table) return false;
		if (strpos($sql, 'FROM boss_collections bc') !== false) {
			/* ROWS AS GIVEN, NO FILTERING. This stub used to drop
			   policy-less rows itself, which made the "a collection with no
			   policy id is not an attachment" check test the stub rather
			   than the library -- it could not fail for any change to the
			   code. The real filter is a WHERE clause, so it is asserted
			   from source below, where a stub cannot stand in for it. */
			$out = array();
			foreach ($this->links as $l)
				$out[] = array('boss_id' => $l['boss_id'], 'policy' => $l['policy']);
			return new BRes($out);
		}
		if (strpos($sql, 'FROM boss_collections') !== false) {
			$out = array();
			preg_match('/boss_id = (\d+)/', $sql, $m);
			foreach ($this->links as $l)
				if ((int)$l['boss_id'] === (int)$m[1])
					$out[] = array('collection_id' => $l['collection_id']);
			return new BRes($out);
		}
		return false;
	}
}
/* The static cache in monstrocity_boss_links_table() is per-process, so each
   scenario runs in its own subprocess rather than fighting it. */
function scenario($code, $have_table, $links) {
	$f = tempnam(sys_get_temp_dir(), 'bcs') . '.php';
	file_put_contents($f,
		"<?php require '" . __DIR__ . "/monstrocity-boss-collections-harness.php';\n"
	  . '$conn = new BConn(' . var_export($have_table, true) . ', '
	  . var_export($links, true) . ");\n" . $code);
	$out = shell_exec('php ' . escapeshellarg($f) . ' 2>&1');
	@unlink($f);
	return trim((string)$out);
}
/* Included by a scenario: define the helpers, run nothing. */
if (basename(__FILE__) !== basename($_SERVER['SCRIPT_FILENAME'])) return;

$CARDANO = 'd5e6c991b4d3f0b0f1a2b3c4d5e6f708192a3b4c5d6e7f8091a2b3c4';
$SOLANA  = 'DeuY9MKJ4zrpZBGfbPwQ8bTrRdWqkjBqMHLkmPwGv7Ru';
$XRPL    = 'rJ9vS3xQmN2kLpWdF7tYbH4cG8aZ1eR6uT:12';

echo "\nnothing attached behaves exactly as before\n";
/*
 * THE WHOLE MIGRATION IS ADDITIVE OR IT IS NOT SHIPPABLE. Every boss on the
 * platform has no links on the day this goes out, so if that case changes at
 * all, every boss changes at once.
 */
$l = monstrocity_boss_policy_list($CARDANO, array());
ok($l === array($CARDANO), 'a boss with nothing attached no longer accepts exactly its own collection');

echo "\nthe boss's own collection is always accepted\n";
/* A half-finished edit in the admin must not be able to lock a boss's own
   holders out of it. */
$l = monstrocity_boss_policy_list($CARDANO, array($SOLANA));
ok($l[0] === $CARDANO, "the boss's own policy is not first in the list");
ok(in_array($SOLANA, $l, true), 'an attached policy is missing from the list');
ok(count($l) === 2, 'the list has the wrong size: ' . json_encode($l));

echo "\nduplicates and blanks cannot pad the list\n";
/* Ticking the boss's own collection as well is the natural thing to do and
   must not post the same policy twice -- get-nft-assets.php builds one
   placeholder per entry, so a duplicate is a wasted bind, and `IN ('x','x')`
   reads as a bug to whoever next looks at the log. */
$l = monstrocity_boss_policy_list($CARDANO, array($CARDANO, $SOLANA, $SOLANA));
ok($l === array($CARDANO, $SOLANA), 'a duplicate policy survived: ' . json_encode($l));
$l = monstrocity_boss_policy_list($CARDANO, array('', '   ', $SOLANA));
ok($l === array($CARDANO, $SOLANA), 'a blank policy survived: ' . json_encode($l));
/* A boss whose own collection has no policy is already invisible in the game
   (get-bosses.php INNER JOINs it), but the list must not carry an empty
   string that would match nothing and look like it should. */
$l = monstrocity_boss_policy_list('', array($SOLANA));
ok($l === array($SOLANA), "an empty own-policy leaked into the list: " . json_encode($l));
ok(monstrocity_boss_policy_list('', array()) === array(),
   'a boss with no policy anywhere returns something to match on');

echo "\na missing table degrades to silence, not to an error\n";
/*
 * NOTHING IN THE CODEBASE CREATES boss_collections. Until somebody runs the
 * statement in monstrocity-schema.md, every query against it returns false
 * without throwing. The read must come back empty -- so every boss falls back
 * to its own collection and the game is unchanged -- rather than warning into
 * a JSON body or fataling the boss list.
 */
$links = array(array('boss_id' => 7, 'collection_id' => 3, 'policy' => $SOLANA));
$out = scenario('var_export(monstrocity_boss_policies($conn));', false, $links);
ok($out === 'array (' . "\n" . ')', 'with the table missing the read did not come back empty: ' . $out);
$out = scenario('var_export(monstrocity_boss_links($conn, 7));', false, $links);
ok($out === 'array (' . "\n" . ')', 'with the table missing the admin read did not come back empty: ' . $out);
$out = scenario('var_dump(monstrocity_boss_links_table($conn));', false, $links);
ok(trim($out) === 'bool(false)', 'the table guard did not report the table missing: ' . $out);
/*
 * NOTE, so nobody reads more into these three than they prove: the explicit
 * monstrocity_boss_links_table() guard and the `if (!$res)` check after the
 * query BOTH produce an empty read, so removing either one on its own changes
 * nothing observable and these checks stay green. That is deliberate belt and
 * braces -- the guard also saves a pointless query -- not something these
 * assertions can hold. Mutation-tested and confirmed equivalent.
 */

echo "\nwith the table there, the links are read\n";
$out = scenario('$p = monstrocity_boss_policies($conn); echo json_encode($p);', true, $links);
ok($out === '{"7":["' . $SOLANA . '"]}', 'the attached policy was not read back: ' . $out);
$out = scenario('echo json_encode(monstrocity_boss_links($conn, 7));', true, $links);
ok($out === '[3]', 'the admin did not read back the ticked collection: ' . $out);
/* A boss with nothing of its own ticked must be ABSENT, not present-and-empty:
   the caller tests isset() to decide whether to fall back. */
$out = scenario('echo json_encode(array_keys(monstrocity_boss_policies($conn)));', true, $links);
ok($out === '[7]', 'a boss with no links appeared in the policy map: ' . $out);

echo "\na collection with no policy id is not an attachment\n";
/*
 * It can never match an NFT (`policy IN (...)` cannot match ''), so counting
 * it would make a boss look configured while accepting nobody.
 *
 * READ FROM SOURCE, BECAUSE A STUB CANNOT RUN SQL. This is a WHERE clause,
 * and the first version of this check had the stub applying the filter and
 * then asserted the result -- which passed no matter what the library did.
 * IS NOT NULL is asserted as well as <> '': a NULL policy makes `c.policy <>
 * ''` evaluate to NULL, which is not TRUE, so the row drops either way, but
 * only one of the two says so to the next reader.
 */
$lib = file_get_contents(__DIR__ . '/lib/monstrocity-boss-lib.php');
ok(strpos($lib, 'WHERE c.policy IS NOT NULL AND c.policy <> ') !== false,
   'the policy filter is gone from the query, so a collection that can never match '
 . 'an NFT now counts as an attachment');
/* The join itself: without it the policy column is whatever bc has, which is
   nothing. */
ok(preg_match('/JOIN collections c ON c\.id = bc\.collection_id/', $lib) === 1,
   'the link rows are no longer joined to the collections they name');

echo "\nthe endpoint and the game use it\n";
$ep = file_get_contents(__DIR__ . '/ajax/get-bosses.php');
ok(strpos($ep, 'monstrocity_boss_policies($conn)') !== false,
   'get-bosses.php never reads the attached collections');
ok(strpos($ep, 'monstrocity_boss_policy_list(') !== false,
   'get-bosses.php does not build the accepted list');
ok(strpos($ep, "'policies' => \$policies") !== false,
   'get-bosses.php does not send the list to the client');
/* THE ONE-POLICY TEST IS GONE, not merely supplemented. */
ok(strpos($ep, "in_array(\$row['policy'], \$userPolicies)") === false,
   'canFight still tests the single collection, so other chains stay locked out');
ok(strpos($ep, 'array_intersect($policies, $userPolicies)') !== false,
   'canFight does not test every accepted collection');
/* READ ONCE, OUTSIDE THE LOOP. Inside it this is one query per boss on a list
   of forty, on a page that already runs four. */
$at_read = strpos($ep, 'monstrocity_boss_policies($conn)');
$at_loop = strpos($ep, 'foreach ($results as $row)');
ok($at_read !== false && $at_loop !== false && $at_read < $at_loop,
   'the link table is read inside the per-boss loop, once per boss');

$game = file_get_contents(__DIR__ . '/monstrocity.php');
ok(strpos($game, 'policyIds: bossPolicies') !== false,
   'the roster fetch still posts a single policy');
ok(strpos($game, 'policyIds: [boss.policy]') === false,
   'the single-policy roster fetch is still in the page');
/* An older cached copy of the page, or a boss row from before this change,
   must not break the fetch. */
ok(strpos($game, '(boss.policies && boss.policies.length)') !== false,
   'the client does not fall back when a boss payload has no policies');

echo "\nthe admin can tick them, and says so when it cannot save\n";
$ad = file_get_contents(__DIR__ . '/admin-monstrocity-boss.php');
ok(strpos($ad, 'name="boss_collections[]"') !== false,
   'the boss form has no collection checkboxes');
ok(strpos($ad, 'DELETE FROM boss_collections WHERE boss_id = ') !== false,
   'the boss form never clears the old links, so unticking does nothing');
ok(strpos($ad, 'INSERT INTO boss_collections (boss_id, collection_id, sort_order)') !== false,
   'the boss form never writes the links');
ok(strpos($ad, 'monstrocity_boss_links_table($conn)') !== false,
   'the boss form does not check whether the table exists');
/*
 * AND THE WARNING MUST SURVIVE THE SAVE. The form redirects on a clean save;
 * if a warning does not block that redirect the admin sees only "Saved" while
 * every tick was discarded -- the exact failure the theme form shipped with.
 */
ok(strpos($ad, "!\$msgs['err'] && empty(\$msgs['warn']) && !empty(\$_POST['after'])") !== false,
   'a save that only warned still redirects, throwing the warning away');
/* Ticks must survive a rejected save too, or an error on the name quietly
   narrows the boss. */
ok(strpos($ad, '$repost_links = $links_post;') !== false,
   'a rejected save loses the ticks, so re-saving narrows the boss');

echo "\nthe migration is written down\n";
/* The table does not exist until somebody runs this by hand. */
$schema = file_get_contents(__DIR__ . '/monstrocity-schema.md');
ok(strpos($schema, 'CREATE TABLE IF NOT EXISTS boss_collections') !== false,
   'monstrocity-schema.md has no CREATE for boss_collections, so nobody can create it');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
