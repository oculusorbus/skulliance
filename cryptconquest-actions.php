<?php
// cryptconquest-actions.php — shared POST action handler for Crypt Conquest.
//
// Same shape as cryptcrawl-actions.php: used by both cryptconquest.php's
// own POST branch (no-JS fallback, full page reload) and
// ajax/cryptconquest-action.php (the normal path once JS has loaded), so
// there's one copy of the actual game-action logic instead of two that
// could quietly drift apart.

// $card: optional ['type','suit','rank'] to render alongside the message --
// used to show the actual card you just recovered on an exact kill. Only the
// card's IDENTITY is stored; cryptconquestRenderGameArea() resolves its art
// from the pools it already has, so no image URL is carried through session.
function cryptconquestFlash($msg, $type = 'info', $card = null, $cards = null) {
	$entry = ['msg' => $msg, 'type' => $type];
	if ($card) $entry['card'] = $card;
	if ($cards) $entry['cards'] = $cards;   // several cards at once, e.g. a perfect guard
	$_SESSION['cryptconquest_flash'][] = $entry;
}

// Queues a sound for the client to play on the next render (emitted as
// data-sfx on #cq-mood -- see cryptconquest-render.php). Needed for outcomes
// the SERVER decides rather than the click: a kill depends on damage
// resolution and Last Stand fires on its own, so there's no button press the
// client could hang either off.
//
// Deliberately NOT carried on the flash modal, which was the first attempt.
// Perfect Guard only shows its modal once per run (see the guard_taught gate
// below), so a modal-borne sound would have played on the first exact defense
// of a run and stayed silent for every one after -- while the sound needs to
// fire on all of them. #cq-mood renders on every swap regardless of modals.
// Several sounds can be queued for one render; they layer.
function cryptconquestSfx($name) {
	$_SESSION['cryptconquest_sfx'][] = $name;
}

