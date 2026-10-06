<?php
/**
 * lib/chain-icons.php — which image file is a chain's logo.
 *
 * ONE MAP, because the slug is not always the filename and the exception
 * has already caused one broken image. blockchains.slug is 'xrpl' and the
 * logo has always been icons/xrp.png, named after the asset rather than
 * the ledger. Everything else — cardano, solana, polygon — is named after
 * its slug.
 *
 * Mapped rather than renaming the file: icons/xrp.png is referenced by
 * the wallet modal in header.php too, and images ship by FTP outside the
 * repo, so a rename is a deploy nobody can do from a commit.
 *
 * SHARED DELIBERATELY. The homepage chain band and the Collections table
 * both draw these logos, and homepage.php cannot include db.php (it is
 * included by the WordPress theme — see homepage-data.php's header for
 * why), so this is a tiny no-dependency file both can require. A second
 * copy of a four-entry map is exactly the drift that put four different
 * IPFS gateway lists in this codebase.
 *
 * Returns the BASENAME, no extension and no directory: callers sit at
 * different depths and on different hosts.
 */
function chain_icon_file($slug) {
	static $named = array(
		/* blockchains.slug => icons/<this>.png */
		'xrpl' => 'xrp',
	);
	$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$slug));
	if ($slug === '') return '';
	return isset($named[$slug]) ? $named[$slug] : $slug;
}

/** Two or three letters for the lettermark shown when the file is not
 *  on the server yet. Icons ship by FTP, so that is a real state. */
function chain_icon_mark($slug) {
	$slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)$slug));
	return $slug === '' ? '?' : strtoupper(substr($slug, 0, 3));
}
