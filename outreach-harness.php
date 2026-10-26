<?php
/**
 * outreach-harness.php — the post that goes into somebody else's Discord.
 *
 * Run: php outreach-harness.php
 *
 * WHY THE BAR IS HIGHER HERE THAN ON AN ADMIN PAGE. This text is pasted
 * into a partner's own chat with their project's name on it, and the
 * readers are the people best placed to notice a wrong figure. A number
 * that is quietly wrong costs more than a page that is visibly broken.
 *
 * So the two things this proves are: a failed read is never printed as a
 * nought, and a nought is never printed at all.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/outreach-stats.php';

/* A connection that answers what it is told to and records what it was
   asked, so the SQL can be read back as well as the result. */
class ORes {
	public $num_rows; private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return isset($this->r[$this->i]) ? $this->r[$this->i++] : null; }
}
class OConn {
	public $log = array();
	public $answers = array();   // substring => row, or false to fail the read
	public function real_escape_string($s) { return addslashes((string)$s); }
	public function query($sql) {
		$q = preg_replace('~\s+~', ' ', trim($sql));
		$this->log[] = $q;
		foreach ($this->answers as $needle => $row) {
			if (stripos($q, $needle) !== false) return $row === false ? false : new ORes(array($row));
		}
		return new ORes(array());
	}
}

/* ---------------------------------------------------------------- *
 * A FAILED READ IS NOT A ZERO
 * ---------------------------------------------------------------- */
echo "a number nobody could read is never printed as a nought\n";
/*
 * THE FAILURE MODE THIS WHOLE SHAPE EXISTS FOR. `return $n ? $n : 0` on a
 * broken query posts "0 missions completed" to a community that completed
 * six hundred -- and nothing errors. The platform has already been bitten
 * twice by a fallback indistinguishable from a legitimate empty state.
 */
$c = new OConn();
$c->answers = array('FROM missions' => false);        // the read fails
ok(outreach_num($c, "SELECT SUM(x) AS n FROM missions", 'n') === null,
   'a failed query comes back as a number rather than as null');
$c2 = new OConn();
$c2->answers = array('FROM missions' => array('n' => null));   // worked, found nothing
ok(outreach_num($c2, "SELECT SUM(x) AS n FROM missions", 'n') === 0.0,
   'a SUM over no rows comes back as null, which would be reported as a broken read');
$c3 = new OConn();
$c3->answers = array('FROM missions' => array('n' => '612'));
ok(outreach_num($c3, "SELECT SUM(x) AS n FROM missions", 'n') === 612.0,
   'a real figure does not survive the read');
/* A row that came back without the column is a changed schema, not a zero. */
$c4 = new OConn();
$c4->answers = array('FROM missions' => array('other' => 1));
ok(outreach_num($c4, "SELECT SUM(x) AS n FROM missions", 'n') === null,
   'a missing column reads as a value instead of a failure');

/* ---------------------------------------------------------------- *
 * EVERY QUERY IS THE ONE THE PLATFORM ALREADY USES
 * ---------------------------------------------------------------- */
echo "\nthe figures are counted the way the rest of the platform counts them\n";
$cs = new OConn();
outreach_stats($cs, 42);
$all = implode(' || ', $cs->log);
/*
 * These are not style checks. Each one is a DIFFERENT wrong number that
 * looks perfectly plausible on the page:
 *   no user_id filter   -> every NFT ever indexed, staked or not
 *   type <> 'credit'    -> spending counted as earning
 *   status <> '1'       -> failed missions counted as completed
 *   defense_id          -> raids the faction SUFFERED, reported as won
 */
ok(strpos($all, 'n.user_id <> 0') !== false,
   'the staked count does not filter on user_id, so it counts unstaked NFTs too -- '
 . 'nfts.user_id is 0 when unstaked');
ok(strpos($all, "type = 'credit'") !== false,
   'the points total is not filtered to credits, so spending is counted as earning');
ok(strpos($all, "m.status = '1'") !== false,
   'missions are counted regardless of outcome, so failures read as completions');
ok(strpos($all, 'rl.id = r.offense_id') !== false,
   'raids are joined on the wrong side: defense_id would report the raids the faction '
 . 'suffered as raids it won');
