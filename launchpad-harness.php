<?php
/* launchpad-harness.php - CLI only. No database.
 *
 * Two things, both about the Drop Ship pair.
 *
 * ONE: every tile on the launchpad points at a file that exists. This is the
 * first page after login and a dead tile there is the worst possible first
 * impression. `dropship/` itself was already one of these once -- it serves
 * index.php, an unconditional redirect to the login, so a logged-in player
 * got bounced to a login page; the fix was to point at dashboard.php, and
 * nothing stopped it happening again.
 *
 * TWO: Drop Ship and Oculus Lounge are ONE codebase switched by a session
 * value, so the two tiles need ?project_id= or each opens whichever game the
 * session was last left on. That id reaches SQL unescaped -- db.php's
 * getProjectName() and getProjectPolicyId() both read
 * $_SESSION['userData']['dropship_project_id'] straight into a query string --
 * and it is now settable from a LINK, not just this page's own form. So the
 * validation is the thing under test here, driven rather than read.
 *
 * Usage: php launchpad-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* Strip comments before scanning for code. A commented-out oculusVipLinks()
   call still contains the string "oculusVipLinks(", and a bare strpos passed
   exactly that mutation -- the fifth time a harness in this repo has matched
   prose instead of the code it was checking.
   TRAILING comments count, not just whole-line ones. The require_once lines
   that pull in vip-links.php each carry a trailing "oculusVipLinks()" note
   saying what they are for, and that note alone satisfied a check for the
   call -- so deleting the actual call passed. The line-comment pattern
   therefore matches anywhere on a line, but skips a slash-slash preceded by
   a colon or a slash so it does not eat the https:// in a URL. */
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	/* ~ as the delimiter, not ! -- the ! inside the (?<! lookbehind closes a
	   !-delimited pattern early and PHP then reads the rest as modifiers:
	   "Unknown modifier '['". */
	$s = preg_replace('~(?<![:/])//.*$~m', '', $s);
	$s = preg_replace('!^\s*#.*$!m', '', $s);
	return $s;
}

$root = __DIR__;
$lp   = file_get_contents($root . '/launchpad.php');

/* ---------------------------------------------------------------- *
 * Every tile points at a file that exists.
 * ---------------------------------------------------------------- */
echo "every launchpad tile points at a real file\n";
$at  = strpos($lp, '$lp_sections = array(');
ok($at !== false, '$lp_sections is gone from launchpad.php');
$end = strpos($lp, "\n);", $at);
$lp_sections = null;
eval(substr($lp, $at, $end - $at + 3));
ok(is_array($lp_sections), '$lp_sections did not evaluate');

$tiles = 0;
foreach ($lp_sections as $name => $sec) {
	foreach ($sec['items'] as $it) {
		$tiles++;
		/* Strip the query string and any #fragment -- monstrocity.php#boss is
		   a real tile and the file is monstrocity.php. */
		$path = preg_replace('/[?#].*$/', '', $it[0]);
		ok(is_file($root . '/' . $path), "the \"{$it[2]}\" tile points at $path, which does not exist");
	}
}
printf("  %d tiles across %d sections\n", $tiles, count($lp_sections));

/* ---------------------------------------------------------------- *
 * The Drop Ship pair each name their own project.
 * ---------------------------------------------------------------- */
echo "\nthe Drop Ship pair each pin their own game\n";
$ds_tiles = array();
foreach ($lp_sections as $sec) {
	foreach ($sec['items'] as $it) {
		if (strpos($it[0], 'dropship/') === 0) $ds_tiles[$it[2]] = $it[0];
	}
}
ok(count($ds_tiles) === 2, 'expected two Drop Ship tiles, found ' . count($ds_tiles));
foreach ($ds_tiles as $label => $href) {
	ok(strpos($href, 'project_id=') !== false,
	   "the \"$label\" tile has no project_id, so it opens whichever game the session was last on");
}
ok(isset($ds_tiles['Drop Ship'])     && strpos($ds_tiles['Drop Ship'], 'project_id=1') !== false,     'Drop Ship no longer pins project 1');
ok(isset($ds_tiles['Oculus Lounge']) && strpos($ds_tiles['Oculus Lounge'], 'project_id=4') !== false, 'Oculus Lounge no longer pins project 4');

/* NSFW IS IN THE DESCRIPTION, not a badge. It was a badge briefly and the
   pill was the wrong shape for it; what matters is only that the warning is
   on screen before the click, so the check is on the text a player reads,
   not on the markup that carries it. */
$lounge = null;
foreach ($lp_sections as $sec) foreach ($sec['items'] as $it) if ($it[2] === 'Oculus Lounge') $lounge = $it;
ok($lounge !== null, 'the Oculus Lounge tile is gone');
ok($lounge !== null && stripos($lounge[3], 'NSFW') !== false,
   'the Oculus Lounge description no longer says NSFW: "' . ($lounge[3] ?? '') . '"');

