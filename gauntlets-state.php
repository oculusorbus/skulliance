<?php
/*
 * gauntlets-state.php -- everything the Gauntlet page needs to know
 * before it can draw itself. No output.
 *
 * Shared by gauntlets.php and ajax/gauntlet-page.php so a round change
 * computes state exactly once, the same way, whichever asked for it.
 */
if (!defined('GAUNTLET_STATE_OK')) { http_response_code(404); exit; }

// ── Load state ──────────────────────────────────────────────
$recent_run         = gauntletGetMostRecentRun($conn, $user_id);
$run_over           = $recent_run ? gauntletIsRunOver($conn, intval($recent_run['id'])) : false;
$active_run         = ($recent_run && !$run_over) ? $recent_run : null;
$pending_encounter  = $active_run ? gauntletGetPendingEncounter($conn, intval($active_run['id'])) : null;
$hand               = $active_run ? gauntletGetHand($conn, intval($active_run['id']))              : [];
$run_stats          = $recent_run ? gauntletGetRunStats($conn, intval($recent_run['id']))           : null;

// State: no_run | pick_card | encounter | run_over
if (!$recent_run)           $state = 'no_run';
elseif ($run_over)          $state = 'run_over';
elseif ($pending_encounter) $state = 'encounter';
else                        $state = 'pick_card';

// Collect flash messages
$flashes = $_SESSION['gauntlet_flash'];
$_SESSION['gauntlet_flash'] = [];

// Result reveal (set during POST, consumed once on next GET)
$last_result = $_SESSION['gauntlet_last_result'] ?? null;
unset($_SESSION['gauntlet_last_result']);

// Gear inventory for encounter resource panel
$gear_inventory = ($state === 'encounter') ? getGearInventory($conn, $user_id) : [];
$weapons_inv = array_filter($gear_inventory, fn($g) => $g['type'] === 'weapon' && $g['quantity'] > 0);
$armor_inv   = array_filter($gear_inventory, fn($g) => $g['type'] === 'armor'  && $g['quantity'] > 0);

// Effect labels shown in resource panel
$consumable_effects = [
	GAUNTLET_C_100    => '+4% win chance',
	GAUNTLET_C_75     => '+3% win chance',
	GAUNTLET_C_50     => '+2% win chance',
	GAUNTLET_C_25     => '+1% win chance',
	GAUNTLET_C_DOUBLE => '2× rewards this fight',
	GAUNTLET_C_RANDOM => 'reward paid in a random project currency',
];
// Odds bonus per consumable (0 = no odds effect)
$consumable_bonuses = [
	GAUNTLET_C_100 => 4,
	GAUNTLET_C_75  => 3,
	GAUNTLET_C_50  => 2,
	GAUNTLET_C_25  => 1,
];

// Consumable inventory for encounter resource panel (exclude Fast Forward)
$consumables_inv = [];
if ($state === 'encounter') {
	$cons_result = $conn->query("
		SELECT a.consumable_id, a.amount, c.name
		FROM amounts a
		INNER JOIN consumables c ON c.id = a.consumable_id
		WHERE a.user_id = $user_id
		  AND a.amount > 0
		  AND a.consumable_id NOT IN (" . GAUNTLET_C_FF . ")
		ORDER BY a.consumable_id ASC
	");
	if ($cons_result) while ($row = $cons_result->fetch_assoc()) $consumables_inv[] = $row;
}

// Fast Forward availability for encounter state
$ff_count = ($state === 'encounter') ? getCurrentAmount($conn, $user_id, GAUNTLET_C_FF) : 0;
$ff_hand  = ($state === 'encounter') ? array_filter($hand, fn($n) => !$n['played'] && intval($n['id']) !== intval($pending_encounter['player_nft_id'])) : [];

// Win chance for display in encounter state
$win_chance_display = 0;
if ($state === 'encounter') {
	$win_chance_display = gauntletCalculateWinChance(
		intval($pending_encounter['player_effective_project_id']),
		intval($pending_encounter['opponent_effective_project_id'])
	);
}

/**
 * Which track the page should be playing.
 *
 * ONE TRACK FOR THE WHOLE RUN. It used to switch on the fighter's
 * health -- frantic under 50%, doom under 25% -- and rounds are far too
 * short for that to read as anything but the song restarting. The
 * theme now runs from the moment the page opens and is not interrupted
 * by a round, a win or a swap. Only the END of a run changes it.
 */
function gaunt_page_mood($state, $run_stats) {
	if ($state !== 'run_over') return 'theme';
	$wins = (int)($run_stats['wins'] ?? 0);
	return $wins >= GAUNTLET_MAX_WINS ? 'win' : 'lose';
}
