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
$ms_admin = mission_is_admin();
/*
 * Which rungs here have never been run, and WHICH KIND.
 *
 * Two different things, and calling both "New" made the badge meaningless:
 * a project can show level 7 as the rung that just opened AND level 5 as
 * one that was passed over, because clearing a level is what unlocks the
 * next and nothing forces you to run every rung on the way up. Only the
 * frontier -- cleared + 1 -- is actually new. The rest were skipped, which
 * is still worth flagging but is not news.
 */
if (!isset($ms_new_ids)) {
	$ms_new_ids = array();
	foreach (mission_frontier($conn) as $r) $ms_new_ids[(int)$r['quest_id']] = !empty($r['frontier']);
}
?>
<?php if (!$ms_quests): ?>
	<div class="ms-empty">
		<h4>No missions for this project yet</h4>
		<p>Partner projects get their missions added as they are configured.
		   Pick another project on the left.</p>
	</div>
<?php else: foreach ($ms_quests as $q):
	$open = empty($q['locked']);
	/*
	 * UNLOCKED MEANS OPENABLE, full stop.
	 *
	 * This used to require a crew and the points as well, so a mission you
	 * had earned but could not staff right now was dead to the touch -- you
	 * could not read its description or look at the art the artist made for
	 * it. Whether you can LAUNCH is a separate question, answered inside.
	 */
	/* An admin can open a locked rung to check how it is configured; the
	   drawer will not let it be launched. Everyone else: unlocked only. */
	$can   = $open || $ms_admin;
	$ready = $open && $q['affordable'] && $q['has_squad'];
	/* WHY it cannot be launched, in the order a player would fix it. */
	$block = '';
	if (!$open)                  $block = 'Clear level ' . (int)$q['unlock_at'] . ' to open this';
	/* Nothing home means nothing can go out, item or not -- see
	   mission_launch(). Worth saying, because a pack full of 100% items
	   looks like it should be enough and is not. */
	else if (!$q['has_squad'])   $block = 'Nothing home to send';
	else if (!$q['affordable'])  $block = ms_n($q['shortfall']) . ' more ' . $q['currency'] . ' needed';
?>
	<div class="ms-quest<?php echo $open ? '' : ' locked'; echo $can ? ' can' : ''; echo $ready ? ' ready' : ''; ?>"
		<?php if ($can): ?>role="button" tabindex="0"
		onclick="msOpenDrawer(<?php echo (int)$q['quest_id']; ?>)"
		onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();msOpenDrawer(<?php
			echo (int)$q['quest_id']; ?>);}"<?php endif; ?>>
		<?php
		/*
		 * A LOCKED RUNG IS HIDDEN AGAIN, deliberately. I had shown the real
		 * title and art dimmed, on the reasoning that you cannot want what
		 * you cannot see -- but a ladder you can read to the top spoils
		 * every reveal the artists built, and the whole point of a level
		 * gate is that the next one is unknown. The requirement line below
		 * still says exactly what to do, so it is a locked door with a sign
		 * on it rather than a mystery.
		 */
		/*
		 * THE LADDER LOOKS THE SAME TO EVERYONE, admin included.
		 *
		 * The admin exception belongs to CLICKING a locked rung, not to
		 * reading it off the grid -- $can above. Inspecting how a mission
		 * is configured happens in the drawer, which shows the real title,
		 * art and description; the grid stays the grid, so what user 1
		 * sees while browsing is what a player sees.
		 *
		 * An earlier version let the admin see through the lock here too,
		 * on the reasoning that configuring is easier with the names
		 * visible. It is, marginally -- and the cost is never seeing your
		 * own ladder the way it actually reads.
		 */
		$show   = $open;
		$q_title = $show ? $q['title']
		         : preg_replace('/[0-9_-]/', '#', preg_replace('/[a-zA-Z_-]/', '?', $q['title']));
		$q_image = $show ? $q['image'] : 'icons/padlock.png';
		?>
		<div class="ms-quest-art">
			<?php /* The padlock is an icon and wants room around it; real
			         artwork is not and does not. An admin looking at a locked
			         rung sees the real piece, so the icon treatment has to key
			         off WHAT IS BEING SHOWN, not off the lock. */ ?>
			<img class="<?php echo $show ? 'ms-quest-pic' : 'ms-quest-lock'; ?>"
				src="<?php echo ms_e($q_image); ?>" alt="" loading="lazy"
				onerror="this.src='/staking/icons/skull.png';">
			<span class="ms-quest-lvl">Lv <?php echo (int)$q['level']; ?></span>
			<?php /* ONLY THE SHORT ONE GOES ON THE ART. The box is 84px wide and
			         already carries the level chip; "Never run" at this size is
			         70px of it and covered the chip entirely. The longer label
			         goes on the action line below, which has the whole card
			         width to itself. */ ?>
			<?php if ($open && !empty($ms_new_ids[(int)$q['quest_id']])): ?>
			<span class="ms-quest-new">New</span>
			<?php endif; ?>
			<?php if ((int)$q['running'] > 0): ?>
			<span class="ms-quest-run"><?php echo (int)$q['running']; ?> out</span>
			<?php endif; ?>
		</div>
		<div class="ms-quest-body">
			<span class="ms-quest-title<?php echo $show ? '' : ' hidden'; ?>"><?php echo ms_e($q_title); ?></span>
			<span class="ms-quest-pay">
				<?php if ((float)$q['cost'] > 0): ?>
				<em><?php echo ms_n($q['cost']); ?></em> &rarr;
				<?php endif; ?>
				<b><?php echo ms_n($q['reward']); ?></b> <?php echo ms_e($q['currency']); ?>
			</span>
			<span class="ms-quest-meta"><?php echo (int)$q['duration']; ?> day<?php
				echo (int)$q['duration'] === 1 ? '' : 's';
				if ((int)$q['duration'] > 0) echo ' &middot; ' . ms_n($q['net_per_day']) . '/day'; ?></span>
			<?php
			/* Two different facts, said plainly rather than by badge colour:
			   "New" is the rung that just opened (cleared + 1), "Never run" is
			   one further down you passed over -- which happens when a mission
			   is added to a ladder below where you already are. */
			$tag = '';
			if ($open && isset($ms_new_ids[(int)$q['quest_id']]))
				$tag = $ms_new_ids[(int)$q['quest_id']] ? 'New' : 'Never run';
			?>
			<?php if ($block && !$open): ?>
			<span class="ms-quest-block"><?php echo ms_e($block);
				echo $ms_admin ? ' <i>inspect</i>' : ''; ?></span>
			<?php elseif ($block): ?>
			<?php /* Unlocked but not launchable: still opens, so say what is
			         missing AND that you can look. */ ?>
			<span class="ms-quest-block"><?php if ($tag): ?><i><?php echo $tag; ?></i> &middot;
				<?php endif; ?><?php echo ms_e($block); ?></span>
			<?php else: ?>
			<span class="ms-quest-go"><?php if ($tag): ?><i><?php echo $tag; ?></i> &middot;
				<?php endif; ?>Send a crew &rarr;</span>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach; endif; ?>
