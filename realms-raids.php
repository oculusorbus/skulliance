<?php
/*
 * realms-raids.php -- the four raid lists, in one order, in one place.
 *
 * PENDING FIRST, BOTH DIRECTIONS, THEN COMPLETED. The order used to be
 * outgoing-pending, outgoing-completed, incoming-pending, incoming-completed
 * -- grouped by direction, which buried the incoming raids you can still do
 * something about underneath a history list. The two pending lists are the
 * short ones and the only ones that are still live, so they lead.
 *
 * ONE COPY. ajax/get-raids.php had its own, which is the fourth panel on
 * this page to be written out twice; the locations panel, the realm panel
 * and the Attack header were the others, and every one of them shipped a
 * redesign the player never saw because the first refresh replaced it.
 *
 * THE COMPLETED LISTS ARE SIDE-EFFECTING. getRaids(..., 'completed') is what
 * resolves finished raids -- endRaid() updates consumables inside it -- so
 * realms.php calls it early, BEFORE the locations panel renders, and hands
 * the markup in here. Do not move those calls into this file: the panel
 * above would then price locations from pre-raid state. The isset() fallback
 * is only for a caller that has not run them, and re-running costs nothing
 * because a null list means there were no completed raids to resolve.
 *
 * Expects: $conn. Optionally $rr_out_done / $rr_in_done already rendered.
 */
$rr_out_pending = getRaids($conn, 'outgoing', 'pending');
$rr_in_pending  = getRaids($conn, 'incoming', 'pending');
$rr_out_done    = isset($rr_out_done) ? $rr_out_done : getRaids($conn, 'outgoing', 'completed');
$rr_in_done     = isset($rr_in_done)  ? $rr_in_done  : getRaids($conn, 'incoming', 'completed');

foreach (array($rr_out_pending, $rr_in_pending, $rr_out_done, $rr_in_done) as $rr_html) {
	/* getRaids() returns nothing at all when a list is empty, so isset() is
	   the emptiness test here, exactly as it was before. */
	if (!isset($rr_html) || $rr_html === '') continue;
	echo '<div class="content raids">' . $rr_html . '</div>';
}
