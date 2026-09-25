<?php
/**
 * dhcarena-practice.php — the Arena with nothing at stake.
 *
 * Random Crews, unlimited battles, no allowance, no recovery, no traits, no
 * ladder. It exists so somebody can learn the game — and so somebody who is not
 * signed in can play it at all.
 *
 * WHY IT RUNS THE REAL ENGINE. dhcarena-prototype.php already did all of this,
 * in fourteen hundred lines of JavaScript that reimplement every rule. Two
 * implementations of one game drift, and the one that drifts is always the one
 * nobody is scoring — so practice would end up teaching a game that is not the
 * game. Everything below calls dhca_new_battle() and dhca_play(), the same
 * functions a ranked battle calls, and gets the same answers by construction.
 *
 * WHY IT IS STATELESS. A ranked battle keeps its board in dhc_arena_state
 * because the result is worth money. A practice battle is worth nothing, so
 * storing it would be all cost: rows to write, rows to clean up, and a user_id
 * to key them by that an anonymous visitor does not have.
 *
 * Instead the battle is REPLAYED. It is fully determined by its seed, the two
 * Crews' traits and the list of slides played so far -- the engine is
 * deterministic, which dhcarena-harness.php asserts on every run -- so the
 * client holds that tiny spec, sends it back with each move, and the server
 * rebuilds the battle from scratch and applies one more slide. A sixty-move
 * replay is microseconds, and there is nothing to tamper WITH: the prize for
 * cheating in practice is a practice win.
 *
 * Pure: no db.php, no $conn, no $_SESSION. That is what lets the public page
 * include it.
 */

require_once __DIR__ . '/dhcarena-engine.php';
require_once __DIR__ . '/dhcfighters-config.php';   // trait slots + the pairing rules

define('DHCAP_MAX_MOVES', 400);     // a replay ceiling; no real battle approaches it

/** Deterministic pick from a list, advancing the caller's seed. */
function dhcap_pick(&$seed, $list) {
	$seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
	return $list[$seed % count($list)];
}

/**
 * One random Fighter's traits, legal by the same rules the assembler enforces.
 *
 * Headgear is drawn LAST and re-drawn if the head refuses it, because that is
 * the only pairing rule between two random slots -- a random Crew that renders
 * a skull poking through its own helmet would teach players that the artwork
 * is broken.
 */
function dhcap_random_traits($rarity, &$seed) {
	$t = array();
	foreach (array('background','torso','head','arms','weapon','effects','companion') as $cat) {
		if (empty($rarity[$cat])) continue;
		$t[$cat] = dhcap_pick($seed, array_keys($rarity[$cat]));
	}
	if (!empty($rarity['headgear'])) {
		$pool = array_keys($rarity['headgear']);
		for ($try = 0; $try < 12; $try++) {
			$hg = dhcap_pick($seed, $pool);
			if (!function_exists('dhcf_headgear_blocked') || !dhcf_headgear_blocked($t['head'], $hg)) {
				$t['headgear'] = $hg; break;
			}
		}
	}
	return $t;
}

/** A Crew of three, the same size a ranked Crew is. */
function dhcap_random_crew($rarity, &$seed) {
	$c = array();
	for ($i = 0; $i < 3; $i++) $c[] = dhcap_random_traits($rarity, $seed);
	return $c;
}

/** Callsigns, so a practice Crew reads as characters rather than as slots. */
function dhcap_names(&$seed) {
	$first = array('Ash','Vex','Kilo','Rune','Nyx','Brak','Sable','Tono','Quill','Mag',
	               'Dross','Hex','Oro','Pike','Zel','Cinder','Vault','Grit');
	$last  = array('the Quiet','Nine','of Ward 6','Blacklight','the Debt','Zero','Halfmask',
	               'the Ledger','Eighty','of Sector C','the Static','Redline');
	$out = array();
	for ($i = 0; $i < 3; $i++) $out[] = dhcap_pick($seed, $first).' '.dhcap_pick($seed, $last);
	return $out;
}

/**
 * A fresh practice battle.
 * @return array(spec, battle) -- the spec is what the client hands back.
 */
