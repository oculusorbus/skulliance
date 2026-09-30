<?php
/**
 * ajax/realm-identity.php
 * Changes this player's realm theme or faction, without reloading the page.
 *
 * WHY IT EXISTS. Both dropdowns used to submit a form: the theme through a
 * hidden #filterNFTsForm, the faction with an inline
 * onchange="...factionsForm.submit()". Realms is one of the heaviest pages on
 * the platform -- seven priced locations, the map data, the raid lists, the
 * soldier roster -- so changing a dropdown meant re-running all of it, losing
 * your scroll position and sitting through the loader, to alter one image or
 * one row. A rejection was worse: verifyRealmTheme() fails, and the page came
 * back with a JS alert() over it.
 *
 * THE REALM IS NOT A PARAMETER, the same rule as the upgrade endpoint: it is
 * whatever getRealmID() says for this session, so a crafted request cannot
 * re-theme somebody else's realm.
 *
 * The POST handlers in realms.php are deliberately left in place. They are
 * the no-JS path, and createRealm() shares them.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhc-json.php';

if (empty($_SESSION['userData']['user_id'])) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$realm_id = (int)getRealmID($conn);
if ($realm_id <= 0) dhc_json(array('ok' => false, 'message' => 'You have no realm.'));

$what  = isset($_POST['what'])  ? (string)$_POST['what'] : '';
$value = isset($_POST['value']) ? (int)$_POST['value']   : -1;
if ($value < 0) dhc_json(array('ok' => false, 'message' => 'Nothing chosen.'));

if ($what === 'theme') {
	/*
	 * 0 is the founder's own theme and bypasses the ownership check, exactly
	 * as the form handler did -- the option is only rendered for that one
	 * discord id, but the check belongs here rather than in the markup.
	 */
	$founder = isset($_SESSION['userData']['discord_id'])
	        && $_SESSION['userData']['discord_id'] === '772831523899965440';
	if ($value === 0 && !$founder) {
		dhc_json(array('ok' => false, 'message' => 'That theme is not available to you.'));
	}
	if ($value !== 0 && !verifyRealmTheme($conn, $value)) {
		$p = getProjectInfo($conn, $value);
		dhc_json(array('ok' => false, 'message' => 'You need at least one '
			. (isset($p['name']) ? $p['name'] : 'project') . ' NFT to use that theme. '
			. 'Buy one and refresh your wallets to try again.'));
	}
	updateRealmTheme($conn, $realm_id, $value);
	$conn->close();
	dhc_json(array('ok' => true, 'message' => 'Theme saved.',
	               'image' => 'images/themes/' . $value . '.jpg'));
}

if ($what === 'faction') {
	if (!verifyRealmFaction($conn, $value)) {
		$p = getProjectInfo($conn, $value);
		dhc_json(array('ok' => false, 'message' => 'You need at least one '
			. (isset($p['name']) ? $p['name'] : 'project') . ' NFT to join that faction. '
			. 'Buy one and refresh your wallets to try again.'));
	}
	updateRealmFaction($conn, $realm_id, $value);
	$conn->close();
	dhc_json(array('ok' => true, 'message' => 'Faction saved.'));
}

$conn->close();
dhc_json(array('ok' => false, 'message' => 'Unknown request.'));
