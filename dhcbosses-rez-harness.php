<?php
/*
 * dhcbosses-rez-harness.php — the crew resurrection.
 *
 * dhcb_rez() needs a database, so the REFUSALS are executed against a
 * stand-in connection that fails every query: every path that must not
 * resurrect has to decide so BEFORE it reaches the database, and that is
 * a property worth having rather than an accident. The paths that do
 * resurrect are pinned structurally, with the comments stripped first.
 *
 * See dhcbosses-rez-design.md for why this exists at all.
 */
require_once __DIR__ . '/dhcbosses-lib.php';

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

/** A connection that answers nothing. Reaching it is the failure. */
class RezNoDb {
	public $touched = 0;
	public function query($sql) { $this->touched++; return false; }
	public function real_escape_string($s) { return $s; }
}

/** A battle in the shape dhcb_rez() reads. */
function rezb($opts = array()) {
	$foes = array();
	foreach (dhcb_bosses() as $i => $bz) {
		$foes[] = array('bossKey' => $bz['key'], 'name' => $bz['name'],
		                'hp' => isset($opts['foeHp']) ? $opts['foeHp'] : 0,
		                'maxHp' => 1000, 'hp0' => 1000, 'ko' => true);
	}
	if (isset($opts['oneUp'])) $foes[0]['hp'] = 500;
	$mine = array(array('ko' => isset($opts['allDown'])), array('ko' => true));
	return array(
		'foes' => $foes, 'mine' => $mine,
		'over' => isset($opts['over']) ? $opts['over'] : null,
		'turn' => 'foes', 'round' => 3, 'fx' => array(), 'log' => array(),
		'meta' => array('run_id' => isset($opts['noRun']) ? 0 : 77,
		                'attacker' => 5, 'day' => '2026-11-09',
		                'wave' => isset($opts['wave']) ? $opts['wave'] : 1,
		                'rez' => isset($opts['rez']) ? $opts['rez'] : 0,
		                'sent' => array('a' => 400, 'b' => 100),
		                'banked' => isset($opts['banked']) ? $opts['banked'] : 0),
	);
}

echo "\nit refuses before it ever reaches the database\n";
/*
 * EVERY ONE OF THESE IS A RUN THAT MUST END, NOT CONTINUE, and each must
 * be decided without a query -- a refusal that costs a round trip is a
 * refusal that runs on every move of every run on the platform.
 */
$cases = array(
	'the crew is down too, which is a loss and the one honest end to a run'
		=> array('allDown' => 1, 'over' => 'foes'),
	'every Fighter is KO, so there is nobody left to fight the next wave'
		=> array('allDown' => 1),
	'a boss is still standing, so this is not a cleared wave'
		=> array('oneUp' => 1),
	'the chain cap is spent'
		=> array('rez' => DHCB_REZ_MAX),
	'there is no run to resurrect into'
		=> array('noRun' => 1),
	/* A LOSS, CHECKED IN ITS OWN RIGHT. In practice over === 'foes' only
	   happens when every Fighter is already KO, so the standing-crew guard
	   below catches this too -- and did, which is why deleting this check
	   passed. Asserted separately so the explicit refusal stays explicit:
	   a verdict against the player must never be overturned by a feature
	   whose whole job is to overturn verdicts. */
	'the battle was already lost'
		=> array('over' => 'foes', 'standingAnyway' => 1),
);
foreach ($cases as $why => $opt) {
	$db = new RezNoDb();
	$b  = rezb($opt);
	/* allDown marks only the first Fighter; the fixture's second is always KO. */
	if (isset($opt['allDown'])) $b['mine'][0]['ko'] = true;
	if (isset($opt['standingAnyway'])) $b['mine'][0]['ko'] = false;
	$got = dhcb_rez($db, $b);
	ok($got === false, "dhcb_rez() resurrected when $why");
	ok($db->touched === 0, "dhcb_rez() queried the database before deciding that $why");
	ok(empty($b['fx']), "dhcb_rez() emitted a rez event when $why");
}

