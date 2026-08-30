<?php
/* ============================================================
   cryptconquest-engine.php — pure rules engine for Crypt Conquest
   (Regicide-inspired, solo mode only). See cryptconquest.md for the
   research/legal/design handoff this implements.

   Deliberately isolated from the DB/session layer: every function here
   takes and/or returns a plain $run array, no $conn, no $_SESSION. That
   split mirrors cryptcrawl-actions.php/db.php's separation for Crypt
   Crawl, and lets the whole ruleset be exercised by a standalone PHP test
   harness with zero setup. The persistence wrapper (cryptconquestStartRun/
   GetActiveRun/SaveRun, table `cryptconquests`) belongs in db.php once
   this is wired to a page -- not written yet, see cryptconquest.md §5.

   $run shape:
     status          'active' | 'won' | 'lost'
     phase           'play'   -- Step 1: choose a card/combo, or yield
                     'suffer' -- Step 4: choose discards to cover pending_attack
                     'over'   -- won/lost, no further actions
     pending_attack  int, only meaningful during 'suffer'
     castle_deck     [ ['type'=>'court','suit'=>..,'rank'=>11|12|13], ... ]  draw-from-front
     current_enemy   ['suit'=>..,'rank'=>..,'damage_taken'=>int,'shield'=>int] | null (only when won)
     tavern_deck     [ card, ... ]  draw-from-front
     hand            [ card, ... ]
     discard         [ card, ... ]
     jesters_used    0-2 (solo: both set aside as flip-charges, never in hand)
     last_rally_used 0 or 1 (see cryptconquest.md §4b)
     enemies_defeated int
     log             [ string, ... ]  narration for the most recent action only

   card shape: ['type'=>'number'|'companion'|'court', 'suit'=>'H'|'D'|'C'|'S', 'rank'=>int|null]
   ============================================================ */

define('CRYPTCONQUEST_MAX_HAND', 8); // solo hand size cap (ruleset: 1 player -> 8)
define('CRYPTCONQUEST_SUITS', ['H', 'D', 'C', 'S']);

function cryptconquestEnemyStats($rank) {
	switch (intval($rank)) {
		case 11: return ['attack' => 10, 'health' => 20]; // Jack
		case 12: return ['attack' => 15, 'health' => 30]; // Queen
		case 13: return ['attack' => 20, 'health' => 40]; // King
	}
	return ['attack' => 0, 'health' => 0];
}

// A card's contribution to an attack/discard total. Companions are always
// 1 regardless of suit; a recovered court card (drawn back into hand via
// the exact-damage-kill rule) counts as its enemy attack value (10/15/20),
// not its raw rank (11/12/13) -- see ruleset §Drawing a defeated enemy.
function cryptconquestCardValue($card) {
	if ($card['type'] === 'companion') return 1;
	if ($card['type'] === 'court') return cryptconquestEnemyStats($card['rank'])['attack'];
	return intval($card['rank']);
}

function cryptconquestCardLabel($card) {
	$suitNames = ['H' => 'Hearts', 'D' => 'Diamonds', 'C' => 'Clubs', 'S' => 'Spades'];
	$rankNames = [11 => 'Jack', 12 => 'Queen', 13 => 'King'];
	$suit = $suitNames[$card['suit']] ?? $card['suit'];
	if ($card['type'] === 'companion') return "$suit Companion";
	$rank = intval($card['rank']);
	$rankLabel = $rankNames[$rank] ?? strval($rank);
	return "$rankLabel of $suit";
}

function cryptconquestShuffledCourtRank($rank) {
	$cards = [];
	foreach (CRYPTCONQUEST_SUITS as $suit) $cards[] = ['type' => 'court', 'suit' => $suit, 'rank' => $rank];
	shuffle($cards);
	return $cards;
}

