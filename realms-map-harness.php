<?php
/* realms-map-harness.php — CLI only. Static. No database.
 *
 * THE PUBLIC MAP, AND THE ONE LINE THAT WOULD SILENTLY UNDO IT.
 *
 * realms-map.php and ajax/realm-dossier.php exist to answer without a
 * login. Including skulliance.php or verify.php turns either one into a
 * redirect to Discord, and nothing about that failure looks like a bug
 * from the inside: the owner is signed in, so the page works perfectly
 * for everybody who could notice. skullpaper.php carries the same rule
 * as a comment; this is the same rule with a test under it.
 *
 * AND THE CARD IS A CONTRACT BETWEEN TWO FILES. map.js reads card.X out
 * of JSON that realm_dossier() builds in PHP. Rename a key on one side
 * and the card quietly loses a row -- no error, no warning, just a
 * briefing that is missing its garrison. So the keys the JavaScript
 * reads are collected from the source and checked against the array the
 * PHP returns, rather than both being listed here and agreeing with
 * each other instead of with the code.
 */
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$map   = (string) file_get_contents(__DIR__ . '/realms-map.php');
$dos   = (string) file_get_contents(__DIR__ . '/ajax/realm-dossier.php');
$lib   = (string) file_get_contents(__DIR__ . '/realms-lib.php');
$js    = (string) file_get_contents(__DIR__ . '/map.js');
$hdr   = (string) file_get_contents(__DIR__ . '/header.php');
$realm = (string) file_get_contents(__DIR__ . '/realms.php');
$css   = (string) file_get_contents(__DIR__ . '/dist/map.css');

/* Comments name every one of these files; an assertion that matched the
   prose would pass on a page that actually includes the gate. */
$strip = function ($s) {
	$s = preg_replace('~/\*.*?\*/~s', '', $s);
	return preg_replace('~^\s*(//|\#).*$~m', '', $s);
};
$mapCode = $strip($map);
$dosCode = $strip($dos);
$jsCode  = preg_replace('~/\*.*?\*/~s', '', $js);

echo "the public map answers without a login\n";
foreach (array('realms-map.php' => $mapCode, 'ajax/realm-dossier.php' => $dosCode) as $what => $code) {
	ok(strpos($code, 'skulliance.php') === false,
	   "$what includes skulliance.php, so every stranger is redirected to Discord");
	ok(strpos($code, 'verify.php') === false,
	   "$what includes verify.php, which gates it the same way");
}
ok(strpos($mapCode, "include_once 'db.php'") !== false,
   'realms-map.php does not include db.php, so it has no connection to draw from');
ok(preg_match('~\$name\s*=~', $mapCode) === 1,
   'realms-map.php never sets $name, so the header cannot tell a member from a stranger');
ok(strpos($mapCode, 'isset($_SESSION[\'userData\'])') !== false,
   'realms-map.php sets $name unconditionally, which draws a member navbar with nobody in it');

echo "\nthe stylesheet gate lets the public page through\n";
/*
 * RUN THE CONDITION, do not read it. This gate has already shipped
 * broken once -- it compared against the whole REQUEST_URI, so any query
 * string dropped map.css and the page rendered unstyled.
 */
preg_match('~\$sk_path = (basename\(parse_url\([^;]+\));~', $hdr, $m);
ok(!empty($m), 'header.php no longer derives the path with basename(parse_url(...))');
if (!empty($m)) {
	/* The WHOLE condition, not the half after the first ===: capturing
	   from there leaves a bare "realms.php" at the front, which is
	   truthy, and the reconstructed test then passes for every page on
	   the platform. */
	preg_match('~if \((\$sk_path ===.*?)\) \{~s', $hdr, $c);
	ok(!empty($c), 'header.php no longer gates map.css on $sk_path');
	$cond = !empty($c) ? $c[1] : 'false';
	$check = function ($uri) use ($m, $cond) {
		$_SERVER['REQUEST_URI'] = $uri;
		$sk_path = eval('return ' . $m[1] . ';');
		return (bool) eval('return ' . $cond . ';');
	};
	ok($check('/staking/realms-map.php'), 'the public map gets no map.css, so it renders unstyled');
	ok($check('/staking/realms-map.php?x=1'), 'a query string drops map.css on the public map');
	ok($check('/staking/realms.php'), 'realms.php lost its map.css');
	ok($check('/staking/realms.php?preview=newcomer'), 'the newcomer preview lost its map.css');
	ok(!$check('/staking/missions.php'), 'map.css is being loaded on pages that have no map');
}

echo "\nthe map scripts load in the one order that works\n";
/* ON THE src ATTRIBUTES, NOT THE FILE NAMES. The comment above these
   tags explains the order and names all three scripts, so a search for
   "map-world.js" finds the prose and passes on a page that no longer
   loads it. */
$kit = strpos($map, 'src="map-kit.js');
$wor = strpos($map, 'src="map-world.js');
$mjs = strpos($map, 'src="map.js');
ok($kit !== false && $wor !== false && $mjs !== false, 'realms-map.php is missing one of the three map scripts');
ok($kit < $wor && $wor < $mjs,
   'map-kit.js and map-world.js must both load before map.js, or it dies at parse time');

echo "\nthe card has somewhere to render on both pages\n";
/* Every id map.js looks up, taken from the source rather than listed. */
preg_match_all('~getElementById\(\s*[\'"]([a-z0-9\-]+)[\'"]\s*\)~i', $jsCode, $ids);
$want = array_values(array_unique($ids[1]));
$popup = array_values(array_filter($want, function ($id) { return strpos($id, 'popup') === 0; }));
ok(count($popup) >= 6, 'map.js looks up fewer popup ids than expected (' . count($popup) . ')');
foreach ($popup as $id) {
	ok(strpos($map, 'id="' . $id . '"') !== false,
	   "realms-map.php has no #$id, so the card cannot fill it");
	ok(strpos($realm, 'id="' . $id . '"') !== false,
	   "realms.php has no #$id, so the card cannot fill it");
}

