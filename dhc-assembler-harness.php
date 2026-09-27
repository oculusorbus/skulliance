<?php
/* dhc-assembler-harness.php — CLI only.
 *
 * dhc-assembler.php is included by two pages that treat it very differently,
 * and both of the things checked here have already broken once:
 *
 *  1. IT LEAKS NOTHING. It is included at GLOBAL scope, so any bare variable it
 *     leaves behind is a global by the time header.php runs. It left $name --
 *     a trait's display name -- and dhcsandbox.php, the one page that includes
 *     it BEFORE the header, printed "U. Vigilance Device #2" where the signed-in
 *     player's username belongs.
 *
 *  2. MODE AND STANDALONE ARE SEPARATE. $dhca_mode is what it can DO (every
 *     trait, draggable layers) and $dhca_standalone is what it LOOKS like and
 *     how it lays out. They were one flag, which made "the sandbox's powers in
 *     Skulliance chrome" inexpressible. Recoupling them would silently give the
 *     integrated sandbox its old ash-and-ochre skin back.
 *
 * Usage: php dhc-assembler-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$rarity = require __DIR__ . '/dhcrarity.php';
if (!function_exists('dhcf_rarity')) { function dhcf_rarity(){ global $rarity; return $rarity; } }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/**
 * ONE SUBPROCESS PER RUN. The assembler declares dhc_title() unguarded, so it
 * can only be included once in a process -- which is exactly right for a page
 * and useless for a loop. Shelling out also makes each case a genuinely fresh
 * request rather than one contaminated by the last, which is the thing being
 * checked. Adding function_exists() guards purely so a test could loop would be
 * changing shipped code to suit the test.
 *
 * Returns array(output, clobbered-variable-names).
 */
function run_assembler($mode, $standalone) {
	$script = '
		$rarity = require ' . var_export(__DIR__ . '/dhcrarity.php', true) . ';
		function dhcf_rarity(){ global $rarity; return $rarity; }
		$seed = array("name"=>"__PLAYER__","avatar_url"=>"__AVATAR__","title"=>"__TITLE__",
		              "url"=>"__URL__","user"=>"__USER__","id"=>"__ID__",
		              "status"=>"__STATUS__","member"=>"__MEMBER__");
		extract($seed);
		$dhca_mode = ' . var_export($mode, true) . ';
		$dhca_owned = null;
		$dhca_standalone = ' . ($standalone ? 'true' : 'false') . ';
		ob_start(); include ' . var_export(__DIR__ . '/dhc-assembler.php', true) . '; $out = ob_get_clean();
		$bad = array();
		foreach ($seed as $k => $v) if (isset($$k) && $$k !== $v) $bad[] = $k;
		echo json_encode(array("out"=>$out, "bad"=>$bad));
	';
	$cmd = PHP_BINARY . ' -d error_reporting=0 -r ' . escapeshellarg($script) . ' 2>/dev/null';
	$raw = shell_exec($cmd);
	$j = json_decode((string)$raw, true);
	if (!is_array($j)) { echo "  (subprocess produced nothing for $mode/$standalone)\n"; return array('', array()); }
	return array($j['out'], $j['bad']);
}

/* ---------- 1. nothing a host page relies on is clobbered ------------------- */
list($out, $clobbered) = run_assembler('sandbox', false);
ok(!$clobbered, 'the assembler clobbers host variables: '.implode(', ', $clobbered));
printf("globals: %s\n", $clobbered
	? 'LEAKED '.implode(', ', $clobbered)
	: 'nothing leaked (checked name, avatar_url, title, url, user, id, status, member)');

/* ---------- 2. behaviour and appearance are independent --------------------- */
$want = array(
	// mode       standalone  palette  ownsBody  reorder
	array('sandbox',  true,  'ash',  true,  'true'),
	array('sandbox',  false, 'navy', false, 'true'),
	array('fighters', false, 'navy', false, 'false'),
	array('fighters', true,  'ash',  true,  'false'),
);
foreach ($want as $w) {
	list($mode, $sa, $pal, $body, $reorder) = $w;
	list($o, ) = run_assembler($mode, $sa);
	$gotPal  = strpos($o, '--ink:#100d0d') !== false ? 'ash' : 'navy';
	$gotBody = (bool)preg_match('/html,body\{margin:0;height:100%\}/', $o);
	$gotRe   = preg_match('/ALLOW_REORDER = (\w+)/', $o, $m) ? $m[1] : '?';
	$good = ($gotPal === $pal && $gotBody === $body && $gotRe === $reorder);
	printf("  mode=%-9s standalone=%-5s -> %-4s  ownsBody=%-5s  reorder=%-5s  %s\n",
		$mode, $sa ? 'true' : 'false', $gotPal,
		$gotBody ? 'true' : 'false', $gotRe, $good ? 'ok' : 'WRONG');
	ok($good, "mode=$mode standalone=".($sa?'true':'false')
	        ." gave $gotPal/".($gotBody?'body':'nobody')."/$gotRe");
}
/* The fourth row is the one that proves they are INDEPENDENT rather than merely
   renamed: 'fighters' with standalone=true is a combination the old single flag
   could not express at all. */
echo "  (row 4 is the combination the single flag could not express)\n";

/* ---------- 3. the tabs are still complete ---------------------------------- */
$src = file_get_contents(__DIR__ . '/dhc-assembler.php');
preg_match('/\$dhc_slots = array\((.*?)\n\);/s', $src, $m1);
preg_match('/\$dhc_tab_order = array\((.*?)\);/s', $src, $m2);
preg_match_all("/'([a-zA-Z0-9]+)'\s*=>\s*array\(/", $m1[1], $sm);
preg_match_all("/'([a-zA-Z0-9]+)'/", $m2[1], $tm);
$draw = $sm[1]; $tabs = $tm[1];
ok(!array_diff($draw, $tabs), 'a slot has no tab: '.implode(',', array_diff($draw, $tabs)));
ok(!array_diff($tabs, $draw), 'a tab has no slot: '.implode(',', array_diff($tabs, $draw)));
ok($draw[0] === 'background', 'draw order no longer starts at background — layers will composite wrong');
printf("tabs: %d slots, %d tabs, opens on '%s', draws from '%s'\n",
	count($draw), count($tabs), $tabs[0], $draw[0]);

echo "\n".($fail ? "FAILED: $fail check(s)\n" : "all assembler checks passed\n");
exit($fail ? 1 : 0);