// Builds a fresh shuffled solo game. Not persisted -- db.php's
// cryptconquestStartRun() (not written yet) will own turning this into a
// DB row the same way cryptcrawlStartRun() does for Crypt Crawl.
function cryptconquestNewGame() {
	// Castle deck: Jacks on top (drawn first) -> Queens -> Kings (drawn last),
	// each tier shuffled independently so suit order within a tier is random
	// but the J-before-Q-before-K progression is guaranteed.
	$jacks = cryptconquestShuffledCourtRank(11);
	$queens = cryptconquestShuffledCourtRank(12);
	$kings = cryptconquestShuffledCourtRank(13);
	$castle = array_merge($jacks, $queens, $kings);

	// Tavern deck: 2-10 of all 4 suits + 4 Animal Companions. Aces are not
	// part of Regicide's component list at all (removed from the box,
	// nothing to build here). Solo: no Jesters mixed in -- see §Solo.
	$tavern = [];
	foreach (CRYPTCONQUEST_SUITS as $suit) {
		for ($rank = 2; $rank <= 10; $rank++) {
			$tavern[] = ['type' => 'number', 'suit' => $suit, 'rank' => $rank];
		}
		$tavern[] = ['type' => 'companion', 'suit' => $suit, 'rank' => null];
	}
	shuffle($tavern);

	$hand = array_splice($tavern, 0, CRYPTCONQUEST_MAX_HAND);
	$first = array_shift($castle);

	return [
		'status' => 'active',
		'phase' => 'play',
		'pending_attack' => 0,
		'castle_deck' => $castle,
		'current_enemy' => [
			'suit' => $first['suit'], 'rank' => $first['rank'],
			'damage_taken' => 0, 'shield' => 0,
		],
		'tavern_deck' => array_values($tavern),
		'hand' => array_values($hand),
		'discard' => [],
		'jesters_used' => 0,
		'last_rally_used' => 0,
		'enemies_defeated' => 0,
		'log' => ["A " . cryptconquestCardLabel(['type' => 'court', 'suit' => $first['suit'], 'rank' => $first['rank']]) . " blocks the way."],
	];
}

// Validates a proposed Step 1 play ($indices into $run['hand']) without
// mutating anything. Returns null if legal, an error string if not.
function cryptconquestValidatePlay($run, $indices) {
	$hand = $run['hand'];
	$n = count($indices);
	if ($n < 1) return 'Select at least one card.';
	$seen = [];
	foreach ($indices as $i) {
		if (!isset($hand[$i])) return 'Invalid card selection.';
		if (isset($seen[$i])) return 'Duplicate card selected.';
		$seen[$i] = true;
	}
	if ($n === 1) return null; // any single card, always legal

	$cards = array_map(function ($i) use ($hand) { return $hand[$i]; }, $indices);
	$companionCount = count(array_filter($cards, function ($c) { return $c['type'] === 'companion'; }));

	if ($n === 2 && $companionCount >= 1) {
		return null; // companion+companion, or companion+any one other card
	}
	if ($companionCount > 0) {
		return "Animal Companions can't join a combo -- play one alone or paired with a single other card.";
	}
	if ($n > 4) return 'Combos are limited to 4 cards.';
	$rank = $cards[0]['rank'];
	foreach ($cards as $c) {
		if ($c['rank'] !== $rank) return 'Combo cards must all share the same rank.';
	}
	$sum = array_sum(array_map('cryptconquestCardValue', $cards));
	if ($sum > 10) return "Combo total can't exceed 10.";
	return null;
}

function cryptconquestHeal(&$run, $count) {
	if ($count <= 0 || empty($run['discard'])) return 0;
	$pile = $run['discard'];
	shuffle($pile);
	$healed = array_splice($pile, 0, min($count, count($pile)));
	// Face-down, no peeking -- placed at the far end of tavern_deck (the
	// "bottom", i.e. drawn last) since draws always take from the front.
	$run['tavern_deck'] = array_merge($run['tavern_deck'], $healed);
	$run['discard'] = array_values($pile);
	return count($healed);
}

function cryptconquestDraw(&$run, $count) {
	$room = CRYPTCONQUEST_MAX_HAND - count($run['hand']);
	$take = max(0, min($count, $room, count($run['tavern_deck'])));
	for ($i = 0; $i < $take; $i++) {
		$run['hand'][] = array_shift($run['tavern_deck']);
	}
	return $take;
}

function cryptconquestDefeatEnemy(&$run, $exact) {
	$enemy = ['type' => 'court', 'suit' => $run['current_enemy']['suit'], 'rank' => $run['current_enemy']['rank']];
	if ($exact) {
		array_unshift($run['tavern_deck'], $enemy); // face-down on TOP of the tavern deck -- drawn next, not shuffled in
	} else {
		$run['discard'][] = $enemy;
	}
	$run['enemies_defeated'] = intval($run['enemies_defeated']) + 1;

	if (empty($run['castle_deck'])) {
		$run['status'] = 'won';
		$run['phase'] = 'over';
		$run['current_enemy'] = null;
		return;
	}
	$next = array_shift($run['castle_deck']);
	$run['current_enemy'] = ['suit' => $next['suit'], 'rank' => $next['rank'], 'damage_taken' => 0, 'shield' => 0];
	$run['phase'] = 'play';
}