echo "\na refusal leaves the battle exactly as it found it\n";
$db = new RezNoDb(); $b = rezb(array('oneUp' => 1)); $was = $b;
dhcb_rez($db, $b);
ok($b === $was, 'a refused resurrection still mutated the battle');

echo "\nthe cap is a cap\n";
ok(DHCB_REZ_MAX >= 1, 'the chain cap is below one, so the feature can never fire');
ok(DHCB_REZ_MAX <= 3,
   'the chain cap is ' . DHCB_REZ_MAX . ', deep enough to push the server into '
   . round(dhcb_wave_pow(1 + DHCB_REZ_MAX), 2) . 'x-power bosses off one run');

echo "\nthe pieces the design depends on\n";
$lib = '';
foreach (token_get_all((string) file_get_contents(__DIR__ . '/dhcbosses-lib.php')) as $t) {
	if (is_array($t)) { if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) continue; $lib .= $t[1]; }
	else $lib .= $t;
}
ok(strlen($lib) > 1000, 'the library could not be read, so the checks below pass on nothing');

/* THE LEDGER IS BANKED ON RESURRECTION. meta.sent is keyed by boss_key and
   boss_key repeats every wave, so without this the new wave's ledger lands
   on the old one's and a chained run reports only its last segment. */
/*
 * SCOPED TO dhcb_rez()'s OWN BODY. The reset of meta.sent is spelled
 * identically inside dhcb_commit(), which initialises it -- so a check
 * against the whole file passed with the reset deleted from the
 * resurrection, matching the other function's line instead.
 */
$rez_body = '';
if (preg_match('~function dhcb_rez\(\$conn, &\$b\) \{(.*?)\n\}~s', $lib, $m_rz)) $rez_body = $m_rz[1];
ok($rez_body !== '' && strlen($rez_body) > 800,
   'dhcb_rez() could not be lifted, so the checks below are reading nothing');

ok(strpos($rez_body, "\$b['meta']['banked'] = \$banked") !== false,
   'the ledger is not banked when the wave turns, so each segment overwrites the last');
ok(strpos($rez_body, "\$b['meta']['sent']   = array()") !== false,
   'meta.sent is not reset on resurrection, so the heal cap reads the previous wave');
ok(preg_match("~\\\$total\\s*\\+=\\s*isset\\(\\\$m\\['banked'\\]\\)~", $lib) === 1,
   'the settle ignores banked segments, so a chained run reports only the wave it died on');

/* THE COMMIT FOLLOWS THE CHAIN. dhcb_commit() targets WHERE wave = meta.wave,
   so advancing it is what makes the next segment write to the right rows. */
ok(strpos($rez_body, "\$b['meta']['wave']   = \$next") !== false,
   'the run stays on its old wave, so damage to the new one writes to the dead rows');

/* IT RUNS BEFORE THE OVER CHECK, or dhca_turn()'s verdict stands and the
   run ends on the frame the wave fell. */
$p_rez = strpos($lib, 'dhcb_rez($conn, $b)');
$p_chk = strpos($lib, 'dhca_check_over($b)');
ok($p_rez !== false && $p_chk !== false && $p_rez < $p_chk,
   'the resurrection runs after the over check, so the run ends before it can fire');
ok(strpos($rez_body, "\$b['over'] = null") !== false,
   'the resurrection never clears the verdict dhca_turn() already reached');

/* waves_cleared IS OPTIONAL AT RUNTIME. Naming a column that does not
   exist fails the statement, and losing the settle is far worse than
   losing a count. */
ok(substr_count($lib, 'UPDATE dhc_boss_runs SET $set_tail') === 2,
   'the settle names waves_cleared with no fallback, so a missing column loses the whole settle');

echo "\nthe client is handed what it needs to draw it\n";
$ui = (string) file_get_contents(__DIR__ . '/dhcarena.php');
ok(strpos($lib, "'k' => 'rez'") !== false, 'no rez event is emitted, so nothing animates');
ok(strpos($ui, "case 'rez':") !== false, 'the board has no rez handler, so the event is skipped');
/* THE CUE IS END-ALIGNED. Reversed sounds swell and CUT, and the cut is
   the landing -- starting them together puts five landings in one cue. */
