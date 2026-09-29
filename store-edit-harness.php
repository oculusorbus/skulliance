<?php
/* store-edit-harness.php — CLI only. Exercises WHO MAY EDIT WHICH LISTING.
 *
 * This tests an AUTHORISATION boundary, so it runs the real files rather
 * than a description of them: db.php's storeItemEditRights() and
 * adminUpdateItem() are read out of db.php itself, and ajax/item-edit.php
 * is executed verbatim in a subprocess against a fake connection. A test
 * that re-implements the rule it is checking passes on the day the rule
 * changes, which is the day it needed to fail.
 *
 * What it pins down:
 *   - a stranger, a logged-out visitor and a member with no project get
 *     nothing
 *   - a partner may edit their own project's listings and no others
 *   - a partner may not push a listing onto somebody else's shelf
 *   - `featured` and `secondary_project_id` are NOT in a partner's UPDATE,
 *     so fixing a typo cannot silently clear an Exclusive flag
 *   - user 1 may do all of it
 *   - the column whitelist survives a hostile POST key
 *
 * Usage: php store-edit-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = __DIR__;
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* ---- pull the two real functions out of db.php --------------------------- */
function extract_fn($src, $name) {
	$at = strpos($src, "function $name(");
	if ($at === false) return '';
	$open = strpos($src, '{', $at);
	$depth = 0; $i = $open; $n = strlen($src);
	for (; $i < $n; $i++) {
		if ($src[$i] === '{') $depth++;
		elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $i++; break; } }
	}
	return substr($src, $at, $i - $at);
}
$db  = file_get_contents($root . '/db.php');
$fns = extract_fn($db, 'storeItemEditRights') . "\n\n" . extract_fn($db, 'adminUpdateItem');
/* ONE SUBSTITUTION, and the only one: mysqli_real_escape_string() insists on
   a real mysqli and there is no connection here. Escaping is not what this
   harness is testing -- authorisation and the column whitelist are -- so the
   call is redirected to an equivalent and everything else runs verbatim. */
$fns = str_replace('mysqli_real_escape_string($conn, ', 'h_escape(', $fns);
ok(strpos($fns, 'mysqli_real_escape_string') === false, 'no un-redirected escape call left');
ok(strpos($fns, 'storeItemEditRights') !== false, 'storeItemEditRights() found in db.php');
ok(strpos($fns, 'adminUpdateItem')     !== false, 'adminUpdateItem() found in db.php');

/* ---- a scratch tree the real endpoint can be run inside ------------------ */
$tmp = sys_get_temp_dir() . '/skl-store-edit-' . getmypid();
@mkdir($tmp . '/ajax', 0777, true);
copy($root . '/ajax/item-edit.php', $tmp . '/ajax/item-edit.php');

/* The stub db.php: the real rights + update functions, a fake connection,
   and the session/POST handed in through the environment. */
$stub = '<?php
$_SESSION = json_decode(getenv("H_SESSION"), true) ?: array();
$_POST    = json_decode(getenv("H_POST"), true) ?: array();
$H_PROJECTS = json_decode(getenv("H_PROJECTS"), true) ?: array();
$H_ITEM     = json_decode(getenv("H_ITEM"), true) ?: array();

function getProjects($conn, $type = "") { global $H_PROJECTS; return $H_PROJECTS; }
function h_escape($v) { return addslashes((string)$v); }

