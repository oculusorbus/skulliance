<?php
/*
 * ajax/mission-launch.php — start one mission, atomically.
 *
 * REPLACES A FOUR-REQUEST DANCE. The old flow built a mission in
 * $_SESSION['userData']['mission'] through process-mission-nft.php and
 * process-mission-consumable.php (one request per NFT and per item),
 * cleared it through two more, and then start-mission.php wrote whatever
 * was left in the session. When the session and the page disagreed the
 * staker got a shipped error telling them to hard-refresh.
 *
 * Here the whole load-out arrives in one POST and mission_launch() rebuilds
 * every fact from the database before writing. The session holds no mission
 * state at all, so there is nothing to desync.
 *
 * THE REQUEST IS NOT TRUSTED. mission_launch() intersects the named NFTs
 * against the eligible set and the named items against what is actually
 * held, and re-derives the level lock and affordability. See missions-lib.php.
 */
include '../db.php';
include '../webhooks.php';
require_once __DIR__ . '/../missions-lib.php';
require_once __DIR__ . '/../dhc-json.php';

if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
	$ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($ck)) $_SESSION = array_merge((array)$_SESSION, $ck);
}

if (mission_user_id() <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
	dhc_json(array('ok' => false, 'message' => 'Launching is a POST.'));

$quest_id = isset($_POST['quest_id']) ? (int)$_POST['quest_id'] : 0;

/* Sent as comma-separated ids rather than nfts[] -- a 400-NFT whale would
   otherwise post 400 array keys, and some hosts cap max_input_vars at 1000. */
function ml_ids($raw) {
	$out = array();
	foreach (explode(',', (string)$raw) as $p) { $p = (int)trim($p); if ($p > 0) $out[] = $p; }
	return $out;
}
$nft_ids  = ml_ids(isset($_POST['nfts'])  ? $_POST['nfts']  : '');
$item_ids = ml_ids(isset($_POST['items']) ? $_POST['items'] : '');

$r = mission_launch($conn, $quest_id, $nft_ids, $item_ids);

/* The page repaints from this rather than reloading, so the new field list
   comes back in the same reply -- rendered by the same partial the first
   paint uses, so a just-launched mission cannot look different from a
   reloaded one. */
if (!empty($r['ok'])) {
	$ms_active = mission_active($conn);
	ob_start(); include __DIR__ . '/../missions-field.php'; $r['field'] = ob_get_clean();
	$r['overview'] = mission_overview($conn);
	$r['ready']    = count(array_filter($ms_active, function($a){ return !empty($a['ready']); }));
}
$conn->close();
dhc_json($r);
