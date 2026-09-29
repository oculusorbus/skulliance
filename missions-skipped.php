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
<div class="ms-skipped">
	<span class="ms-skipped-label"><?php echo count($ms_old); ?> you have never run</span>
	<?php foreach (array_slice($ms_old, 0, 8) as $r): ?>
	<button type="button" class="ms-skip" onclick="msOpenDrawer(<?php echo (int)$r['quest_id']; ?>)"
		title="<?php echo ms_e($r['project'] . ' - level ' . (int)$r['level'] . ' - '
			. ms_n($r['reward']) . ' ' . $r['currency']); ?>">
		<img src="<?php echo ms_e($r['image']); ?>" alt="" loading="lazy"
			onerror="this.src='/staking/icons/skull.png';">
		<?php echo ms_e($r['title']); ?>
	</button>
	<?php endforeach; ?>
	<?php if (count($ms_old) > 8): ?>
	<span class="ms-skipped-label">+<?php echo count($ms_old) - 8; ?> more</span>
	<?php endif; ?>
</div>
