<?php
/* dhcarena-live-harness.php — CLI only. The parts of live play that are pure:
   the perspective flip, seats, the invite/idle clocks, and a whole two-player
   match driven as two humans with no AI in it.

   The flip is the piece worth testing hardest. The engine has one 'mine' and
   one 'foes', both players are 'mine' to themselves, and getting that wrong
   does not crash -- it shows somebody the other person's Crew as their own and
   lets them watch their own Fighters take the damage they just dealt.

   Usage: php dhcarena-live-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/dhcarena-engine.php';
$rarity = require __DIR__ . '/dhcrarity.php';

/* dhcarena-live.php pulls in dhcarena-lib.php, which needs a database. The
   pure functions do not, so they are copied in by including the file with a
   stand-in already defined -- same trick the render harness uses. */
if (!function_exists('dhcf_rarity')) { function dhcf_rarity(){ global $rarity; return $rarity; } }

/* Pull just the pure half of the live lib. Everything tested below is free of
   $conn; requiring the whole file would drag the platform in. */
eval('?>' . preg_replace(
	'/^require_once __DIR__ . \'\/dhcarena-lib\.php\';$/m', '',
	preg_replace('/\nfunction (dhcal_row|dhcal_current|dhcal_close|dhcal_opponents|dhcal_pick|'
		. 'dhcal_challenge|dhcal_decline|dhcal_accept|dhcal_save|dhcal_clock|dhcal_move|'
		. 'dhcal_poll|dhcal_payload|dhcal_finish|dhcal_announce)\([\s\S]*?\n\}\n/', "\n",
		file_get_contents(__DIR__ . '/dhcarena-live.php'))));

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* ---------- 1. seats and sides ---------------------------------------------- */
$row = array('id'=>1, 'host_id'=>10, 'guest_id'=>20, 'status'=>1,
             'updated_at'=>date('Y-m-d H:i:s'));
ok(dhcal_seat($row, 10) === 'host',  'the host is the host');
ok(dhcal_seat($row, 20) === 'guest', 'the guest is the guest');
ok(dhcal_seat($row, 30) === '',      'a stranger has no seat');
ok(dhcal_side('host') === 'mine' && dhcal_side('guest') === 'foes', 'sides map to engine sides');
echo "seats: ok\n";

/* ---------- 2. the clocks ---------------------------------------------------- */
$inv = array('status'=>0, 'updated_at'=>date('Y-m-d H:i:s', time() - DHCAL_INVITE_S - 5));
ok(dhcal_expired($inv), 'a stale invite expires');
$inv['updated_at'] = date('Y-m-d H:i:s');
ok(!dhcal_expired($inv), 'a fresh invite does not');
$run = array('status'=>1, 'updated_at'=>date('Y-m-d H:i:s', time() - DHCAL_IDLE_S - 5));
ok(dhcal_expired($run), 'an abandoned board expires');
$run['status'] = 2;
ok(!dhcal_expired($run), 'a finished match is not "expired"');
echo "clocks: ok\n";

/* ---------- 3. the flip ------------------------------------------------------ */
function pick_traits($rarity, &$seed) {
	$t = array();
	foreach (array('torso','head','headgear','arms','weapon','background','companion','effects') as $c) {
		$pool = array(); foreach ($rarity[$c] as $slug => $i) $pool[] = $slug;
		$seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
		$t[$c] = $pool[$seed % count($pool)];
	}
	return $t;
}
function crew3($rarity, &$seed) { $c = array(); for ($i=0;$i<3;$i++) $c[] = pick_traits($rarity, $seed); return $c; }

$seed = 31337;
$hostCrew = crew3($rarity, $seed); $guestCrew = crew3($rarity, $seed);
$names = array('mine'=>array('H1','H2','H3'), 'foes'=>array('G1','G2','G3'));
$b = dhca_new_battle($hostCrew, $guestCrew, $names, $rarity, 4242);
$b['meta'] = array('moves'=>array(), 'tl'=>array());

$pub  = dhca_public($b);
$hv   = dhcal_view($pub, 'host');
$gv   = dhcal_view($pub, 'guest');