ok(strpos($ui, 'REZ_LAYERS') !== false && strpos($ui, 'sfxReversed') !== false,
   'the resurrection cue is gone, or is no longer reversed');
ok(strpos($ui, 'var lead = REZ_LEN - full') !== false,
   'the cue layers are no longer end-aligned, so each one lands at its own time');
/*
 * THE CREW QUAKES WITH THE BOARD, AND IT STARTS DEAD.
 *
 * Two reports, both about the same thing: the bars repainted after the
 * board animation finished, so the characters read as reloading rather
 * than as the thing shaking the board; and the preview began from a live
 * crew, so there was no death to come back from.
 */
ok(strpos($ui, "re.classList.add('quaking')") !== false,
   'the crew does not quake with the board, so it reads as reloading after the shake');
ok(preg_match('~\.arena-wrap \.tok\.quaking\{animation:tokQuake ~', $ui) === 1,
   'the token quake keyframes are gone');
ok(preg_match('~\.arena-wrap \.tok\.quaking\.hard\{animation:tokQuakeHard ~', $ui) === 1,
   'the quake does not escalate, so the crew shakes evenly while the board builds');
/* On the SAME beat as the board, inside the same timer. */
preg_match('~bw\.classList\.add\(hard \? .shake-hard. : .shake.\);(.*?)\}, Math\.round~s', $ui, $m_beat);
ok(count($m_beat) === 2 && strpos($m_beat[1], "re.classList.add('quaking')") !== false,
   'the crew quake is not on the same beat as the board shake, so the two drift apart');

/* IT STARTS FROM THE DEAD STATE. .tok.ko is the grey and has been since
   long before this feature -- the preview has to put itself into it. */
ok(strpos($ui, 'rt.ko = true;') !== false,
   'the resurrection does not begin from the dead state, so there is nothing to come back from');
ok(strpos($ui, "re.classList.add('rez-flash')") !== false,
   'nothing flashes at the landing, so the moment passes rather than arrives');
ok(preg_match('~@keyframes tokRezFlash\{~', $ui) === 1, 'the flash keyframes are gone');

/* A BOSS DOES NOT COME APART. The collapse takes a Fighter to pieces
   trait by trait, which on one flat picture slides a rectangle off at an
   angle and leaves its jagged edge hanging. `art` is non-empty only for
   a boss -- the client already short-circuits on it elsewhere. */
ok(strpos($ui, "if (f && f.art) { flashTok(f, 'big'); return; }") !== false,
   'a boss still runs the trait-by-trait collapse, which looks wrong on a single image');
preg_match('~function killAnim\(f\)\{(.*?)\n\}~s', $ui, $m_ka);
$ka = count($m_ka) === 2 ? $m_ka[1] : '';
ok($ka !== '' && strpos($ka, 'f.art') < strpos($ka, "e.classList.add('dying')"),
   'the boss check comes after the collapse starts, so it runs anyway');

/* transform and filter only; no layout property on any frame. */
foreach (array('tokQuake', 'tokQuakeHard', 'tokRezFlash') as $kf_name) {
	preg_match('~@keyframes ' . $kf_name . '\{(.*?)\n\}~s', $ui, $m_k);
	$kf = count($m_k) === 2 ? $m_k[1] : '';
	ok($kf !== '', $kf_name . ' could not be lifted');
	foreach (array('left:', 'top:', 'width:', 'height:', 'margin') as $thrash) {
		ok($kf !== '' && strpos($kf, $thrash) === false,
		   $kf_name . ' animates ' . $thrash . ', which is a layout thrash on every frame');
	}
}
ok(preg_match('~prefers-reduced-motion: reduce\)\{\s*\.arena-wrap \.tok\.quaking,~s', $ui) === 1,
   'the crew quake ignores prefers-reduced-motion');

