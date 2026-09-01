<?php
// cryptconquest-actions.php — shared POST action handler for Crypt Conquest.
//
// Same shape as cryptcrawl-actions.php: used by both cryptconquest.php's
// own POST branch (no-JS fallback, full page reload) and
// ajax/cryptconquest-action.php (the normal path once JS has loaded), so
// there's one copy of the actual game-action logic instead of two that
// could quietly drift apart.

function cryptconquestFlash($msg, $type = 'info') {
	$_SESSION['cryptconquest_flash'][] = ['msg' => $msg, 'type' => $type];
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
			if ($outcome) $final_run = $outcome['run'];
			if (!$outcome) {
				cryptconquestFlash('No active run.', 'error');
			} elseif (!$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'], 'error');
			} elseif (!empty($outcome['result']['defeated'])) {
				cryptconquestFlash(!empty($outcome['result']['won']) ? '👑 The last King falls -- the Necropolis is yours!' : 'Enemy defeated!', 'win');
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
			} elseif (!empty($outcome['result']['rallied'])) {
				cryptconquestFlash("LAST STAND! Your hand alone couldn't cover it -- you refuse to fall. (once per run)", 'win');
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