ok($hv['mine'][0]['name'] === 'H1', 'the host sees their own Crew as mine');
ok($gv['mine'][0]['name'] === 'G1', 'the guest sees their own Crew as mine');
ok($gv['foes'][0]['name'] === 'H1', "the guest sees the host's Crew as foes");
ok($hv['turn'] === 'mine' && $gv['turn'] === 'foes', 'the opening turn is the host\'s, both ways');
ok($hv['board'] === $gv['board'], 'the board is NOT transformed -- it is symmetric');

// flipping twice is the identity, which is the property that makes this safe
$twice = dhcal_view(dhcal_view($pub, 'guest'), 'guest');
ok(json_encode($twice['mine']) === json_encode($pub['mine']), 'flipping twice returns the original');
ok($twice['turn'] === $pub['turn'], 'flipping the turn twice returns the original');
echo "flip: ok\n";

/* fx sides flip too, or the guest watches their own Fighters take the damage
   they just dealt -- which does not crash and does not look like a bug */
$fx = array(array('k'=>'hit','side'=>'foes','i'=>0,'v'=>10),
            array('k'=>'act','side'=>'mine','i'=>1),
            array('k'=>'wave','chain'=>1,'cells'=>array(1,2,3)));
$ff = dhcal_flip_fx($fx);
ok($ff[0]['side'] === 'mine' && $ff[1]['side'] === 'foes', 'fx sides flip');
ok(!isset($ff[2]['side']) && $ff[2]['cells'] === array(1,2,3), 'a sideless fx is untouched');
echo "fx flip: ok\n";

/* ---------- 4. the timeline tail is bounded ---------------------------------- */
$t = array('meta'=>array('moves'=>array(), 'tl'=>array()), 'fx'=>array(array('k'=>'x')));
for ($i = 0; $i < DHCAL_TL_KEEP + 6; $i++) { $t['fx'] = array(array('k'=>'m'.$i)); dhcal_record_move($t, $i, $i+1); }
ok(count($t['meta']['tl']) === DHCAL_TL_KEEP, 'only the last '.DHCAL_TL_KEEP.' timelines are kept');
$keys = array_map('intval', array_keys($t['meta']['tl'])); sort($keys);
ok(end($keys) === count($t['meta']['moves']), 'the newest kept timeline is the newest move');
ok($keys[0] === count($t['meta']['moves']) - DHCAL_TL_KEEP + 1, 'the tail is contiguous');
echo "timeline tail: ok (keeps ".DHCAL_TL_KEEP.", drops the rest)\n";

/* ---------- 5. a whole match, two humans, no AI ------------------------------ */
/* The thing that would make live unplayable is a turn that never comes back.
   This plays both seats as people -- never calling dhca_ai_move() -- and
   demands the match reach a winner with both sides having moved. */
