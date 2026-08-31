<?php
// cryptcrawl-actions.php — shared POST action handler for Crypt Crawl.
//
// Used by both cryptcrawl.php's own POST branch (still the no-JS fallback:
// a real <form method="post"> submit, full page reload after) and
// ajax/cryptcrawl-action.php (the normal path once JS has loaded: a fetch()
// that gets back just the #cc-game-area fragment, no navigation at all) —
// one copy of the actual game-action logic instead of two that could
// quietly drift apart.

// $source tags which flow the flash came from (e.g. 'flee'/'medkit'/
// 'laststand') so the client can selectively suppress just those categories
// (see the audio player's 🔕 button in cryptcrawl.php / #cc-flash-backdrop's
// data-source in cryptcrawl-render.php) without touching untagged flashes
// like Abandon Run's confirmation. null (the default) is never suppressible.
function cryptcrawlFlash($msg, $type = 'info', $source = null) {
	$_SESSION['cryptcrawl_flash'][] = ['msg' => $msg, 'type' => $type, 'source' => $source];
}

// Performs one Crypt Crawl action (start_run/play_card/flee/abandon) for
// $user_id, queuing any resulting flash message the same way the page
// always has. $post is $_POST (or an equivalent array) with at least
// 'action', and for play_card also 'card_index'/'use_weapon'.
// Returns the final $run for whichever action just ran (null for
// start_run/flee/unknown, or when there was no active run to act on) --
// added so callers (ajax/cryptcrawl-action.php, cryptcrawl.php's own no-JS
// POST handler) can know a run just ended (status won/lost) from data
// already resolved in memory, with zero further DB queries, and use that
// to guarantee the game-over confirmation renders even if the full
// cryptcrawlRenderGameArea() call that follows fails for any reason. See
// cryptcrawlMinimalGameOverHtml() in cryptcrawl-render.php.
function cryptcrawlHandleAction($conn, $user_id, $post) {
	if (!isset($_SESSION['cryptcrawl_flash'])) $_SESSION['cryptcrawl_flash'] = [];
	$action = $post['action'] ?? '';

	if ($action === 'start_run') {
		cryptcrawlStartRun($conn, $user_id);
		// One-shot signal read (and cleared) by cryptcrawlRenderGameArea()'s
		// #cc-mood output -- the audio player forces the Theme track
		// specifically on a fresh delve, not whatever the normal loop's
		// last-saved track happened to be (could be the Reprise, e.g. from
		// an earlier escape-from-danger this same session).
		$_SESSION['cryptcrawl_just_started'] = true;
		return null;

	} elseif ($action === 'play_card') {
		$run = cryptcrawlGetActiveRun($conn, $user_id);
		if (!$run) return null;
		$card_index = intval($post['card_index'] ?? -1);
		$use_weapon = isset($post['use_weapon']) && $post['use_weapon'] === '1';
		// Detect a diminished heal before playing it, so we can flash a
		// clear message — the small note under the Heal button wasn't
		// loud enough on its own (a player used two medkits back to back
		// and didn't notice the second one healed for less).
		$room_before = json_decode($run['room'], true) ?: [];
		$card_before = $room_before[$card_index] ?? null;
		$diminished_potion = $card_before && $card_before['type'] === 'potion' && intval($run['potion_used_this_room']) === 1;
		$second_wind_was_available = intval($run['second_wind_used'] ?? 0) === 0;
		// No flash for a run-ending outcome — the game_over screen's own
		// result panel says the same thing, better, and having both was
		// redundant (two near-identical sentences stacked on load).
		$updated = cryptcrawlPlayCard($conn, intval($run['id']), $card_index, $use_weapon);
		if ($diminished_potion) {
			$half_heal = max(1, intval(intval($card_before['rank']) / 2));
			cryptcrawlFlash("Half effect - you've already used a medkit this crypt. (+$half_heal HP)", 'info', 'medkit');
		}
		// Last Stand fired this exact play if it was available going in
		// and is now spent -- the only place that flag ever changes.
		if ($second_wind_was_available && $updated && intval($updated['second_wind_used']) === 1) {
			cryptcrawlFlash('LAST STAND! You refuse to fall - surviving at 1 HP. (once per delve)', 'win', 'laststand');
		}
		return $updated;

	} elseif ($action === 'flee') {
		$run = cryptcrawlGetActiveRun($conn, $user_id);
		if ($run) {
			$before = json_decode($run['room'], true) ?: [];
			$updated = cryptcrawlFleeRoom($conn, intval($run['id']));
			if ($updated && intval($updated['fled_last_room']) === 1 && count($before) === 4) {
				cryptcrawlFlash('You slipped past that crypt.', 'info', 'flee');
			} else {
				cryptcrawlFlash("Can't flee twice in a row - face the crypt.", 'error', 'flee');
			}
		}
		return null;

	} elseif ($action === 'abandon') {
		$updated = cryptcrawlAbandonRun($conn, $user_id);
		cryptcrawlFlash('Run abandoned.', 'info');
		return $updated;
	}
	return null;
}
