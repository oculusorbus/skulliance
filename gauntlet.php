<?php
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';

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
		$nft_id  = intval($_POST['nft_id'] ?? 0);
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

	} elseif ($action === 'resolve_encounter') {
		$enc_id       = intval($_POST['encounter_id']  ?? 0);
		$consumable_id = intval($_POST['consumable_id'] ?? 0);
		$weapon_id    = intval($_POST['weapon_id']     ?? 0);
		$armor_id     = intval($_POST['armor_id']      ?? 0);
		if ($enc_id > 0) {
			$outcome = gauntletResolveEncounter($conn, $user_id, $enc_id, $consumable_id, $weapon_id, $armor_id);
			if ($outcome === 'win') {
				$run = gauntletGetActiveRun($conn, $user_id);
				if (!$run) gauntletFlash('Victory! You swept the gauntlet!', 'win');
				else       gauntletFlash('Win! Pick your next card.', 'win');
			} elseif ($outcome === 'loss') {
				gauntletFlash('Defeat. Your run ends here.', 'loss');
			} else {
				gauntletFlash('Something went wrong. Please try again.', 'error');
			}
		}
	}

	header('Location: gauntlet.php');
	exit;
}

include 'header.php';

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
.nft-card-info        { padding: 8px 10px; }
.nft-card-name        { font-size: .78rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nft-card-project     { font-size: .7rem; color: rgba(255,255,255,.4); margin-top: 2px; }
.played-label         { position: absolute; top: 6px; right: 6px; background: rgba(0,0,0,.7); color: rgba(255,255,255,.5); font-size: .65rem; padding: 2px 6px; border-radius: 3px; }

/* Encounter arena */
.arena                { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: start; margin-bottom: 24px; }
@media (max-width: 600px) { .arena { grid-template-columns: 1fr; } }
.arena-vs             { display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; color: rgba(255,255,255,.3); padding-top: 40px; }
.arena-card           { background: #002f44; border: 2px solid rgba(255,255,255,.1); border-radius: 10px; overflow: hidden; }
.arena-card.player    { border-color: #00c8a0; }
.arena-card img       { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.arena-card-label     { font-size: .65rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.4); padding: 8px 10px 2px; }
.arena-card-name      { font-size: .82rem; font-weight: 600; color: #fff; padding: 0 10px 4px; }
.arena-card-project   { font-size: .72rem; color: rgba(255,255,255,.45); padding: 0 10px 10px; }

/* Odds display */
.odds-bar-wrap        { margin-bottom: 20px; }
.odds-label           { font-size: .8rem; color: rgba(255,255,255,.5); margin-bottom: 6px; }
.odds-bar             { height: 10px; border-radius: 5px; background: rgba(224,85,85,.35); position: relative; overflow: hidden; }
.odds-bar-fill        { height: 100%; border-radius: 5px; background: #00c8a0; transition: width .5s ease; }
.odds-pct             { font-size: 1.5rem; font-weight: 700; color: #00c8a0; margin-bottom: 4px; }
.odds-pct.danger      { color: #e05555; }

/* Resource panel */
.resource-panel       { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 8px; padding: 14px; margin-bottom: 20px; }
.resource-title       { font-size: .75rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.35); margin-bottom: 10px; }
.resource-tabs        { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.resource-tab         { padding: 4px 12px; border-radius: 4px; font-size: .78rem; cursor: pointer; background: rgba(255,255,255,.06); color: rgba(255,255,255,.5); border: 1px solid transparent; transition: all .15s; position: relative; }
.resource-tab.active  { background: rgba(0,200,160,.15); color: #00c8a0; border-color: #00c8a0; }
.resource-tab.has-selection::after { content: ''; position: absolute; top: 3px; right: 3px; width: 6px; height: 6px; border-radius: 50%; background: #00c8a0; }
.resource-section     { display: none; }
.resource-section.active { display: block; }
.resource-item        { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 6px; cursor: pointer; border: 1px solid transparent; margin-bottom: 6px; transition: all .15s; }
.resource-item:hover  { background: rgba(255,255,255,.05); }
.resource-item.selected { background: rgba(0,200,160,.1); border-color: #00c8a0; }
.resource-item img    { width: 28px; height: 28px; object-fit: contain; }
.resource-item-name   { font-size: .8rem; color: rgba(255,255,255,.8); flex: 1; }
.resource-item-qty    { font-size: .75rem; color: rgba(255,255,255,.4); }
.resource-none        { font-size: .8rem; color: rgba(255,255,255,.25); padding: 6px 0; }
.resource-effect      { display: block; font-size: .7rem; color: rgba(255,255,255,.35); margin-top: 2px; }
.resource-item.selected .resource-effect { color: #00c8a0; }

/* Action buttons */
.btn-fight            { width: 100%; padding: 14px; font-size: 1rem; font-weight: 700; background: #00c8a0; color: #000; border: none; border-radius: 8px; cursor: pointer; letter-spacing: .05em; transition: opacity .15s; }
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
.history-odds         { font-size: .75rem; color: rgba(255,255,255,.3); }
.history-avatar       { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; vertical-align: middle; margin: 0 3px 0 6px; }
.history-text a       { color: #00c8a0; text-decoration: none; font-size: .78rem; }
.history-text a:hover { text-decoration: underline; }
.arena-opponent-user  { display: flex; align-items: center; gap: 6px; padding: 4px 10px 10px; }
.arena-opponent-user img  { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; }
.arena-opponent-user a    { font-size: .75rem; color: #00c8a0; text-decoration: none; }
.arena-opponent-user a:hover { text-decoration: underline; }

.section-heading      { font-size: .75rem; text-transform: uppercase; letter-spacing: .1em; color: rgba(255,255,255,.3); margin: 24px 0 10px; }
</style>

<div class="row">
<div class="main">
<div class="gauntlet-wrap">

	<div class="gauntlet-title">Gauntlet</div>
	<div class="gauntlet-subtitle">Draw a hand of <?php echo GAUNTLET_HAND_SIZE; ?> NFTs. Survive 3 opponents to sweep the run.</div>

	<?php foreach ($flashes as $f): ?>
		<div class="flash <?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['msg']); ?></div>
	<?php endforeach; ?>

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
		<div class="run-result-icon"><?php echo $swept ? '&#x1F3C6;' : '&#x1F480;'; ?></div>
		<div class="run-result-title"><?php echo $swept ? 'Gauntlet Swept!' : 'Run Over'; ?></div>
		<div class="run-result-sub"><?php echo $wins; ?> win<?php echo $wins !== 1 ? 's' : ''; ?> &middot; <?php echo $losses; ?> loss</div>
	</div>
	<?php
	// Show encounter history for completed run
	$hist_r = $conn->query("
		SELECT ge.outcome, grn.effective_project_id AS player_effective_project_id, ge.opponent_effective_project_id,
		       on2.name AS opponent_nft_name, op.name AS opponent_project_name, ge.consumable_id, ge.weapon_id, ge.armor_id,
		       ou.username AS opponent_username, ou.discord_id AS opponent_discord_id, ou.avatar AS opponent_avatar
		FROM gauntlet_encounters ge
		INNER JOIN gauntlet_run_nfts grn ON grn.run_id = ge.run_id AND grn.nft_id = ge.player_nft_id
		INNER JOIN nfts on2      ON on2.id = ge.opponent_nft_id
		INNER JOIN collections oc ON oc.id = on2.collection_id
		INNER JOIN projects op   ON op.id  = oc.project_id
		LEFT JOIN  users ou      ON ou.id  = ge.opponent_user_id
		WHERE ge.run_id = ".intval($recent_run['id'])." AND ge.outcome != 'pending'
		ORDER BY ge.id ASC
	");
	if ($hist_r && $hist_r->num_rows):
	?>
	<div class="section-heading">Run History</div>
	<div class="history-list">
	<?php while ($hr = $hist_r->fetch_assoc()):
		$base_wc = gauntletCalculateWinChance(intval($hr['player_effective_project_id']), intval($hr['opponent_effective_project_id']));
	?>
		<div class="history-row">
			<div class="history-badge <?php echo $hr['outcome']; ?>"><?php echo strtoupper(substr($hr['outcome'], 0, 1)); ?></div>
			<div class="history-text">vs <?php echo htmlspecialchars($hr['opponent_nft_name']); ?> (<?php echo htmlspecialchars($hr['opponent_project_name']); ?>)<?php if (!empty($hr['opponent_username'])): ?> &middot;<?php if (!empty($hr['opponent_discord_id']) && !empty($hr['opponent_avatar'])): ?><img class="history-avatar" src="https://cdn.discordapp.com/avatars/<?php echo htmlspecialchars($hr['opponent_discord_id']); ?>/<?php echo htmlspecialchars($hr['opponent_avatar']); ?>.png" alt=""><?php endif; ?><a href="profile.php?username=<?php echo urlencode($hr['opponent_username']); ?>"><?php echo htmlspecialchars($hr['opponent_username']); ?></a><?php endif; ?></div>
			<div class="history-odds">Base <?php echo $base_wc; ?>%</div>
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
	<div class="section-heading">Your Hand — Pick a Card to Fight</div>
	<div class="hand-grid">
	<?php foreach ($hand as $card):
		$img = getIPFS($card['ipfs'], $card['collection_id'], $card['project_id']);
	?>
		<div class="nft-card <?php echo $card['played'] ? 'played' : ''; ?>" onclick="pickCard(<?php echo intval($card['id']); ?>)">
			<?php if ($card['played']): ?><span class="played-label">Used</span><?php endif; ?>
			<img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" loading="lazy">
			<div class="nft-card-info">
				<div class="nft-card-name"><?php echo htmlspecialchars($card['name']); ?></div>
				<div class="nft-card-project"><?php echo htmlspecialchars($card['project_name']); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
	</div>
	<form method="POST" id="pick-form">
		<input type="hidden" name="action" value="start_encounter">
		<input type="hidden" name="nft_id" id="pick-nft-id" value="0">
	</form>

	<?php
	// ── State: encounter ────────────────────────────────────
	elseif ($state === 'encounter'):
		$enc = $pending_encounter;
		$player_img   = getIPFS($enc['player_ipfs'],   $enc['player_collection_id'],   $enc['player_project_id']);
		$opponent_img = getIPFS($enc['opponent_ipfs'], $enc['opponent_collection_id'], $enc['opponent_project_id_real']);
		$odds_class   = $win_chance_display >= 50 ? '' : 'danger';
	?>
	<div class="arena">
		<div class="arena-card player">
			<div class="arena-card-label">Your Card</div>
			<img src="<?php echo htmlspecialchars($player_img); ?>" alt="<?php echo htmlspecialchars($enc['player_nft_name']); ?>">
			<div class="arena-card-name"><?php echo htmlspecialchars($enc['player_nft_name']); ?></div>
			<div class="arena-card-project"><?php echo htmlspecialchars($enc['player_project_name']); ?></div>
		</div>
		<div class="arena-vs">VS</div>
		<div class="arena-card">
			<div class="arena-card-label">Opponent</div>
			<img src="<?php echo htmlspecialchars($opponent_img); ?>" alt="<?php echo htmlspecialchars($enc['opponent_nft_name']); ?>">
			<div class="arena-card-name"><?php echo htmlspecialchars($enc['opponent_nft_name']); ?></div>
			<div class="arena-card-project"><?php echo htmlspecialchars($enc['opponent_project_name']); ?></div>
			<?php if (!empty($enc['opponent_username'])): ?>
			<div class="arena-opponent-user">
				<?php if (!empty($enc['opponent_discord_id']) && !empty($enc['opponent_avatar'])): ?>
				<img src="https://cdn.discordapp.com/avatars/<?php echo htmlspecialchars($enc['opponent_discord_id']); ?>/<?php echo htmlspecialchars($enc['opponent_avatar']); ?>.png" alt="">
				<?php endif; ?>
				<a href="profile.php?username=<?php echo urlencode($enc['opponent_username']); ?>"><?php echo htmlspecialchars($enc['opponent_username']); ?></a>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="odds-bar-wrap">
		<div class="odds-pct <?php echo $odds_class; ?>" id="odds-pct"><?php echo $win_chance_display; ?>% win chance</div>
		<div class="odds-label" id="odds-label">Base odds — select a resource to see its effect</div>
		<div class="odds-bar"><div class="odds-bar-fill" id="odds-fill" style="width:<?php echo $win_chance_display; ?>%"></div></div>
	</div>

	<?php if ($ff_count > 0 && count($ff_hand) > 0): ?>
	<button class="btn-ff" onclick="toggleFF()" type="button">&#x21BA; Fast Forward — Swap Card (<?php echo $ff_count; ?> available)</button>
	<div class="ff-overlay" id="ff-overlay">
		<div class="ff-overlay-title">Pick a replacement card from your hand</div>
		<div class="ff-hand-grid">
		<?php foreach ($ff_hand as $card):
			$img = getIPFS($card['ipfs'], $card['collection_id'], $card['project_id']);
		?>
			<div class="nft-card" onclick="submitFF(<?php echo intval($enc['id']); ?>, <?php echo intval($card['id']); ?>)">
				<img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>">
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

	<form method="POST" id="fight-form">
		<input type="hidden" name="action" value="resolve_encounter">
		<input type="hidden" name="encounter_id" value="<?php echo intval($enc['id']); ?>">
		<input type="hidden" name="consumable_id" id="fight-consumable" value="0">
		<input type="hidden" name="weapon_id"     id="fight-weapon"    value="0">
		<input type="hidden" name="armor_id"      id="fight-armor"     value="0">

		<?php if (!empty($consumables_inv) || !empty($weapons_inv) || !empty($armor_inv)): ?>
		<div class="resource-panel">
			<div class="resource-title">Deploy Resources (optional — 1 per category)</div>
			<div class="resource-tabs">
				<?php if (!empty($consumables_inv)): ?>
				<div class="resource-tab active" id="tab-btn-consumable" onclick="switchTab('consumable', this)">Items</div>
				<?php endif; ?>
				<?php if (!empty($weapons_inv)): ?>
				<div class="resource-tab <?php echo empty($consumables_inv) ? 'active' : ''; ?>" id="tab-btn-weapon" onclick="switchTab('weapon', this)">Weapons</div>
				<?php endif; ?>
				<?php if (!empty($armor_inv)): ?>
				<div class="resource-tab <?php echo (empty($consumables_inv) && empty($weapons_inv)) ? 'active' : ''; ?>" id="tab-btn-armor" onclick="switchTab('armor', this)">Armor</div>
				<?php endif; ?>
			</div>

			<?php if (!empty($consumables_inv)): ?>
			<div class="resource-section active" id="tab-consumable">
				<?php foreach ($consumables_inv as $ci):
					$icon = 'icons/' . strtolower(str_replace(['%', ' '], ['', '-'], $ci['name'])) . '.png';
				?>
				<?php $c_bonus = $consumable_bonuses[intval($ci['consumable_id'])] ?? 0; ?>
				<div class="resource-item" onclick="selectResource('consumable', <?php echo intval($ci['consumable_id']); ?>, this)" data-type="consumable" data-id="<?php echo intval($ci['consumable_id']); ?>" data-bonus="<?php echo $c_bonus; ?>">
					<img src="<?php echo htmlspecialchars($icon); ?>" onerror="this.style.display='none'">
					<span class="resource-item-name">
						<?php echo htmlspecialchars($ci['name']); ?>
						<?php if (isset($consumable_effects[intval($ci['consumable_id'])])): ?>
						<span class="resource-effect"><?php echo $consumable_effects[intval($ci['consumable_id'])]; ?></span>
						<?php endif; ?>
					</span>
					<span class="resource-item-qty">x<?php echo intval($ci['amount']); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if (!empty($weapons_inv)): ?>
			<div class="resource-section <?php echo empty($consumables_inv) ? 'active' : ''; ?>" id="tab-weapon">
				<?php foreach ($weapons_inv as $wi): ?>
				<div class="resource-item" onclick="selectResource('weapon', <?php echo intval($wi['item_id']); ?>, this)" data-type="weapon" data-id="<?php echo intval($wi['item_id']); ?>" data-bonus="<?php echo intval($wi['weapon_level']); ?>">
					<span class="resource-item-name">
						<?php echo htmlspecialchars($wi['weapon_name']); ?> (Lv <?php echo intval($wi['weapon_level']); ?>)
						<span class="resource-effect">+<?php echo intval($wi['weapon_level']); ?>% win chance — consumed on use</span>
					</span>
					<span class="resource-item-qty">x<?php echo intval($wi['quantity']); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if (!empty($armor_inv)): ?>
			<div class="resource-section <?php echo (empty($consumables_inv) && empty($weapons_inv)) ? 'active' : ''; ?>" id="tab-armor">
				<?php foreach ($armor_inv as $ai): ?>
				<div class="resource-item" onclick="selectResource('armor', <?php echo intval($ai['item_id']); ?>, this)" data-type="armor" data-id="<?php echo intval($ai['item_id']); ?>" data-bonus="<?php echo intval($ai['armor_level']); ?>">
					<span class="resource-item-name">
						<?php echo htmlspecialchars($ai['armor_name']); ?> (Lv <?php echo intval($ai['armor_level']); ?>)
						<span class="resource-effect">+<?php echo intval($ai['armor_level']); ?>% win chance — consumed on use</span>
					</span>
					<span class="resource-item-qty">x<?php echo intval($ai['quantity']); ?></span>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<button type="submit" class="btn-fight">&#x2694; Fight!</button>
	</form>

	<?php
	// Encounter history for current run (resolved only)
	$hist_r = $conn->query("
		SELECT ge.outcome, grn.effective_project_id AS player_effective_project_id, ge.opponent_effective_project_id,
		       on2.name AS opponent_nft_name, op.name AS opponent_project_name,
		       ou.username AS opponent_username, ou.discord_id AS opponent_discord_id, ou.avatar AS opponent_avatar
		FROM gauntlet_encounters ge
		INNER JOIN gauntlet_run_nfts grn ON grn.run_id = ge.run_id AND grn.nft_id = ge.player_nft_id
		INNER JOIN nfts on2      ON on2.id = ge.opponent_nft_id
		INNER JOIN collections oc ON oc.id = on2.collection_id
		INNER JOIN projects op   ON op.id  = oc.project_id
		LEFT JOIN  users ou      ON ou.id  = ge.opponent_user_id
		WHERE ge.run_id = ".intval($active_run['id'])." AND ge.outcome != 'pending'
		ORDER BY ge.id ASC
	");
	if ($hist_r && $hist_r->num_rows):
	?>
	<div class="section-heading">This Run</div>
	<div class="history-list">
	<?php while ($hr = $hist_r->fetch_assoc()):
		$base_wc = gauntletCalculateWinChance(intval($hr['player_effective_project_id']), intval($hr['opponent_effective_project_id']));
	?>
		<div class="history-row">
			<div class="history-badge <?php echo $hr['outcome']; ?>"><?php echo strtoupper(substr($hr['outcome'], 0, 1)); ?></div>
			<div class="history-text">vs <?php echo htmlspecialchars($hr['opponent_nft_name']); ?> (<?php echo htmlspecialchars($hr['opponent_project_name']); ?>)<?php if (!empty($hr['opponent_username'])): ?> &middot;<?php if (!empty($hr['opponent_discord_id']) && !empty($hr['opponent_avatar'])): ?><img class="history-avatar" src="https://cdn.discordapp.com/avatars/<?php echo htmlspecialchars($hr['opponent_discord_id']); ?>/<?php echo htmlspecialchars($hr['opponent_avatar']); ?>.png" alt=""><?php endif; ?><a href="profile.php?username=<?php echo urlencode($hr['opponent_username']); ?>"><?php echo htmlspecialchars($hr['opponent_username']); ?></a><?php endif; ?></div>
			<div class="history-odds">Base <?php echo $base_wc; ?>%</div>
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

// Pick card from hand
function pickCard(nftId) {
	document.querySelectorAll('.nft-card').forEach(c => c.classList.remove('selected'));
	event.currentTarget.classList.add('selected');
	document.getElementById('pick-nft-id').value = nftId;
	setTimeout(() => document.getElementById('pick-form').submit(), 120);
}

var gauntletBaseOdds = <?php echo isset($win_chance_display) ? intval($win_chance_display) : 0; ?>;

// Resource selection — 1 per type; types are independent
function selectResource(type, id, el) {
	const alreadySelected = el.dataset.selected === '1';
	// Deselect all items of this type only
	document.querySelectorAll('.resource-item[data-type="' + type + '"]').forEach(i => { i.classList.remove('selected'); i.dataset.selected = '0'; });
	if (!alreadySelected) {
		el.classList.add('selected');
		el.dataset.selected = '1';
		if (type === 'consumable') document.getElementById('fight-consumable').value = id;
		if (type === 'weapon')     document.getElementById('fight-weapon').value     = id;
		if (type === 'armor')      document.getElementById('fight-armor').value      = id;
	} else {
		if (type === 'consumable') document.getElementById('fight-consumable').value = 0;
		if (type === 'weapon')     document.getElementById('fight-weapon').value     = 0;
		if (type === 'armor')      document.getElementById('fight-armor').value      = 0;
	}
	// Update tab dot indicator
	var tabBtn = document.getElementById('tab-btn-' + type);
	if (tabBtn) tabBtn.classList.toggle('has-selection', !alreadySelected);
	updateOddsDisplay();
}

function updateOddsDisplay() {
	var bonus = 0;
	var anySelected = false;
	document.querySelectorAll('.resource-item[data-selected="1"]').forEach(function(sel) {
		bonus += parseInt(sel.dataset.bonus || '0', 10);
		anySelected = true;
	});
	var total = Math.min(100, gauntletBaseOdds + bonus);
	var pct   = document.getElementById('odds-pct');
	var fill  = document.getElementById('odds-fill');
	var lbl   = document.getElementById('odds-label');
	if (pct) {
		pct.textContent = total + '% win chance';
		pct.className   = 'odds-pct' + (total < 50 ? ' danger' : '');
	}
	if (fill) fill.style.width = total + '%';
	if (lbl) {
		if (bonus > 0)      lbl.textContent = 'Base ' + gauntletBaseOdds + '% + ' + bonus + '% from resources = ' + total + '%';
		else if (anySelected) lbl.textContent = 'Base ' + gauntletBaseOdds + '% — selected items do not affect odds';
		else                lbl.textContent = 'Base odds — select resources to see their effect';
	}
}

// Resource tab switching
function switchTab(tab, el) {
	document.querySelectorAll('.resource-tab').forEach(t => t.classList.remove('active'));
	document.querySelectorAll('.resource-section').forEach(s => s.classList.remove('active'));
	el.classList.add('active');
	const sec = document.getElementById('tab-' + tab);
	if (sec) sec.classList.add('active');
}

// Fast Forward overlay
function toggleFF() {
	const ov = document.getElementById('ff-overlay');
	if (ov) ov.classList.toggle('open');
}

function submitFF(encId, newNftId) {
	document.getElementById('ff-new-nft-id').value = newNftId;
	document.getElementById('ff-form').submit();
}
</script>
</html>
