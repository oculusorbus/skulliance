<?php
/**
 * lib/realm-preview.php — who may look at Realms through a newcomer's eyes.
 *
 * NO INCLUDES, ON PURPOSE. realms-lib.php requires db.php, and db.php
 * opens a connection the moment it is parsed, so anything that lives
 * there cannot be tested without a database. This is one pure decision
 * and it belongs somewhere the harness can simply call it.
 */

/**
 * Is this request asking to see Realms the way a newcomer sees it?
 *
 * THE OWNER CANNOT SEE HIS OWN FRONT DOOR. Realms branches hard on
 * checkRealm(): with a realm you get the map, the raid lists and the
 * locations panel, and without one you get a single panel asking you to
 * name a realm and pick a faction. Everyone who can change that panel
 * has had a realm for years, so the first thing a new player meets is
 * the one screen nobody on this side of it ever looks at.
 *
 * PURE, AND SEPARATE FROM THE PAGE, so the harness can run it. What it
 * decides is who is allowed to see the preview, which is the part worth
 * being sure about -- a page that rendered the newcomer view for an
 * actual player would be telling them they have no realm.
 *
 * User 1 only, the same rule the admin nav uses, and checked here again
 * rather than trusting that the link was only drawn for an admin.
 */
function realm_preview_requested($get, $session) {
	/* The is_array() on $get is a stated contract, not a behaviour:
	   isset() on a string offset already returns false without a
	   warning, so nothing reaches it. Kept because this function is a
	   GATE and a gate should say what it accepts. The session check
	   below is a different matter -- drop that one and a malformed
	   session is a fatal TypeError. */
	if (!is_array($get) || !isset($get['preview'])) return false;
	if (!is_string($get['preview']) || $get['preview'] !== 'newcomer') return false;
	if (!is_array($session) || !isset($session['userData']['user_id'])) return false;
	return (int) $session['userData']['user_id'] === 1;
}