// Performs one Crypt Conquest action (start_run/play/yield/suffer/
// flip_jester/abandon) for $user_id, queuing any resulting flash message.
// $post is $_POST (or an equivalent array): 'action', and for play/suffer
// also 'card_indices' (array of hand indices).
// Returns the run THIS request just ended (status won/lost), or null. Mirrors
// cryptcrawlHandleAction()'s own return value and exists for the same reason:
// ajax/cryptconquest-action.php needs to know "did this request finish a run"
// so it can render THAT run's result, rather than asking
// cryptconquestRenderGameArea() "is any run active?" -- which answers with a
// live board whenever an orphaned active row exists. See
// cryptcrawl-loss-screen-bug.md.
function cryptconquestHandleAction($conn, $user_id, $post) {
	if (!isset($_SESSION['cryptconquest_flash'])) $_SESSION['cryptconquest_flash'] = [];
	$action = $post['action'] ?? '';
	// Set by whichever branch below actually resolves a run; returned at the
	// end only if that run genuinely finished.
	$final_run = null;

	if ($action === 'start_run') {
		cryptconquestStartRun($conn, $user_id);
		// One-shot signal read (and cleared) by cryptconquestRenderGameArea()'s
		// #cq-mood output -- the audio player forces the Theme track
		// specifically on a fresh conquest, not whatever the normal loop's
		// last-saved track happened to be. Same pattern as Crypt Crawl's
		// cryptcrawl_just_started.
		$_SESSION['cryptconquest_just_started'] = true;

	} elseif ($action === 'play') {
		$run = cryptconquestGetActiveRun($conn, $user_id);
		if ($run) {
			$indices = array_values(array_unique(array_map('intval', (array)($post['card_indices'] ?? []))));
			$outcome = cryptconquestDoPlay($conn, $user_id, intval($run['id']), $indices);
			// Mark cards Diamonds just pulled so the render can file them in
			// distinctly -- otherwise they land mixed into the existing hand and
			// the suit's actual effect is invisible.
			if ($outcome && !empty($outcome['result']['drawn'])) {
				$_SESSION['cryptconquest_drawn'] = array_map('cryptconquestCardArtKey', $outcome['result']['drawn']);
			}
			if ($outcome) $final_run = $outcome['run'];
			if (!$outcome) {
				cryptconquestFlash('No active run.', 'error');
			} elseif (!$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'], 'error');
			} elseif (!empty($outcome['result']['defeated'])) {
				$r = $outcome['result'];
				$card_label = $r['card_label'] ?? 'The court card';
				// Every branch here is a regent dying, so every branch gets the
				// kill sound -- including the winning blow, which is still a
				// King falling even though the result screen takes over after.
				cryptconquestSfx('kill');
				// Checked SEPARATELY from the message branches below, because
				// the winning blow can itself be an exact kill -- and those
				// branches are ordered won-first, so folding this in there
				// would have silently dropped the exact-match sound on the
				// single most satisfying hit in the game.
				// Layered on top of the kill sound rather than replacing it:
				// the regent still died, the precision is the extra.
				if (!empty($r['exact'])) cryptconquestSfx('exactmatch');
				if (!empty($r['won'])) {
					cryptconquestFlash('👑 The last King falls -- the Necropolis is yours!', 'win');
				} elseif (!empty($r['exact'])) {
					// Exact kill: the card is recovered face-down on top of the deck,
					// so show it. This is the game's one precision reward and it used
					// to be indistinguishable from any other kill.
					cryptconquestFlash('EXACT KILL! ' . $card_label . ' joins your deck, face-down on top -- you will draw it back.', 'win', $r['card'] ?? null);
				} else {
					cryptconquestFlash($card_label . ' defeated. Not an exact kill, so it goes to the discard pile.', 'win');
				}
			}
		}

	} elseif ($action === 'yield') {
		$run = cryptconquestGetActiveRun($conn, $user_id);
		if ($run) {
			$outcome = cryptconquestDoYield($conn, $user_id, intval($run['id']));
			if ($outcome) $final_run = $outcome['run'];
			if ($outcome && !$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'], 'error');
			} elseif ($outcome && !empty($outcome['result']['dead_end'])) {
				cryptconquestFlash('Hand empty, no Jesters left to refill it -- no way to fight on.', 'error');
			}
		}

	} elseif ($action === 'suffer') {
		$run = cryptconquestGetActiveRun($conn, $user_id);
		if ($run) {
			$indices = array_values(array_unique(array_map('intval', (array)($post['card_indices'] ?? []))));
			$outcome = cryptconquestDoSuffer($conn, $user_id, intval($run['id']), $indices);
			if ($outcome) $final_run = $outcome['run'];
			if (!$outcome) {
				cryptconquestFlash('No active run.', 'error');
			} elseif (!$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'], 'error');
			} elseif (!empty($outcome['result']['exact_defense'])) {
				// Keyed off exact_defense, NOT off 'saved' being non-empty:
				// covering exactly with a full hand returns nothing (the
				// perfect-guard loop stops at the 8-card limit), and that's
				// still an exact match the player earned the sound for.
				cryptconquestSfx('exactmatch');
				// PERFECT GUARD. Exact defenses land on ~68% of turns (measured
				// over 2,500 simulated games), so a blocking modal every time
				// would read as the game nagging you for playing well. Celebrate
				// once per run to teach the rule; after that the recovered cards
				// just glow in hand, which also shows WHICH cards came back --
				// something a modal can't do as clearly, since they're already
				// sitting in your hand.
				// An exact cover on a FULL hand returns nothing, so everything
				// below is conditional on cards actually coming back -- the
				// modal would otherwise read "your strongest held:  return to
				// your hand", and worse, would burn the once-per-run teaching
				// moment on a guard that demonstrated no reward.
				$saved = $outcome['result']['saved'] ?? [];
				if ($saved) {
					if (($_SESSION['cryptconquest_guard_taught'] ?? null) !== intval($run['id'])) {
						$_SESSION['cryptconquest_guard_taught'] = intval($run['id']);
						$names = array_map('cryptconquestCardLabel', $saved);
						cryptconquestFlash('PERFECT GUARD! You covered it exactly, so your strongest held: '
							. implode(' and ', $names) . ' return to your hand.', 'win', null, $saved);
					}
					// Always flagged, so the render can highlight them in hand.
					$_SESSION['cryptconquest_saved'] = array_map('cryptconquestCardArtKey', $saved);
				}
			} elseif (!empty($outcome['result']['rallied'])) {
				// Last Stand fires at most once per run, so this modal is the one
				// and only chance to explain what happened. Spell it out rather
				// than assuming the player connects an emptied hand, a survived
				// hit, and a sudden fresh draw on their own.
				$ls = $outcome['result'];
				$ls_cards = $ls['rallied_cards'] ?? [];
				$ls_atk = intval($ls['attack'] ?? 0);
				cryptconquestFlash(
					'LAST STAND! Your entire hand still fell short of the ' . $ls_atk . ' incoming damage -- '
					. 'but instead of falling, the blow is forgiven and you rally ' . count($ls_cards)
					. ' fresh card' . (count($ls_cards) === 1 ? '' : 's') . ' from the crypt to fight on. '
					. 'This can only happen once per run.',
					'win', null, $ls_cards
				);
				cryptconquestSfx('laststand');
			} elseif (!empty($outcome['result']['died'])) {
				cryptconquestFlash('The crypt claims you.', 'error');
			}
		}

	} elseif ($action === 'flip_jester') {
		$run = cryptconquestGetActiveRun($conn, $user_id);
		if ($run) {
			$outcome = cryptconquestDoFlipJester($conn, $user_id, intval($run['id']));
			if ($outcome) $final_run = $outcome['run'];
			if (!$outcome || !$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'] ?? 'No active run.', 'error');
			} else {
				cryptconquestFlash('Jester flipped -- hand discarded and refilled.', 'info');
			}
		}

	} elseif ($action === 'abandon') {
		$final_run = cryptconquestAbandonRun($conn, $user_id);
		cryptconquestFlash('Conquest abandoned.', 'info');
	}

	// Only report a run that actually FINISHED this request.
	if ($final_run && in_array($final_run['status'] ?? '', ['won', 'lost'], true)) {
		return $final_run;
	}
	return null;
}