echo "\nthe card's keys mean the same thing on both sides of the fetch\n";
/*
 * THE CONTRACT. renderDossier() reads card.<key>; realm_dossier()
 * returns '<key>' => ... . Collected from each side, so renaming one
 * without the other fails here instead of quietly dropping a row.
 */
$rd = substr($jsCode, strpos($jsCode, 'function renderDossier('));
preg_match_all('~\bcard\.([a-z_]+)~i', $rd, $k);
$read = array_values(array_unique($k[1]));
ok(count($read) >= 6, 'renderDossier reads suspiciously few card fields (' . count($read) . ')');
$ret = substr($lib, strpos($lib, 'function realm_dossier('));
foreach ($read as $key) {
	ok(preg_match("~'" . preg_quote($key, '~') . "'\s*=>~", $ret) === 1,
	   "map.js reads card.$key and realm_dossier() never returns it");
}
/* And the nested ones the card walks into. */
foreach (array('ready', 'training') as $key) {
	ok(preg_match("~'" . $key . "'\s*=>~", $ret) === 1, "the garrison has no '$key'");
}
foreach (array('active', 'recent') as $key) {
	ok(preg_match("~'" . $key . "'\s*=>~", $ret) === 1, "the raid list has no '$key'");
}
foreach (array('won', 'lost', 'held', 'fell') as $key) {
	ok(preg_match("~'" . $key . "'\s*=>~", $ret) === 1, "the record has no '$key'");
	ok(strpos($rd, "rd-res-" . $key) !== false || in_array($key, array('lost', 'fell'), true)
	   || strpos($rd, $key) !== false, "the card never renders a '$key' result");
}

echo "\nthe endpoint takes an id and nothing else\n";
ok(preg_match('~intval\(\$_GET\[[\'"]realm_id[\'"]\]\)~', $dosCode) === 1,
   'ajax/realm-dossier.php does not intval the realm id it is handed');
ok(preg_match('~\$realm_id\s*=\s*intval~', $strip($lib)) === 1,
   'realm_dossier() does not intval its own argument, so a caller can hand it anything');
ok(strpos($dosCode, 'json_encode') !== false, 'the endpoint answers something other than JSON');

echo "\nthe territory watermark is gone\n";
ok(strpos($jsCode, 'LOGO_MAX') === false,
   'the faction logo is still drawn across each territory, over the relief');
/* The label pill keeps its icon: that is where the faction is named. */
ok(substr_count($jsCode, "'icons/' + faction.currency") === 1,
   'the faction icon should appear once, in the label pill');

echo "\nthe signup bar tells the truth and is not the old slab\n";
/*
 * THE CLAIM HAS ALREADY BEEN WRONG ONCE. It read "you are on this map
 * from the first week", which getFactionsRealmsMapData() contradicts:
 * the map draws realms that have raided inside REALM_MAP_ACTIVE_DAYS,
 * so a realm claimed today and left alone is not on it. A newcomer
 * told otherwise goes looking for themselves and is not there.
 */
/* $mapCode, not $map: the comment above the bar quotes the wrong
   copy verbatim to explain why it went, so a search of the raw file
   finds the explanation and fails on a page that is already fixed. */
ok(stripos($mapCode, 'from the first week') === false,
   'realms-map.php still promises a newcomer the map in their first week, which the query does not');
ok(strpos($mapCode, 'REALM_MAP_ACTIVE_DAYS') !== false,
   'the signup copy does not cite REALM_MAP_ACTIVE_DAYS, so it can drift from the filter it describes');
ok(preg_match('~\b(30|thirty)\s*day~i', $mapCode) === 0,
   'the signup copy hardcodes the window instead of echoing the constant');
/* The global .button is width:100%, font-size:2rem and a drop shadow --
   the old platform slab the bar was called out for wearing. */
ok(preg_match('~class="button"~', $mapCode) === 0,
   'realms-map.php uses the global .button, which is the old 2rem full-width slab');
ok(strpos($mapCode, 'class="rm-go"') !== false, 'the signup bar has no call to action');
ok(preg_match('~\.rm-go\s*[,{]~', $css) === 1, 'dist/map.css has no rule for .rm-go');
ok(preg_match('~\.rm-go\s*\{[^}]*text-decoration~s', $css) === 1,
   '.rm-go does not restate text-decoration, so flexbox.css underlines it as a link');
ok(preg_match('~\.rm-go\s*\{[^}]*color~s', $css) === 1,
   '.rm-go does not restate colour, so it inherits the global link blue');
/* .main carries text-align:center in flexbox.css, so prose dropped into
   it centres itself. Every scoped text block here must say otherwise. */
ok(preg_match('~\.rm-join-copy\s*\{[^}]*text-align\s*:\s*left~s', $css) === 1,
   '.rm-join-copy does not set text-align, so .main centres the signup copy');

echo "\nthe dossier is styled\n";
/* The selector itself, not a prefix of a longer one: a plain strpos for
   ".rd-chip" is satisfied by ".rd-chip-x", so renaming the rule passes. */
foreach (array('#popup-dossier', '.rd-chip', '.rd-list', '.rd-shielded') as $sel) {
	ok(preg_match('~' . preg_quote($sel, '~') . '\s*[,{]~', $css) === 1,
	   "dist/map.css has no rule for $sel");
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
