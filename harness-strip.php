<?php
/**
 * Strip comments from source before a harness asserts against it.
 *
 * WHY THIS IS NOT THE ONE-LINE REGEX every harness in this repo had inline.
 *
 * The usual pattern matches a block comment with a non-greedy `.*?` between
 * a slash-star and the next star-slash. That treats `accept="image/x"`, where
 * x is the MIME wildcard, as the start of a comment and deletes everything up
 * to the next real close. On the admin pages that is between 4KB and 9KB of
 * LIVE CODE, so assertions ran against a mangled copy of the file: two checks
 * on the art board failed for that reason and nothing else, and any that
 * passed did so without ever seeing the code they name.
 *
 * Comments in this codebase open a line. A block comment is therefore only
 * recognised when its opener is the first non-whitespace on its line, which
 * no MIME wildcard ever is. The trade is that a trailing block comment after
 * code on the same line survives; nothing here writes those, and the thing
 * this protects against -- a commented-out gate like a slashed-out
 * admin_require() -- is always line-opening.
 *
 * The line-comment rule keeps its lookbehind, so a URL's double slash and a
 * path like image/png are left alone.
 */
function harness_strip_comments($src) {
	$src = preg_replace('~^[ \t]*/\*.*?\*/~ms', '', $src);
	$src = preg_replace('~(?<![:/])//[^\n]*~', '', $src);
	return $src;
}
