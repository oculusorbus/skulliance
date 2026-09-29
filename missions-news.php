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
<?php
/*
 * TWO LISTS, NOT ONE, and only one of them is allowed to shout.
 *
 * JUST OPENED is the thing this notice was built for: a long mission
 * lands while you are away, clears its level, opens the next rung, and
 * nothing says so -- then the next visit is spent pressing Start All
 * Free, the whole idle roster goes out on level 1s, and the new rung
 * stays unopened because there is nobody left to send. That is why the
 * band sits ABOVE the deployment bar: below it, the warning would arrive
 * after the press that causes the problem.
 *
 * NEVER RUN is different. A rung further down the ladder that you passed
 * over -- which happens whenever a mission is added BELOW where you
 * already are -- is worth knowing about, but it is not news and it was
 * not news last week either. Shown as a loud amber alert it nags forever
 * about something deliberately skipped. So it gets a quiet line, and
 * when there is nothing newly opened the whole band goes quiet with it.
 */
$ms_fresh = array(); $ms_old = array();
foreach ($ms_new_rungs as $r) {
	if (!empty($r['frontier'])) $ms_fresh[] = $r; else $ms_old[] = $r;
}
$ms_new_go = 0;
foreach ($ms_fresh as $r) if ($r['affordable'] && $r['has_squad']) $ms_new_go++;
?>
<?php if ($ms_fresh): ?>
<div class="ms-news">
	<div class="ms-news-head">
		<b><?php echo count($ms_fresh); ?> new mission<?php
			echo count($ms_fresh) === 1 ? '' : 's'; ?> just opened</b>
		<span><?php echo $ms_new_go
			? 'Send these before the bulk launchers take the crew.'
			: 'Nothing spare to send right now, so these stay open.'; ?></span>
	</div>
	<div class="ms-news-list">
	<?php foreach (array_slice($ms_fresh, 0, 6) as $r):
		$go  = $r['affordable'] && $r['has_squad'];
		$why = !$r['has_squad'] ? 'No idle NFTs'
		     : (!$r['affordable'] ? ms_n($r['shortfall']) . ' ' . $r['currency'] . ' short' : ''); ?>
		<?php /* Unlocked, so it opens either way -- reading it and looking
		         at the art is half the reason the notice exists. */ ?>
		<button type="button" class="ms-new<?php echo $go ? ' go' : ''; ?>"
			onclick="msOpenDrawer(<?php echo (int)$r['quest_id']; ?>)">
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
			<i class="ms-new-flag">New</i>
		</button>
	<?php endforeach; ?>
	</div>
	<?php if (count($ms_fresh) > 6): ?>
	<p class="ms-news-more"><?php echo count($ms_fresh) - 6; ?> more waiting on other projects.</p>
	<?php endif; ?>
	<?php if ($ms_old): ?>
	<?php include __DIR__ . '/missions-skipped.php'; ?>
	<?php endif; ?>
</div>
<?php elseif ($ms_old): ?>
<?php /* Nothing new: the quiet variant. Same information, no alarm. */ ?>
<div class="ms-news quiet">
	<?php include __DIR__ . '/missions-skipped.php'; ?>
</div>
<?php endif; ?>