// Step 1+2+3: play $indices from hand (single card, a combo, or a companion
// pairing -- see cryptconquestValidatePlay). Advances to 'suffer' phase if
// the enemy survives, or resolves the kill (and possibly the win) in place.
function cryptconquestPlay(&$run, $indices) {
	if ($run['phase'] !== 'play') return ['ok' => false, 'error' => "Can't play a card right now."];
	$error = cryptconquestValidatePlay($run, $indices);
	if ($error) return ['ok' => false, 'error' => $error];

	rsort($indices); // remove high-to-low so earlier indices stay valid mid-loop
	$played = [];
	foreach ($indices as $i) {
		$played[] = $run['hand'][$i];
		array_splice($run['hand'], $i, 1);
	}
	$played = array_reverse($played); // cosmetic: restore the player's selection order

	$attackValue = array_sum(array_map('cryptconquestCardValue', $played));
	$enemySuit = $run['current_enemy']['suit'];
	$suitsPlayed = array_unique(array_map(function ($c) { return $c['suit']; }, $played));
	// The enemy's own suit is immune -- its power is inert, numeric damage isn't.
	$activeSuits = array_values(array_diff($suitsPlayed, [$enemySuit]));

	$log = [];
	if (in_array('C', $activeSuits, true)) {
		$attackValue *= 2;
		$log[] = "Clubs double the attack -- $attackValue damage.";
	}
	// Hearts resolves before Diamonds when both trigger together (ruleset §Turn structure).
	if (in_array('H', $activeSuits, true)) {
		$healed = cryptconquestHeal($run, $attackValue);
		$log[] = $healed > 0 ? "Hearts: $healed card(s) healed back from the discard pile." : 'Hearts: discard pile was empty, nothing to heal.';
	}
	if (in_array('D', $activeSuits, true)) {
		$drawn = cryptconquestDraw($run, $attackValue);
		$log[] = $drawn > 0 ? "Diamonds: drew $drawn card(s)." : 'Diamonds: hand already full, nothing drawn.';
	}
	if (in_array('S', $activeSuits, true)) {
		$run['current_enemy']['shield'] += $attackValue;
		$log[] = "Spades: +$attackValue shield against this enemy.";
	}

	foreach ($played as $c) $run['discard'][] = $c;

	$run['current_enemy']['damage_taken'] += $attackValue;
	$enemyStats = cryptconquestEnemyStats($run['current_enemy']['rank']);
	$remaining = $enemyStats['health'] - $run['current_enemy']['damage_taken'];

	if ($remaining <= 0) {
		$exact = $remaining === 0;
		$label = cryptconquestCardLabel(['type' => 'court', 'suit' => $run['current_enemy']['suit'], 'rank' => $run['current_enemy']['rank']]);
		$log[] = $exact ? "Exact kill! $label recovered face-down atop the deck." : "$label defeated.";
		cryptconquestDefeatEnemy($run, $exact);
		$run['log'] = $log;
		return ['ok' => true, 'defeated' => true, 'won' => $run['status'] === 'won'];
	}

	$attack = max(0, $enemyStats['attack'] - $run['current_enemy']['shield']);
	$run['phase'] = 'suffer';
	$run['pending_attack'] = $attack;
	$run['log'] = $log;
	return ['ok' => true, 'defeated' => false];
}

// Step 1 alternative: skip straight to Step 4, no card played, no suit
// power, no shield gained this turn (existing shield on the enemy still applies).
function cryptconquestYield(&$run) {
	if ($run['phase'] !== 'play') return ['ok' => false, 'error' => "Can't yield right now."];
	// Dead-end guard, not a raw Regicide rule -- found by fuzz-testing this
	// engine, not by the ruleset doc. An empty hand with both Jesters
	// already flipped can never be refilled (Diamonds' draw power requires
	// a card in hand to play in the first place), so nothing can ever be
	// played, drawn, healed, or shielded again -- the position is dead even
	// though raw Regicide always permits yielding. At the table a player
	// just concedes here; a digital run needs to resolve it instead of
	// yielding at 0 damage forever. Only fires when there's truly no way
	// back (jesters_used >= 2) -- 0 or 1 unused Jesters means flipping one
	// is still the right move, not a loss.
	if (empty($run['hand']) && intval($run['jesters_used']) >= 2) {
		$run['status'] = 'lost';
		$run['phase'] = 'over';
		$run['log'] = ['Hand empty, no Jesters left to refill it -- no way to fight on. The crypt claims you.'];
		return ['ok' => true, 'dead_end' => true];
	}
	$enemyStats = cryptconquestEnemyStats($run['current_enemy']['rank']);
	$attack = max(0, $enemyStats['attack'] - $run['current_enemy']['shield']);
	$run['phase'] = 'suffer';
	$run['pending_attack'] = $attack;
	$run['log'] = ['Yielded -- no card played, no shield gained this turn.'];
	return ['ok' => true];
}

