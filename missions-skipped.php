<?php
/*
 * missions-skipped.php -- rungs you passed over, as a quiet row of chips.
 *
 * Deliberately not cards and deliberately not amber. These are unlocked
 * missions with no missions row at all, which usually means a mission was
 * added to a ladder BELOW where the player already was. Worth being able
 * to find; not worth an alert every single visit for the rest of time.
 */
?>
<?php
/*
 * The obvious question about this row is the right one -- "if I never ran
 * these, how is the level above them open?" -- but there is more than one
 * answer and this partial cannot tell which:
 *
 *   the rung was ADDED to the ladder after the player climbed past, which
 *   quests.id ordering does show (mission_frontier's added_later), or
 *
 *   a HIGHER level was cleared without this one, which the old page made
 *   possible for the admin account -- getMissions() rendered the submit
 *   form for locked missions when the discord id matched, and
 *   startMission() never re-checked the lock.
 *
 * So the label states the fact and nothing more, and the reason goes on
 * the individual chip only where the evidence supports it.
 * missions-probe.php tells the cases apart from the actual rows.
 */
?>
<div class="ms-skipped">
	<span class="ms-skipped-label"><?php echo count($ms_old); ?> unlocked, never run</span>
	<?php foreach (array_slice($ms_old, 0, 8) as $r): ?>
	<button type="button" class="ms-skip" onclick="msOpenDrawer(<?php echo (int)$r['quest_id']; ?>)"
		title="<?php echo ms_e($r['project'] . ' - level ' . (int)$r['level'] . ' - '
			. ms_n($r['reward']) . ' ' . $r['currency']
			. (!empty($r['added_later']) ? ' - added to this ladder after you passed it' : '')); ?>">
		<img src="<?php echo ms_e($r['image']); ?>" alt="" loading="lazy"
			onerror="this.src='/staking/icons/skull.png';">
		<?php echo ms_e($r['title']); ?>
	</button>
	<?php endforeach; ?>
	<?php if (count($ms_old) > 8): ?>
	<span class="ms-skipped-label">+<?php echo count($ms_old) - 8; ?> more</span>
	<?php endif; ?>
</div>
