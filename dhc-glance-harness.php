<?php
/*
 * dhc-glance-harness.php — the drop count beside a game.
 *
 * The owner was opening dhcfighters.php before every session and reading
 * fourteen rows to learn one thing per row: if I play this, do I get a
 * trait. This puts that one thing in the Play menu and on the Launchpad.
 *
 * Driven against a stub connection, so the counts are real arithmetic
 * rather than a search for the right-looking markup.
 */
require_once __DIR__ . '/dhcfighters-config.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

class GlanceDb {
	public $rows;
	public function __construct($rows) { $this->rows = $rows; }
	public function query($s) { return new GlanceRes($this->rows); }
	public function real_escape_string($v) { return $v; }
}
class GlanceRes {
	private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; }
	public function fetch_assoc() { return isset($this->r[$this->i]) ? $this->r[$this->i++] : null; }
}
/* The memo is per user id, so each case needs its own. */
$uid = 100;
function glance($rows) {
	global $uid;
	return dhcf_drops_glance(new GlanceDb($rows), ++$uid);
}

echo "\nthe count is what is left, not what was taken\n";
$g = glance(array(array('source' => 'skullswap', 'c' => '1')));
ok(isset($g['skullswap.php']), 'skullswap.php is not in the glance at all');
ok($g['skullswap.php']['left'] === 2,
   'one drop taken of three leaves ' . $g['skullswap.php']['left'] . ', not 2');
ok($g['obscura.php']['left'] === 3,
   'a game with nothing taken today does not show its full cap');

$g2 = glance(array(array('source' => 'skullswap', 'c' => '3')));
ok($g2['skullswap.php']['left'] === 0, 'a spent game does not read as spent');
/* OVERSPENT READS AS ZERO, NOT NEGATIVE. A paired half or a race could
   put the count above the cap, and "-1 left" is worse than "none". */
$g3 = glance(array(array('source' => 'skullswap', 'c' => '9')));
ok($g3['skullswap.php']['left'] === 0,
   'a count above the cap produced ' . $g3['skullswap.php']['left'] . ', not 0');

echo "\nonly games with a real daily count are counted\n";
$g4 = glance(array());
/*
 * A NUMBER AGAINST THESE WOULD BE A LIE. DHC Bosses pays when the week
 * closes and Drop Ship only when you take the lead -- both carry
 * limit_note on the full table for exactly this reason.
 */
ok(!isset($g4['dhcbosses.php']),
   'DHC Bosses has a daily number beside it, and it pays when the week closes');
ok(!isset($g4['dhcportal.php']),
   'the Portal has a daily number beside it, and it pays one per jettison');
foreach ($GLOBALS['DHCF_GAMES'] as $k => $game) {
	if (empty($game['url'])) continue;
	if (!isset($g4[$game['url']])) continue;
	ok(empty($game['unlisted']), $k . ' is unlisted but appears in the glance');
	ok(empty($game['limit_note']), $k . ' has a limit_note but shows a daily number');
	ok(dhcf_cap($k) > 0, $k . ' has no daily cap but shows a daily number');
}
ok(count($g4) >= 10, 'only ' . count($g4) . ' games carry a count, which is too few to be right');

/*
 * THE TWO GUARDS ABOVE ARE UNREACHABLE WITH TODAY'S CONFIG, and that is
 * why they need injecting to test. Every unlisted game and every game
 * with a limit_note also happens to have cap 0, so the cap check catches
 * all of them and deleting either guard changes no outcome -- which is a
 * guard that cannot be shown to work. Add one game that is unlisted with
 * a real cap, and one that is capped but limit-noted, and both become
 * live.
 */
$GLOBALS['DHCF_GAMES']['harness_unlisted'] = array(
	'label' => 'x', 'url' => 'harness-unlisted.php', 'cap' => 3, 'unlisted' => true,
	'category' => 'wildcard', 'base' => 'run');
$GLOBALS['DHCF_GAMES']['harness_noted'] = array(
	'label' => 'y', 'url' => 'harness-noted.php', 'cap' => 3,
	'limit_note' => 'paid when the moon is right',
	'category' => 'wildcard', 'base' => 'run');
$g5 = glance(array());
ok(!isset($g5['harness-unlisted.php']),
   'an unlisted game with a daily cap is given a number in the menu');
ok(!isset($g5['harness-noted.php']),
   'a capped game whose real limit is not daily is given a daily number anyway');