/* ---------------------------------------------------------------- *
 * The Play menu in the nav carries the same pair, pinned the same way.
 * ---------------------------------------------------------------- */
echo "\nthe Play menu pins them too\n";
$hdr = file_get_contents($root . '/header.php');
preg_match_all('#<a href="(dropship/dashboard\.php[^"]*)"[^>]*>(.*?)</a>#', $hdr, $nav);
ok(count($nav[1]) === 2, 'expected two Drop Ship links in header.php, found ' . count($nav[1]));
foreach ($nav[1] as $i => $href) {
	$text = trim(strip_tags($nav[2][$i]));
	ok(strpos($href, 'project_id=') !== false,
	   "the nav's \"$text\" link has no project_id, so it opens whichever game the session was last on");
}
ok(strpos($hdr, 'dropship/dashboard.php?project_id=4') !== false, 'Oculus Lounge is not in the Play menu');
/* NO NSFW IN THE MENU, deliberately -- the nav is a plain list of game names
   and the label went in the launchpad description instead, which is the one
   place with room to say it. Asserted so it does not drift back in: this is
   a decision, not an omission. */
ok(strpos($hdr, 'Oculus Lounge (NSFW)') === false && strpos($hdr, 'nav-nsfw') === false,
   'NSFW is back in the Play menu; it belongs in the launchpad description only');

/* ---------------------------------------------------------------- *
 * The way into Oculus Lounge is stated wherever it is asked about.
 *
 * The gate tests a Discord ROLE, not the token, so "buy a VIP token" on its
 * own is incomplete advice -- it already sent someone who owned one away
 * refused and none the wiser. These three links travel together or the
 * second step goes missing again, which is the step nobody can guess.
 * ---------------------------------------------------------------- */
echo "\nthe VIP route is complete wherever it is given\n";
$vip_src = no_comments(file_get_contents($root . '/dropship/vip-links.php'));
preg_match_all("/define\('(OCULUS_[A-Z_]+)',\s*'([^']+)'\)/", $vip_src, $dm);
$urls = array_combine($dm[1], $dm[2]);
ok(count($urls) === 3, 'expected three URLs in dropship/vip-links.php, found ' . count($urls));
ok(isset($urls['OCULUS_VIP_BUY'])     && strpos($urls['OCULUS_VIP_BUY'], 'wayup.io') !== false,       'the VIP token listing link is gone');
ok(isset($urls['OCULUS_VIP_DISCORD']) && strpos($urls['OCULUS_VIP_DISCORD'], 'discord.com/invite') !== false,
   'the Discord invite is missing or is not the canonical invite URL (a t.co shortlink is a third-party redirect on the one step nobody can guess)');
ok(isset($urls['OCULUS_SITE'])        && strpos($urls['OCULUS_SITE'], 'oculuslounge.vip') !== false,  'the official site link is gone');

/* The filter is part of the listing link: the bare collection is the whole
   Disco Solaris drop, most of which is not a VIP token. */
ok(isset($urls['OCULUS_VIP_BUY']) && strpos($urls['OCULUS_VIP_BUY'], '?do=true&f=') !== false,
   'the VIP listing link lost its rarity filter, so it now points at the whole collection');

/* Every page that turns someone away, or that is read by someone who has not
   got in yet, renders the set rather than hand-copying part of it. */
foreach (array(
	'dropship/dashboard.php'    => 'the Play gate, which is where a player is actually refused',
	'dropship/instructions.php' => 'the Where to Buy section',
	'dropship/discoin.php'      => 'the DISCOIN page, read by exactly the person with no token',
) as $file => $why) {
	$src = no_comments(file_get_contents($root . '/' . $file));
	ok(strpos($src, 'oculusVipLinks(') !== false, "$why no longer renders the VIP route");
	ok(strpos($src, "require_once 'vip-links.php'") !== false, "$file calls oculusVipLinks() without requiring vip-links.php");
	/* A hand-copied URL is the drift this partial exists to prevent. */
	ok(substr_count($src, 'wayup.io/collection/3d250a78') === 0,
	   "$file has its own copy of the VIP listing URL again; it should come from vip-links.php");
}

/* The Skull Paper cannot include PHP, so its copy is checked against the
   constants rather than trusted. */
$md = file_get_contents($root . '/skullpaper/games-oculus-lounge.md');
foreach ($urls as $name => $url) {
	ok(strpos($md, $url) !== false,
	   "games-oculus-lounge.md has drifted from $name in vip-links.php");
}
ok(stripos($md, 'role') !== false,
   'the Skull Paper no longer says the gate is a Discord role, which is the half players miss');

