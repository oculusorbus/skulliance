<?php
/*
 * dropship/vip-links.php -- the route into Oculus Lounge, in one place.
 *
 * Oculus Lounge is gated on a Discord ROLE, not on the token itself:
 * dashboard.php asks dropshipMemberHasRole() for role 966399108011163678 in
 * the Oculus Lounge guild. So buying the VIP token is only the first of two
 * steps, and a player who does just that is still refused at the door with
 * no idea why. That has already happened to someone holding the token.
 *
 * Three links, and the ORDER is the instruction: buy it, register it, then
 * the site if you want to know more before spending anything.
 *
 * ONE COPY because they appear in three places -- the Play gate on
 * dashboard.php, the Where to Buy section on instructions.php, and the
 * DISCOIN page, which is read by exactly the person who does not have a
 * token yet. Three hand-copied triples would drift, and the thing that
 * drifts is always the one nobody is looking at.
 *
 * The Skull Paper's games-oculus-lounge.md carries the same three URLs and
 * cannot include PHP, so launchpad-harness.php asserts they match this file.
 */

// Marketplace listing, filtered to the "Legendary - VIP" rarity tier. The
// query string is part of the link: the collection without it is the whole
// Disco Solaris drop, most of which is not a VIP token.
if (!defined('OCULUS_VIP_BUY')) {
	define('OCULUS_VIP_BUY', 'https://www.wayup.io/collection/3d250a78df7ad14e9472d9b63159ef2d099740c593c0ba53059f144a?do=true&f=JTdCJTIyUmFyaXR5JTNBJTIyJTNBJTdCJTIyTGVnZW5kYXJ5JTIwLSUyMFZJUCUyMiUzQXRydWUlN0QlN0Q%3D');
}

// The Oculus Lounge Discord, where the token is registered and the role is
// granted. The canonical invite, NOT the t.co shortlink it was handed over
// as: this is the one step a player has no other way to discover, and it
// should not depend on a third-party redirect still being there.
if (!defined('OCULUS_VIP_DISCORD')) {
	define('OCULUS_VIP_DISCORD', 'https://discord.com/invite/fNuBhYnnuR');
}

if (!defined('OCULUS_SITE')) {
	define('OCULUS_SITE', 'https://oculuslounge.vip/');
}

/*
 * Renders the three links as one sentence of instruction.
 *
 * $intro is whatever the calling page needs in front of them -- the Play
 * gate says you are refused, the DISCOIN page says there is a permanent
 * alternative, the instructions page says nothing at all. Pass HTML; it is
 * the page's own copy, not user input.
 */
function oculusVipLinks($intro = '') {
	$buy  = htmlspecialchars(OCULUS_VIP_BUY,     ENT_QUOTES, 'UTF-8');
	$dis  = htmlspecialchars(OCULUS_VIP_DISCORD, ENT_QUOTES, 'UTF-8');
	$site = htmlspecialchars(OCULUS_SITE,        ENT_QUOTES, 'UTF-8');

	return '<p class="ol-vip">'
	     . ($intro !== '' ? $intro . ' ' : '')
	     . '<a href="' . $buy . '" target="_blank" rel="noopener">Buy a VIP Token</a>, '
	     . 'then <a href="' . $dis . '" target="_blank" rel="noopener">register it in the Oculus Lounge Discord</a> '
	     . '- the game checks for the Discord role, so holding the token is only half of it. '
	     . '<a href="' . $site . '" target="_blank" rel="noopener">Learn more about Oculus Lounge</a>.'
	     . '</p>';
}