unset($GLOBALS['DHCF_GAMES']['harness_unlisted'], $GLOBALS['DHCF_GAMES']['harness_noted']);
/* Two games share monstrocity.php as a prefix and must not collide. */
ok(isset($g4['monstrocity.php']) && isset($g4['monstrocity.php#boss']),
   'Monstrocity and Boss Battles do not both appear, so one URL has swallowed the other');

echo "\nsigned out, it says nothing at all\n";
ok(dhcf_drops_glance(new GlanceDb(array()), 0) === array(),
   'the glance returns counts for a signed-out visitor');
ok(dhcf_drops_badge(new GlanceDb(array()), 0, 'skullswap.php') === '',
   'a signed-out visitor is shown a drop count');
ok(dhcf_drops_badge(new GlanceDb(array()), 0, 'not-a-game.php') === '',
   'an unknown URL produces a badge');

echo "\nzero is drawn, because a blank means something else\n";
/*
 * An empty space beside a game is indistinguishable from a game that
 * never pays -- which is the question the whole badge exists to answer.
 */
for ($i = 0; $i <= 3; $i++) {
	ok(dhcf_drops_glyph($i) !== '', 'the glyph for ' . $i . ' is empty');
}
ok(dhcf_drops_glyph(0) !== dhcf_drops_glyph(3), 'spent and full draw the same glyph');
ok(dhcf_drops_glyph(99) === '99', 'a count past the glyph table does not fall back to digits');
ok(dhcf_drops_glyph(-1) === dhcf_drops_glyph(0),
   'a negative count does not clamp to zero, so it can index past the table');

$uid = 500;
$badge_out  = dhcf_drops_badge(new GlanceDb(array(array('source' => 'skullswap', 'c' => '3'))), 501, 'skullswap.php');
$uid = 600;
$badge_left = dhcf_drops_badge(new GlanceDb(array()), 601, 'skullswap.php');
ok(strpos($badge_out, 'out') !== false, 'a spent game is not marked out, so it cannot be dimmed');
ok(strpos($badge_left, 'out') === false, 'a game with drops left is marked out');
/* The glyph alone reads as "circled digit two" -- the sentence it
   compresses has to be there for anyone not looking at it. */
ok(strpos($badge_left, 'aria-label="3 of 3 trait drops left today"') !== false,
   'the badge carries no accessible label, so it is a bare symbol to a screen reader');
ok(strpos($badge_out, 'aria-label="no trait drops left today"') !== false,
   'a spent badge does not say so in words');

echo "\nit is wired in, and it cannot break a page\n";
$hdr = (string) file_get_contents(__DIR__ . '/header.php');
$lp  = (string) file_get_contents(__DIR__ . '/launchpad.php');
ok(substr_count($hdr, 'dhcf_drops_badge($conn, $sk_glance_uid,') >= 10,
   'the Play menu carries ' . substr_count($hdr, 'dhcf_drops_badge($conn, $sk_glance_uid,')
 . ' badges, which is too few for the games that pay daily');
ok(strpos($lp, 'dhcf_drops_badge($conn, $lp_glance_uid') !== false,
   'the Launchpad tiles carry no badge');
/*
 * THE NAVBAR DRAWS ON SIXTY-THREE PAGES and must never be the reason one
 * of them does not. Both readers define a no-op fallback, so a page that
 * has never heard of DHC echoes an empty string rather than fataling on
 * an undefined function.
 */
foreach (array('header.php' => $hdr, 'launchpad.php' => $lp) as $f => $src) {
	ok(strpos($src, "if (!function_exists('dhcf_drops_badge')) {") !== false,
	   $f . ' has no fallback for dhcf_drops_badge(), so a page without the DHC library fatals');
}
/* Right-aligned, or it is a dozen things to look at rather than a list. */
$css = (string) file_get_contents(__DIR__ . '/dist/flexbox.css');
ok(strpos($css, '.nav-dropdown-menu a .dhcf-glance') !== false
   && preg_match('~\.nav-dropdown-menu a \.dhcf-glance \{[^}]*margin-left: auto~s', $css) === 1,
   'the menu badge is not pushed to the right edge, so the counts do not line up');
ok(preg_match('~\.nav-dropdown-menu a \{[^}]*display: flex~s', $css) === 1,
   'the menu anchors are not flex, so margin-left:auto does nothing');

