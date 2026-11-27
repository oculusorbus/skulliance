<?php
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
require_once __DIR__ . '/gauntlets-fight-lib.php';     // the turn-based fight
require_once __DIR__ . '/gauntlets-fight-render.php';  // shared with ajax/gauntlet-fight.php

$user_id = intval($_SESSION['userData']['user_id']);

// ── Flash messages via session ──────────────────────────────
if (!isset($_SESSION['gauntlet_flash'])) $_SESSION['gauntlet_flash'] = [];

function gauntletFlash($msg, $type = 'info') {
	$_SESSION['gauntlet_flash'][] = ['msg' => $msg, 'type' => $type];
}

// ── POST action handling — must run before any output ───────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';

	if ($action === 'start_run') {
		$run_id = gauntletStartRun($conn, $user_id);
		if (!$run_id) gauntletFlash('No available NFTs to draw a hand. Use your NFTs in staking first.', 'error');

	} elseif ($action === 'start_encounter') {
		$nft_id  = intval($_POST['gauntlet_pick_nft_id'] ?? 0);
		$run     = gauntletGetActiveRun($conn, $user_id);
		if ($run && $nft_id > 0) {
			$result = gauntletStartEncounter($conn, $user_id, intval($run['id']), $nft_id);
			if (!$result) gauntletFlash('Could not start encounter. Please try again.', 'error');
		}

	} elseif ($action === 'fast_forward') {
		$enc_id     = intval($_POST['encounter_id'] ?? 0);
		$new_nft_id = intval($_POST['new_nft_id'] ?? 0);
		if ($enc_id > 0 && $new_nft_id > 0) {
			$ok = gauntletFastForward($conn, $user_id, $enc_id, $new_nft_id);
			if (!$ok) gauntletFlash('Could not swap card. Make sure you have a Fast Forward item.', 'error');
		}

	} elseif ($action === 'fight_turn') {
		/*
		 * THE NO-JS PATH. ajax/gauntlet-fight.php does this without a
		 * navigation so the music keeps playing; this is the same turn
		 * through a plain form post, for a browser that cannot. The game
		 * is complete either way -- only the continuous soundtrack is
		 * lost, which is exactly the trade cryptcrawl.php makes.
		 */
		$enc_id = intval($_POST['encounter_id'] ?? 0);
		$turn   = (string)($_POST['turn'] ?? 'bare');
		$kind = $turn; $item = 0;
		if (strpos($turn, ':') !== false) { list($kind, $item) = explode(':', $turn, 2); $item = intval($item); }
		if ($enc_id > 0) {
			$res = gaunt_fight_turn($conn, $user_id, $enc_id, $kind, $item);
			if (empty($res['ok'])) gauntletFlash($res['why'] ?: 'That move is not available.', 'error');
		}

	} elseif ($action === 'close_fight') {
		$enc_id = intval($_POST['encounter_id'] ?? 0);
		if ($enc_id > 0) {
			/* The fight decided this over a dozen exchanges; this only
			   records it and pays it out. */
			$outcome = gauntletResolveFight($conn, $user_id, $enc_id);
			if ($outcome === 'win') {
				$run  = gauntletGetActiveRun($conn, $user_id);
				$rw_r = $conn->query("
					SELECT ge.run_id, op.currency AS opponent_currency
					FROM gauntlets_encounters ge
					INNER JOIN nfts on2       ON on2.id = ge.opponent_nft_id
					INNER JOIN collections oc ON oc.id  = on2.collection_id
					INNER JOIN projects op    ON op.id  = oc.project_id
					WHERE ge.id = $enc_id LIMIT 1
				");
				if ($rw_r && $rw_r->num_rows) {
					$rw      = $rw_r->fetch_assoc();
					$wnum_r2 = $conn->query("SELECT COUNT(*) AS c FROM gauntlets_encounters WHERE run_id=".intval($rw['run_id'])." AND outcome='win'");
					$wnum2   = ($wnum_r2 && $wnum_r2->num_rows) ? intval($wnum_r2->fetch_assoc()['c']) : 1;
					$enc_cons_r  = $conn->query("SELECT consumable_id FROM gauntlets_encounters_consumables WHERE encounter_id=$enc_id");
					$enc_cons    = [];
					if ($enc_cons_r) while ($ecr = $enc_cons_r->fetch_assoc()) $enc_cons[] = intval($ecr['consumable_id']);
					$is_dbl = in_array(GAUNTLET_C_DOUBLE, $enc_cons);
					$tx_cur_r = $conn->query("SELECT p.currency FROM transactions t INNER JOIN projects p ON p.id = t.project_id WHERE t.gauntlet_encounter_id = $enc_id AND t.user_id = $user_id AND t.type = 'credit' LIMIT 1");
					$actual_currency = ($tx_cur_r && $tx_cur_r->num_rows) ? $tx_cur_r->fetch_assoc()['currency'] : $rw['opponent_currency'];
					$_SESSION['gauntlet_last_result'] = [
						'outcome'    => 'win',
						'amount'     => ($wnum2 * 100) * ($is_dbl ? 2 : 1),
						'currency'   => $actual_currency,
						'win_number' => $wnum2,
					];
				}
				if (!$run) {
					gauntletFlash('Victory! You swept the gauntlet!', 'win');
					/*
					 * TRAIT DROP -- for SURVIVING THE GAUNTLET, not for winning a
					 * single encounter. A run is three fights; winning the first
					 * one is not the achievement, and hooking the per-encounter
					 * outcome paid out three times a run.
					 *
					 * No active run left is the game's own test for a sweep --
					 * the same condition the victory message uses, so the drop
					 * and the message can never disagree.
					 *
					 * Flagged rather than fired: this page is post-redirect-get,
					 * so a drop claimed here would be lost in the redirect.
					 */
					$_SESSION['dhcf_gauntlet_win'] = 1;
				} else {
					gauntletFlash('Win! Pick your next card.', 'win');
				}
			} elseif ($outcome === 'loss') {
				$_SESSION['gauntlet_last_result'] = ['outcome' => 'loss'];
				gauntletFlash('Defeat. Your run ends here.', 'loss');
			} else {
				gauntletFlash('Something went wrong. Please try again.', 'error');
			}
		}
	}

	header('Location: gauntlets.php');
	exit;
}

