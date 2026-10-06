<?php
/**
 * lib/ipfs-gateways.php — the public IPFS gateways this platform will try,
 * best-first. ONE list, because there were four and they had all drifted.
 *
 * MEASURED FROM THE SERVER 2026-10-05 (polygon-probe.php, gateways step),
 * against a CID pinned everywhere AND a CID only some hosts carry, so that
 * "this gateway refuses us" could be told apart from "this gateway has not
 * got that content":
 *
 *   ipfs.io, nftstorage.link, w3s.link, dweb.link   429 on both -> refusing us
 *   gateway.pinata.cloud                            301 / 0     -> unusable
 *   4everland.io                                    200 / ---   -> reachable
 *   ipfs.filebase.io                                200 / 200   -> was in no list
 *
 * Four of the six the platform was using had quietly stopped answering this
 * server at all. lib/image-cache-lib.php RACES its gateways, so that cost no
 * wall clock and nothing looked broken -- it had simply been reduced to one
 * live runner without anyone noticing. db.php's ensureNFTImageCached() walks
 * them in turn at 25s each and had been failing after 100 seconds every time.
 *
 * ORDER MATTERS only where the caller walks the list. A racer may ignore it.
 *
 * Re-measure with: php polygon-probe.php step=gateways
 */
function ipfs_gateways() {
	return array(
		'https://ipfs.filebase.io/ipfs/',
		'https://4everland.io/ipfs/',
		'https://ipfs.io/ipfs/',
		'https://nftstorage.link/ipfs/',
		'https://w3s.link/ipfs/',
		'https://gateway.pinata.cloud/ipfs/',
		'https://dweb.link/ipfs/',
	);
}