// Step 4: cover $run['pending_attack'] by discarding $indices from hand.
// A short selection that isn't the whole hand is just rejected as a mistake
// (not a loss) so the UI can safely let a player under-select and retry.
// Only a whole-hand selection that still doesn't cover it can trigger
// Last Rally (once per run) or the loss -- see cryptconquest.md §4b.
function cryptconquestSufferDamage(&$run, $indices) {
	if ($run['phase'] !== 'suffer') return ['ok' => false, 'error' => "Nothing to cover right now."];
	$attack = intval($run['pending_attack']);
	$hand = $run['hand'];

	if ($attack <= 0) {
		$run['phase'] = 'play';
		$run['pending_attack'] = 0;
		$run['log'] = ['Fully shielded -- no damage suffered.'];
		return ['ok' => true, 'died' => false, 'rallied' => false];
	}

	$seen = [];
	foreach ($indices as $i) {
		if (!isset($hand[$i]) || isset($seen[$i])) return ['ok' => false, 'error' => 'Invalid discard selection.'];
		$seen[$i] = true;
	}
	$chosenTotal = array_sum(array_map(function ($i) use ($hand) { return cryptconquestCardValue($hand[$i]); }, $indices));
	$handTotal = array_sum(array_map('cryptconquestCardValue', $hand));
	$isWholeHand = count($indices) === count($hand);

	if ($chosenTotal < $attack) {
		if (!$isWholeHand) {
			return ['ok' => false, 'error' => "That only covers $chosenTotal of the $attack damage -- discard more, or your whole hand if you can't reach it."];
		}
		// Whole hand discarded and still short -- Last Rally, or the run ends.
		rsort($indices);
		foreach ($indices as $i) { $run['discard'][] = $run['hand'][$i]; array_splice($run['hand'], $i, 1); }
		if (intval($run['last_rally_used']) === 0) {
			$run['last_rally_used'] = 1;
			$run['phase'] = 'play';
			$run['pending_attack'] = 0;
			$run['log'] = ["LAST RALLY! Only $handTotal of $attack damage covered -- you refuse to fall. (once per run)"];
			return ['ok' => true, 'died' => false, 'rallied' => true];
		}
		$run['status'] = 'lost';
		$run['phase'] = 'over';
		$run['log'] = ["Only $handTotal of $attack damage covered -- the crypt claims you."];
		return ['ok' => true, 'died' => true, 'rallied' => false];
	}

	rsort($indices);
	foreach ($indices as $i) { $run['discard'][] = $run['hand'][$i]; array_splice($run['hand'], $i, 1); }
	$run['phase'] = 'play';
	$run['pending_attack'] = 0;
	$run['log'] = ["Covered $attack damage with $chosenTotal from hand."];
	return ['ok' => true, 'died' => false, 'rallied' => false];
}

// Solo-only: discard the whole hand and refill to max, at the start of
// Step 1 or Step 4 (before choosing discards). Twice per game (one charge
// per Jester). Doesn't touch phase/pending_attack -- if called mid-'suffer',
// the same pending_attack still needs covering with the fresh hand.
function cryptconquestFlipJester(&$run) {
	if (!in_array($run['phase'], ['play', 'suffer'], true)) return ['ok' => false, 'error' => "Can't flip a Jester right now."];
	if (intval($run['jesters_used']) >= 2) return ['ok' => false, 'error' => 'No Jesters left to flip.'];
	foreach ($run['hand'] as $c) $run['discard'][] = $c;
	$run['hand'] = [];
	$drawn = cryptconquestDraw($run, CRYPTCONQUEST_MAX_HAND);
	$run['jesters_used'] = intval($run['jesters_used']) + 1;
	$run['log'] = ["Flipped a Jester -- hand discarded and refilled ($drawn card(s))."];
	return ['ok' => true];
}

// Solo win tier, keyed off Jesters flipped (Last Rally firing doesn't
// affect it) -- see cryptconquest.md §4b for the renaming rationale.
function cryptconquestTier($run) {
	switch (intval($run['jesters_used'])) {
		case 0: return 'Flawless Conquest';
		case 1: return 'Hard-Fought Conquest';
		default: return 'Narrow Conquest';
	}
}