// Tell nginx not to buffer this response so the loader flushes to the browser immediately
header('X-Accel-Buffering: no');
include 'header.php';
?>
<style>
#gauntlet-loader {
	position: fixed; inset: 0;
	background: #07111d;
	z-index: 9999;
	display: flex; flex-direction: column;
	align-items: center; justify-content: center; gap: 20px;
	transition: opacity 0.5s ease;
}
#gauntlet-loader.fade-out { opacity: 0; pointer-events: none; }
@keyframes lb { to { width:90%; } }
.loader-bar-wrap { width:200px;height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden; }
.loader-bar      { height:100%;background:#00c8a0;width:0%;animation:lb 9s ease-out forwards; }
.loader-text     { font-size:.78rem;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase; }
</style>
<div id="gauntlet-loader">
	<div style="animation:lp 1.2s ease-in-out infinite;"><img src="/staking/pwa/skulliance-logo-icon.png" alt="" width="35" height="48"></div>
	<div class="loader-bar-wrap"><div class="loader-bar"></div></div>
	<div class="loader-text">Loading Gauntlet</div>
</div>
<?php
if (ob_get_level() > 0) { ob_flush(); flush(); }

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
?>
<style>
/* ── Gauntlet page ── */
.gauntlet-wrap        { max-width: 900px; margin: 0 auto; padding: 20px 16px 60px; }
.gauntlet-title       { font-size: 2rem; font-weight: 700; color: #00c8a0; letter-spacing: .05em; margin-bottom: 4px; text-transform: uppercase; }
.gauntlet-subtitle    { color: rgba(255,255,255,.45); font-size: .85rem; margin-bottom: 24px; }

/* Run progress bar */
.run-progress         { display: flex; gap: 10px; margin-bottom: 24px; }
.run-pip              { flex: 1; height: 6px; border-radius: 3px; background: rgba(255,255,255,.1); }
.run-pip.win          { background: #00c8a0; }
.run-pip.loss         { background: #e05555; }

/* Flash messages */
.flash                { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: .9rem; }
.flash.win            { background: rgba(0,200,160,.15); border: 1px solid #00c8a0; color: #00c8a0; }
.flash.loss           { background: rgba(224,85,85,.15);  border: 1px solid #e05555; color: #e05555; }
.flash.error          { background: rgba(224,85,85,.1);   border: 1px solid rgba(224,85,85,.4); color: #e05555; }
.flash.info           { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.15); color: rgba(255,255,255,.7); }

/* NFT cards */
.hand-grid            { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px; }
@media (max-width: 600px) { .hand-grid { grid-template-columns: repeat(2, 1fr); } }

.nft-card             { background: #002f44; border: 2px solid rgba(255,255,255,.08); border-radius: 10px; overflow: hidden; cursor: pointer; transition: border-color .2s, transform .15s; position: relative; }
.nft-card:hover:not(.played) { border-color: #00c8a0; transform: translateY(-3px); }
.nft-card.played      { opacity: .35; cursor: default; pointer-events: none; }
.nft-card.selected    { border-color: #00c8a0; box-shadow: 0 0 12px rgba(0,200,160,.35); }
.nft-card img         { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.nft-img              { background: url('icons/skull.png') center/40% no-repeat rgba(0,0,0,.15); }
.nft-card-info        { padding: 8px 10px; }
.nft-card-name        { font-size: .78rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nft-card-project     { font-size: .7rem; color: rgba(255,255,255,.4); margin-top: 2px; }
.played-label         { position: absolute; top: 6px; right: 6px; background: rgba(0,0,0,.7); color: rgba(255,255,255,.5); font-size: .65rem; padding: 2px 6px; border-radius: 3px; }

/* Encounter arena */
.arena                { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: start; margin-bottom: 24px; }
.arena-vs             { display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; color: rgba(255,255,255,.3); padding-top: 40px; }
@media (max-width: 600px) {
	.arena { gap: 8px; }
	.arena-vs { font-size: 1rem; padding-top: 30px; }
	.arena-card-label { font-size: .6rem; padding: 6px 6px 2px; }
	.arena-card-name  { font-size: .72rem; padding: 0 6px 3px; }
	.arena-card-project, .arena-card-user { font-size: .65rem; padding: 0 6px 6px; }
}
.arena-card           { background: #002f44; border: 2px solid rgba(255,255,255,.1); border-radius: 10px; overflow: hidden; }
.arena-card.player    { border-color: #00c8a0; }
.arena-card img       { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.arena-card-label     { font-size: .65rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.4); padding: 8px 10px 2px; text-align: center; }
.arena-card-name      { font-size: .82rem; font-weight: 600; color: #fff; padding: 0 10px 4px; text-align: center; }
.arena-card-project   { font-size: .72rem; color: rgba(255,255,255,.45); padding: 0 10px 4px; text-align: center; }
.arena-card-user      { display: flex; align-items: center; justify-content: center; gap: 5px; padding: 0 10px 10px; }
.arena-card-user img  { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.arena-card-user a    { font-size: .72rem; color: #00c8a0; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.arena-card-user a:hover { text-decoration: underline; }

/* Odds display */

/* Resource panel */

/* Action buttons */
.btn-fight            { width: 100%; padding: 14px; font-size: 1rem; font-weight: 700; background: #00c8a0; color: #000; border: none; border-radius: 8px; cursor: pointer; letter-spacing: .05em; transition: opacity .15s; margin-bottom: 16px; }
.btn-fight:hover      { opacity: .85; }
.btn-ff               { width: 100%; padding: 10px; font-size: .82rem; background: rgba(255,255,255,.07); color: rgba(255,255,255,.65); border: 1px solid rgba(255,255,255,.15); border-radius: 8px; cursor: pointer; margin-bottom: 10px; transition: all .15s; }
.btn-ff:hover         { background: rgba(255,255,255,.12); }
.btn-ff:disabled      { opacity: .3; cursor: default; }
.btn-start            { padding: 14px 40px; font-size: 1rem; font-weight: 700; background: #00c8a0; color: #000; border: none; border-radius: 8px; cursor: pointer; letter-spacing: .05em; transition: opacity .15s; }
.btn-start:hover      { opacity: .85; }

/* FF card picker overlay */
.ff-overlay           { display: none; background: rgba(0,0,0,.85); border: 1px solid rgba(255,255,255,.12); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
.ff-overlay.open      { display: block; }
.ff-overlay-title     { font-size: .8rem; color: rgba(255,255,255,.5); margin-bottom: 12px; }
.ff-hand-grid         { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }

/* Run results */
.run-results          { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 10px; padding: 20px; margin-bottom: 24px; text-align: center; }
.run-result-icon      { font-size: 3rem; margin-bottom: 8px; }
.run-result-title     { font-size: 1.4rem; font-weight: 700; color: #fff; margin-bottom: 4px; }
.run-result-sub       { font-size: .85rem; color: rgba(255,255,255,.45); }

/* Encounter history */
.history-list         { display: flex; flex-direction: column; gap: 8px; }
.history-row          { display: flex; align-items: center; gap: 12px; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.06); border-radius: 8px; padding: 10px 14px; }
.history-badge        { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .7rem; font-weight: 700; flex-shrink: 0; }
.history-badge.win    { background: rgba(0,200,160,.2); color: #00c8a0; }
.history-badge.loss   { background: rgba(224,85,85,.2); color: #e05555; }
.history-text         { font-size: .82rem; color: rgba(255,255,255,.7); flex: 1; }
.history-avatar       { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin: 0 3px 0 6px; }
.history-text a       { color: #00c8a0; text-decoration: none; font-size: .78rem; }
.history-text a:hover { text-decoration: underline; }
.history-meta         { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; flex-shrink: 0; }
.history-reward       { display: flex; align-items: center; gap: 4px; font-size: .78rem; font-weight: 600; white-space: nowrap; }
.history-reward.win   { color: #00c8a0; }
.history-reward.loss  { color: rgba(224,85,85,.75); }
.history-reward img   { width: 14px; height: 14px; object-fit: contain; vertical-align: middle; }
.history-reward .reward-random { font-size: .65rem; color: rgba(255,255,255,.3); }
.history-odds         { font-size: .72rem; color: rgba(255,255,255,.25); text-align: right; }

.section-heading      { font-size: .75rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.3); margin: 24px 0 10px; }

/* Win reward banner (pick_card state) */
.win-reward-banner    { display: flex; align-items: center; justify-content: center; gap: 10px; background: rgba(0,200,160,.1); border: 1px solid rgba(0,200,160,.35); border-radius: 8px; padding: 14px 20px; margin-bottom: 20px; }
.win-reward-amount    { font-size: 1.5rem; font-weight: 700; color: #00c8a0; line-height: 1; }
.win-reward-banner img { width: 30px; height: 30px; object-fit: contain; }
.win-reward-currency  { font-size: .8rem; color: rgba(255,255,255,.4); }

/* Fight animation overlay (pre-submit suspense) */

/* Result reveal overlay (post-fight modal) */
#result-reveal { position: fixed; inset: 0; z-index: 9998; background: rgba(7,17,29,.97); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; cursor: pointer; animation: rr-in .3s ease forwards; }
#result-reveal.hiding { animation: rr-out .4s ease forwards; }
.rr-accent  { position: absolute; inset: 0; pointer-events: none; }
#result-reveal.win  .rr-accent { box-shadow: inset 0 0 120px rgba(0,200,160,.18); }
#result-reveal.loss .rr-accent { box-shadow: inset 0 0 120px rgba(224,85,85,.18); }
.rr-icon    { font-size: 5rem; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .15s both; }
.rr-title   { font-size: 2.8rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .25s both; }
#result-reveal.win  .rr-title { color: #00c8a0; }
#result-reveal.loss .rr-title { color: #e05555; }
.rr-reward  { display: flex; align-items: center; gap: 10px; font-size: 1.6rem; font-weight: 700; color: #00c8a0; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .35s both; }
.rr-reward img { width: 32px; height: 32px; object-fit: contain; }
.rr-win-num { font-size: .78rem; color: rgba(255,255,255,.35); letter-spacing: .12em; text-transform: uppercase; animation: rr-pop .45s cubic-bezier(.18,.89,.32,1.28) .45s both; }
.rr-dismiss { font-size: .72rem; color: rgba(255,255,255,.2); letter-spacing: .1em; text-transform: uppercase; margin-top: 8px; animation: rr-pop .45s ease .8s both; }
@keyframes rr-in  { from { opacity: 0; } to { opacity: 1; } }
@keyframes rr-out { from { opacity: 1; } to { opacity: 0; pointer-events: none; } }
@keyframes rr-pop { from { opacity: 0; transform: scale(.55); } to { opacity: 1; transform: scale(1); } }
</style>

<div class="row">
<div class="main">
<div class="gauntlet-wrap">

	<div class="gauntlet-title">Gauntlet</div>
	<div class="gauntlet-subtitle">Draw a hand of <?php echo GAUNTLET_HAND_SIZE; ?> NFTs. Survive 3 opponents to sweep the run.</div>

	<?php foreach ($flashes as $f): ?>
		<div class="flash <?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['msg']); ?></div>
	<?php endforeach; ?>

	<?php if ($last_result): ?>
	<div id="result-reveal" class="<?php echo $last_result['outcome'] === 'win' ? 'win' : 'loss'; ?>" onclick="dismissResult()">
		<div class="rr-accent"></div>
		<?php if ($last_result['outcome'] === 'win'): ?>
		<div class="rr-icon">⚔️</div>
		<div class="rr-title">Victory!</div>
		<div class="rr-reward">
			+<?php echo number_format($last_result['amount']); ?>
			<img src="icons/<?php echo htmlspecialchars(strtolower($last_result['currency'])); ?>.png" onerror="this.src='icons/skull.png'" alt="">
			<?php echo htmlspecialchars($last_result['currency']); ?>
		</div>
		<div class="rr-win-num">Win <?php echo intval($last_result['win_number']); ?> of <?php echo GAUNTLET_MAX_WINS; ?></div>
		<?php else: ?>
		<div class="rr-icon"><img src="/staking/pwa/skulliance-logo-icon.png" alt="" width="58" height="80"></div>
		<div class="rr-title">Defeated</div>
		<?php endif; ?>
		<div class="rr-dismiss">Tap to continue</div>
	</div>
	<?php endif; ?>

	<?php if ($recent_run): ?>
	<div class="run-progress">
		<?php for ($i = 0; $i < GAUNTLET_MAX_WINS; $i++):
			$wins   = intval($run_stats['wins']   ?? 0);
			$losses = intval($run_stats['losses'] ?? 0);
			$cls = '';
			if ($i < $wins)   $cls = 'win';
			elseif ($losses && $i === $wins) $cls = 'loss';
		?>
		<div class="run-pip <?php echo $cls; ?>"></div>
		<?php endfor; ?>
	</div>
	<?php endif; ?>

	<?php
	// ── State: no run ───────────────────────────────────────
	if ($state === 'no_run'):
	?>
	<div style="text-align:center; padding: 40px 0;">
		<div style="font-size:3.5rem; margin-bottom:16px;">&#x2694;</div>
		<div style="color:rgba(255,255,255,.6); margin-bottom:24px; font-size:.9rem;">Enter the Gauntlet and prove your NFTs in battle.</div>
		<form method="POST">
			<input type="hidden" name="action" value="start_run">
			<button type="submit" class="btn-start">Enter the Gauntlet</button>
		</form>
	</div>

	<?php
	// ── State: run over ─────────────────────────────────────
	elseif ($state === 'run_over'):
		$wins   = intval($run_stats['wins']);
		$losses = intval($run_stats['losses']);
		$swept  = ($wins >= GAUNTLET_MAX_WINS);
	?>
	<div class="run-results">
		<div class="run-result-icon"><?php echo $swept ? '&#x1F3C6;' : '<img src="/staking/pwa/skulliance-logo-icon.png" alt="" width="35" height="48">'; ?></div>
		<div class="run-result-title"><?php echo $swept ? 'Gauntlet Swept!' : 'Run Over'; ?></div>
		<div class="run-result-sub"><?php echo $wins; ?> win<?php echo $wins !== 1 ? 's' : ''; ?> &middot; <?php echo $losses; ?> loss</div>
	</div>
	<?php
	// Show encounter history for completed run
	$hist_r = $conn->query("
		SELECT ge.outcome, grn.effective_project_id AS player_effective_project_id, ge.opponent_effective_project_id,
		       on2.name AS opponent_nft_name, op.name AS opponent_project_name, op.currency AS opponent_currency,
		       pp.currency AS player_currency,
		       (SELECT GROUP_CONCAT(gec.consumable_id ORDER BY gec.id) FROM gauntlets_encounters_consumables gec WHERE gec.encounter_id = ge.id) AS consumable_ids,
		       (SELECT p2.currency FROM transactions t2 INNER JOIN projects p2 ON p2.id = t2.project_id WHERE t2.gauntlet_encounter_id = ge.id AND t2.type = 'credit' LIMIT 1) AS actual_reward_currency,
		       ge.weapon_id, ge.armor_id,
		       ww.level AS weapon_level, aa.level AS armor_level,
		       ou.username AS opponent_username, ou.discord_id AS opponent_discord_id, ou.avatar AS opponent_avatar
		FROM gauntlets_encounters ge
		INNER JOIN gauntlets_nfts grn ON grn.run_id = ge.run_id AND grn.nft_id = ge.player_nft_id
		INNER JOIN nfts pn       ON pn.id  = ge.player_nft_id
		INNER JOIN collections pc ON pc.id = pn.collection_id
		INNER JOIN projects pp   ON pp.id  = pc.project_id
		INNER JOIN nfts on2      ON on2.id = ge.opponent_nft_id
		INNER JOIN collections oc ON oc.id = on2.collection_id
		INNER JOIN projects op   ON op.id  = oc.project_id
		LEFT JOIN  users ou      ON ou.id  = ge.opponent_user_id
		LEFT JOIN  weapons ww    ON ww.id  = ge.weapon_id
		LEFT JOIN  armor aa      ON aa.id  = ge.armor_id
		WHERE ge.run_id = ".intval($recent_run['id'])." AND ge.outcome != 'pending'
		ORDER BY ge.id ASC
	");
	if ($hist_r && $hist_r->num_rows):
	?>
	<div class="section-heading">Run History</div>
	<div class="history-list">
	<?php $hist_win_count = 0; while ($hr = $hist_r->fetch_assoc()):
		$base_wc      = gauntletCalculateWinChance(intval($hr['player_effective_project_id']), intval($hr['opponent_effective_project_id']));
		$hr_cons      = $hr['consumable_ids'] ? array_map('intval', explode(',', $hr['consumable_ids'])) : [];
		$is_double    = in_array(GAUNTLET_C_DOUBLE, $hr_cons);
		if ($hr['outcome'] === 'win') $hist_win_count++;
		$reward_amt   = ($hist_win_count * 100) * ($is_double ? 2 : 1);
		$cons_bonus   = 0; foreach ($hr_cons as $hc) $cons_bonus += ($consumable_bonuses[$hc] ?? 0);
		$effective_wc = min(100, $base_wc + $cons_bonus + intval($hr['weapon_level'] ?? 0) + intval($hr['armor_level'] ?? 0));
		$actual_currency = $hr['actual_reward_currency'] ?: $hr['opponent_currency'];
		if ($hr['outcome'] === 'win') {
			$reward_icon  = 'icons/' . strtolower($actual_currency) . '.png';
			$reward_label = '+' . $reward_amt;
			$reward_class = 'win';
		} else {
			$reward_icon  = 'icons/' . strtolower($hr['player_currency']) . '.png';
			$reward_label = 'opp +' . GAUNTLET_WIN_REWARD;
			$reward_class = 'loss';
		}
	?>
		<div class="history-row">
			<div class="history-badge <?php echo $hr['outcome']; ?>"><?php echo strtoupper(substr($hr['outcome'], 0, 1)); ?></div>
			<div class="history-text">vs <?php echo htmlspecialchars($hr['opponent_nft_name']); ?> (<?php echo htmlspecialchars($hr['opponent_project_name']); ?>)<?php if (!empty($hr['opponent_username'])): ?> &middot;<?php if (!empty($hr['opponent_discord_id']) && !empty($hr['opponent_avatar'])): ?><img class="history-avatar" src="<?php echo htmlspecialchars(avatar_url(htmlspecialchars($hr['opponent_discord_id']), htmlspecialchars($hr['opponent_avatar']))); ?>" alt="" onerror="this.src='icons/skull.png'"><?php endif; ?><a href="profile.php?username=<?php echo urlencode($hr['opponent_username']); ?>"><?php echo htmlspecialchars($hr['opponent_username']); ?></a><?php endif; ?></div>
			<div class="history-meta">
				<div class="history-reward <?php echo $reward_class; ?>"><?php echo $reward_label; ?><img src="<?php echo htmlspecialchars($reward_icon); ?>" onerror="this.src='icons/skull.png'"></div>
				<div class="history-odds"><?php echo $effective_wc; ?>%</div>
			</div>
		</div>
	<?php endwhile; ?>
	</div>
	<?php endif; ?>
	<div style="text-align:center; margin-top:24px;">
		<form method="POST">
			<input type="hidden" name="action" value="start_run">
			<button type="submit" class="btn-start">New Run</button>
		</form>
	</div>

	<?php
	// ── State: pick card ────────────────────────────────────
	elseif ($state === 'pick_card'):
		$wins = intval($run_stats['wins'] ?? 0);
	?>
	<?php if ($last_result && $last_result['outcome'] === 'win'): ?>
	<div class="win-reward-banner">
		<span class="win-reward-amount">+<?php echo number_format($last_result['amount']); ?></span>
		<img src="icons/<?php echo htmlspecialchars(strtolower($last_result['currency'])); ?>.png" onerror="this.src='icons/skull.png'">
		<span class="win-reward-currency"><?php echo htmlspecialchars($last_result['currency']); ?></span>
	</div>
	<?php endif; ?>
	<div class="section-heading">Your Hand — Pick a Card to Fight</div>
	<div class="hand-grid">
	<?php foreach ($hand as $card):
		$img = getIPFS($card['ipfs'], $card['collection_id'], $card['project_id']);
	?>
		<div class="nft-card <?php echo $card['played'] ? 'played' : ''; ?>" onclick="pickCard(<?php echo intval($card['id']); ?>)">
			<?php if ($card['played']): ?><span class="played-label">Used</span><?php endif; ?>
			<img class="nft-img" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" loading="lazy" onload="this.style.background='none'" onerror="this.src='icons/skull.png';this.style.background='none'">
			<div class="nft-card-info">
				<div class="nft-card-name"><?php echo htmlspecialchars($card['name']); ?></div>
				<div class="nft-card-project"><?php echo htmlspecialchars($card['project_name']); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
	</div>
	<form method="POST" id="pick-form">
		<input type="hidden" name="action" value="start_encounter">
		<input type="hidden" name="gauntlet_pick_nft_id" id="pick-nft-id" value="0">
	</form>

	<?php
	// ── State: encounter ────────────────────────────────────
	elseif ($state === 'encounter'):
		$enc = $pending_encounter;
		$player_img   = getIPFS($enc['player_ipfs'],   $enc['player_collection_id'],   $enc['player_project_id']);
		$opponent_img = getIPFS($enc['opponent_ipfs'], $enc['opponent_collection_id'], $enc['opponent_project_id_real']);
		$odds_class   = $win_chance_display >= 50 ? '' : 'danger';
	?>
	<?php
		$player_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : ($_SESSION['userData']['name'] ?? '');
		$player_discord_id = $_SESSION['userData']['discord_id'] ?? '';
		$player_avatar     = $_SESSION['userData']['avatar']     ?? '';
	?>
	<?php
	/*
	 * THE FIGHT REPLACES THE ARENA, THE ODDS BAR AND THE REVEAL OVERLAY.
	 *
	 * All three belonged to a coin flip: two portraits, a percentage, and
	 * a "crossing swords" animation to dramatise a single rand(1,100).
	 * There is a real fight here now, so the odds bar has nothing to
	 * describe and the reveal has nothing to reveal -- the exchanges ARE
	 * the drama. gauntlets-fight-render.php draws the portraits, because
	 * it is the thing that has to animate them.
	 */
	$gf_missing = gaunt_schema_missing($conn);
	if ($gf_missing): ?>
	<div class="flash error">
		<strong>The fight needs three columns that are not there yet.</strong>
		Missing: <?php echo htmlspecialchars(implode(', ', $gf_missing)); ?>.
		Run this once, then reload:
		<pre style="white-space:pre-wrap;margin:8px 0 0;font-size:.75rem;"><?php echo htmlspecialchars(gaunt_schema_sql()); ?></pre>
	</div>
	<?php else:
		$gf_enc = $enc;
		$gf_enc['player_img']   = $player_img;
		$gf_enc['opponent_img'] = $opponent_img;
		$gf_fight = gaunt_fight_start($conn, $user_id, $enc);
		gauntletRenderFight($gf_enc, $gf_fight,
			gaunt_loadout($conn, $user_id, intval($enc['run_id'])));
		include __DIR__ . '/gauntlets-fight-ui.php';
	endif; ?>

	<?php if ($ff_count > 0 && count($ff_hand) > 0): ?>
	<button class="btn-ff" onclick="toggleFF()" type="button"><img src="icons/fast-forward.png" onerror="this.style.display='none'" style="width:16px;height:16px;object-fit:contain;vertical-align:middle;margin-right:6px;">Fast Forward — Swap Card (<?php echo $ff_count; ?> available)</button>
	<div class="ff-overlay" id="ff-overlay">
		<div class="ff-overlay-title">Pick a replacement card from your hand</div>
		<div class="ff-hand-grid">
		<?php foreach ($ff_hand as $card):
			$img = getIPFS($card['ipfs'], $card['collection_id'], $card['project_id']);
		?>
			<div class="nft-card" onclick="submitFF(<?php echo intval($enc['id']); ?>, <?php echo intval($card['id']); ?>)">
				<img class="nft-img" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" onload="this.style.background='none'" onerror="this.src='icons/skull.png';this.style.background='none'">
				<div class="nft-card-info">
					<div class="nft-card-name"><?php echo htmlspecialchars($card['name']); ?></div>
					<div class="nft-card-project"><?php echo htmlspecialchars($card['project_name']); ?></div>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
	</div>
	<form method="POST" id="ff-form">
		<input type="hidden" name="action" value="fast_forward">
		<input type="hidden" name="encounter_id" value="<?php echo intval($enc['id']); ?>">
		<input type="hidden" name="new_nft_id" id="ff-new-nft-id" value="0">
	</form>
	<?php endif; ?>

	<?php
	/*
	 * THE LOADOUT PANEL IS GONE. It staked items BEFORE a single roll --
	 * pick a weapon, pick an armour, tick some consumables, watch a
	 * percentage move by four points, then flip a coin. The fight spends
	 * them a turn at a time instead, so the choice is made with the
	 * fight in front of you rather than blind, and the buttons that make
	 * it live in gauntlets-fight-render.php.
	 */
	?>

	<?php
	// Encounter history for current run (resolved only)
	$hist_r = $conn->query("
		SELECT ge.outcome, grn.effective_project_id AS player_effective_project_id, ge.opponent_effective_project_id,
		       on2.name AS opponent_nft_name, op.name AS opponent_project_name, op.currency AS opponent_currency,
		       pp.currency AS player_currency,
		       (SELECT GROUP_CONCAT(gec.consumable_id ORDER BY gec.id) FROM gauntlets_encounters_consumables gec WHERE gec.encounter_id = ge.id) AS consumable_ids,
		       (SELECT p2.currency FROM transactions t2 INNER JOIN projects p2 ON p2.id = t2.project_id WHERE t2.gauntlet_encounter_id = ge.id AND t2.type = 'credit' LIMIT 1) AS actual_reward_currency,
		       ge.weapon_id, ge.armor_id,
		       ww.level AS weapon_level, aa.level AS armor_level,
		       ou.username AS opponent_username, ou.discord_id AS opponent_discord_id, ou.avatar AS opponent_avatar
		FROM gauntlets_encounters ge
		INNER JOIN gauntlets_nfts grn ON grn.run_id = ge.run_id AND grn.nft_id = ge.player_nft_id
		INNER JOIN nfts pn       ON pn.id  = ge.player_nft_id
		INNER JOIN collections pc ON pc.id = pn.collection_id
		INNER JOIN projects pp   ON pp.id  = pc.project_id
		INNER JOIN nfts on2      ON on2.id = ge.opponent_nft_id
		INNER JOIN collections oc ON oc.id = on2.collection_id
		INNER JOIN projects op   ON op.id  = oc.project_id
		LEFT JOIN  users ou      ON ou.id  = ge.opponent_user_id
		LEFT JOIN  weapons ww    ON ww.id  = ge.weapon_id
		LEFT JOIN  armor aa      ON aa.id  = ge.armor_id
		WHERE ge.run_id = ".intval($active_run['id'])." AND ge.outcome != 'pending'
		ORDER BY ge.id ASC
	");
	if ($hist_r && $hist_r->num_rows):
	?>
	<div class="section-heading">This Run</div>
	<div class="history-list">
	<?php $hist_win_count = 0; while ($hr = $hist_r->fetch_assoc()):
		$base_wc      = gauntletCalculateWinChance(intval($hr['player_effective_project_id']), intval($hr['opponent_effective_project_id']));
		$hr_cons      = $hr['consumable_ids'] ? array_map('intval', explode(',', $hr['consumable_ids'])) : [];
		$is_double    = in_array(GAUNTLET_C_DOUBLE, $hr_cons);
		if ($hr['outcome'] === 'win') $hist_win_count++;
		$reward_amt   = ($hist_win_count * 100) * ($is_double ? 2 : 1);
		$cons_bonus   = 0; foreach ($hr_cons as $hc) $cons_bonus += ($consumable_bonuses[$hc] ?? 0);
		$effective_wc = min(100, $base_wc + $cons_bonus + intval($hr['weapon_level'] ?? 0) + intval($hr['armor_level'] ?? 0));
		$actual_currency = $hr['actual_reward_currency'] ?: $hr['opponent_currency'];
		if ($hr['outcome'] === 'win') {
			$reward_icon  = 'icons/' . strtolower($actual_currency) . '.png';
			$reward_label = '+' . $reward_amt;
			$reward_class = 'win';
		} else {
			$reward_icon  = 'icons/' . strtolower($hr['player_currency']) . '.png';
			$reward_label = 'opp +' . GAUNTLET_WIN_REWARD;
			$reward_class = 'loss';
		}
	?>
		<div class="history-row">
			<div class="history-badge <?php echo $hr['outcome']; ?>"><?php echo strtoupper(substr($hr['outcome'], 0, 1)); ?></div>
			<div class="history-text">vs <?php echo htmlspecialchars($hr['opponent_nft_name']); ?> (<?php echo htmlspecialchars($hr['opponent_project_name']); ?>)<?php if (!empty($hr['opponent_username'])): ?> &middot;<?php if (!empty($hr['opponent_discord_id']) && !empty($hr['opponent_avatar'])): ?><img class="history-avatar" src="<?php echo htmlspecialchars(avatar_url(htmlspecialchars($hr['opponent_discord_id']), htmlspecialchars($hr['opponent_avatar']))); ?>" alt="" onerror="this.src='icons/skull.png'"><?php endif; ?><a href="profile.php?username=<?php echo urlencode($hr['opponent_username']); ?>"><?php echo htmlspecialchars($hr['opponent_username']); ?></a><?php endif; ?></div>
			<div class="history-meta">
				<div class="history-reward <?php echo $reward_class; ?>"><?php echo $reward_label; ?><img src="<?php echo htmlspecialchars($reward_icon); ?>" onerror="this.src='icons/skull.png'"></div>
				<div class="history-odds"><?php echo $effective_wc; ?>%</div>
			</div>
		</div>
	<?php endwhile; ?>
	</div>
	<?php endif; ?>

	<?php endif; // end state switch ?>

</div><!-- .gauntlet-wrap -->
</div><!-- .main -->
</div><!-- .row -->

<div class="footer">
	<p>Skulliance<br>Copyright &copy; <span id="year"></span></p>
</div>
</div>
</div>
</body>
<?php $conn->close(); ?>
<script>
document.getElementById('year') && (document.getElementById('year').textContent = new Date().getFullYear());

// Show loader overlay (used before form submissions for immediate feedback)
/*
 * A FULL-SCREEN OVERLAY GOES TO THE TOP OF THE PAGE AND PINS IT THERE.
 *
 * REPORTED: picking a card lower down the list, or opening the items
 * panel, made the fight overlay and the loader "load off centre towards
 * the top". The CSS is not the problem -- both are position:fixed inset:0
 * with justify-content:center, and rendered against the real stylesheets
 * while scrolled 900px down they measure full-viewport with their content
 * dead centre. It is the scroll position.
 *
 * Picking a card POSTs and navigates. You had to scroll down to reach that
 * card, so the browser restores that scroll on the next page -- and this
 * page STREAMS, flushing the loader before the rest of the document
 * exists. The overlay is laid out against a viewport that is still being
 * established and then the page jumps underneath it, which on iOS leaves
 * the fixed layer anchored where it started rather than where the viewport
 * ended up. The further down the list you picked, the further off it sat.
 *
 * So: put the window back at the top, and hold it there while the overlay
 * is up. Both halves are needed. Scrolling alone still lets a stray touch
 * move the page behind a modal; locking alone leaves it locked at whatever
 * offset it inherited.
 */
function lockForOverlay() {
	window.scrollTo(0, 0);
	document.body.style.overflow = 'hidden';
}
function unlockAfterOverlay() {
	document.body.style.overflow = '';
}

function showLoader(msg) {
	var loader = document.getElementById('gauntlet-loader');
	if (!loader) return;
	lockForOverlay();
	var txt = loader.querySelector('.loader-text');
	if (txt && msg) txt.textContent = msg;
	// Reset bar animation by replacing the element
	var bar = loader.querySelector('.loader-bar');
	if (bar) { var nb = bar.cloneNode(true); bar.parentNode.replaceChild(nb, bar); }
	loader.classList.remove('fade-out');
	loader.style.display = 'flex';
	loader.style.opacity = '1';
}

/*
 * THIS PAGE ALWAYS STARTS AT THE TOP, and that is the other half of the
 * fix above. Every move here is a POST that reloads the page, so the
 * browser restores wherever you were scrolled -- and the first thing this
 * page shows is a full-screen loader, which has nothing to do with the
 * card you happened to be looking at. The restore is what put the overlay
 * and the viewport out of step in the first place.
 *
 * scrollRestoration is set as early as the script runs rather than on
 * unload, so it covers a back-navigation into the page too.
 */
if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

/* The loader is in the markup and visible before any of this runs, and a
   result reveal is printed server-side after a fight -- so whatever the
   browser did with the scroll, start level with the overlay. */
(function(){
	if (document.getElementById('gauntlet-loader') || document.getElementById('result-reveal')) {
		lockForOverlay();
	}
})();

// Fade out page loader now that DOM is fully rendered
(function(){
	var loader = document.getElementById('gauntlet-loader');
	if (loader) {
		loader.classList.add('fade-out');
		setTimeout(function(){
			loader.style.display = 'none';
			/* Only give the page back if nothing else is covering it: the
			   result reveal is printed server-side and outlives the loader
			   by up to four seconds. */
			if (!document.getElementById('result-reveal')
			    || document.getElementById('result-reveal').style.display === 'none') {
				unlockAfterOverlay();
			}
		}, 500);
	}
})();

/* Show the loader on every form submission. The old exclusion for
   #fight-form went with it: that form staked a loadout and rolled once,
   and the fight posts a turn at a time through ajax/gauntlet-fight.php. */
document.querySelectorAll('form[method="POST"]').forEach(function(f) {
	if (f.id === 'gf-form') return;   /* the fight swaps a fragment, not a page */
	f.addEventListener('submit', function() {
		var action = (f.querySelector('[name="action"]') || {}).value || '';
		var msg = action === 'fast_forward' ? 'Swapping Card…' : 'Loading Gauntlet';
		showLoader(msg);
	});
});

// Result reveal — auto-dismiss after 4s or on tap
(function(){
	var rr = document.getElementById('result-reveal');
	if (!rr) return;
	var t = setTimeout(dismissResult, 4000);
	rr._autoTimer = t;
})();
function dismissResult() {
	var rr = document.getElementById('result-reveal');
	if (!rr || rr.classList.contains('hiding')) return;
	clearTimeout(rr._autoTimer);
	rr.classList.add('hiding');
	setTimeout(function(){ rr.style.display = 'none'; unlockAfterOverlay(); }, 400);
}

// Fight animation — show suspense overlay, then submit

// Pick card from hand
function pickCard(nftId) {
	document.querySelectorAll('.nft-card').forEach(c => c.classList.remove('selected'));
	event.currentTarget.classList.add('selected');
	document.getElementById('pick-nft-id').value = nftId;
	showLoader('Loading Gauntlet');
	setTimeout(() => document.getElementById('pick-form').submit(), 120);
}


// Resource selection — consumables are multi-select (toggle); weapon/armor are single-select


// Resource tab switching

// Fast Forward overlay
function toggleFF() {
	const ov = document.getElementById('ff-overlay');
	if (ov) ov.classList.toggle('open');
}

function submitFF(encId, newNftId) {
	document.getElementById('ff-new-nft-id').value = newNftId;
	showLoader('Swapping Card…');
	document.getElementById('ff-form').submit();
}
</script>

<?php include 'dhc-dropmodal.php'; ?>
<?php if (!empty($_SESSION['dhcf_gauntlet_win'])): unset($_SESSION['dhcf_gauntlet_win']); ?>
<script>
/* Gauntlets pays Effects for SWEEPING a gauntlet -- all three encounters, no
   loss. Claimed once: the flag is cleared as it is read, so refreshing the
   victory page cannot farm it. The server enforces the daily cap regardless. */
if (window.DHC_DROP) DHC_DROP({ game: 'gauntlets', value: 1, delay: 900 });
</script>
<?php endif; ?>
</html>
