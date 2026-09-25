<?php
/**
 * ajax/dhcarena-practice.php — the Arena with nothing at stake.
 *
 * NO db.php AND NO skulliance.php, deliberately. Practice touches no table,
 * pays nothing and ranks nobody, so it needs neither a database nor a signed-in
 * player -- and not needing them is exactly what lets the public page use it.
 * Adding either include here would quietly make the public Arena a members-only
 * Arena again.
 *
 * The board is not stored anywhere. The client holds a small spec -- seed, the
 * two Crews' traits, and the player's slides so far -- and posts it back with
 * each move; the server rebuilds the battle from it and applies one more slide.
 * See dhcarena-practice.php for why that is sound and what it is protecting.
 */
require_once __DIR__ . '/../dhcarena-practice.php';
require_once __DIR__ . '/../dhc-json.php';

/* A spec is small: six Fighters' traits plus a move list. Anything much larger
   is not a spec, and rebuilding from it would cost more than refusing it. */
define('DHCAP_MAX_SPEC', 32768);

$rarity = require __DIR__ . '/../dhcrarity.php';
$do     = isset($_POST['do']) ? $_POST['do'] : '';

function dhcap_out($ok, $extra = array(), $msg = '') {
	dhc_json(array_merge(array('ok' => $ok, 'message' => $msg), $extra));
}

if ($do === 'new') {
	list($spec, $b) = dhcap_new($rarity);
	dhcap_out(true, array('spec' => $spec, 'state' => dhca_public($b)));
}

if ($do === 'move') {
	$raw = isset($_POST['spec']) ? (string)$_POST['spec'] : '';
	if ($raw === '' || strlen($raw) > DHCAP_MAX_SPEC) dhcap_out(false, array(), 'Start a new practice battle.');
	$spec = json_decode($raw, true);
	if (!is_array($spec)) dhcap_out(false, array(), 'Start a new practice battle.');

	list($ok, $spec, $b) = dhcap_move($spec, $rarity, (int)($_POST['a'] ?? -1), (int)($_POST['z'] ?? -1));
	if (!$ok) {
		/* A refusal here is a stale page or an edited spec, and in practice
		   neither is worth a diagnosis -- there is nothing to protect. */
		dhcap_out(false, $b ? array('state' => dhca_public($b)) : array(), 'That move was refused.');
	}
	$out = array('spec' => $spec, 'state' => dhca_public($b));
	if ($b['over'] !== null) $out['over'] = $b['over'];
	dhcap_out(true, $out);
}

dhcap_out(false, array(), 'Unknown action.');
