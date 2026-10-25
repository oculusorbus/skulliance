<?php
/**
 * dhcbosses.php — the DHC Bosses interface.
 *
 * ============================================================================
 * THIS FILE IS A MODE SWITCH AND NOTHING ELSE. THAT IS DELIBERATE.
 * ============================================================================
 *
 * DHC Bosses is the Arena engine with different opponents and a different
 * settlement. It plays on the same board, with the same gems, the same reach
 * rule, the same bombs, the same Crew of three and the same client. The only
 * things that differ are the setup column -- which bosses are standing, how
 * many Crews you have left today, the trait cache, the weekly ladder -- and
 * those are the parts dhcarena.php now branches on $DHC_MODE.
 *
 * SO WHY A SEPARATE PAGE AT ALL, since the panels briefly lived side by side
 * on the Arena: one Crew picker cannot serve two games honestly. Each Fighter
 * has two independent recovery clocks, and a single picker has to show both at
 * once -- so a Fighter resting from a ranked battle sat next to a boss button
 * it was perfectly able to press, and the only way to tell was reading two
 * badges per card. Two addresses, one clock each, no legend.
 *
 * AND WHY NOT A SECOND IMPLEMENTATION, which is the other obvious shape: the
 * client in dhcarena.php is roughly two thousand lines of board, drag, cascade
 * animation, timeline, Fighter inspector and end-card. A copy would be correct
 * the day it was made and wrong from the next engine fix onward. Every fix the
 * Arena gets, this page gets, because it IS the Arena with $DHC_MODE set.
 *
 * It is public for the same reason the Arena is: the X share posts this URL,
 * and the people most worth reaching do not have a session. dhcarena.php
 * handles that itself -- it restores the session cookie, decides $dhca_guest,
 * and only includes skulliance.php for a member -- so there is nothing to do
 * here but name the mode.
 */
$DHC_MODE = 'bosses';
require __DIR__ . '/dhcarena.php';
