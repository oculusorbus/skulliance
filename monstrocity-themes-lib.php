<?php
/**
 * Monstrocity themes, from the database instead of a JS array literal.
 *
 * WHAT THIS REPLACES. 49 themes lived as a hand-maintained `const themes = [...]`
 * inside monstrocity.php, wrapped in two nested PHP conditionals. Adding a theme
 * meant editing a 242KB file; nothing tied a theme to a project or collection;
 * and a second copy in monstrocity-test.php had already drifted to 40 themes.
 *
 * THE RULE FOR THIS MIGRATION: the generated JS must be BYTE-IDENTICAL to what
 * the literal produced, for every combination of $innercircle and $dev. Not
 * "equivalent", not "looks right" -- identical. monstrocity-themes-harness.php
 * enforces that against a snapshot of the original. Formatting can be modernised
 * later; it is held fixed here so that the only thing this change can be blamed
 * for is the data source.
 *
 * VISIBILITY IS TWO FLAGS, NOT A TIER. The $dev block was nested inside the
 * $innercircle block, so the one dev theme needs BOTH. A single tier column
 * would have shown it to a dev who is not Inner Circle -- a case the old code
 * never produced.
 *
 * Schema and seed: monstrocity-schema.md. Nothing here creates the table.
 */

/** Every active theme, in render order. Returns array of assoc rows. */
function monstrocity_themes($conn) {
	$out = array();
	/* No mysqlnd on this host, so no get_result(). Plain query, and there is
	   nothing user-supplied in it to escape. */
	$res = $conn->query(
		'SELECT slug, project_name, title, policy_ids, orientations, ipfs_prefixes,
		        background, extension, theme_group, group_order,
		        req_innercircle, req_dev
		   FROM monstrocity_themes
		  WHERE active = 1
		  ORDER BY sort_order ASC, id ASC');
	if ($res) { while ($r = $res->fetch_assoc()) $out[] = $r; }
	return $out;
}

/** Can this viewer see this theme? Mirrors the nesting of the old conditionals. */
function monstrocity_theme_visible($row, $innercircle, $dev) {
	if (!empty($row['req_innercircle']) && !$innercircle) return false;
	if (!empty($row['req_dev']) && !$dev)                 return false;
	return true;
}

/**
 * The `const themes = [...]` body, byte-for-byte as monstrocity.php used to
 * carry it. Returns ONLY the array contents -- the caller supplies
 * "const themes = [" and "];" so the surrounding file is untouched.
 *
 * The whitespace here is not stylistic. It is the whitespace the original used
 * (tab + spaces, then three tabs for the last two fields) and the harness
 * compares it byte for byte, so do not tidy it.
 */
function monstrocity_themes_js($conn, $innercircle, $dev) {
	$rows = monstrocity_themes($conn);

	/*
	 * IF THE TABLE IS NOT THERE, RENDER THE FROZEN LIST INSTEAD OF NOTHING.
	 *
	 * The live server is updated by `git pull`, so there is a window where
	 * this code exists and monstrocity_themes does not -- and the failure
	 * mode without this is a public game page with an empty theme picker.
	 * The snapshot is the exact list this replaced, so falling back to it
	 * means the worst case of a mis-ordered deploy is "yesterday's themes",
	 * not a broken page.
	 *
	 * Loud, not silent: an empty result is always worth a log line, because
	 * the other way to get here is somebody emptying the table by accident.
	 */
	if (!$rows) {
		error_log('monstrocity_themes_js: no rows from monstrocity_themes '
		        . '(table missing, empty, or query failed) -- falling back to the frozen snapshot');
		$snap = __DIR__ . '/monstrocity-themes-snapshot.php';
		if (is_file($snap)) {
			ob_start();
			include $snap;            // renders under the $innercircle/$dev in scope
			return rtrim(ob_get_clean(), " \t\r\n");
		}
		return '';
	}

	/* Group contiguously, exactly as the literal did. */
	$groups = array();
	foreach ($rows as $r) {
		if (!monstrocity_theme_visible($r, $innercircle, $dev)) continue;
		$g = $r['theme_group'];
		if (!isset($groups[$g])) $groups[$g] = array();
		$groups[$g][] = $r;
	}

	/* No empty-group guard here on purpose: $groups only gains a key when a
	   visible row lands in it, so an empty group cannot exist. A guard for it
	   would be a line no test could ever reach -- the mutation that deleted it
	   changed nothing, which is how it was found. */
	$blocks = array();
	foreach ($groups as $gname => $items) {
		$lines = array();
		foreach ($items as $r) {
			$lines[] =
				  "\t        {\n"
				. "\t          value: \""        . $r['slug']          . "\",\n"
				. "\t          project: \""      . $r['project_name']  . "\",\n"
				. "\t          title: \""        . $r['title']         . "\",\n"
				. "\t          policyIds: \""    . $r['policy_ids']    . "\",\n"
				. "\t          orientations: \"" . $r['orientations']  . "\",\n"
				. "\t          ipfsPrefixes: \"" . $r['ipfs_prefixes'] . "\",\n"
				. "\t\t\t  background: " . (!empty($r['background']) ? 'true' : 'false') . ",\n"
				. "\t\t\t  extension: \"" . $r['extension'] . "\" // Applies only to character images\n"
				. "\t        }";
		}
		$blocks[] = "\t    {\n"
		          . "\t      group: \"" . $gname . "\",\n"
		          . "\t      items: [\n"
		          . implode(",\n", $lines) . "\n"
		          . "\t      ]\n"
		          . "\t    }";
	}
	return implode(",\n", $blocks);
}