function dhcap_new($rarity, $seed = 0) {
	$seed = $seed ? ($seed & 0x7FFFFFFF) : random_int(1, 0x7FFFFFFE);
	$s = $seed;
	$mine = dhcap_random_crew($rarity, $s);
	$foes = dhcap_random_crew($rarity, $s);
	$names = array('mine' => dhcap_names($s), 'foes' => dhcap_names($s));
	$spec = array('seed' => $seed, 'mine' => $mine, 'foes' => $foes,
	              'names' => $names, 'moves' => array());
	return array($spec, dhcap_build($spec, $rarity));
}

/**
 * Rebuild a battle from its spec and replay every move recorded in it.
 * Returns null if the spec is malformed -- which for practice means somebody
 * hand-edited it, and the right answer is simply a new battle.
 */
function dhcap_build($spec, $rarity) {
	if (!is_array($spec) || !isset($spec['seed'], $spec['mine'], $spec['foes'], $spec['names'])) return null;
	if (count($spec['mine']) !== 3 || count($spec['foes']) !== 3) return null;

	$b = dhca_new_battle($spec['mine'], $spec['foes'], $spec['names'], $rarity, (int)$spec['seed']);

	$moves = isset($spec['moves']) && is_array($spec['moves']) ? $spec['moves'] : array();
	if (count($moves) > DHCAP_MAX_MOVES) return null;
	foreach ($moves as $mv) {
		if ($b['over'] !== null) break;
		if (!is_array($mv) || count($mv) < 2) return null;
		if (!dhcap_exchange($b, (int)$mv[0], (int)$mv[1])) return null;
	}
	$b['fx'] = array();      // the replay is not a performance; only the NEW move is
	$b['log'] = array();
	return $b;
}

/**
 * One whole exchange: the player's slide, then the defending Crew's answers for
 * as long as it keeps earning them. Identical in shape to dhca_move().
 *
 * ONLY PLAYER MOVES ARE EVER RECORDED, and the defence is re-derived here on
 * every replay rather than stored alongside them. That is not a size
 * optimisation, it is the thing that makes the replay faithful at all:
 * dhca_ai_move() picks from a shortlist and CONSUMES battle randomness doing
 * it. Replaying a stored AI slide skips that draw, so the seed advances by a
 * different amount, and from the very next move the replayed board is a
 * different board. Re-running the AI consumes exactly what it consumed the
 * first time, because everything it reads is deterministic.
 */
function dhcap_exchange(&$b, $a, $z) {
	if (!dhca_play($b, 'mine', (int)$a, (int)$z)) return false;
	$guard = 0;
	while ($b['over'] === null && $b['turn'] === 'foes' && $guard++ < 12) {
		$mv = dhca_ai_move($b);
		/*
		 * NO RESHUFFLE HERE, unlike dhca_move(). dhca_play() already reshuffles
		 * at the END of any move that leaves no move available, so a board
		 * handed to the AI always has one -- and it does it inside dhca_play(),
		 * where a replay reproduces it exactly. Reshuffling out here would
		 * consume randomness the replay knows nothing about.
		 */
		if (!$mv) { $b['turn'] = 'mine'; break; }
		$fx = $b['fx']; $lg = $b['log'];
		if (!dhca_play($b, 'foes', $mv[0], $mv[1])) { $b['turn'] = 'mine'; break; }
		$b['fx']  = array_merge($fx, $b['fx']);
		$b['log'] = array_merge($lg, $b['log']);
	}
	return true;
}

/**
 * Apply one player slide and let the defending Crew answer, exactly as
 * dhca_move() does for a ranked battle -- including the fx and log merge, so
 * the client plays the whole exchange back as one timeline.
 *
 * @return array(ok, spec, battle)
 */
function dhcap_move($spec, $rarity, $a, $z) {
	$b = dhcap_build($spec, $rarity);
	if (!$b)                  return array(false, $spec, null);
	if ($b['over'] !== null)  return array(false, $spec, $b);
	if ($b['turn'] !== 'mine')return array(false, $spec, $b);
	if (!dhcap_exchange($b, $a, $z)) return array(false, $spec, $b);
	$spec['moves'][] = array((int)$a, (int)$z);
	return array(true, $spec, $b);
}
