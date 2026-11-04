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

/* ---------------------------------------------------------------- *
 * THE PICKER WORKS BEFORE A PROJECT IS PICKED
 * ---------------------------------------------------------------- */
echo "\nthe project picker works in the only state it is ever used in\n";
/*
 * THE BUG THIS REPLACED, AND IT SHIPPED. The select's change handler lived
 * in the page's one <script>, which sits inside the `else` that needs a
 * project -- so the control that CHOOSES a project only worked once one
 * had already been chosen. Picking the first one did nothing at all.
 *
 * I had previewed the page with a project forced in and checked the source
 * for the strings I expected. Neither touches the empty page, which is the
 * one every visit starts on.
 *
 * A FORM CANNOT BE LEFT DEAD BY WHERE A SCRIPT SITS, so the control is a
 * real GET form and the script only removes the button it makes redundant.
 */
/*
 * THE BRANCH IS GONE, AND THE PROPERTY IS STRONGER FOR IT. These three
 * assertions were anchored on `<?php if (!$out_p):` and on the form
 * sitting ABOVE it -- a structural way of saying "the picker is not
 * trapped inside the branch that already has a project". No project is now
 * a MODE rather than an empty state, so there is no branch to be trapped
 * in, and the honest test is that no such branch exists at all. Rewritten
 * rather than deleted: the bug it caught shipped once.
 */
$formAt = strpos($OUT, '<form class="adm-form" method="get" action="admin-outreach.php"');
ok($formAt !== false, 'the project picker is not a form, so it needs script to do anything');
ok(strpos($OUT, 'if (!$out_p):') === false && strpos($OUT, 'if ($out_p):') === false,
   'the page gates something on whether a project is chosen again; the picker and its '
 . 'script must render in every state, because choosing one is the only thing the empty '
 . 'page is for');
/* A control with no name submits nothing, and the page would reload to itself. */
ok(strpos($OUT, '<select name="project" id="ou-project">') !== false,
   'the select has no name attribute, so submitting the form sends no project');
/* The wiring script has to be outside that branch too. */
$wireAt = strpos($OUT, "s.addEventListener('change', function () { f.submit(); });");
ok($wireAt !== false, 'the script that submits the picker on change is gone');
/* NO SCRIPT, STILL USABLE. The button is the fallback and the script is
   what hides it, not the other way round. */
ok(strpos($OUT, '<button type="submit" class="adm-btn" id="ou-go">') !== false,
   'there is no submit button, so the picker is dead without JavaScript');
ok(strpos($OUT, 'if (g) g.hidden = true;') !== false,
   'the button is hidden in markup rather than by the script, so a no-JS visit has no '
 . 'way to submit');
/* AND IT MUST NOT NAVIGATE TWICE. The old handler assigned window.location
   on the same event the form now submits on. */
ok(substr_count($OUT, "window.location = 'admin-outreach.php?project='") === 0,
   'the old location-assigning handler is still present alongside the form, so one pick '
 . 'navigates twice');

/* ---------------------------------------------------------------- *
 * WHAT IS MENTIONED STARTS ON, AND STAYS PUT
 * ---------------------------------------------------------------- */
echo "\nthe game list starts fully ticked and survives a project change\n";
/*
 * ALL OF THEM BY DEFAULT. The whole Play list is about 1,430 characters
 * against Discord's 2,000, so there was never a budget reason to ship a
 * subset -- and taking a game OUT is a decision the operator can see the
 * cost of, where putting one back means first noticing it is missing.
 */
ok(strpos($OUT, "echo \$i < 6 ? ' checked' : ''") === false,
   'the game list still ticks only the first few by default');
ok(preg_match('~<input type="checkbox" data-g="[^"]*"\s*\n?\s*data-n="[^"]*" checked>~', $OUT) === 1,
   'the checkboxes are not all checked in the markup');

/*
 * AND THE CHOICE SURVIVES, because changing project is a full page load.
 * Two things about HOW it is stored are load-bearing, and both are the
 * kind of thing that fails silently a month later:
 *
 *   KEYED ON THE NAME. data-g is a position in a catalogue that gains and
 *   loses entries; keyed on that, adding one game shifts every stored
 *   choice onto a different game.
 *
 *   THE UNTICKED SET IS WHAT IS SAVED, not the ticked one. Everything is
 *   on by default, so a game added tomorrow is on for everybody. Store
 *   the ticked set instead and every future game is silently missing from
 *   every post, because it was absent from a list written before it
 *   existed -- the post would quietly omit the newest thing on the
 *   platform, which is the thing most worth mentioning.
 */
ok(strpos($OUT, "data-n=\"<?php echo htmlspecialchars(\$g['name'], ENT_QUOTES); ?>\"") !== false,
   'the checkbox carries no name, so a saved selection can only be keyed on position');
ok(strpos($OUT, "c.getAttribute('data-n')") !== false
   && strpos($OUT, "off.push(c.getAttribute('data-g'))") === false,
   'the saved selection is keyed on catalogue position, so adding a game moves every '
 . 'stored choice onto a different one');
ok(strpos($OUT, 'if (!c.checked) off.push(') !== false,
   'the TICKED set is saved rather than the unticked one, so a game added later is '
 . 'missing from every post and nobody notices');
ok(strpos($OUT, 'localStorage.setItem(SKIP') !== false
   && strpos($OUT, 'localStorage.getItem(SKIP') !== false,
   'the selection is not stored, so it resets on every project change');
