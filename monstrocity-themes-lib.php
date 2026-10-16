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

/**
 * Policy ids per theme, taken from the collections attached to it.
 *
 * TWO QUERIES, NOT A GROUP_CONCAT. A join with GROUP_CONCAT would need a
 * GROUP BY that ONLY_FULL_GROUP_BY can reject depending on the server's mode,
 * and GROUP_CONCAT silently truncates at group_concat_max_len (1024 by
 * default, about 17 policies) -- silently being the problem. Two plain reads
 * and a merge in PHP have neither failure mode.
 *
 * Returns array(theme_id => 'policy,policy,...'); themes with nothing attached
 * are simply absent.
 */
function monstrocity_theme_policies($conn) {
	$out = array();
	$res = $conn->query(
		'SELECT tc.theme_id, c.policy
		   FROM monstrocity_theme_collections tc
		   JOIN collections c ON c.id = tc.collection_id
		  WHERE c.policy <> \'\'
		  ORDER BY tc.theme_id ASC, tc.sort_order ASC, c.id ASC');
	if (!$res) return $out;                 // table not there yet: legacy strings stand
	while ($r = $res->fetch_assoc()) {
		$id = (int)$r['theme_id'];
		if (!isset($out[$id])) $out[$id] = array();
		if (!in_array($r['policy'], $out[$id], true)) $out[$id][] = $r['policy'];
	}
	foreach ($out as $id => $list) $out[$id] = implode(',', $list);
	return $out;
}

/** Every active theme, in render order. Returns array of assoc rows. */
function monstrocity_themes($conn) {
	$out = array();
	/* No mysqlnd on this host, so no get_result(). Plain query, and there is
	   nothing user-supplied in it to escape. */
	$res = $conn->query(
		'SELECT id, slug, project_name, title, policy_ids, orientations, ipfs_prefixes,
		        background, extension, theme_group, group_order,
		        req_innercircle, req_dev
		   FROM monstrocity_themes
		  WHERE active = 1
		  ORDER BY sort_order ASC, id ASC');
	if ($res) { while ($r = $res->fetch_assoc()) $out[] = $r; }
	if (!$out) return $out;

	/*
	 * ATTACHED COLLECTIONS WIN OVER THE STATIC STRING.
	 *
	 * A theme can cover several collections, which is why some of them carry a
	 * list of policy ids in the first place. Where those collections exist on
	 * the platform, the policy belongs to the collection and should be read
	 * from it, so changing a policy is one edit in one place.
	 *
	 * Where they do not -- and that is most themes, because plenty are for
	 * projects that are not on Skulliance at all -- the legacy string stands
	 * untouched. With nothing attached, which is the state at migration time,
	 * every theme renders exactly the policy ids it always did.
	 */
	$derived = monstrocity_theme_policies($conn);
	foreach ($out as $i => $r) {
		$id = (int)$r['id'];
		if (isset($derived[$id]) && $derived[$id] !== '') $out[$i]['policy_ids'] = $derived[$id];
	}
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

/* ===========================================================================
 * ADMIN SIDE
 *
 * Pure functions here, IO in admin-monstrocity.php, so the rules can be tested
 * without a database or a web server. Same split the missions admin uses.
 * ======================================================================== */

/** Where a theme's art lives. Relative, because the page and the game both
 *  reference it and only one of them knows the document root. */
function monstrocity_theme_dir($slug) { return 'images/monstrocity/' . $slug; }

/** The three folders every theme needs before Phase 3 can fill them. */
function monstrocity_theme_subdirs() { return array('base', 'leader', 'battle-damaged'); }

/**
 * A slug is a FOLDER NAME and goes straight into a filesystem path and a URL,
 * so it is checked hard rather than trusted. Returns '' or the reason.
 */
function monstrocity_slug_problem($slug, $taken = array()) {
	if ($slug === '')                               return 'The folder name is required.';
	if (strlen($slug) > 64)                         return 'The folder name is longer than 64 characters.';
	if (!preg_match('/^[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?$/', $slug))
		return 'The folder name may use lowercase letters, numbers and hyphens only, '
		     . 'and must start and end with a letter or number.';
	/* '.' and '..' are already excluded by the pattern, but say so, because
	   the reason this is strict is path traversal and not tidiness. */
	if (in_array($slug, $taken, true))              return 'Another theme already uses the folder "' . $slug . '".';
	return '';
}

/** Orientations the game understands. 'Random' means the source collection
 *  faces both ways and each character is decided by eye. */
function monstrocity_orientations() { return array('', 'Left', 'Right', 'Random'); }

/** Extensions the character pipeline understands. gif/mov are legacy-only:
 *  Phase 3 generates png. */
function monstrocity_extensions() { return array('png', 'gif', 'jpg', 'mov'); }

/**
 * Everything wrong with a submitted theme, as a list of sentences.
 * $taken is the slugs already in use, excluding this theme's own.
 */
function monstrocity_validate_theme($t, $taken = array()) {
	$e = array();
	$p = monstrocity_slug_problem(isset($t['slug']) ? $t['slug'] : '', $taken);
	if ($p !== '') $e[] = $p;

	if (trim((string)(isset($t['project_name']) ? $t['project_name'] : '')) === '')
		$e[] = 'The project name is required -- it is what players see above the theme.';
	if (trim((string)(isset($t['title']) ? $t['title'] : '')) === '')
		$e[] = 'The title is required.';
	if (trim((string)(isset($t['theme_group']) ? $t['theme_group'] : '')) === '')
		$e[] = 'A group is required, because the picker renders themes under group headings.';

	if (!in_array(isset($t['orientations']) ? $t['orientations'] : '', monstrocity_orientations(), true))
		$e[] = 'Orientation must be blank, Left, Right or Random.';
	if (!in_array(isset($t['extension']) ? $t['extension'] : '', monstrocity_extensions(), true))
		$e[] = 'Extension must be one of: ' . implode(', ', monstrocity_extensions()) . '.';

	/* A policy id is 56 hex characters. Checking it catches the single most
	   common paste error -- a truncated id, or an asset name pasted instead --
	   which otherwise fails silently as a theme that matches nobody. */
	$pol = trim((string)(isset($t['policy_ids']) ? $t['policy_ids'] : ''));
	if ($pol !== '') {
		foreach (explode(',', $pol) as $one) {
			$one = trim($one);
			if ($one === '') { $e[] = 'The policy id list has an empty entry (a stray comma).'; continue; }
			if (!preg_match('/^[0-9a-f]{56}$/i', $one))
				$e[] = 'Policy id "' . $one . '" is not 56 hex characters.';
		}
	}

	/* The nesting the old code had: the dev block sat inside the Inner Circle
	   block, so dev-only without Inner Circle was never a state it could
	   produce, and the renderer would show it to a dev who is not Inner
	   Circle. Refuse it rather than quietly widening who can see a theme. */
	if (!empty($t['req_dev']) && empty($t['req_innercircle']))
		$e[] = 'A dev-only theme must also be Inner Circle -- that is how the visibility nests.';

	return $e;
}

/**
 * Which collections a theme should be linked to, from a submitted id list.
 * Returns ints, de-duplicated, order preserved -- the order is the order the
 * policy ids come out in, so it is the admin's choice and not the table's.
 */
function monstrocity_clean_collection_ids($raw) {
	$out = array();
	foreach ((array)$raw as $v) {
		$i = (int)$v;
		if ($i > 0 && !in_array($i, $out, true)) $out[] = $i;
	}
	return $out;
}
