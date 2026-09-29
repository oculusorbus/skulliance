<?php
/*
 * missions-ladder.php — one project's missions, as a ladder.
 *
 * A PARTIAL, included by missions.php's first paint and by
 * ajax/mission-data.php when a different project is picked. Picking a
 * project already went through ajax before this rebuild; picking a MISSION
 * was the full page POST, and that is what the drawer replaces.
 *
 * LOCKED MISSIONS KEEP THEIR NAMES. The old renderer ran the title through
 * preg_replace('/[a-zA-Z_-]/','?') and swapped the art for a padlock, so a
 * newcomer's grid was mostly "?????? ####". You cannot want something you
 * cannot see. A locked rung here shows what it is, what it pays, and the
 * one thing you have to do to open it.
 */
if (!isset($ms_project)) $ms_project = 0;
if (!isset($ms_quests))  $ms_quests  = $ms_project ? mission_quests($conn, $ms_project) : array();
if (!function_exists('ms_n')) { function ms_n($v) { return number_format((float)$v); } }
if (!function_exists('ms_e')) { function ms_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
/* Which rungs here have never been run. Same set the notice at the top of
   the page is built from, so browsing a project agrees with it. */
if (!isset($ms_new_ids)) {
	$ms_new_ids = array();
	foreach (mission_frontier($conn) as $r) $ms_new_ids[(int)$r['quest_id']] = true;
}
?>
<?php if (!$ms_quests): ?>
	<div class="ms-empty">
		<h4>No missions for this project yet</h4>
		<p>Partner projects get their missions added as they are configured.
		   Pick another project on the left.</p>
	</div>
<?php else: foreach ($ms_quests as $q):
	$open  = empty($q['locked']);
	$can   = $open && $q['affordable'] && $q['has_squad'];
	/* WHY it cannot be launched, in the order a player would fix it. */
	$block = '';
	if (!$open)                  $block = 'Clear level ' . (int)$q['unlock_at'] . ' to open this';
	else if (!$q['has_squad'])   $block = 'No idle NFTs for this project';
	else if (!$q['affordable'])  $block = ms_n($q['shortfall']) . ' more ' . $q['currency'] . ' needed';
?>
	<div class="ms-quest<?php echo $open ? '' : ' locked'; echo $can ? ' can' : ''; ?>"
		<?php if ($can): ?>role="button" tabindex="0"
		onclick="msOpenDrawer(<?php echo (int)$q['quest_id']; ?>)"
		onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();msOpenDrawer(<?php
			echo (int)$q['quest_id']; ?>);}"<?php endif; ?>>
		<div class="ms-quest-art">
			<img src="<?php echo ms_e($q['image']); ?>" alt="" loading="lazy"
				onerror="this.src='/staking/icons/skull.png';">
			<span class="ms-quest-lvl">Lv <?php echo (int)$q['level']; ?></span>
			<?php if ($open && isset($ms_new_ids[(int)$q['quest_id']])): ?>
			<span class="ms-quest-new">New</span>
			<?php endif; ?>
			<?php if ((int)$q['running'] > 0): ?>
			<span class="ms-quest-run"><?php echo (int)$q['running']; ?> out</span>
			<?php endif; ?>
		</div>
		<div class="ms-quest-body">
			<span class="ms-quest-title"><?php echo ms_e($q['title']); ?></span>
			<span class="ms-quest-pay">
				<?php if ((float)$q['cost'] > 0): ?>
				<em><?php echo ms_n($q['cost']); ?></em> &rarr;
				<?php endif; ?>
				<b><?php echo ms_n($q['reward']); ?></b> <?php echo ms_e($q['currency']); ?>
			</span>
			<span class="ms-quest-meta"><?php echo (int)$q['duration']; ?> day<?php
				echo (int)$q['duration'] === 1 ? '' : 's';
				if ((int)$q['duration'] > 0) echo ' &middot; ' . ms_n($q['net_per_day']) . '/day'; ?></span>
			<?php if ($block): ?>
			<span class="ms-quest-block"><?php echo ms_e($block); ?></span>
			<?php else: ?>
			<span class="ms-quest-go">Send a crew &rarr;</span>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach; endif; ?>
