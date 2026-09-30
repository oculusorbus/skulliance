<?php
/*
 * realms-attack.php -- the Attack panel: pick a realm and raid it.
 *
 * ONE COPY. realms.php and ajax/get-realms.php each carried their own
 * version of this header, which is exactly how the locations panel and the
 * realm panel both shipped redesigned-but-invisible: the page rendered the
 * new markup, the first refresh replaced it with the old, and on desktop
 * that refresh happens immediately. Third time, so it is a partial.
 *
 * THE HEADING IS INSIDE THE PANEL NOW. <h2>Realms</h2> sat outside
 * .content.realms and #filter-nfts floated to the right inside it, so the
 * title and the Sort By control sat on different lines at opposite ends of
 * a very wide panel with nothing tying them together. They are one header
 * row now, the same shape the locations and realm panels already use.
 *
 * Expects: $conn, and a caller that has already established the viewer has
 * an active realm. $ra_sort / $ra_group are optional and default to the
 * page's own first render.
 */
$ra_sort  = isset($ra_sort)  ? $ra_sort  : 'weakness';
$ra_group = isset($ra_group) ? $ra_group : 'Eligible';
$ra_sel = function ($s, $g) use ($ra_sort, $ra_group) {
	return ($ra_sort === $s && $ra_group === $g) ? ' selected' : '';
};
?>
<div class="content realms" id="filtered-content">
	<div class="ra-head">
		<h2>Realms</h2>
		<div id="filter-nfts" class="ra-sort">
			<label for="filterRealms"><strong>Sort By:</strong></label>
			<select onchange="javascript:filterRealms(this.options[this.selectedIndex]);"
			        name="filterRealms" id="filterRealms" class="dropdown">
				<optgroup label="Eligible">
					<option value="weakness"<?php echo $ra_sel('weakness','Eligible'); ?>>Weakness</option>
					<option value="strength"<?php echo $ra_sel('strength','Eligible'); ?>>Strength</option>
					<option value="wealth"<?php echo $ra_sel('wealth','Eligible'); ?>>Wealth</option>
					<option value="random"<?php echo $ra_sel('random','Eligible'); ?>>Random</option>
				</optgroup>
				<optgroup label="All">
					<option value="weakness"<?php echo $ra_sel('weakness','All'); ?>>Weakness</option>
					<option value="strength"<?php echo $ra_sel('strength','All'); ?>>Strength</option>
					<option value="wealth"<?php echo $ra_sel('wealth','All'); ?>>Wealth</option>
					<option value="random"<?php echo $ra_sel('random','All'); ?>>Random</option>
				</optgroup>
			</select>
		</div>
	</div>
	<div id="realms-list">
		<?php getRealms($conn, $ra_sort, $ra_group); ?>
	</div>
</div>
