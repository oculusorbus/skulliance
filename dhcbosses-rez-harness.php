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
/* The hue cycles on both sides or they disagree from wave 6. */
ok(strpos($ui, '((e.wave - 1) % 5) + 1') !== false,
   'the client caps the wave hue instead of cycling it');
ok(strpos($ui, '((max(1, (int)$wv) - 1) % 5) + 1') !== false,
   'the page caps the wave hue instead of cycling it, so it disagrees with the client');
/* The preview plays the real player, not a copy. */
ok(strpos($ui, 'window.DHCA_PLAY_FX') !== false,
   'the timeline player is not exposed, so the admin preview can only mock the animation');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