/*
 * THE HUE HOLDS AT THE LAST STEP past wave 5, and all three places that
 * decide it have to agree: dhcb_wave_hue() for the share card and the
 * Discord embed, the panel's data-wave, and the resurrection handler.
 *
 * This was WRAPPING, and the first week to reach wave 6 showed why that
 * is wrong -- the deepest the week had ever gone came back in wave 1's
 * colours. Executed rather than matched, so the next rewrite has to stay
 * correct rather than stay recognisable.
 */
for ($hw = 1; $hw <= 20; $hw++) {
	$want = min(4, $hw - 1) * 72;
	ok(dhcb_wave_hue($hw) === $want,
	   'dhcb_wave_hue(' . $hw . ') is ' . dhcb_wave_hue($hw) . 'deg, not ' . $want . 'deg');
}
ok(dhcb_wave_hue(20) === dhcb_wave_hue(5),
   'wave 20 does not wear wave 5\'s colour, so the series wraps again somewhere');
ok(dhcb_wave_hue(6) !== dhcb_wave_hue(1),
   'wave 6 is back to wave 1\'s colour, so the deepest the week has gone looks like the shallowest');
ok(strpos($ui, 'Math.min(5, Math.max(1, e.wave))') !== false,
   'the resurrection handler no longer caps the hue, so a rez into wave 6 lands on wave 1 colours');
ok(strpos($ui, 'data-wave="<?php echo min(5, max(1, (int)$wv)); ?>"') !== false,
   'the panel no longer caps the hue the same way dhcb_wave_hue() does');
/*
 * THE PREVIEW LIVES ON THE BOARD IT ANIMATES.
 *
 * The first one was a separate admin page that iframed dhcarena.php --
 * which is the Arena LANDING page, a Crew picker with no board on it, so
 * it previewed a crew picker. Same lesson admin-chest.php already
 * records: a reveal that only happens inside a live board has to be
 * previewed inside one.
 */
ok(strpos($ui, 'window.DHCA_PLAY_FX') !== false,
   'the timeline player is not exposed, so the preview can only mock the animation');
ok(strpos($ui, "id=\"rezPrev\"") !== false,
   'the in-game rez preview button is gone, so there is no way to see it on demand');
ok(strpos($ui, '$IS_BOSS && function_exists(\'adminIsSuper\') && adminIsSuper()') !== false,
   'the rez preview is not gated on boss mode AND admin, so players can see a tool');
ok(!is_file(__DIR__ . '/admin-bossrez-preview.php'),
   'admin-bossrez-preview.php is back; it iframed the Arena landing page, which has no board');

/*
 * NO PRACTICE BOARD. Practice deals two random CREWS, so its foes are
 * Fighters with Fighter art -- the resurrection repaints foe tokens and
 * turns the boss colourway, and against Fighters that is a shake and a
 * sound with nothing that looks like a boss getting up. A preview of the
 * wrong thing is worse than no preview.
 */
ok(strpos($ui, "id=\"bossPrac\"") === false,
   'the practice board button is back; practice deals Fighters, so it cannot preview a boss rising');

/*
 * AND THE PREVIEW MUST NOT CLOBBER THE BOARD. playTimeline()'s finish()
 * ends with `S = state`, so handing it null nulled the live board: the
 * second press did nothing because S.foes was gone, and on a real run it
 * would have taken the battle down with it.
 */
ok(strpos($ui, 'playTimeline(fx || [], S, function () {})') !== false,
   'DHCA_PLAY_FX passes something other than S as the state, and finish() assigns it over the live board');
ok(strpos($ui, 'playTimeline(fx || [], null') === false,
   'DHCA_PLAY_FX passes null as the state again, which nulls S on the first preview');
/* It must not reach the server: a preview that commits is not a preview. */
preg_match('~var pv = document\.getElementById\(.rezPrev.\);(.*?)\n\}\)\(\);~s', $ui, $m_pv);
$pv = count($m_pv) === 2 ? $m_pv[1] : '';
ok($pv !== '', 'the rez preview handler could not be lifted');
ok($pv !== '' && strpos($pv, 'fetch(') === false && strpos($pv, 'XMLHttpRequest') === false,
   'the rez preview calls the server, so looking at the animation can change the week');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
