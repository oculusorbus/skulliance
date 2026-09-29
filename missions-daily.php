<?php
/*
 * missions-daily.php — the daily reward, in one strip.
 *
 * REPLACES seven full-width rows with the claim state buried under them.
 * A staker whose whole visit is "claim my daily" should be able to answer
 * "is there anything to claim" without reading a table, which is why this
 * sits directly under the masthead and why it collapses to a single line
 * once the day is done.
 *
 * THE IDS ARE A CONTRACT, exactly as in missions-field.php. skulliance.js's
 * dailyReward() does the claim and then writes into #reward, #claimed,
 * #progress_bar and #remaining and hides #claimRewardButton -- and it is
 * also what reveals the day-seven DHC Fighters trait through
 * DHC_SHOW_DROP(). Claiming works and pays out; this markup is shaped to
 * fit it rather than the other way round. All five ids are kept, and
 * dailyReward() sets display:flex on three of them, so their styling has
 * to survive being made a flex box.
 */
if (!isset($ms_daily)) $ms_daily = mission_daily($conn);
if (!function_exists('ms_n')) { function ms_n($v) { return number_format((float)$v); } }
if (!function_exists('ms_e')) { function ms_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!$ms_daily) return;
$md_today = null;
foreach ($ms_daily['days'] as $d) if (!empty($d['current'])) $md_today = $d;
?>
<div class="ms-daily<?php echo $ms_daily['eligible'] ? ' go' : ''; ?>">
	<div class="ms-daily-left">
		<span class="ms-daily-kick">Daily reward<?php
			echo $ms_daily['streak'] > 0 ? ' &middot; day ' . (int)$ms_daily['today'] . ' of 7' : ''; ?></span>

		<?php /* THE LADDER AS SEVEN CHIPS. Same information the seven rows
		         carried -- which days are done, what each pays -- in one line
		         you can read without scrolling. Hover gives the detail. */ ?>
		<div class="ms-daily-track">
		<?php foreach ($ms_daily['days'] as $d):
			$cls = $d['claimed'] ? 'done' : ($d['current'] ? 'now' : '');
			$tip = 'Day ' . $d['day'] . ': ' . $d['item'] . ' + ' . ms_n($d['amount'])
			     . ($d['currency'] !== '' ? ' ' . $d['currency'] : ' points'); ?>
			<span class="ms-day <?php echo $cls; ?>" title="<?php echo ms_e($tip); ?>">
				<img src="<?php echo ms_e($d['item_icon']); ?>" alt=""
					onerror="this.style.display='none'">
				<i><?php echo (int)$d['day']; ?></i>
			</span>
		<?php endforeach; ?>
		</div>
	</div>

	<div class="ms-daily-right">
		<?php if ($ms_daily['eligible']): ?>
			<?php /* #reward is where dailyReward() writes what you actually
			         drew, so it starts as the promise and becomes the result. */ ?>
			<div class="ms-daily-line" id="reward">
				<strong>Day <?php echo (int)$ms_daily['today']; ?>:</strong>
				<?php if ($md_today): ?>
				<img class="icon" src="<?php echo ms_e($md_today['item_icon']); ?>" alt=""
					onerror="this.style.display='none'">
				<span>+ <?php echo ms_n($md_today['amount']); ?> points</span>
				<?php endif; ?>
			</div>
			<?php /* Hidden until the claim lands; dailyReward() fills all three. */ ?>
			<div class="ms-daily-line" id="claimed" style="display:none;"></div>
			<div class="ms-daily-line" id="progress_bar" style="display:none;"></div>
			<div class="ms-daily-line" id="remaining" style="display:none;"></div>
			<input id="claimRewardButton" type="button" value="Claim reward"
				class="button" onclick="javascript:dailyReward();">
		<?php else: ?>
			<div class="ms-daily-line ms-daily-done"><strong>Claimed today</strong></div>
			<div class="ms-daily-line ms-daily-next"><?php echo $ms_daily['remaining']; ?></div>
			<div class="ms-daily-bar"><?php echo $ms_daily['bar']; ?></div>
		<?php endif; ?>
	</div>

	<div class="ms-daily-foot">
		<span><?php echo ms_n($ms_daily['total']); ?> full streaks completed</span>
		<form action="leaderboards.php" method="post">
			<input type="hidden" name="filterbystreak" value="monthly-streaks">
			<input type="submit" class="ms-daily-lb" value="Streak leaderboard">
		</form>
	</div>
</div>