/*
 * A ROW'S HEIGHT MUST NOT DEPEND ON WHETHER IT HAS A BADGE.
 *
 * It did. The badge was set at 1rem against a smaller label, so the row
 * grew to its tallest inline box -- a menu of two different row heights,
 * taller overall, long enough to need scrolling for something that used
 * to be in view. Reported as the menu growing for no reason.
 *
 * Two things fix it together and both are asserted: the anchor fixes its
 * own line-height, and the badge inherits rather than setting its own.
 */
ok(preg_match('~\.nav-dropdown-menu a \{[^}]*line-height: [\d.]+~s', $css) === 1,
   'the menu anchors have no fixed line-height, so a tall glyph grows the row it is in');
preg_match('~\.nav-dropdown-menu a \.dhcf-glance \{([^}]*)\}~s', $css, $m_bg);
$bg = count($m_bg) === 2 ? $m_bg[1] : '';
ok($bg !== '', 'the menu badge rule could not be found');
/* CAPTURED AND COMPARED, not matched with a negative lookahead: `\s*`
   backtracks to zero width, the lookahead then sees " inherit" rather
   than "inherit", and the check reported correct CSS as broken. */
$glance_prop = function ($block, $prop) {
	return preg_match('~' . $prop . ':\s*([a-z0-9.%-]+)~', $block, $m) ? $m[1] : '(unset)';
};
/*
 * AN EXPLICIT HEIGHT IS WHAT BOUNDS THE ROW, and it is the only thing
 * that does. The glyph may be drawn by a face this stylesheet never
 * names -- font fallback ignores font-family -- and that face's line box
 * is what grew the row. A box with its own height has a line box the
 * glyph cannot push.
 *
 * Two earlier attempts are recorded in the failures below because both
 * looked right: inheriting font-size (does nothing to a fallback's
 * metrics) and drawing the ring in CSS (correct on height, came out an
 * ellipse).
 */
ok($bg !== '' && preg_match('~height:\s*(\d+)px~', $bg, $m_h) === 1 && (int)$m_h[1] <= 20,
   'the menu badge has no fixed pixel height under 20px, so a substituted face can grow the row');
ok($bg !== '' && preg_match('~font-size:\s*(\d+)px~', $bg, $m_f) === 1 && (int)$m_f[1] <= 20,
   'the menu badge has no pixel font-size, so the glyph is drawn at whatever the row inherits');
ok($bg !== '' && strpos($bg, 'overflow: hidden') !== false,
   'nothing backstops a face with stranger metrics, so the glyph can spill out of its box');
ok($bg !== '' && strpos($bg, 'flex: 0 0 auto') !== false,
   'the badge can be stretched by the flex row, so its height is not actually fixed');
/*
 * THE ACTUAL BUG, AND IT WAS NEVER THE FONT.
 *
 * `.navbar span` sets `padding: 14px 20px` on EVERY span in the navbar,
 * for the trigger labels. 28px of vertical padding and 40px of
 * horizontal, inherited by the count: that is the rows that grew, the
 * ring that came out an ellipse when it was drawn in CSS, and every
 * height this rule declared appearing to be ignored. Measured in
 * headless Chrome -- a badged row was 53px against 44px, and the badge's
 * computed height was 28px while this rule asked for 16px.
 *
 * Three symptoms, one inherited rule, and both earlier fixes were aimed
 * at font metrics that had nothing to do with it.
 */
ok($bg !== '' && preg_match('~(^|[^-])padding:\s*0~', $bg) === 1,
   'the count does not zero its padding, so .navbar span gives it 14px 20px and the row grows again');
/* NO WIDTH. One was set when the ring was drawn here and the box came
   out wider than asked, so the ring was an ellipse. The glyph brings its
   own circle; nothing in this rule has to be round. */
ok($bg !== '' && preg_match('~[^-]width:~', $bg) !== 1,
   'the menu badge sets a width again, which is what made the ring an ellipse');

/* The Launchpad tile carries the same badge and the same risk. */
$lpc = (string) file_get_contents(__DIR__ . '/launchpad.php');
preg_match('~\.lp-tile-name \.dhcf-glance \{([^}]*)\}~s', $lpc, $m_tg);
$tg = count($m_tg) === 2 ? $m_tg[1] : '';
ok($tg !== '', 'the tile badge rule could not be found');
ok($tg !== '' && preg_match('~height:\s*(\d+)px~', $tg, $m_th) === 1 && (int)$m_th[1] <= 20,
   'the tile badge has no fixed pixel height under 20px, so a tile with a count is taller than one without');
ok($tg !== '' && strpos($tg, 'overflow: hidden') !== false,
   'the tile badge has no overflow backstop, so a tall face can spill');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
