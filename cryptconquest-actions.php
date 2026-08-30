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
function cryptconquestHandleAction($conn, $user_id, $post) {
	if (!isset($_SESSION['cryptconquest_flash'])) $_SESSION['cryptconquest_flash'] = [];
	$action = $post['action'] ?? '';

	if ($action === 'start_run') {
		cryptconquestStartRun($conn, $user_id);

	} elseif ($action === 'play') {
		$run = cryptconquestGetActiveRun($conn, $user_id);
		if ($run) {
			$indices = array_values(array_unique(array_map('intval', (array)($post['card_indices'] ?? []))));
			$outcome = cryptconquestDoPlay($conn, $user_id, intval($run['id']), $indices);
			if (!$outcome) {
				cryptconquestFlash('No active run.', 'error');
			} elseif (!$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'], 'error');
			} elseif (!empty($outcome['result']['defeated'])) {
				cryptconquestFlash(!empty($outcome['result']['won']) ? '👑 The last King falls -- the kingdom is yours!' : 'Enemy defeated!', 'win');
			}
		}

	} elseif ($action === 'yield') {
		$run = cryptconquestGetActiveRun($conn, $user_id);
		if ($run) {
			$outcome = cryptconquestDoYield($conn, $user_id, intval($run['id']));
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
			if (!$outcome) {
				cryptconquestFlash('No active run.', 'error');
			} elseif (!$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'], 'error');
			} elseif (!empty($outcome['result']['rallied'])) {
				cryptconquestFlash("LAST RALLY! Your hand alone couldn't cover it -- you refuse to fall. (once per run)", 'win');
			} elseif (!empty($outcome['result']['died'])) {
				cryptconquestFlash('The crypt claims you.', 'error');
			}
		}

	} elseif ($action === 'flip_jester') {
		$run = cryptconquestGetActiveRun($conn, $user_id);
		if ($run) {
			$outcome = cryptconquestDoFlipJester($conn, $user_id, intval($run['id']));
			if (!$outcome || !$outcome['result']['ok']) {
				cryptconquestFlash($outcome['result']['error'] ?? 'No active run.', 'error');
			} else {
				cryptconquestFlash('Jester flipped -- hand discarded and refilled.', 'info');
			}
		}

	} elseif ($action === 'abandon') {
		cryptconquestAbandonRun($conn, $user_id);
		cryptconquestFlash('Conquest abandoned.', 'info');
	}
}
