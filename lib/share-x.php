<?php
/**
 * lib/share-x.php -- the "Share on X" composer link, in one place.
 *
 * MOVED OUT OF db.php BECAUSE DROP SHIP CANNOT INCLUDE IT. Drop Ship has
 * its own db.php and its own database connection; including the platform's
 * on top of it is a fatal redeclare, which is the same wall
 * dropshipSoldierImage() hit. The choice was a second copy of the 280-char
 * budget and the @skulliance tail, or one file both sides require. Same
 * call lib/koios.php settled for the API headers.
 *
 * NO DEPENDENCIES ON PURPOSE. This is string building. It must be safe to
 * require from a public page that has no session, no database and no
 * header.php -- dropship-card.php and dropshipgame.php are exactly that.
 *
 * Guarded, so the order the two sides load it in cannot matter.
 */

//=============================================================================
// SHARE ON X
//
// Builds a pre-filled X (Twitter) "post composer" link for a player to share
// a result from a game's finish modal. One click opens X with the text
// already written; they press Post. Costs nothing -- this is the public Web
// Intent endpoint, NOT the paid API. No developer account, no OAuth, no
// tokens, no per-post charge.
//
// The imagery comes free too: every $page_path below points at a PUBLIC
// marketing page carrying `twitter:card = summary_large_image` plus an
// og:image, so X fetches that page and renders its art as a full-width card.
// Nothing is uploaded and no image is generated per run. This is why the
// target must stay a public page -- one behind skulliance.php's login gate
// would serve X a redirect to error.php and the card would silently render
// as a bare link.
//
// @skulliance is appended to every post so the main account sees them and can
// repost -- the whole point being reach that isn't just the official account
// talking to itself.
//=============================================================================
if (!defined('SKULLIANCE_X_HANDLE')) define('SKULLIANCE_X_HANDLE', '@skulliance');

if (!function_exists('shareOnXUrl')) {
function shareOnXUrl($body, $page_path) {
	$tail = "\n\n" . SKULLIANCE_X_HANDLE;

	// X counts ANY url as 23 characters (t.co wraps them all), regardless of
	// how long the real one is, and the url= param below is appended to the
	// post body. Budget for that here so a long result line gets trimmed at
	// a sensible place instead of X silently cutting the handle off the end
	// -- which would quietly defeat the point of tagging the account.
	$limit = 280 - 24 - mb_strlen($tail);
	$body  = trim($body);
	if (mb_strlen($body) > $limit) {
		$body = rtrim(mb_substr($body, 0, $limit - 1)) . '…';
	}

	$url = 'https://skulliance.io/staking/' . ltrim($page_path, '/');
	return 'https://x.com/intent/post?text=' . rawurlencode($body . $tail)
	     . '&url=' . rawurlencode($url);
}

// The finish-modal button itself. Same markup everywhere so the five games
// stay consistent; $class lets each game pass its own button styling
// (cc-btn / cq-btn / etc) so it sits properly next to that game's own
// buttons rather than looking bolted on.
//
// target=_blank with rel=noopener: the composer must not replace the game
// the player is still sitting in.
}
if (!function_exists('shareOnXButton')) {
function shareOnXButton($body, $page_path, $class = 'small-button', $style = '', $label = '𝕏 Share Result') {
	return '<a href="' . htmlspecialchars(shareOnXUrl($body, $page_path))
	     . '" class="' . htmlspecialchars($class) . '"'
	     . ($style !== '' ? ' style="' . htmlspecialchars($style) . '"' : '')
	     . ' target="_blank" rel="noopener">'
	     . htmlspecialchars($label) . '</a>';
}
}
