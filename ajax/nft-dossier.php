<?php
/**
 * ajax/nft-dossier.php?nft_id=N -- one NFT's current life, as JSON.
 *
 * Feeds the panel My NFTs opens when a card is clicked. The panel used to be
 * a link straight out to a block explorer, which says the NFT exists and
 * nothing about what it is doing here.
 *
 * WHO MAY LOOK IS DECIDED HERE, once. The dossier library does not know and
 * must not: a rule re-derived per query is a rule that will disagree with
 * itself. The test is the same one the rest of the platform uses for
 * somebody else's collection -- the owner always, anyone else only when that
 * owner's profile is public -- so this exposes nothing a leaderboard click
 * does not already.
 */
ob_start();
ini_set('display_errors', 0);
register_shutdown_function(function () {
	$err = error_get_last();
	if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
		while (ob_get_level() > 0) ob_end_clean();
		if (!headers_sent()) header('Content-Type: application/json');
		echo json_encode(array('success' => false,
			'message' => 'Fatal: ' . $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')'));
	}
});

include '../db.php';
require_once __DIR__ . '/../lib/nft-dossier.php';

header('Content-Type: application/json');

$nft_id = (int)($_GET['nft_id'] ?? $_POST['nft_id'] ?? 0);
if ($nft_id < 1) {
	ob_clean();
	echo json_encode(array('success' => false, 'message' => 'Missing nft_id.'));
	exit;
}

$d = nft_dossier($conn, $nft_id);
if (!$d) {
	ob_clean();
	echo json_encode(array('success' => false, 'message' => 'No such NFT.'));
	exit;
}

/* ---- who is asking ---- */
$me    = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
$owner = (int)$d['nft']['user_id'];
$mine  = ($me > 0 && $me === $owner);

if (!$mine) {
	/* Somebody else's. visibility 2 is the public setting the Collection and
	   the leaderboards already honour. */
	$vis = 0;
	$r = $conn->query("SELECT visibility FROM users WHERE id = " . $owner . " LIMIT 1");
	if ($r && $r->num_rows) { $row = $r->fetch_assoc(); $vis = (int)$row['visibility']; }
	if ($vis != 2) {
		ob_clean();
		echo json_encode(array('success' => false, 'message' => 'That collection is private.'));
		exit;
	}
}

/* The picture, resolved the same way the grid resolves it: a local copy when
   there is one, a gateway otherwise. */
$img    = getIPFS($d['nft']['ipfs'], $d['nft']['collection_id'], $d['nft']['project_id']);
$cached = (strpos($img, '/staking/images/nfts/') === 0);

/*
 * The Diamond Skull is another NFT, so its picture resolves exactly like the
 * main one -- local copy first, gateway otherwise. Done here rather than in
 * the library for the same reason the main image is: getIPFS() is db.php's.
 */
if (!empty($d['diamond'])) {
	$d['diamond']['image'] = getIPFS($d['diamond']['ipfs'],
	                                 (int)$d['diamond']['collection_id'],
	                                 (int)$d['diamond']['project_id']);
	unset($d['diamond']['ipfs']);     // the panel has no use for the raw CID
}

ob_clean();
echo json_encode(array(
	'success'  => true,
	'mine'     => $mine,
	'image'    => $img,
	'cached'   => $cached,
	'explorer' => nftExplorerUrl($conn, $d['nft']['asset_id'],
	                             (int)($d['nft']['blockchain_id'] ?: 1)),
	'nft'      => $d['nft'],
	'missions' => $d['missions'],
	'missionsDone' => $d['missions_done'],
	'gauntlets'=> $d['gauntlets'],
	'raids'    => $d['raids'],
	'diamond'  => $d['diamond'],
	'soldier'  => $d['soldier'],
	/*
	 * THE TRAITS, PLUS WHETHER ANYBODY HAS LOOKED.
	 *
	 * traits is a LIST of {name, value} so its order survives the trip --
	 * see nftd_trait_pairs(). metadataAt is what lets the panel say "no
	 * traits on this one" and "not read from the chain yet" as the
	 * different things they are; without it both are an empty list.
	 *
	 * metadata is the stored document itself, for the raw view. It is
	 * already trimmed of image/files/social links by nft_metadata_clean()
	 * at write time, and everything left in it is public on chain -- this
	 * exposes nothing the asset's own explorer page does not.
	 */
	'traits'     => $d['traits'],
	'metadataAt' => $d['metadata_at'],
	'metadata'   => $d['metadata'],
), JSON_UNESCAPED_SLASHES);
$conn->close();
