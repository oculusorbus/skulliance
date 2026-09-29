<?php
/*
 * missions-news.php -- rungs that opened while you were not looking.
 *
 * A PARTIAL, included by missions.php's first paint and re-rendered by
 * ajax/mission-data.php after anything that launches a mission, because
 * launching a rung is exactly what stops it being "never run".
 *
 * IT SITS ABOVE THE DEPLOYMENT BAR ON PURPOSE. The failure it fixes, in the
 * holder's words: a long mission lands, clears a level and opens a new rung,
 * and the next visit is spent pressing Start All Free -- which sends the
 * whole idle roster out on level 1s, leaving nobody for the rung that just
 * opened. Below the bar, this notice would arrive after the press that
 * causes the problem. See mission_frontier() for what counts as new.
 */
if (!isset($ms_new_rungs)) $ms_new_rungs = mission_frontier($conn);
if (!function_exists('ms_n')) { function ms_n($v) { return number_format((float)$v); } }
if (!function_exists('ms_e')) { function ms_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
?>
<?php if ($ms_new_rungs): ?>
<?php
/*
 * NEWLY UNLOCKED -- and this sits ABOVE the deployment bar on purpose.
 *
 * The whole failure it fixes is: a long mission lands, clears a level
 * and opens a new rung, and the next visit is spent pressing Start All
 * Free -- which sends the entire idle roster out on level 1s, leaving
 * nobody for the rung that just opened. Putting this below the bar
 * would put it after the press that causes the problem.
 */
$ms_new_go = 0;
foreach ($ms_new_rungs as $r) if ($r['affordable'] && $r['has_squad']) $ms_new_go++;
?>
<div class="ms-news">
	<div class="ms-news-head">
<b><?php echo count($ms_new_rungs); ?> mission<?php
	echo count($ms_new_rungs) === 1 ? '' : 's'; ?> you have never run <?php
	echo count($ms_new_rungs) === 1 ? 'is' : 'are'; ?> open</b>
<span><?php echo $ms_new_go
	? 'Send these before the bulk launchers take the crew.'
	: 'Nothing spare to send right now -- these stay open.'; ?></span>
	</div>
	<div class="ms-news-list">
	<?php foreach (array_slice($ms_new_rungs, 0, 6) as $r):
$go = $r['affordable'] && $r['has_squad'];
$why = !$r['has_squad'] ? 'No idle NFTs'
     : (!$r['affordable'] ? ms_n($r['shortfall']) . ' ' . $r['currency'] . ' short' : ''); ?>
<button type="button" class="ms-new<?php echo $go ? ' go' : ''; ?>"
	<?php if ($go): ?>onclick="msOpenDrawer(<?php echo (int)$r['quest_id']; ?>)"<?php
	else: ?>onclick="msJumpToProject(<?php echo (int)$r['project_id']; ?>)"<?php endif; ?>>
	<img src="<?php echo ms_e($r['image']); ?>" alt="" loading="lazy"
		onerror="this.src='/staking/icons/skull.png';">
	<span class="ms-new-body">
		<span class="ms-new-title"><?php echo ms_e($r['title']); ?></span>
		<span class="ms-new-sub"><?php echo ms_e($r['project']); ?> &middot; Level <?php
			echo (int)$r['level']; ?> &middot; <?php echo ms_n($r['reward']); ?> <?php
			echo ms_e($r['currency']); ?></span>
		<span class="ms-new-act<?php echo $go ? '' : ' warn'; ?>"><?php
			echo $go ? 'Send a crew &rarr;' : ms_e($why); ?></span>
	</span>
	<?php if (!empty($r['frontier'])): ?><i class="ms-new-flag">New</i><?php endif; ?>
</button>
	<?php endforeach; ?>
	</div>
	<?php if (count($ms_new_rungs) > 6): ?>
	<p class="ms-news-more"><?php echo count($ms_new_rungs) - 6; ?> more waiting on other projects.</p>
	<?php endif; ?>
</div>
<?php endif; ?>