ok(strpos($all, "r.outcome = '1'") !== false, 'lost raids are counted as won');
ok(strpos($all, 'rl.project_id = 42') !== false,
   'the faction is not scoped to the project, so every raid on the platform is claimed');
/* THE PROJECT ID IS AN INT IN EVERY ONE. It is interpolated, so a string
   reaching any of these is an injection on an admin page. */
ok(substr_count($all, '= 42') + substr_count($all, '= 42 ') >= 4,
   'the project id does not reach every query');
$bad = new OConn();
outreach_stats($bad, "7 OR 1=1");
ok(strpos(implode(' ', $bad->log), 'OR 1=1') === false,
   'the project id is interpolated without a cast, so it is an injection');

/* ---------------------------------------------------------------- *
 * WHAT THE POST ACTUALLY SAYS
 * ---------------------------------------------------------------- */
echo "\na nought is left out, and so is anything nobody could read\n";
function ostat($v) { return array('label' => 'x', 'value' => $v, 'kind' => 'count'); }
$full = array(
	'holders' => ostat(128), 'staked' => ostat(1904), 'distributed' => ostat(4210500),
	'missions' => ostat(612), 'raids' => ostat(341), 'realms' => ostat(57),
);
$lines = outreach_lines($full, 'DRKL');
ok(count($lines) === 5, 'five lines expected from six stats (holders and NFTs share one): '
                      . count($lines));
ok($lines[0] === '128 holders staking 1,904 NFTs',
   'the holder and NFT counts are not one sentence: ' . $lines[0]);
ok(in_array('4,210,500 DRKL distributed', $lines, true),
   'the points line does not name the project currency');

/*
 * A NOUGHT IS AN ARGUMENT AGAINST TURNING UP. "0 raids won by the faction"
 * in a recruitment post is worse than silence, and a project that never
 * had a faction never lost one -- the absence is not a result.
 */
$none = $full;
$none['raids'] = ostat(0); $none['realms'] = ostat(0);
$l2 = outreach_lines($none, 'DRKL');
ok(count($l2) === 3, 'a nought reached the post: ' . implode(' / ', $l2));
foreach ($l2 as $l) ok(strpos($l, '0 ') !== 0, 'a line opens with a nought: ' . $l);
/* And an unreadable figure is left out for the opposite reason: it is not
   known to be true. */
$broken = $full;
$broken['missions'] = ostat(null);
$l3 = outreach_lines($broken, 'DRKL');
ok(count($l3) === 4 && !in_array('612 missions completed', $l3, true),
   'a figure that could not be read was claimed anyway');
/* Either half of the holders sentence stands alone. */
ok(outreach_lines(array('holders' => ostat(9), 'staked' => ostat(0)), 'X')
   === array('9 holders staking'), 'the holder line does not stand without the NFT count');
ok(outreach_lines(array('holders' => ostat(0), 'staked' => ostat(40)), 'X')
   === array('40 NFTs staked'), 'the NFT line does not stand without the holder count');
ok(outreach_lines(array(), 'X') === array(),
   'a project with nothing recorded still produces lines');

/* ---------------------------------------------------------------- *
 * THE CATALOGUE HAS ONE HOME
 * ---------------------------------------------------------------- */
echo "\nthe list of games is not written down twice\n";
/*
 * The outreach post tells a community what is on the platform now. A
 * hand-written second copy would be wrong within a month, and wrong in the
 * direction that matters: a post that omits the game somebody would have
 * come back for. Same reason admin_tabs() exists.
 */
require_once __DIR__ . '/lib/launchpad-catalogue.php';
ok(isset($lp_sections['Play']['items']) && count($lp_sections['Play']['items']) > 5,
   'the shared catalogue has no Play list');
$LP  = file_get_contents(__DIR__ . '/launchpad.php');
$OUT = harness_strip_comments(file_get_contents(__DIR__ . '/admin-outreach.php'));
ok(strpos($LP, '$lp_sections = array(') === false,
   'launchpad.php has its own copy of the catalogue again, so the outreach post and the '
 . 'Launchpad can list different platforms');
ok(strpos($LP, "require_once __DIR__ . '/lib/launchpad-catalogue.php'") !== false,
   'launchpad.php no longer reads the shared catalogue');
ok(strpos($OUT, "require_once __DIR__ . '/lib/launchpad-catalogue.php'") !== false,
   'the outreach tool does not read the shared catalogue');