class FakeRes {
	public $num_rows; private $rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class FakeConn {
	public $error = ""; public $log = array();
	function query($sql) {
		$this->log[] = $sql;
		global $H_PROJECTS, $H_ITEM;
		if (strpos($sql, "SHOW COLUMNS FROM items") === 0) {
			$r = array();
			foreach (array("id","name","image_url","price","quantity","project_id",
			               "secondary_project_id","featured","override") as $c)
				$r[] = array("Field" => $c);
			return new FakeRes($r);
		}
		if (strpos($sql, "SELECT id, project_id FROM items") === 0)
			return new FakeRes($H_ITEM ? array($H_ITEM) : array());
		if (strpos($sql, "SELECT id FROM projects WHERE id") === 0) {
			preg_match("/id = \'(\\\\d+)\'/", $sql, $m);
			$id = isset($m[1]) ? (int)$m[1] : 0;
			return new FakeRes(isset($H_PROJECTS[$id]) ? array(array("id" => $id)) : array());
		}
		if (strpos($sql, "UPDATE items SET") === 0) { fwrite(STDERR, $sql . "\n"); return true; }
		return new FakeRes(array());
	}
	function close() {}
}
$conn = new FakeConn();
' . "\n" . $fns . "\n";
file_put_contents($tmp . '/db.php', $stub);

/* ---- drive the real endpoint -------------------------------------------- */
function run($session, $post, $projects, $item) {
	global $tmp;
	$env = array(
		'H_SESSION'  => json_encode($session),
		'H_POST'     => json_encode($post),
		'H_PROJECTS' => json_encode($projects),
		'H_ITEM'     => json_encode($item),
	);
	$d = array(1 => array('pipe','w'), 2 => array('pipe','w'));
	/* CWD IS ajax/, not the tree root. `include '../db.php'` starts with a
	   dot, so PHP resolves it against the WORKING DIRECTORY and ignores
	   include_path entirely -- which is why this works under mod_php, where
	   the cwd is the running script's own directory, and why running it from
	   anywhere else silently finds no db.php at all. */
	$p = proc_open(escapeshellarg(PHP_BINARY) . ' item-edit.php',
		$d, $pipes, $tmp . '/ajax', array_merge($_ENV, $env));
	$out = stream_get_contents($pipes[1]); fclose($pipes[1]);
	$err = stream_get_contents($pipes[2]); fclose($pipes[2]);
	proc_close($p);
	$j = json_decode(trim($out), true);
	/* the UPDATE, if one was issued, comes back on stderr */
	$sql = '';
	foreach (explode("\n", $err) as $line)
		if (strpos($line, 'UPDATE items SET') === 0) $sql = $line;
	return array('json' => $j, 'sql' => $sql, 'raw' => $out, 'err' => $err);
}

/* Two artists and the platform. Artist A owns projects 9 and 12; artist B
   owns 29. Project 7 (DIAMOND) belongs to nobody. */
$PROJECTS = array(
	7  => array('name' => 'Diamond',  'currency' => 'DIAMOND', 'discord_id' => ''),
	9  => array('name' => 'Artist A', 'currency' => 'AAA',     'discord_id' => '1111'),
	12 => array('name' => 'A Second', 'currency' => 'ASX',     'discord_id' => '1111'),
	29 => array('name' => 'Artist B', 'currency' => 'BBB',     'discord_id' => '2222'),
);
$ITEM9 = array('id' => '50', 'project_id' => '9');   // belongs to artist A

$SESS_ADMIN   = array('userData' => array('user_id' => 1,  'discord_id' => '9999'));
$SESS_A       = array('userData' => array('user_id' => 44, 'discord_id' => '1111'));
$SESS_B       = array('userData' => array('user_id' => 55, 'discord_id' => '2222'));
$SESS_MEMBER  = array('userData' => array('user_id' => 66, 'discord_id' => '7777'));
$SESS_OUT     = array();

function post($over = array()) {
	return array_merge(array('item_id' => 50, 'name' => 'Fixed Name',
		'image_url' => 'https://example.com/a.png', 'price' => 100,
		'quantity' => 5, 'project_id' => 9), $over);
}

echo "authorisation\n";

$r = run($SESS_OUT, post(), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, 'logged out: refused');
ok($r['sql'] === '', 'logged out: no UPDATE issued');

$r = run($SESS_MEMBER, post(), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, 'member with no project: refused');
ok($r['sql'] === '', 'member with no project: no UPDATE issued');

$r = run($SESS_B, post(), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, "other artist: refused someone else's listing");
ok($r['sql'] === '', 'other artist: no UPDATE issued');
echo "  refusal reads: " . ($r['json']['message'] ?? '?') . "\n";

$r = run($SESS_A, post(), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'owning artist: allowed on their own listing');

$r = run($SESS_ADMIN, post(), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'user 1: allowed on anything');

echo "\nmoving a listing between shelves\n";

$r = run($SESS_A, post(array('project_id' => 12)), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'artist may move it to their OTHER project');

$r = run($SESS_A, post(array('project_id' => 29)), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, "artist may NOT move it onto another artist's shelf");
echo "  refusal reads: " . ($r['json']['message'] ?? '?') . "\n";

$r = run($SESS_ADMIN, post(array('project_id' => 29)), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'user 1 may move it anywhere');

echo "\nfields a partner must not reach\n";

/* THE REGRESSION THIS EXISTS FOR: a partner's form does not render these,
   so their POST omits them -- and reading them as 0 would clear an
   Exclusive flag every time its owner fixed a typo. */
$r = run($SESS_A, post(), $PROJECTS, $ITEM9);
ok(strpos($r['sql'], 'featured')             === false, 'partner UPDATE leaves `featured` alone');
ok(strpos($r['sql'], 'secondary_project_id') === false, 'partner UPDATE leaves `secondary_project_id` alone');

/* And a hand-rolled POST cannot smuggle them in either. */
$r = run($SESS_A, post(array('featured' => 1, 'secondary_project_id' => 29)), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'partner posting admin fields still saves');
ok(strpos($r['sql'], 'featured')             === false, 'partner CANNOT set `featured` by hand');
ok(strpos($r['sql'], 'secondary_project_id') === false, 'partner CANNOT set a second currency by hand');

$r = run($SESS_ADMIN, post(array('featured' => 1, 'secondary_project_id' => 12)), $PROJECTS, $ITEM9);
ok(strpos($r['sql'], "`featured` = '1'")      !== false, 'user 1 CAN set `featured`');
ok(strpos($r['sql'], '`secondary_project_id`') !== false, 'user 1 CAN set a second currency');

echo "\nthe column whitelist\n";

$r = run($SESS_ADMIN, post(array('override' => 1, 'id' => 999, 'user_id' => 3)), $PROJECTS, $ITEM9);
ok(strpos($r['sql'], 'override') === false, 'an unlisted POST key never becomes a SET clause');
ok(strpos($r['sql'], '`user_id`') === false, 'nor does a made-up one');
ok(strpos($r['sql'], "WHERE id = '50'") !== false, 'the WHERE comes from the validated item_id');

echo "\nvalidation\n";

$r = run($SESS_A, post(array('name' => '')), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, 'empty name refused');
$r = run($SESS_A, post(array('name' => 'Hi<script>alert(1)</script>')), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, 'a tag that is not <br> is refused');
$r = run($SESS_A, post(array('name' => "Galaxy of Sons<br>Claimer's Choice")), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), '<br> is allowed, because listings use it');
$r = run($SESS_A, post(array('image_url' => 'javascript:alert(1)')), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, 'a javascript: image URL is refused');
$r = run($SESS_A, post(array('image_url' => 'images/thing.png')), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'a site-relative image path is allowed');
$r = run($SESS_A, post(array('quantity' => -1)), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'quantity -1 (unlimited) is allowed');
$r = run($SESS_A, post(array('quantity' => 0)), $PROJECTS, $ITEM9);
ok($r['json'] && !empty($r['json']['success']), 'quantity 0 (delist) is allowed');
$r = run($SESS_A, post(array('price' => -5)), $PROJECTS, $ITEM9);
ok($r['json'] && $r['json']['success'] === false, 'a negative price is refused');
$r = run($SESS_A, post(array('item_id' => 9999)), $PROJECTS, array());
ok($r['json'] && $r['json']['success'] === false, 'a missing item is refused');

/* cleanup */
@unlink($tmp . '/ajax/item-edit.php'); @rmdir($tmp . '/ajax');
@unlink($tmp . '/db.php'); @rmdir($tmp);

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all store-edit checks passed\n";