$games = 0; $hostWins = 0; $turnsTot = 0; $stuck = 0; $bothMoved = 0; $viewBad = 0;
$oneSided = array(); $turnStuck = 0; $againBad = 0;
for ($g = 0; $g < 25; $g++) {
	$sd = $g * 977 + 5;
	$hc = crew3($rarity, $sd); $gc = crew3($rarity, $sd);
	$b = dhca_new_battle($hc, $gc, $names, $rarity, 7000 + $g);
	$b['meta'] = array('moves'=>array(), 'tl'=>array());
	$byHost = 0; $byGuest = 0; $guard = 0;
	while ($b['over'] === null && $guard++ < 400) {
		$side = $b['turn'];
		// a person picks; dhca_ai_move stands in for their judgement, but it is
		// called for BOTH sides here, which is the live case
		$mv = dhca_ai_move($b);
		if (!$mv) { dhca_fill_board($b); $mv = dhca_ai_move($b); }
		if (!$mv) break;
		if (!dhca_play($b, $side, $mv[0], $mv[1])) break;
		dhcal_record_move($b, $mv[0], $mv[1]);
		if ($side === 'mine') $byHost++; else $byGuest++;

		/*
		 * THE TURN MUST PASS UNLESS THE GAME SAID IT DOES NOT.
		 *
		 * Tested per move rather than inferred from the final score, because
		 * inference cannot tell the two apart. The earlier version of this
		 * section demanded both seats move in every match and failed on a
		 * legitimate first-round blowout; the obvious repair -- allow a
		 * one-sided match if it ended in round 1 -- is WORSE THAN USELESS,
		 * since a turn stuck on one seat never advances the round either
		 * (dhca_play only increments it when the turn passes to 'mine'), so
		 * the stuck case satisfies it too.
		 *
		 * dhca_play() resets fx at the top of every move, so an 'again' in
		 * there belongs to THIS move and nothing earlier. Either the engine
		 * announced an extra turn, or the seat changed hands.
		 */
		if ($b['over'] === null) {
			$again = false;
			foreach ($b['fx'] as $e) if (isset($e['k']) && $e['k'] === 'again') $again = true;
			if (!$again && $b['turn'] === $side) $turnStuck++;
			if ($again && $e !== null && $b['turn'] !== $side) $againBad++;
		}

		// every intermediate state must survive the flip in both seats
		$p = dhca_public($b);
		$g1 = dhcal_view($p, 'guest');
		if ($g1['mine'][0]['name'] !== $p['foes'][0]['name']
		 || $g1['turn'] === $p['turn']  // turn must differ between the seats
		 || count($g1['fx']) !== count($p['fx'])) $viewBad++;
	}
	if ($b['over'] === null) { $stuck++; continue; }
	$games++; $turnsTot += $b['round'];
	if ($b['over'] === 'mine') $hostWins++;
	if ($byHost > 0 && $byGuest > 0) $bothMoved++;
	else $oneSided[] = sprintf('g%d host=%d guest=%d round=%d', $g, $byHost, $byGuest, $b['round']);
}
ok($stuck === 0, 'no match failed to finish');

/*
 * A SEAT THAT NEVER MOVES IS ONLY A BUG IF THE MATCH WAS STILL GOING.
 *
 * This asserted `$bothMoved === $games` and failed on 1 of 25 -- seed g13,
 * where the host chained four matches of five or more and wiped the crew
 * before the guest ever had the board. That is DHCA_EXTRA_TURN working: a
 * five goes again, and a good enough opening can end it inside one round.
 * The check was asserting that the game's own rule never fires hard enough
 * to matter, which it is entitled to do.
 *
 * What it was REACHING for is a turn that never comes back, and the honest
 * test of that is the round count. The host always opens, so only the guest
 * can finish on zero moves -- and if that happens the round counter must
 * still read 1, because the round only advances when the turn passes to
 * 'mine'. A one-sided match that got to round 3 means the turn is stuck on
 * one seat, which IS the bug this was written for.
 */
ok($turnStuck === 0,
   "the turn stayed with the same seat after $turnStuck move(s) that earned no extra turn -- "
 . 'a seat that keeps the board forever is the failure this section exists for');
ok($againBad === 0,
   "the turn changed hands on $againBad move(s) that DID earn an extra turn, so the extra "
 . 'turn is announced and then not given');

foreach ($oneSided as $d) {
	ok(strpos($d, 'round=1') !== false,
	   "a seat never moved and the match was still running: $d -- the turn is stuck on one "
	 . 'side, which is not DHCA_EXTRA_TURN ending it early');
	ok(strpos($d, 'host=0') === false,
	   "the HOST never moved: $d -- the host opens every match, so zero host moves means the "
	 . 'opening turn never happened');
}
ok($viewBad === 0, 'every intermediate state flipped cleanly');
printf("match: %d played, host won %d (%.0f%%), avg %.1f rounds, %d stuck, %d bad views, "
     . "%d first-round blowout%s\n",
	$games, $hostWins, $games ? $hostWins / $games * 100 : 0,
	$games ? $turnsTot / $games : 0, $stuck, $viewBad,
	count($oneSided), count($oneSided) === 1 ? '' : 's');

/* No seat advantage beyond going first. Not a balance rule -- just a check that
   'mine' and 'foes' are not accidentally asymmetric in a mode where a person
   sits in each. Wide bounds: 25 games is a small sample and the first move is
   a real edge. */
ok($games === 0 || ($hostWins / $games > 0.25 && $hostWins / $games < 0.75),
   'neither seat is structurally broken');

echo "\n".($fail ? "FAILED: $fail check(s)\n" : "all live checks passed\n");
exit($fail ? 1 : 0);