/* localStorage THROWS in a private window or with site data blocked, and
   an admin tool must not go down over a remembered checkbox. */
ok(substr_count($OUT, 'catch (e)') >= 2,
   'a localStorage access is unguarded, so a private window takes the page down');
ok(strpos($OUT, "JSON.parse(localStorage.getItem(SKIP) || '[]') || []") !== false,
   'a missing or corrupt stored value is not defended, so one bad write breaks the list');
/* AND SAVING HAPPENS ON THE CHANGE, beside the redraw. */
ok(strpos($OUT, "addEventListener('change', function () { saveSkip(); paint(true); })") !== false,
   'the selection is redrawn but never saved, so it is lost on the next project change');

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

/* ---------------------------------------------------------------- *
 * PLATFORM-WIDE, WHEN NO PROJECT IS PICKED
 * ---------------------------------------------------------------- */
echo "\nno project picked builds the same post about the whole platform\n";

function ostat_all($v) { return array('label' => 'x', 'value' => $v, 'kind' => 'count'); }

/*
 * THE ONE MEASURE THAT IS NOT THE PER-PROJECT ONE SUMMED, AND WHY.
 *
 * outreach_stats() sums transactions.amount for one project, which is one
 * currency and therefore one number. Added across every project it is
 * CARBON plus DREAD plus DRKL plus thirty-odd others -- a figure that is
 * not a quantity of anything, printed into a marketing post with a
 * currency-shaped label on it. The platform version COUNTS payouts
 * instead. If SUM(amount) ever appears there, that reasoning has been lost
 * and the post starts making a claim nobody can check.
 */
$src_all = (string) file_get_contents(__DIR__ . '/lib/outreach-stats.php');
$fn = substr($src_all, strpos($src_all, 'function outreach_stats_all'));
$fn = substr($fn, 0, strpos($fn, "\nfunction "));
ok(strpos($fn, 'SUM(amount)') === false,
   'the platform figures sum transactions.amount across every project, which adds forty '
 . 'different currencies together and prints the total as if it were a quantity');
ok(strpos($fn, "COUNT(*) AS n FROM transactions WHERE type = 'credit'") !== false,
   'the platform figures no longer count reward payouts');
ok(strpos($fn, 'project_id') === false, 'a platform-wide query still filters on a project id');

$full = array('holders'=>ostat_all(900), 'staked'=>ostat_all(40716),
              'projects'=>ostat_all(42), 'collections'=>ostat_all(319),
              'payouts'=>ostat_all(1240318), 'missions'=>ostat_all(55000),
              'raids'=>ostat_all(812), 'realms'=>ostat_all(140));
$la = outreach_lines_all($full);
ok(count($la) === 6, 'the platform post built ' . count($la) . ' lines, not 6');
ok(strpos($la[0], '900 stakers holding 40,716 NFTs') !== false,
   'stakers and NFTs are not one sentence: ' . $la[0]);
ok(strpos($la[1], '42 projects across 319 collections') !== false,
   'the scale line is wrong: ' . $la[1]);
ok(strpos(implode(' ', $la), 'reward payouts') !== false, 'the payout count is missing');

/* A nought is an argument against turning up, so it is left out; an
   unreadable figure is left out for the opposite reason, not known true. */
$zero = $full; $zero['raids'] = ostat_all(0); $zero['realms'] = ostat_all(0);
$lz = implode(' ', outreach_lines_all($zero));
ok(strpos($lz, 'raids') === false && strpos($lz, 'Realms') === false,
   'a nought is printed into the post as "0 raids won"');
$bad = $full; $bad['payouts'] = ostat_all(null);
ok(strpos(implode(' ', outreach_lines_all($bad)), 'payouts') === false,
   'a failed read is printed as a claim');
ok(outreach_lines_all(array()) === array(), 'an empty stats set still produces lines');

/* NO CURRENCY ARGUMENT, which is the signature saying there is no platform
   currency to name -- the reason the summed-points line does not exist. */
$rf = new ReflectionFunction('outreach_lines_all');
ok($rf->getNumberOfParameters() === 1,
   'outreach_lines_all() takes a currency, which implies a platform currency that does '
 . 'not exist');

/* ---- and the page actually uses it ---- */
ok(strpos($OUT, 'outreach_stats_all($conn)') !== false,
   'the page never reads the platform figures, so no project picked is still a dead end');
ok(strpos($OUT, 'outreach_lines_all($out_stats)') !== false,
   'the page never builds the platform lines');
ok(strpos($OUT, 'Your favorite project is still live on Skulliance') !== false,
   'the platform post has no heading of its own');
/*
 * ONE BUILDER, TWO MODES. The JS composed both headings out of the project
 * name, which there is nothing to compose from platform-wide, so the
 * finished strings are passed in. A second builder would drift from the
 * first on the next copy change.
 */
ok(strpos($OUT, "'**' + P.heading + '**") !== false,
   'the post builder composes its heading from a project name again, which platform-wide '
 . 'has none');
ok(strpos($OUT, 'P.name') === false, 'the builder still reads P.name, which is not sent');
ok(substr_count($OUT, 'function build()') === 1,
   'there is a second post builder; the two will drift on the next copy change');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall outreach checks passed\n");
exit($fail ? 1 : 0);