/* AND IT MUST NOT PULL IN THE PAGE. The first cut included launchpad.php
   with output buffered, which re-runs db.php, verify.php and header.php
   inside a page that has already drawn its own. */
ok(strpos($OUT, "include __DIR__ . '/launchpad.php'") === false
   && strpos($OUT, "include 'launchpad.php'") === false,
   'the outreach tool includes the whole Launchpad page to read one array');
/* STRIPPED. The file's own header explains that it pulls in no db.php and
   no header.php, and a bare search found that sentence -- the third time
   this session a check has matched the prose describing the rule instead
   of the code obeying it. */
$cat = harness_strip_comments(file_get_contents(__DIR__ . '/lib/launchpad-catalogue.php'));
foreach (array('db.php', 'header.php', 'skulliance.php', 'verify.php') as $dep)
	ok(strpos($cat, $dep) === false,
	   'the shared catalogue pulls in ' . $dep . ', so it cannot be required by a page '
	 . 'that has already drawn its header');

/* ---------------------------------------------------------------- *
 * THE PAGE SENDS NOTHING
 * ---------------------------------------------------------------- */
echo "\nnothing can leave this page by accident\n";
/*
 * It builds text for a human to read, edit and paste. A webhook here would
 * be a button that posts into somebody else's Discord, which is not a
 * thing this tool should be one misclick away from.
 */
ok(strpos($OUT, 'admin_require()') !== false, 'the tool is not behind the admin gate');
foreach (array('discordmsg', 'curl_exec', 'file_get_contents(\'http', 'webhooks.php', '$_POST')
         as $send)
	ok(strpos($OUT, $send) === false,
	   'the outreach tool reaches for ' . $send . ' -- it must build text and send nothing');
ok(strpos($OUT, 'outreach_stats(') !== false, 'the page does not read the project figures');
ok(strpos($OUT, 'outreach_lines(') !== false,
   'the page builds its own lines instead of the ones the library filters');

/* THE LIMIT IS REAL AND STATED. Discord refuses a non-Nitro message over
   2,000 characters, and a post that is silently too long is pasted, 
   rejected, and re-written by hand. */
ok(strpos($OUT, '2000') !== false, 'nothing enforces or shows the 2,000-character limit');

/* AND THE WHOLE THING FITS, with every game ticked -- measured, not hoped.
   Built here the way the page builds it. */
$games = array();
foreach ($lp_sections['Play']['items'] as $it)
	if (is_array($it) && count($it) >= 4) $games[] = $it;
$body = "**Dread City is still live on Skulliance**\n\n"
      . "Your DRKL has been earning this whole time, and the platform around it has grown "
      . "a long way past staking.\n\n**What Dread City has done here so far**\n";
foreach ($lines as $l) $body .= '• ' . $l . "\n";
$body .= "\n**What is here now**\n";
foreach ($games as $g) $body .= $g[1] . '  **' . $g[2] . '** — ' . $g[3] . "\n";
$body .= "\nNothing to set up again. Sign in with the same wallet and everything is where "
       . "you left it.\n\nhttps://www.skulliance.io/staking/";
ok(mb_strlen($body) < 2000,
   'the post does not fit a non-Nitro message with every game ticked: ' . mb_strlen($body));
echo "  measured: " . mb_strlen($body) . " characters with all " . count($games)
   . " games listed\n";

/* AND THE TWO TOOLS CHAIN. They are one job in two halves -- the words
   and the image for the same Discord post -- so the project carries from
   one to the other rather than being picked twice. */
$FLY = harness_strip_comments(file_get_contents(__DIR__ . '/admin-flyers.php'));
ok(strpos($OUT, 'admin-flyers.php?project=') !== false,
   'the outreach tool links the flyer without naming the project, so it has to be chosen '
 . 'again -- which is a way to pick the wrong one');
ok(strpos($FLY, "/[?&]project=(\\d+)/") !== false,
   'the flyer tool ignores ?project=, so the link from Outreach arrives empty');
ok(strpos($FLY, "dispatchEvent(new Event('change'))") !== false,
   'the flyer tool sets the select without firing its handler, so the name, ticker and '
 . 'icon never fill in');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall outreach checks passed\n");
exit($fail ? 1 : 0);
