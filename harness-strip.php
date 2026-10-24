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
 * ONE EXCEPTION, AND IT IS EVERYWHERE: `<?php /* ... *\/ ?>`, which is how
 * every template on this platform comments its markup. The opener is not
 * first on its line -- `<?php ` is -- so these survived, and a harness that
 * greps stripped source then matches its own explanation. That cost three
 * false passes and one false FAIL in a single session: a check asserting
 * the page no longer uses 100vw found the phrase in the comment saying so.
 *
 * Still safe against the MIME wildcard, because the opener must be preceded
 * by `<?php` and nothing else: `accept="image/*"` cannot reach it.
 *
 * The line-comment rule keeps its lookbehind, so a URL's double slash and a
 * path like image/png are left alone.
 */
function harness_strip_comments($src) {
	$src = preg_replace('~^[ \t]*(?:<\?php\s+)?/\*.*?\*/~ms', '', $src);
	$src = preg_replace('~(?<![:/])//[^\n]*~', '', $src);
	return $src;
}

/*
 * PROBE FILES THAT CANNOT SURVIVE THE RUN.
 *
 * Several harnesses drive the real code by planting a file where the
 * real art and audio live -- dropship/sounds/, dropship/images/ -- and
 * removing it afterwards. "Afterwards" is not good enough: a fatal
 * partway through skips the cleanup, and the next `git add -A` commits
 * the leftover. That happened: dropship/sounds/grenadecontra.mp3 went
 * into a commit and broke `git pull` on the live server, because the
 * real file was sitting there untracked and could not be overwritten.
 *
 * Registered at creation and removed on shutdown, which runs for a
 * fatal and an exit() alike. Those folders are also in .gitignore now;
 * this is the other half, so a probe does not linger locally either.
 */
$GLOBALS['harness_probes'] = array();
function harness_probe($path, $contents = 'x') {
	$dir = dirname($path);
	if (!is_dir($dir)) @mkdir($dir, 0777, true);
	file_put_contents($path, $contents);
	$GLOBALS['harness_probes'][] = $path;
	return $path;
}
function harness_probe_clean() {
	foreach (array_reverse($GLOBALS['harness_probes']) as $p) @unlink($p);
	/* Walk the empty parents back out, but never past the repo. */
	foreach (array_reverse($GLOBALS['harness_probes']) as $p) {
		$d = dirname($p);
		while ($d !== __DIR__ && strpos($d, __DIR__) === 0 && @rmdir($d)) $d = dirname($d);
	}
	$GLOBALS['harness_probes'] = array();
}
register_shutdown_function('harness_probe_clean');
