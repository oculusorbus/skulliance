<?php
/* arena-guide-harness.php - CLI only. No database, no network.
 *
 * The Arena's Guide button, and the Skull Paper sections behind it.
 *
 * WHAT FAILS SILENTLY HERE. The guide is a VIEW of
 * skullpaper/games-dhc-arena.md rather than a second copy of the rules,
 * which is the right call -- two pieces of text saying the same thing
 * means one of them is wrong, and here it would be the one players read
 * while playing. The cost is that the guide now depends on HEADINGS IN
 * ANOTHER FILE, and renaming one is an ordinary, reasonable edit that
 * nobody would think to check. The section just stops appearing. The page
 * still loads, the modal still opens, and the rules are simply missing.
 *
 * So: ask the real function for the real sections, and fail on any that
 * are not there.
 *
 * Usage: php arena-guide-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/doc-sections.php';
$page = file_get_contents(__DIR__ . '/dhcarena.php');
$src  = harness_strip_comments($page);

/* The sections the page asks for, READ OUT OF THE PAGE rather than typed
   here -- a list in two places is the thing this whole file is about. */
preg_match("/doc_sections\('games-dhc-arena', array\((.*?)\)\);/s", $src, $m);
$WANT = array();
if ($m) { preg_match_all("/'([^']+)'/", $m[1], $mm); $WANT = $mm[1]; }

echo "every section the guide asks for is in the Skull Paper\n";
{
	ok(count($WANT) >= 5, 'the guide asks for only ' . count($WANT) . ' sections');
	$r = doc_sections('games-dhc-arena', $WANT);
	ok(empty($r['missing']),
	   'renamed or deleted in skullpaper/games-dhc-arena.md, so the guide is missing them: '
	 . implode(', ', $r['missing']));
	ok($r['html'] !== '', 'the guide renders nothing at all');
	ok(strlen($r['html']) > 6000,
	   'the guide is only ' . strlen($r['html']) . ' bytes; a section has probably fallen out');

	/*
	 * AND EVERY SECTION CARRIES SOMETHING. A heading can survive while
	 * its body is emptied -- the section is still "found", nothing is
	 * reported missing, and the guide renders a bare heading with no
	 * rules under it. Checked one at a time, because in the whole render
	 * a hollow section is lost in the noise of the others.
	 */
	foreach ($WANT as $sec) {
		$one = doc_sections('games-dhc-arena', array($sec));
		ok(strlen($one['html']) > 300,
		   '"' . $sec . '" renders only ' . strlen($one['html']) . ' bytes -- the heading is '
		 . 'there but there is nothing under it');
	}

	/* The rules a player actually asks about mid-match. */
	foreach (array('Shield', 'Bomb', 'cascade', 'Charge') as $term) {
		ok(stripos($r['html'], $term) !== false,
		   'the guide never mentions ' . $term . ', which is a thing on the board');
	}
	/* A ## section must bring its ### subsections with it. */
	ok(stripos($r['html'], 'Match size is reach') !== false,
	   'asking for "The board" did not bring its subsections, so the reach table is gone');
}

echo "\nit is a view of the Skull Paper, not a copy of it\n";
{
	/* The rules must not be retyped into the page. If they ever are, this
	   is the moment the two start drifting. */
	ok(strpos($src, "doc_sections('games-dhc-arena'") !== false,
	   'the guide no longer renders from the Skull Paper');
	/*
	 * THE PANEL'S BODY IS THE RENDER AND NOTHING ELSE.
	 *
	 * Not "no rules text anywhere on the page" -- the entrance already
	 * carries a short hand-written "How a battle works" blurb, which
	 * predates this and is a different job: four lines to orient somebody
	 * who has never played, against the full rules for somebody mid-match.
	 * It IS a second copy of a few facts and it can drift; that is the
	 * owner's copy to keep or drop. What must not happen is the GUIDE
	 * growing hand-written rules, because then the long text drifts too
	 * and the Skull Paper stops being the source.
	 */
	ok(preg_match('~<div id="ag-body"><\?php echo \$ag\[.html.\]; \?></div>~', $page) === 1,
	   'the Guide panel contains something other than the rendered Skull Paper sections');
	ok(strpos($src, 'skullpaper.php?page=games-dhc-arena') !== false,
	   'the guide does not link to the full page for everything it leaves out');

	/* Links inside it have to be real titles, not de-hyphenated slugs. */
	$r = doc_sections('games-dhc-arena', $WANT);
	ok(strpos($r['html'], '>games monstrocity<') === false
	   && strpos($r['html'], '>games skull swap<') === false,
	   'a cross-link is labelled with its slug rather than the page title');
	ok(strpos($r['html'], '[[') === false, 'an unresolved [[wiki-link]] is showing');
	/* Screenshots are dropped: the guide opens over the thing it describes,
	   and the images arrive by FTP so a missing one is a broken frame. */
	ok(strpos($r['html'], '<img') === false, 'a screenshot is being loaded into the guide');
}

echo "\nthe button and the panel are wired\n";
{
	ok(strpos($src, 'id="aGuide"') !== false, 'there is no Guide button');
	ok(strpos($src, 'id="ag-veil"') !== false, 'there is no Guide panel');
	ok(strpos($src, "getElementById('aGuide')") !== false, 'nothing opens the Guide');

	/*
	 * GUARDED ON BOTH ELEMENTS. The panel is not printed when
	 * doc_sections() comes back empty, and a listener bound to null
	 * throws -- which takes every handler registered after it with it.
	 * That is exactly how both Rivalries buttons shipped dead.
	 */
	ok(strpos($src, 'if (!veil || !btn)') !== false,
	   'the Guide binds its listeners without checking the panel was printed');
	ok(strpos($src, "if (btn) btn.hidden = true;") !== false,
	   'with no panel the button stays visible and does nothing when pressed');

	/*
	 * SCOPED TO THE GUIDE'S OWN BLOCK. dhcarena.php binds Escape in more
	 * than one place, so a file-wide search for it passes even with the
	 * Guide's own handler deleted -- it was finding another panel's.
	 */
	$gi = strpos($src, "var veil = document.getElementById('ag-veil')");
	ok($gi !== false, 'the Guide block could not be found to check');
	$guide = $gi === false ? '' : substr($src, $gi, 1200);
	ok(strpos($guide, "e.key === 'Escape'") !== false, 'Escape does not close the Guide');
	ok(strpos($guide, 'e.target === veil') !== false,
	   'clicking anywhere inside the panel closes it, including on the text being read');
	ok(strpos($guide, 'btn.addEventListener') !== false, 'the Guide button opens nothing');
	/* hidden AND the class, or a display:none veil still traps the tab key. */
	ok(strpos($src, 'veil.hidden = true') !== false && strpos($src, 'veil.hidden = false') !== false,
	   'the panel is only hidden by a class, so it stays in the tab order');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
