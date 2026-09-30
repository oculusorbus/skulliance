<?php
/*
 * realms-locations.php -- the locations panel.
 *
 * ONE COPY. realms.php and ajax/get-locations.php each carried their own
 * near-verbatim version of this; they had already drifted (one had a Guide
 * button, the other did not). Both include this now, so the page and its
 * refresh cannot disagree.
 *
 * Expects $rl_panel from realm_location_panel(). $rl_guide says whether to
 * draw the Guide button -- the refresh does not, because the button is
 * outside the fragment it replaces.
 */
if (!isset($rl_panel) || !$rl_panel) return;
if (!function_exists('rl_e')) { function rl_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$rl_guide = isset($rl_guide) ? $rl_guide : false;
$rl_cons  = $rl_panel['consumables'];
?>
<div class="rl-head">
	<img class="rl-logo" src="images/realms-logo.png" alt="Realms">
	<div class="rl-head-acts">
		<?php if ($rl_guide): ?>
		<button type="button" class="rl-btn" onclick="openGuideModal()">Guide</button>
		<?php endif; ?>
		<button type="button" class="rl-btn" onclick="deactivateRealm(<?php echo (int)$rl_panel['realm_id']; ?>)">Deactivate</button>
	</div>
</div>

<?php /* THE TWO NUMBERS THAT DECIDE A RAID, which the panel never showed.
         Per-location success was printed on every card; the figure that
         actually applies is the AVERAGE across the locations of a type --
         getLocationSuccessRateBoost() -- so a player could equip one location
         to +10% and believe their raids were at +10%. */ ?>
<div class="rl-boosts">
	<span><b><?php echo (int)$rl_panel['boosts']['offense']; ?>%</b> offense success</span>
	<span><b><?php echo (int)$rl_panel['boosts']['defense']; ?>%</b> defense success</span>
	<span class="rl-boosts-note">averaged across each side's locations &mdash; this is what a raid uses</span>
</div>

<div class="rl-inv">
	<div class="rl-inv-head">
		<strong>Inventory</strong>
		<span class="rl-inv-acts">
			<button type="button" class="rl-btn sm" onclick="openInventoryInfoModal()">What these do</button>
			<button type="button" class="rl-btn sm" id="stock-all-btn" onclick="stockAllLocations()">Stock every location</button>
		</span>
	</div>
	<div class="rl-slots">
		<?php foreach ($rl_cons as $cid => $cname): $qty = $rl_panel['inventory'][$cid]; ?>
		<span class="rl-slot <?php echo $qty > 0 ? 'has' : 'none'; ?>" id="inv-slot-<?php echo $cid; ?>"
			title="<?php echo rl_e($cname . ($qty > 0 ? " — you have $qty" : ' — none left')); ?>">
			<img src="icons/<?php echo rl_e(realm_con_icon($cname)); ?>" alt="" onerror="this.src='icons/skull.png'">
			<i id="inv-qty-<?php echo $cid; ?>"><?php echo $qty; ?></i>
		</span>
		<?php endforeach; ?>
	</div>
</div>

<?php
$rl_prev = '';
foreach ($rl_panel['rows'] as $r):
	if ($rl_prev !== $r['type']):
		if ($rl_prev !== '') echo "</div>\n";
		echo '<div class="rl-group rl-' . rl_e($r['type']) . '">'
		   . '<h4>' . rl_e(ucfirst($r['type'])) . '</h4>';
	endif;
	$rl_prev = $r['type'];
	$q  = $r['quote'];
	$ef = $r['effects'];
?>
	<?php
	$rl_manage = array(1=>'Portal',2=>'Armory',3=>'Tower',4=>'Barracks',
	                   5=>'Factory',6=>'Crypt',7=>'Mine');
	$rl_can_manage = isset($rl_manage[$r['id']]);
	?>
	<div class="rl-loc" id="loc-row-<?php echo $r['id']; ?>">
		<?php /* THE HEADER IS THE MANAGE BUTTON. There used to be a separate
		         "Manage Armory" button on every row, and with Stock beside it
		         the pair needed 127px the column did not have -- the seven
		         kit slots wrapped to a second line to make room. Clicking the
		         location to manage the location is the obvious gesture and it
		         takes seven buttons off the panel. A <button>, not a div with
		         a handler, so it is reachable by keyboard. */ ?>
		<<?php echo $rl_can_manage ? 'button type="button"' : 'div'; ?> class="rl-loc-top<?php
			echo $rl_can_manage ? ' can' : ''; ?>"<?php
			if ($rl_can_manage): ?> onclick="openLocationModal(<?php echo $r['id']; ?>)"
			title="<?php echo rl_e('Manage the ' . $rl_manage[$r['id']] . ' — ' . $r['description']); ?>"<?php
			else: ?> title="<?php echo rl_e($r['description']); ?>"<?php endif; ?>>
			<img class="rl-loc-icon" src="icons/locations/<?php echo rl_e($r['name']); ?>.png"
				alt="" onerror="this.src='icons/skull.png'">
			<div class="rl-loc-id">
				<strong><?php echo rl_e(strtoupper($r['name'])); ?></strong>
				<?php /* ONE line about the loadout, saying what it DOES. The four
				         name tags that used to sit here repeated the seven slots
				         below them exactly. */ ?>
				<span class="rl-loc-sub" id="loc-sub-<?php echo $r['id']; ?>"><?php
					$bits = array();
					if ($ef['success'] > 0) $bits[] = '+' . $ef['success'] . '% success';
					foreach ($ef['notes'] as $n) $bits[] = $n;
					echo $bits ? implode(' &middot; ', array_map('rl_e', $bits))
					           : '<em>nothing equipped</em>';
				?></span>
			</div>
			<span class="rl-loc-lv<?php echo $r['level'] >= $rl_panel['ceiling'] ? ' over' : ''; ?>">
				<b>Lv <?php echo (int)$r['level']; ?></b>
			</span>
		</<?php echo $rl_can_manage ? 'button' : 'div'; ?>>

		<div class="rl-loc-offer" id="loc-upgrade-<?php echo $r['id']; ?>">
		<?php if ($r['running']): ?>
			<?php /* Same countdown hook the old markup used, so the existing
			         tick script keeps driving it. */ ?>
			<span class="rl-run">Upgrading to Lv<?php echo (int)$r['running']['target']; ?></span>
			<span class="countdown" data-deadline="<?php echo (int)$r['running']['deadline']; ?>">&mdash;</span>
			<span class="rl-run-note"><?php echo (int)$r['running']['days']; ?>
				<?php echo $r['running']['days'] == 1 ? 'day' : 'days'; ?> total</span>
		<?php elseif (!empty($q['at_ceiling'])): ?>
			<?php /* NO PURCHASE UP HERE, and the reason said out loud. There used
			         to be a "Maintain Lv10" button on these rows; at 10 it cost
			         1,000 and ten days to change nothing, and above 10 the
			         completion reset the level. Both are fixed, and the honest
			         answer is that there is nothing left to buy. */ ?>
			<?php /* Short on purpose: long enough to push Stock onto its own
			         line was long enough to say twice. */ ?>
			<span class="rl-cap">Past the ceiling &mdash; raids move it from here</span>
		<?php elseif ($q && $q['ok']): ?>
			<button type="button" class="rl-btn go" id="upgrade-button-<?php echo $r['id']; ?>"
				onclick="upgradeRealmLocation(this, <?php echo (int)$rl_panel['realm_id']; ?>, <?php echo $r['id']; ?>, <?php echo (int)$q['duration']; ?>, <?php echo (int)$q['cost']; ?>, <?php echo $r['id']; ?>)">
				Upgrade to Lv<?php echo (int)$q['duration']; ?>
			</button>
			<span class="rl-price"><?php echo number_format($q['cost']) . ' ' . rl_e($q['currency']); ?>
				&middot; <?php echo (int)$q['duration']; ?> <?php echo $q['duration'] == 1 ? 'day' : 'days'; ?></span>
		<?php elseif ($q): ?>
			<?php /* Short. The points path is offered inline rather than behind a
			         button that hid every other location's -- see the note on
			         togglePointsButtons in skulliance.js. */ ?>
			<span class="rl-short" id="upgrade-message-<?php echo $r['id']; ?>"><?php echo rl_e($q['why']); ?></span>
			<button type="button" class="rl-btn" id="points-button-<?php echo $r['id']; ?>"
				onclick="pointsOption(this, <?php echo (int)$rl_panel['realm_id']; ?>, <?php echo $r['id']; ?>, <?php echo (int)$q['duration']; ?>, <?php echo (int)$q['cost']; ?>)">
				Pay with other points
			</button>
		<?php endif; ?>
			<?php /* Stock and Manage live on this row, pushed right, rather than
			         beside the slots. Measured: seven 28px slots plus these two
			         buttons needed 357px in a 356px column, so the slots wrapped
			         to a second line and every card grew a row. The slots get
			         the full width now and this row was already flexible. */ ?>
			<span class="rl-loc-acts">
				<button type="button" class="rl-btn sm" id="stock-btn-<?php echo $r['id']; ?>"
					onclick="stockLocation(<?php echo $r['id']; ?>)">Stock</button>
			</span>
		</div>

		<div class="rl-loc-kit">
			<div class="rl-slots" id="loc-consumables-<?php echo $r['id']; ?>">
			<?php foreach ($rl_cons as $cid => $cname):
				$on  = isset($r['equipped'][$cid]);
				$qty = $rl_panel['inventory'][$cid];
				$cls = $on ? 'on' : ($qty > 0 ? 'has' : 'none');
				$tip = $cname . ($on ? ' — equipped, click to remove'
				                     : ($qty > 0 ? " — click to equip ($qty left)" : ' — none left'));
				$act = $on ? 'removeLocationConsumable(' . $r['id'] . ',' . $cid . ')'
				           : ($qty > 0 ? 'applyLocationConsumable(' . $r['id'] . ',' . $cid . ')' : '');
			?>
				<span class="rl-slot <?php echo $cls; ?>" id="loc-con-<?php echo $r['id'] . '-' . $cid; ?>"
					title="<?php echo rl_e($tip); ?>"<?php if ($act): ?> onclick="<?php echo $act; ?>"<?php endif; ?>>
					<img src="icons/<?php echo rl_e(realm_con_icon($cname)); ?>" alt="" onerror="this.src='icons/skull.png'">
					<?php if ($on): ?><i class="on">&#10003;</i>
					<?php elseif ($qty > 0): ?><i id="loc-inv-<?php echo $r['id'] . '-' . $cid; ?>"><?php echo $qty; ?></i><?php endif; ?>
				</span>
			<?php endforeach; ?>
			</div>
		</div>
	</div>
<?php endforeach; if ($rl_prev !== '') echo "</div>\n"; ?>
