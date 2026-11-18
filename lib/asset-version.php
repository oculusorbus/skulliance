<?php
/**
 * ONE CACHE-BUSTING TOKEN FOR EVERY STATIC ASSET ON THE PLATFORM.
 *
 * WHAT THIS REPLACES, and why it mattered. Every stylesheet and script tag
 * was written `?v=<?php echo skl_asset_v(); ?>`. A URL that changes on
 * every request can never be served from the browser cache, so a returning
 * player re-downloaded flexbox.css, modal.css, polish.css, polish-sweep.js
 * and wallet.js on EVERY page view -- forty-six such tags across twenty-two
 * pages. On a good connection that is waste. On a weak cellular one it is
 * the difference between a page and a failure: reported as a stuck loading
 * bar, and once as a page that rendered with no CSS at all, both of which
 * came good on a refresh because the refetch happened to succeed.
 *
 * It did not even bust the cache reliably. rand(0,999) has a thousand
 * values, so roughly one load in a thousand DID hit a cached copy -- and
 * after a deploy that copy is the old file. The worst of both: no caching,
 * and non-deterministic staleness.
 *
 * THE TOKEN IS THE VERSION FILE, which this project already bumps on every
 * commit for the PWA's update banner. service-worker.js says the same thing
 * in its own header -- that asset caching "has to be invalidated by that
 * same VERSION signal, not by a cache name someone remembers to bump" --
 * so this is the signal it names, used for the thing it was named for.
 *
 * Read once per request and kept, because a dozen tags on a page should not
 * be a dozen stat calls.
 */
function skl_asset_v() {
	static $v = null;
	if ($v !== null) return $v;

	$file = dirname(__DIR__) . '/VERSION';
	$raw  = @file_get_contents($file);

	/*
	 * NO VERSION FILE IS NOT A REASON TO GO BACK TO RANDOM. Fall back to the
	 * file's own mtime, and then to a literal: anything stable is better than
	 * a token that changes per request, which is the bug this exists to fix.
	 */
	if ($raw === false || trim($raw) === '') {
		$mt  = @filemtime($file);
		$raw = $mt ? (string) $mt : 'dev';
	}

	/* A query string, so only what is safe in one. */
	$v = preg_replace('/[^A-Za-z0-9._-]/', '', trim($raw));
	if ($v === '') $v = 'dev';
	return $v;
}
