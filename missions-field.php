<?php
/*
 * missions-field.php — the "in the field" list.
 *
 * A PARTIAL, included both by missions.php's first paint and by
 * ajax/mission-data.php when the list needs repainting after a launch, a
 * retreat or a bulk deployment. One file so the markup cannot drift between
 * the version you land on and the version you get back.
 *
 * THE IDS ARE A CONTRACT. skulliance.js's completeMissions() reveals each
 * mission's outcome in place -- it writes into mission-result-, currency-,
 * mission-reward- and consumable-, and adds .success or .failure to
 * mission-row-. retreat() hides retreat-button-. Claiming is the money path
 * and it worked; this card is built to fit it rather than the other way
 * round. Renaming any of these silently breaks the claim reveal.
 */
if (!isset($ms_active)) $ms_active = mission_active($conn, defined('MS_FIELD_CAP') ? MS_FIELD_CAP : 0);
if (!isset($ms_total))  $ms_total  = mission_active_total($conn);
if (!function_exists('ms_n')) { function ms_n($v) { return number_format((float)$v); } }
if (!function_exists('ms_e')) { function ms_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$ms_ready_ids = array(); $ms_ready_quests = array();
foreach ($ms_active as $a) if (!empty($a['ready'])) {
	$ms_ready_ids[]    = (int)$a['mission_id'];
	$ms_ready_quests[] = (int)$a['quest_id'];
}
?>
<?php if (!$ms_active): ?>
	<div class="ms-empty">
		<h4>Nothing out right now</h4>
		<p>Pick a mission below and send a crew. Most run for a day or two;
		   you can retreat at any point and get your points and items back.</p>
	</div>
<?php else: ?>
	<?php if ($ms_ready_ids): ?>
	<?php /* Claims go through the existing completeMissions(), which expects
	         two comma-separated lists in the same order. */ ?>
	<div class="ms-claimbar" id="claim-missions-button">
		<div><b><?php echo count($ms_ready_ids); ?> mission<?php
			echo count($ms_ready_ids) === 1 ? '' : 's'; ?> landed</b>
			<span>Collect to see which made it back.</span></div>
		<button type="button" class="button" onclick="msClaimAll(this, '<?php
			echo implode(',', $ms_ready_ids); ?>', '<?php
			echo implode(',', $ms_ready_quests); ?>')">Collect all</button>
	</div>
	<?php endif; ?>
	<div class="ms-cards">
	<?php foreach ($ms_active as $a): $mid = (int)$a['mission_id']; ?>
		<div class="ms-mcard<?php echo !empty($a['ready']) ? ' ready' : ''; ?>" id="mission-row-<?php echo $mid; ?>">
			<div class="ms-mcard-art">
				<img src="<?php echo ms_e($a['image']); ?>" alt="" loading="lazy"
					onerror="this.src='/staking/icons/skull.png';">
			</div>
			<div class="ms-mcard-body">
				<div class="ms-mcard-top">
					<div>
						<span class="ms-mcard-title"><?php echo ms_e($a['title']); ?></span>
						<span class="ms-mcard-sub"><?php echo ms_e($a['project']); ?> &middot; Level <?php
							echo (int)$a['level']; ?></span>
					</div>
					<div class="ms-mcard-time">
						<span class="ms-mcard-time-label"><?php
							echo !empty($a['ready']) ? 'Landed' : 'Time left'; ?></span>
						<?php if (empty($a['ready'])): ?>
						<span class="ms-mcard-clock countdown" data-deadline="<?php echo (int)$a['due']; ?>">--</span>
						<?php else: ?>
						<span class="ms-mcard-clock go">Ready</span>
						<?php endif; ?>
					</div>
				</div>

				<div class="ms-mcard-stats">
					<span><i>Reward</i><b id="mission-reward-<?php echo $mid; ?>"><?php
						echo ms_n($a['reward']); ?> <span id="currency-<?php echo $mid; ?>"><?php
						echo ms_e($a['currency']); ?></span></b></span>
					<span><i>Success</i><b class="<?php echo $a['success'] >= 100 ? 'ms-sure' : ''; ?>"><?php
						echo (int)$a['success']; ?>%</b></span>
					<span><i>Crew</i><b><?php echo (int)$a['nfts']; ?></b></span>
					<span><i>Runs</i><b><?php echo (int)$a['duration']; ?>d<?php
						echo !empty($a['fast']) ? ' <em>fast</em>' : ''; ?></b></span>
				</div>

				<div class="ms-mcard-foot">
					<div class="ms-mcard-items" id="consumable-<?php echo $mid; ?>">
						<?php foreach ($a['items'] as $it): ?>
						<img src="<?php echo ms_e($it['icon']); ?>" title="<?php echo ms_e($it['name']); ?>"
							alt="<?php echo ms_e($it['name']); ?>" onerror="this.style.display='none'">
						<?php endforeach; ?>
					</div>
					<div class="ms-mcard-act" id="mission-result-<?php echo $mid; ?>">
						<?php if (empty($a['ready'])): ?>
						<button type="button" class="ms-retreat" id="retreat-button-<?php echo $mid; ?>"
							onclick="retreat('<?php echo $mid; ?>', '<?php echo (int)$a['quest_id']; ?>');">Retreat</button>
						<?php else: ?>
						<button type="button" class="ms-collect" onclick="msClaimAll(this, '<?php
							echo $mid; ?>', '<?php echo (int)$a['quest_id']; ?>')">Collect</button>
						<?php endif; ?>
					</div>
				</div>

				<div class="ms-mcard-bar"><i style="width:<?php echo (int)$a['percent']; ?>%"></i></div>
			</div>
		</div>
	<?php endforeach; ?>
	</div>
	<?php if ($ms_total > count($ms_active)): ?>
	<?php /* Everything ready is already above, so what is hidden is only
	         still-running -- say that, rather than implying something
	         claimable is out of sight. */ ?>
	<div class="ms-more">
		<span><?php echo number_format($ms_total - count($ms_active)); ?> more still out</span>
		<button type="button" class="ms-more-btn" onclick="msShowAllField(this)">Show all <?php
			echo number_format($ms_total); ?></button>
	</div>
	<?php endif; ?>
<?php endif; ?>