/* ---------------------------------------------------------------- *
 * The real validation block out of dropship/dropship.php, driven.
 * ---------------------------------------------------------------- */
echo "\nan unknown project_id never reaches the session\n";
$ds_src = file_get_contents($root . '/dropship/dropship.php');
$b_at   = strpos($ds_src, '$project_id = "";');
ok($b_at !== false, 'the project-init block moved in dropship/dropship.php');
$b_end  = strpos($ds_src, "\n}\n", strpos($ds_src, 'if(!isset($_SESSION[\'userData\'][\'dropship_project_id\'])){', $b_at));
$block  = substr($ds_src, $b_at, $b_end - $b_at + 3);

/* getProjects() excludes Filthy Mermaid and Dread City, so 2 and 3 are not
   selectable -- the same list the Select Project dropdown is built from. */
function getProjects($conn) { return array(1 => 'Drop Ship', 4 => 'Oculus Lounge'); }

function init($post, $get, $session_has = null) {
	$_POST = $post; $_GET = $get;
	$_SESSION = array('userData' => array());
	if ($session_has !== null) $_SESSION['userData']['dropship_project_id'] = $session_has;
	$conn = null;
	eval($GLOBALS['block']);
	return array(
		$_SESSION['userData']['dropship_project_id'] ?? null,
		$project_id_changed,
	);
}

[$v, $ch] = init(array(), array('project_id' => '4'));
ok($v === 4,         "a link to project 4 should select it, got " . var_export($v, true));
ok($ch === 'true',   'switching to a new project should flag the change');

[$v, $ch] = init(array(), array('project_id' => '1'), 4);
ok($v === 1,         "a link to project 1 from the Lounge should switch back, got " . var_export($v, true));
ok($ch === 'true',   'switching back should flag the change');

[$v, $ch] = init(array(), array('project_id' => '4'), 4);
ok($v === 4,         'reselecting the current project should leave it alone');
ok($ch === 'false',  'reselecting the current project should NOT flag a change');

[$v] = init(array('project_id' => '4'), array());
ok($v === 4,         'the Select Project form must still work');

[$v] = init(array('project_id' => '1'), array('project_id' => '4'));
ok($v === 1,         'the form POST must win over a query string on the same request');

/* The one that matters. $_SESSION['userData']['dropship_project_id'] is
   interpolated into SQL in db.php, so THE PROPERTY UNDER TEST IS NOT "bad
   input is rejected" -- it is that whatever ends up on the session is an
   INTEGER drawn from getProjects(), never a string that came off the wire.
   Those are different claims and the first one is wrong:
   intval("4; DROP TABLE results") is 4, a perfectly real project, so that
   input legitimately selects Oculus Lounge. It is harmless because what is
   stored is the int 4 and the rest of the string is gone. An earlier version
   of this harness asserted the default instead and "failed" on exactly those
   two cases while the code was right. */
$allowed = getProjects(null);
foreach (array(
	"1 OR 1=1"                      => 'a tacked-on SQL clause',
	"0"                             => 'project 0, which does not exist',
	"99"                            => 'a project that does not exist',
	"2"                             => 'a project the dropdown deliberately hides',
	"4; DROP TABLE results"         => 'a stacked statement',
	"' UNION SELECT 1,2 -- "        => 'a quoted union',
	"<script>alert(1)</script>"     => 'markup',
	"4.9"                           => 'a decimal',
	"  4  "                         => 'padded whitespace',
) as $bad => $desc) {
	foreach (array('GET'  => array(array(), array('project_id' => $bad)),
	               'POST' => array(array('project_id' => $bad), array())) as $how => $args) {
		[$v] = init($args[0], $args[1], 4);
		ok(is_int($v),
		   "$desc by $how left a " . gettype($v) . " on the session: " . var_export($v, true)
		 . ' -- that value goes straight into a query string in db.php');
		ok(isset($allowed[$v]),
		   "$desc by $how stored " . var_export($v, true) . ', which getProjects() does not list');
		ok($v !== $bad,
		   "$desc by $how survived verbatim onto the session");
	}
}

/* And the ids that genuinely do not exist leave the session alone rather
   than silently moving the player to another game. */
foreach (array('0', '99', '2', "' UNION SELECT 1,2 -- ") as $nonexistent) {
	[$v] = init(array(), array('project_id' => $nonexistent), 4);
	ok($v === 4, "project_id=$nonexistent moved the player off their current game, to " . var_export($v, true));
	[$v] = init(array(), array('project_id' => $nonexistent));
	ok($v === 1, "project_id=$nonexistent on a fresh session should fall back to Drop Ship, got " . var_export($v, true));
}

[$v] = init(array(), array());
ok($v === 1, 'a first visit with no project named should default to Drop Ship');
[$v] = init(array(), array(), 4);
ok($v === 4, 'a plain visit must not disturb the project already on the session');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
