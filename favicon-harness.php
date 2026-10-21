<?php
/**
 * favicon-harness.php — every page wears the Skulliance icon.
 *
 * Run: php favicon-harness.php
 *
 * The icon links were pasted into three pages and absent from every page that
 * skips header.php, which is every standalone game. That is not a bug any
 * single page's tests would catch, because each page was individually
 * consistent -- it is only visible by looking at ALL of them at once, which
 * is what this does.
 */
$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

$root = __DIR__;

echo "\nthe include emits a complete icon set\n";
/* Rendered, not grepped: it is a PHP file and the comment block at the top
   must not leak into the markup. */
ob_start(); include $root . '/lib/favicon-head.php'; $out = ob_get_clean();
ok(strpos($out, '<?php') === false && strpos($out, 'WHY AN INCLUDE') === false,
   'the include leaks its own comment block into the page head');
foreach (array('apple-touch-icon.png', 'favicon-32.png', 'favicon-16.png', 'icon-192.png') as $f)
	ok(strpos($out, $f) !== false, "the icon set is missing $f");
ok(substr_count($out, 'rel="icon"') === 3, 'the icon set does not declare all three png sizes');
ok(strpos($out, 'rel="shortcut icon"') !== false,
   'no shortcut icon, which is the one older browsers and some bookmark bars read');
/* ROOT-RELATIVE. The login cookie is host-only, so www and apex are separate
   sessions; hardcoding a host is the habit that breaks links between them. */
ok(strpos($out, 'http://') === false && strpos($out, 'https://') === false,
   'the icon hrefs name a host, so they are pinned to one of the two we serve');
ok(substr_count($out, 'href="/staking/pwa/') === 5,
   'not every icon href is root-relative under /staking/pwa/');

echo "\nthe files it points at exist in the repo\n";
/* Images deploy by FTP, not git, so a path is only verifiable against the
   live server -- but pwa/ is one of the few image dirs that IS committed.
   Checked here because a 404 icon looks identical to no icon at all. */
preg_match_all('~href="/staking/(pwa/[^"]+)"~', $out, $m);
foreach (array_unique($m[1]) as $rel)
	ok(is_file($root . '/' . $rel), "the icon file $rel is not in the repo");

echo "\nevery page a player can land on includes it\n";
/*
 * THE LIST IS EXPLICIT ON PURPOSE. A new standalone page gets no icon by
 * default -- that is exactly how seven game pages ended up blank -- so this
 * names them and a new one has to be added here deliberately.
 */
/*
 * THE VALUE IS THE INCLUDE EXPRESSION, because Drop Ship is one directory
 * down and reaches the include as ../lib/. It is a separate app with its own
 * header.php, which is precisely why it was blank: every check here keyed on
 * the root-relative spelling and could not see it.
 */
$pages = array(
	'header.php'            => '/lib/',   // every logged-in page
	'index.php'             => '/lib/',   // the public landing page
	'monstrocity.php'       => '/lib/', 'match3rpg.php'          => '/lib/',
	'skullswap.php'         => '/lib/', 'dhcgame.php'            => '/lib/',
	'skullracergame.php'    => '/lib/', 'cryptcrawlgame.php'     => '/lib/',
	'cryptconquestgame.php' => '/lib/', 'guardiansgame.php'      => '/lib/',
	'gallery.php'           => '/lib/', 'error.php'              => '/lib/',
	'dropship/header.php'   => '/../lib/',   // every Drop Ship page
);
foreach ($pages as $p => $rel) {
	$src = file_get_contents($root . '/' . $p);
	$at  = strpos($src, "__DIR__ . '" . $rel . "favicon-head.php'");
	ok($at !== false, "$p does not include the icon set, so it is blank in the tab");
	if ($at === false) continue;
	/* INSIDE THE HEAD. A <link rel=icon> after </head> is parsed into the
	   body and ignored, which looks exactly like forgetting it. */
	$hd = strpos($src, '<head>');
	$he = strpos($src, '</head>');
	ok($hd !== false && $he !== false && $at > $hd && $at < $he,
	   "$p includes the icon set outside its <head>, where it does nothing");
	ok(substr_count($src, "__DIR__ . '" . $rel . "favicon-head.php'") === 1,
	   "$p includes the icon set more than once");
}

echo "\nnobody keeps a private copy\n";
/*
 * The whole point. homepage.php is the one exception and is listed as such:
 * WordPress includes it from ABSPATH . 'staking/homepage.php' and serves it
 * at the site root, so its head is not ours to normalise.
 */
foreach ($pages as $p => $rel) {
	$src = file_get_contents($root . '/' . $p);
	ok(strpos($src, 'apple-touch-icon') === false,
	   "$p still hardcodes its own icon tags alongside the include");
}
ok(strpos(file_get_contents($root . '/homepage.php'), 'apple-touch-icon') !== false,
   'homepage.php lost its icons; it is served by WordPress from the site root and '
 . 'keeps its own head on purpose');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
