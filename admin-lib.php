<?php
/**
 * admin-lib.php — the rules behind the admin panel, with no output.
 *
 * WHY A LIB AND NOT JUST admin.php. Every write this panel makes is also
 * an authorisation boundary, and store-edit-harness.php already paid for
 * the lesson that a page which decides for itself what to draw and an
 * endpoint which decides for itself what to accept are two rules that
 * drift apart. One answer, both callers, and a harness that runs the real
 * thing rather than a description of it.
 *
 * NOTHING HERE ECHOES and nothing here includes db.php, so the whole file
 * is testable against a fake connection. See admin-harness.php.
 *
 * The mission maths is written down in missions-economy.md; this is the
 * enforcing half of that document.
 */

if (!defined('ADMIN_SUPER_USER')) define('ADMIN_SUPER_USER', 1);

/* The multiplier ceiling. missions-economy.md section 5b: past this level
   a mission keeps climbing in cost and duration but not in rate, which is
   what stops a 46-mission project paying eight times a 6-mission one.
   0 disables the cap and reproduces the live uncapped behaviour exactly. */
if (!defined('ADMIN_MULT_CAP')) define('ADMIN_MULT_CAP', 0);

/*
 * WHO OWNS A NEW PROJECT'S STORE LISTINGS BY DEFAULT.
 *
 * projects.discord_id is what storeItemEditRights() matches a signed-in
 * creator against, so it decides who may edit that project's shop items.
 * Most partners do not run their own shop, and leaving the field blank
 * means NOBODY can edit those listings -- not even through the partner
 * path -- which only shows up the first time one needs fixing.
 *
 * So a new project defaults to the admin's own id. Editing an existing
 * project never substitutes it: a blank there was somebody's decision,
 * and silently filling it in would hand over listings.
 */
if (!defined('ADMIN_DEFAULT_DISCORD')) define('ADMIN_DEFAULT_DISCORD', '772831523899965440');

/**
 * WHO MAY USE THIS PANEL.
 *
 * Deliberately narrower than storeItemEditRights(): that one hands a
 * partner creator the keys to their own listings, which is right for a
 * shop. This panel creates projects and rewrites mission economics, so it
 * is user 1 and nobody else. Returning a reason rather than a bare false
 * so the page can say why instead of 404-ing a confused admin.
 */
function adminRights() {
	$uid = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
	if ($uid <= 0)                   return array('ok' => false, 'why' => 'not signed in');
	if ($uid !== ADMIN_SUPER_USER)   return array('ok' => false, 'why' => 'not an admin');
	return array('ok' => true, 'why' => '', 'user_id' => $uid);
}

function adminIsSuper() { $r = adminRights(); return $r['ok']; }

/* ---------- naming ------------------------------------------------------- */

/**
 * THE FILENAME A MISSION'S ART WILL TAKE.
 *
 * Must stay identical to mission_art_slug() in missions-lib.php -- that is
 * the function the game renders through, and a panel that previews a
 * different name than the one the player's browser asks for is worse than
 * no preview. admin-harness.php asserts the two agree.
 */
function admin_mission_slug($title) {
	return strtolower(str_replace("'", "", str_replace(" ", "-", (string)$title)));
}

/**
 * Is that slug safe to put on a filesystem and in a URL?
 *
 * mission_art_slug() only strips apostrophes and spaces, so a colon, a
 * slash, an ampersand or an accented character lands straight in the
 * filename. 455 live rows contain none -- discipline, not enforcement.
 * This is the enforcement.
 */
function admin_slug_problem($slug) {
	$slug = (string)$slug;
	if ($slug === '')                               return 'the title produces an empty filename';
	if (strlen($slug) > 120)                        return 'the title is too long to be a filename';
	if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $slug)) {
		$bad = preg_replace('/[a-z0-9\-]/', '', $slug);
		$bad = count_chars($bad, 3);
		return 'the title would put ' . ($bad === '' ? 'an unusable character' : '"' . $bad . '"')
		     . ' in the filename -- use only letters, numbers, spaces and apostrophes';
	}
	return '';
}

/* ---------- mission economics ------------------------------------------- */

/**
 * WHAT THE NEXT MISSION ON THIS PROJECT SHOULD COST.
 *
 * The cost ladder is the only free choice in the scheme (see
 * missions-economy.md section 1a), and the safest choice is to CONTINUE
 * WHAT THE PROJECT ALREADY DOES rather than impose a house style on a
 * ladder somebody already tuned.
 *
 * Two families exist live. A project on flat 100s gets 100 * level. A
 * project on the wide spread gets the next rung of the master sequence,
 * and once that runs out it continues at its own last step -- which is
 * better than silently switching family mid-ladder, because switching is
 * exactly what made append-safety fail in the first draft of the tiers.
 *
 * A brand-new project starts on flat 100s: it is what the newer half of
 * the platform already uses, and it is the only schedule where every
 * field depends on the level alone, so appending stays a pure INSERT.
 */
function admin_wide_ladder() {
	return array(200, 300, 500, 700, 1000, 1500, 2000, 2500, 3000);
}

function admin_next_cost($existing_costs, $level) {
	$level = (int)$level;
	if ($level <= 1) return 0;

	$paid = array();
	foreach ((array)$existing_costs as $c) { $c = (int)$c; if ($c > 0) $paid[] = $c; }
	sort($paid);

	/* No history, or the project is already on flat 100s: 100 * level. */
	$flat = true;
	foreach ($paid as $i => $c) { if ($c !== 100 * ($i + 2)) { $flat = false; break; } }
	if (!$paid || $flat) return 100 * $level;

	/* On the wide spread: take the next rung past the highest used. */
	$top = end($paid);
	foreach (admin_wide_ladder() as $rung) if ($rung > $top) return $rung;

	/* Past the master sequence. Continue by its own final step rather than
	   dropping back to a flat 100, which would make a mission CHEAPER than
	   the one below it. */
	$n = count($paid);
	$step = ($n >= 2) ? $paid[$n - 1] - $paid[$n - 2] : 500;
	return $top + max(100, (int)round($step / 100) * 100);
}

/** reward = cost * (1 + min(level, cap)/10). Cap 0 means uncapped. */
function admin_mission_reward($cost, $level, $cap = null) {
	$cap   = ($cap === null) ? ADMIN_MULT_CAP : (int)$cap;
	$level = (int)$level;
	$k     = ($cap > 0) ? min($level, $cap) : $level;
	return (int)(((int)$cost * (10 + $k)) / 10);
}

/** duration = cost / 100, in days. One field with the cost, never two. */
function admin_mission_duration($cost) { return (int)((int)$cost / 100); }

/** The number missions-lib.php puts in front of the player. */
function admin_mission_per_day($cost, $reward, $duration) {
	$duration = (int)$duration;
	return ($duration > 0) ? (((float)$reward - (float)$cost) / $duration) : 0.0;
}

/**
 * Everything the editor should pre-fill for a new mission at this level.
 * The operator writes the title, the description and the art; the
 * economics are not their problem.
 */
/**
 * WHAT A RUNG PAYS, GIVEN ITS COST AND LEVEL. One function, because there
 * were two and they disagreed.
 *
 * The free intro is the special case: level 1 is cost 0, reward 10, one
 * day on all 39 projects, and it cannot be derived from the cost because
 * admin_mission_duration(0) is 0 and admin_mission_reward(0, 1) is 0.
 *
 * THAT IS NOT HYPOTHETICAL. admin_mission_defaults() special-cased level
 * 1 and the save handler called the two raw functions directly, so the
 * form displayed "reward 10, 1 day" and the POST computed 0 and 0 -- and
 * admin_validate_mission() then refused the save with "Level 1 is the
 * free intro", which is true and was not the user's doing. Level 1 could
 * not be saved on any project. Both callers go through here now so the
 * displayed numbers and the written ones cannot drift again.
 *
 * Returns array(cost, reward, duration) -- cost included because level 1
 * forces it to 0 whatever was posted.
 */
function admin_mission_derive($cost, $level, $cap = null) {
	$level = (int)$level;
	if ($level <= 1) return array('cost' => 0, 'reward' => 10, 'duration' => 1);
	$cost = (int)$cost;
	return array('cost'     => $cost,
	             'reward'   => admin_mission_reward($cost, $level, $cap),
	             'duration' => admin_mission_duration($cost));
}

function admin_mission_defaults($existing_costs, $level, $cap = null) {
	$level = (int)$level;
	$cost  = ($level <= 1) ? 0 : admin_next_cost($existing_costs, $level);
	$d     = admin_mission_derive($cost, $level, $cap);
	return array('level' => $level, 'cost' => $d['cost'], 'reward' => $d['reward'],
	             'duration' => $d['duration'],
	             'per_day' => admin_mission_per_day($d['cost'], $d['reward'], $d['duration']));
}

/**
 * EVERY RULE FROM missions-economy.md SECTION 7, IN ONE PLACE.
 *
 * Returns a list of human-readable problems; empty means saveable. It
 * takes the project's OTHER levels rather than reading them itself so the
 * whole function stays testable with no database.
 */
function admin_validate_mission($m, $other_levels = array(), $cap = null) {
	$e     = array();
	$level = (int)(isset($m['level']) ? $m['level'] : 0);
	$cost  = (int)(isset($m['cost']) ? $m['cost'] : 0);
	$rew   = (int)(isset($m['reward']) ? $m['reward'] : 0);
	$dur   = (int)(isset($m['duration']) ? $m['duration'] : 0);
	$title = trim((string)(isset($m['title']) ? $m['title'] : ''));

	if ($title === '') $e[] = 'A mission needs a title; it is also the art filename.';
	else {
		$p = admin_slug_problem(admin_mission_slug($title));
		if ($p !== '') $e[] = ucfirst($p) . '.';
	}

	if ($level < 1) $e[] = 'Level must be 1 or more.';
	if (in_array($level, array_map('intval', (array)$other_levels), true))
		$e[] = "Level $level already exists on this project.";

	if ($level === 1) {
		/* The free intro, and it is the same on all 39 projects. */
		if ($cost !== 0 || $rew !== 10 || $dur !== 1)
			$e[] = 'Level 1 is the free intro: cost 0, reward 10, duration 1 day.';
	} else {
		if ($cost <= 0)        $e[] = 'Cost must be more than 0 above level 1.';
		if ($cost % 100 !== 0) $e[] = 'Cost must be a multiple of 100, or the duration cannot be whole days.';
		$want_dur = admin_mission_duration($cost);
		if ($dur !== $want_dur)
			$e[] = "Duration must be $want_dur day" . ($want_dur === 1 ? '' : 's')
			     . " for a cost of " . number_format($cost) . " -- duration is cost / 100, not a separate dial.";
		$want_rew = admin_mission_reward($cost, $level, $cap);
		if ($rew !== $want_rew)
			$e[] = 'Reward must be ' . number_format($want_rew) . ' for level ' . $level
			     . ' at that cost. Changing it breaks the one rule the whole economy rests on.';
		if ($rew % 10 !== 0)
			$e[] = 'Reward is not a multiple of ten, which means a multiplier finer than a tenth got in.';
	}
	return $e;
}

/**
 * A WHOLE PROJECT'S LADDER, checked as a set.
 *
 * Per-row validation cannot see a gap or a missing level 1, and both are
 * the kind of thing that only shows up as "mission 7 is unreachable"
 * weeks later. $rows is level => array(cost, reward, duration).
 */
function admin_validate_ladder($rows, $cap = null) {
	$e = array();
	if (!$rows) return array('This project has no missions yet.');

	$levels = array_map('intval', array_keys($rows));
	sort($levels);
	if ($levels[0] !== 1) $e[] = 'There is no level 1, so the project has no free opening mission.';
	for ($i = 1; $i < count($levels); $i++) {
		if ($levels[$i] !== $levels[$i - 1] + 1)
			$e[] = 'Levels jump from ' . $levels[$i - 1] . ' to ' . $levels[$i]
			     . '; the ladder has to be contiguous or the gap is unreachable.';
	}
	foreach ($rows as $lv => $r) {
		$r['level'] = (int)$lv;
		if (!isset($r['title'])) $r['title'] = 'placeholder';
		foreach (admin_validate_mission($r, array(), $cap) as $msg) $e[] = "Level $lv: $msg";
	}
	return $e;
}

/* ---------- reordering ---------------------------------------------- */

/**
 * REORDERING A LADDER, AND THE PROPERTY THAT MAKES IT SAFE.
 *
 * The rungs belong to the PROJECT; the story belongs to the MISSION. A
 * project's ladder is its sorted list of costs -- 200, 300, 500, 700 --
 * and level N always pays what level N paid. Dragging a mission does not
 * move its cost with it; it moves the mission onto a different rung.
 *
 * So reordering NEVER CHANGES WHAT THE PROJECT PAYS. The same rungs exist
 * before and after, the same total CARBON goes out over the same number
 * of days, and all that changes is which title sits at which tier. That
 * is the whole reason this is safe to offer as a drag: the worst case is
 * a story out of order, never an economy quietly rewritten.
 *
 * Level 1 is the free intro wherever it lands, so dragging a paid mission
 * to the top makes it free and pushes the old opener onto rung 2.
 *
 * $rows is id => array(level, cost, ...) as the table has them now.
 * $ordered_ids is the new order, top first. Returns:
 *   array('plan' => id => array(level, cost, reward, duration), 'errors' => array())
 */
function admin_reorder_plan($rows, $ordered_ids, $cap = null) {
	$errors = array();
	$have = array_map('intval', array_keys($rows));
	$want = array();
	foreach ((array)$ordered_ids as $i) { $i = (int)$i; if ($i > 0) $want[] = $i; }

	/* A PERMUTATION, NOTHING ELSE. A short list would silently drop
	   missions off the ladder; a long one would renumber something from
	   another project. Compared as sorted sets so order is irrelevant
	   here and duplicates are caught. */
	$a = $have; $b = $want; sort($a); sort($b);
	if ($a !== $b) {
		$errors[] = 'That order does not match this project\'s missions exactly '
		          . '(' . count($want) . ' given, ' . count($have) . ' expected). Nothing was changed.';
		return array('plan' => array(), 'errors' => $errors);
	}

	/* The project's own rungs, in level order. Costs are taken from the
	   ladder as it stands rather than regenerated, so a wide-spread
	   project keeps its wide spread and a flat one keeps its flat steps. */
	$by_level = array();
	foreach ($rows as $id => $r) $by_level[(int)$r['level']] = (int)$r['cost'];
	ksort($by_level);
	$rungs = array_values($by_level);

	$plan = array();
	foreach ($want as $pos => $id) {
		$level = $pos + 1;
		if ($level === 1) {
			$plan[$id] = array('level' => 1, 'cost' => 0, 'reward' => 10, 'duration' => 1);
			continue;
		}
		$cost = isset($rungs[$pos]) ? (int)$rungs[$pos] : admin_next_cost($rungs, $level);
		$plan[$id] = array(
			'level'    => $level,
			'cost'     => $cost,
			'reward'   => admin_mission_reward($cost, $level, $cap),
			'duration' => admin_mission_duration($cost),
		);
	}
	return array('plan' => $plan, 'errors' => $errors);
}

/**
 * The order after moving one mission one place up or down.
 *
 * The no-JS half of the feature: up and down are submit buttons, so the
 * ladder can always be reordered even where a drag cannot happen -- a
 * touch screen, a keyboard, or JS that failed to load. Both paths end in
 * admin_reorder_plan(), so there is one set of rules and not two.
 */
function admin_reorder_move($rows, $quest_id, $direction) {
	$order = array();
	foreach ($rows as $id => $r) $order[(int)$r['level']] = (int)$id;
	ksort($order);
	$order = array_values($order);

	$at = array_search((int)$quest_id, $order, true);
	if ($at === false) return $order;
	$to = ($direction === 'up') ? $at - 1 : $at + 1;
	if ($to < 0 || $to >= count($order)) return $order;      // already at the end
	$tmp = $order[$at]; $order[$at] = $order[$to]; $order[$to] = $tmp;
	return $order;
}

/* ---------- currency icons ---------------------------------------------- */

/**
 * A PROJECT'S CURRENCY IS A FILENAME.
 *
 * Every surface renders it as icons/{lowercase(currency)}.png -- gallery,
 * realms, skulliance.php, Skull Swap's tile set. So two projects sharing a
 * currency share an icon, and skullswap.php's board dedupes on
 * LOWER(currency), which means the collision is not even visible as two
 * entries. Case-insensitive, because that is how the game groups them.
 */
function admin_currency_icon($currency) {
	return 'icons/' . strtolower(str_replace('$', '', trim((string)$currency))) . '.png';
}

function admin_currency_problem($currency, $taken = array()) {
	$c = trim((string)$currency);
	if ($c === '')                            return 'A project needs a currency; it is the icon filename.';
	if (!preg_match('/^[A-Za-z0-9]{2,16}$/', $c))
		return 'Currency must be 2 to 16 letters or digits -- it becomes a filename and a tile on the Skull Swap board.';
	foreach ((array)$taken as $t) {
		if (strcasecmp(trim((string)$t), $c) === 0)
			return 'Another project already uses "' . $t . '". They would share one icon, and Skull Swap would '
			     . 'show them as a single tile.';
	}
	return '';
}

/* ---------- uploads ------------------------------------------------------ */

/**
 * WHAT MAY BE UPLOADED, AND AS WHAT.
 *
 * quests.extension drives which files the game asks for, and the pairing
 * is not obvious: an mp4 mission needs the .mp4 AND a .gif beside it,
 * because every <img> surface asks for the gif (mission_art_ext()).
 *
 * mov is refused rather than accepted-and-converted. There is no ffmpeg
 * and no exec() on this host and Imagick has no video delegates, so there
 * is nothing to convert it WITH -- and accepting it would write a row the
 * game renders as a broken tile. A refusal with a reason is the honest
 * version. See missions-economy.md section 6d.
 */
function admin_art_kinds() {
	return array(
		'png' => array('still' => 'png', 'video' => false, 'mime' => array('image/png')),
		'jpg' => array('still' => 'jpg', 'video' => false, 'mime' => array('image/jpeg')),
		'gif' => array('still' => 'gif', 'video' => false, 'mime' => array('image/gif')),
		'mp4' => array('still' => 'gif', 'video' => true,  'mime' => array('video/mp4')),
	);
}

/** Mirrors mission_art_ext() in missions-lib.php. The harness pins them equal. */
function admin_art_ext($extension) { return ($extension === 'mp4') ? 'gif' : $extension; }

/**
 * Which files an operator still owes us for this mission.
 * $have is the set of extensions actually uploaded, e.g. array('mp4').
 */
function admin_art_missing($extension, $have) {
	$kinds = admin_art_kinds();
	if (!isset($kinds[$extension])) return array('"' . $extension . '" is not a format this platform renders.');
	$have = array_map('strtolower', (array)$have);
	$need = array($kinds[$extension]['still']);
	if ($kinds[$extension]['video']) $need[] = $extension;
	$missing = array();
	foreach ($need as $n) if (!in_array($n, $have, true)) $missing[] = $n;
	if ($missing && $extension === 'mp4')
		return array('An mp4 mission needs both files: the .mp4 and a .gif beside it for the still image. '
		           . 'Missing: .' . implode(', .', $missing) . '.');
	return $missing ? array('Missing the .' . implode(', .', $missing) . ' file.') : array();
}

/**
 * WHAT HAPPENS TO THE FILES WHEN A MISSION IS EDITED.
 *
 * The art filename is the TITLE, so renaming a mission renames its
 * files. Before this, an edit left the old files behind and the row
 * pointing at names that did not exist -- a broken tile plus permanent
 * debt in images/missions/, growing by one orphan per rename.
 *
 * A PLAN, NOT AN ACTION. This decides and returns; the caller applies.
 * The whole point is that something which deletes art can be exercised
 * exhaustively with no filesystem, and admin-harness.php does.
 *
 *   $old_slug   '' for a new mission
 *   $new_ext    the format the row will claim
 *   $disk_old   extensions on disk for the OLD slug
 *   $disk_new   extensions on disk for the NEW slug
 *   $uploaded   extensions arriving in this request, written to the NEW slug
 *
 * Returns rename/delete/missing/keep, all as plain "slug.ext" strings.
 * `missing` non-empty means DO NOT SAVE: the row would claim a format
 * whose file is not there.
 */
function admin_art_plan($old_slug, $new_slug, $new_ext, $disk_old, $disk_new, $uploaded) {
	$kinds = admin_art_kinds();
	$out = array('rename' => array(), 'delete' => array(), 'missing' => array(), 'keep' => array());
	if (!isset($kinds[$new_ext])) { $out['missing'][] = $new_ext; return $out; }

	$same     = ($old_slug === $new_slug || $old_slug === '');
	$disk_old = array_values(array_unique(array_map('strtolower', (array)$disk_old)));
	$disk_new = array_values(array_unique(array_map('strtolower', (array)$disk_new)));
	$uploaded = array_values(array_unique(array_map('strtolower', (array)$uploaded)));

	/* An mp4 needs its .gif too -- that is the still every <img> asks
	   for (mission_art_ext). Everything else is its own still. */
	$required = array($kinds[$new_ext]['still']);
	if ($kinds[$new_ext]['video']) $required[] = $new_ext;
	$required = array_values(array_unique($required));

	/* An upload always wins: it is the newest thing the operator did. */
	$have_new = array_values(array_unique(array_merge($same ? $disk_old : $disk_new, $uploaded)));

	foreach ($required as $ext) {
		if (in_array($ext, $have_new, true)) { $out['keep'][] = "$new_slug.$ext"; continue; }
		/* Not under the new name, but the old name has it: rename rather
		   than demand a re-upload. This is the whole request. */
		if (!$same && in_array($ext, $disk_old, true)) {
			$out['rename'][] = array("$old_slug.$ext", "$new_slug.$ext");
			continue;
		}
		$out['missing'][] = $ext;
	}

	/* NO DEBT. Two sources of it: files still under the old name that
	   nothing renamed, and files under the new name in a format this
	   mission no longer claims -- an mp4 switched to png leaves a .mp4
	   and a .gif behind otherwise. */
	foreach ($disk_old as $ext) {
		if ($same) continue;
		$renamed = false;
		foreach ($out['rename'] as $r) if ($r[0] === "$old_slug.$ext") { $renamed = true; break; }
		if (!$renamed) $out['delete'][] = "$old_slug.$ext";
	}
	foreach (array_unique(array_merge($same ? $disk_old : $disk_new, $uploaded)) as $ext) {
		if (!in_array($ext, $required, true)) $out['delete'][] = "$new_slug.$ext";
	}

	/*
	 * A RENAME'S DESTINATION MUST NEVER ALSO BE DELETED -- that is how a
	 * successful rename loses the file a moment later.
	 *
	 * It cannot happen as this stands, and the reason is worth writing
	 * down rather than guarding: a rename destination is always a
	 * REQUIRED extension under the new slug, and the two delete sources
	 * are old-slug files (a different name) and new-slug files in
	 * formats that are NOT required. The sets cannot intersect.
	 *
	 * There was a filter here for it. It was removed because it could
	 * never fire, and unreachable code that looks load-bearing is worse
	 * than none -- a mutation proved it changed nothing. The invariant
	 * is asserted in admin-harness.php instead, so a future change that
	 * adds a third delete source fails loudly there rather than being
	 * silently papered over here.
	 */
	$out['delete'] = array_values(array_unique($out['delete']));
	return $out;
}

/**
 * Apply a plan. Returns '' or a message, and leaves the files as it
 * found them if a rename fails partway.
 *
 * ORDER IS THE SAFETY. Renames first, because they are reversible;
 * deletions LAST, and only once the caller has confirmed the row saved.
 * Deleting before the write is how an edit that fails validation takes
 * the art with it.
 */
function admin_art_apply_renames($dir, $plan) {
	$done = array();
	foreach ($plan['rename'] as $r) {
		$from = $dir . '/' . $r[0];
		$to   = $dir . '/' . $r[1];
		if (!is_file($from)) continue;
		if (!@rename($from, $to)) {
			foreach (array_reverse($done) as $u) @rename($dir . '/' . $u[1], $dir . '/' . $u[0]);
			return 'Could not rename ' . htmlspecialchars($r[0]) . ' to ' . htmlspecialchars($r[1])
			     . '. Nothing was changed.';
		}
		$done[] = $r;
	}
	return '';
}

function admin_art_apply_deletes($dir, $plan) {
	$gone = 0;
	foreach ($plan['delete'] as $f) {
		/* Belt and braces on a destructive call: only ever a bare
		   slug.ext inside the missions directory, never a path. */
		if (!preg_match('/^[a-z0-9][a-z0-9\-]*\.(png|jpg|gif|mp4)$/', $f)) continue;
		if (is_file($dir . '/' . $f) && @unlink($dir . '/' . $f)) $gone++;
	}
	return $gone;
}

/*
 * HOW BIG AN IMAGE MAY BE, AND HOW MUCH MEMORY IT NEEDS.
 *
 * ImageMagick allocates its pixel cache at Q16 -- EIGHT bytes a pixel,
 * not four -- and a resize needs a working copy on top, so the real cost
 * is roughly 16 bytes per pixel. A 5000x5000 upload is 25 megapixels and
 * therefore about 400MB.
 *
 * AND PAST ITS MEMORY LIMIT, IMAGEMAGICK DOES NOT FAIL. It spills the
 * pixel cache to DISK and carries on, which turns a two-second resize
 * into minutes. That is what took the staking site down on 2026-10-05: a
 * 5000x5000 mission image against a flat 256MB limit, the request never
 * returning, and every navigation behind it passing the service worker's
 * 20s timeout and landing on offline.html. The server was never down.
 *
 * So the limit is sized to the IMAGE rather than fixed, and anything
 * genuinely absurd is refused up front from the header alone -- instantly,
 * and with a number in the message -- instead of being accepted and
 * silently ground through swap.
 */
if (!defined('ADMIN_IMAGE_MAX_MP'))  define('ADMIN_IMAGE_MAX_MP', 30);          // megapixels
if (!defined('ADMIN_IMAGE_MAX_MEM')) define('ADMIN_IMAGE_MAX_MEM', 576);        // MB ceiling

/**
 * Decide before decoding. Pure, so admin-harness.php can cover the sums
 * without an image or an Imagick.
 *
 * Returns array('ok' => bool, 'mem' => bytes, 'why' => message).
 */
/**
 * BYTES PER PIXEL IN THIS IMAGICK BUILD, which is not a constant.
 *
 * ImageMagick's pixel cache is quantum-depth x 4 channels. A Q16 build is
 * 8 bytes a pixel; an HDRI build stores every channel as a FLOAT and is 16,
 * whatever the quantum says. The budget below assumed the small number, so
 * on an HDRI host it granted an image roughly half of what it needed and
 * ImageMagick answered "cache resources exhausted" at OpenPixelCache --
 * in 4ms, before a single row was decoded.
 *
 * Asked rather than assumed: the shell php on this host has no Imagick at
 * all, so a CLI reading could not have answered this either way.
 */
/**
 * WHY THE LAST IMAGE WAS STORED WITHOUT BEING OPTIMISED, if it was.
 *
 * admin_write_image() no longer refuses an upload because Imagick could
 * not process it. It keeps the original and leaves the reason here for
 * the page to show as a warning rather than an error.
 */
function adm_image_note($set = null) {
	static $note = '';
	if ($set !== null) $note = (string) $set;
	return $note;
}

function adm_imagick_bpp() {
	if (!class_exists('Imagick')) return 8;
	$d    = @Imagick::getQuantumDepth();
	$bits = isset($d['quantumDepthLong']) ? (int) $d['quantumDepthLong'] : 16;
	$v    = @Imagick::getVersion();
	$vs   = isset($v['versionString']) ? (string) $v['versionString'] : '';
	$per  = (stripos($vs, 'HDRI') !== false) ? 4 : max(1, intdiv($bits, 8));
	return max(4, $per * 4);
}

function admin_image_budget($w, $h, $frames = 1) {
	$w = (int)$w; $h = (int)$h;
	$frames = max(1, (int)$frames);
	if ($w <= 0 || $h <= 0)
		return array('ok' => false, 'mem' => 0, 'why' => 'That file does not read as an image.');

	$mp = ($w * $h) / 1000000;
	if ($mp > ADMIN_IMAGE_MAX_MP) {
		return array('ok' => false, 'mem' => 0, 'why' => sprintf(
			'That image is %d x %d (%s megapixels), over the %d megapixel limit. '
			. 'Resize it to about 2000px on the long edge and upload again -- mission art is '
			. 'never displayed above 1000px, so nothing is lost.',
			$w, $h, rtrim(rtrim(number_format($mp, 1), '0'), '.'), ADMIN_IMAGE_MAX_MP));
	}

	/*
	 * FRAMES COUNT, AND THE FIRST VERSION OF THIS DID NOT COUNT THEM.
	 *
	 * coalesceImages() expands every frame to the full canvas, so an
	 * animated GIF costs width x height x 8 bytes PER FRAME, not once.
	 * A 555 x 778 Buffy Bot at 58 frames is 191MB coalesced and roughly
	 * double that at peak -- and this function handed Imagick the 64MB
	 * floor, because it only ever looked at one frame. Past its limit
	 * Imagick SPILLS TO DISK rather than failing, so the symptom was a
	 * form that churned until the service worker gave up at 20s and
	 * showed the offline page. Exactly the 5000x5000 failure again, in
	 * the one dimension the fix for it did not measure.
	 */
	/*
	 * SOURCE PLUS THE COPY THAT LIVES BESIDE IT, AT THIS BUILD'S REAL
	 * BYTES PER PIXEL.
	 *
	 * The old figure was a flat w x h x 16, which on a Q16 HDRI host is
	 * EXACTLY the source cache and nothing else -- no destination, no
	 * working room. ImageMagick refused at the first allocation with
	 * "cache resources exhausted", in 4ms. Before RESOURCETYPE_DISK was
	 * pinned to 0 the same shortfall spilled to disk instead and ground,
	 * which is the "it locks up for a while and sometimes saves" this
	 * started as. Same cause, two symptoms, depending on whether the
	 * spill was available.
	 *
	 * resizeImage() holds the destination alongside the source, so the
	 * budget is both. x1.6 for the pyramid Lanczos builds and the odd
	 * clone, which is slack rather than a measured figure -- the point is
	 * that it is no longer less than one.
	 */
	/*
	 * THE OLD FIGURE WAS w x h x 16, AND ONLY ONE NUMBER IN IT WAS WRONG.
	 *
	 * 16 was "8 bytes a pixel, twice" -- the Q16 pixel cache plus one
	 * working copy for the coalesce or the resize. The 8 is the part that
	 * is not a constant: an HDRI build stores every channel as a float and
	 * costs 16 bytes a pixel, so on this host the budget was granting
	 * EXACTLY the source cache and nothing else. ImageMagick refused at
	 * the first allocation -- "cache resources exhausted" at
	 * OpenPixelCache, in 4ms, on a perfectly ordinary 1MB comic page.
	 *
	 * Before RESOURCETYPE_DISK was pinned to 0 the same shortfall spilled
	 * to disk instead and ground away, which is the "it locks the server
	 * up for a while and sometimes saves" this began as. One cause, two
	 * symptoms, depending on whether the spill was available.
	 *
	 * So: the same x2, measured against what a pixel actually costs here.
	 * Deliberately NOT a bigger multiplier -- a first attempt counted the
	 * resize and the coalesce separately, which refused the 58-frame GIF
	 * that has always worked and broke the frame-linearity this function
	 * is held to. The harness caught both.
	 */
	$bpp  = adm_imagick_bpp();
	$need = (int) max(96 * 1024 * 1024, (float) $w * (float) $h * $bpp * 2 * $frames);
	$cap   = ADMIN_IMAGE_MAX_MEM * 1024 * 1024;
	if ($need > $cap) {
		return array('ok' => false, 'mem' => 0, 'why' => sprintf(
			'That is %d frame%s at %d x %d, which needs about %dMB to process (%d bytes a pixel '
			. 'on this server) and the limit is %dMB. Either cut the frame count or scale it '
			. 'down -- mission art is never shown above 1000px wide.',
			$frames, $frames === 1 ? '' : 's', $w, $h,
			(int)($need / 1048576), $bpp, ADMIN_IMAGE_MAX_MEM));
	}
	return array('ok' => true, 'mem' => $need, 'why' => '');
}

/**
 * HOW MANY FRAMES, WITHOUT DECODING ANYTHING.
 *
 * Imagick would tell us, but only after reading the file -- which is the
 * allocation we are trying to decide about. A GIF announces each frame
 * with a Graphic Control Extension (21 F9 04), so counting those is a
 * byte scan over a few megabytes and costs nothing. Anything that is not
 * a GIF is treated as one frame: PNG and JPEG are, and an APNG scanned
 * this way reads as 1, which errs toward attempting rather than refusing.
 */
function admin_frame_count($path) {
	$fh = @fopen($path, 'rb');
	if (!$fh) return 1;
	$magic = fread($fh, 6);
	if ($magic !== 'GIF87a' && $magic !== 'GIF89a') { fclose($fh); return 1; }
	$n = 0; $tail = '';
	while (!feof($fh)) {
		$chunk = $tail . fread($fh, 1 << 20);
		$n += substr_count($chunk, "\x21\xf9\x04");
		/* Carry the last 2 bytes so a marker split across reads is counted. */
		$tail = substr($chunk, -2);
	}
	fclose($fh);
	return max(1, $n);
}

/**
 * Resize and optimise through the SAME Imagick path the NFT cache uses.
 *
 * lib/image-cache-lib.php already does this in the web SAPI --
 * ajax/cache-nft-image.php drives it over HTTP -- so the memory limits,
 * the gif coalescing and the Lanczos filter are settled and proven here
 * rather than invented. Returns '' on success or a message.
 */
function admin_write_image($tmp_path, $dest_path, $max_width = 1000) {
	if (!is_readable($tmp_path)) return 'The upload did not arrive.';
	if (!class_exists('Imagick')) {
		/* Still better than refusing: an unoptimised image renders. */
		return @copy($tmp_path, $dest_path) ? '' : 'Could not write ' . basename($dest_path) . '.';
	}
	/* FROM THE HEADER, BEFORE ANY DECODE. getimagesize() reads a few
	   bytes; it is the difference between an instant refusal and a
	   request that never comes back. */
	$size   = @getimagesize($tmp_path);
	$w      = $size ? (int)$size[0] : 0;
	$h      = $size ? (int)$size[1] : 0;
	$frames = admin_frame_count($tmp_path);

	/*
	 * NOTHING TO DO IS THE CHEAPEST THING TO DO.
	 *
	 * An animated GIF already inside the display width needs no resize,
	 * and coalescing 58 frames to change nothing is the whole cost for
	 * none of the benefit. Copy it and keep the artist's exact timing.
	 * Single-frame art still goes through Imagick, where the strip and
	 * the recompress are cheap and worth having.
	 */
	if ($frames > 1 && $w > 0 && $w <= $max_width)
		return @copy($tmp_path, $dest_path) ? '' : 'Could not write ' . basename($dest_path) . '.';

	$budget = admin_image_budget($w, $h, $frames);
	if (!$budget['ok']) return $budget['why'];

	try {
		$im = new Imagick();
		$im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $budget['mem']);
		$im->setResourceLimit(Imagick::RESOURCETYPE_MAP,    $budget['mem']);
		/*
		 * AREA IS THE ONE THAT DECIDES WHETHER MEMORY IS USED AT ALL.
		 *
		 * ImageMagick switches an image to a DISK-BACKED pixel cache when
		 * its area exceeds the area limit -- no matter how much memory it
		 * has been allowed. That is why raising MEMORY and MAP alone did
		 * not stop "cache resources exhausted": the cache was never going
		 * to memory, and DISK was pinned to 0, so the fallback it had
		 * already chosen was the one thing forbidden.
		 */
		/* GENEROUSLY, because AREA is a THRESHOLD and not an allocation:
		   it only decides whether the cache may live in memory, and
		   setting it equal to the memory budget means an image that needs
		   the whole budget is pushed to disk by a hair. MEMORY and MAP
		   above are the real ceilings. */
		$im->setResourceLimit(Imagick::RESOURCETYPE_AREA, $budget['mem'] * 4);
		/*
		 * AND THE DISK IS A FALLBACK AGAIN, BOUNDED RATHER THAN BANNED.
		 *
		 * Pinning it to 0 was too blunt and it is my own mistake twice
		 * over: lib/image-cache-lib.php does this same work on this same
		 * host and leaves disk alone, and its header says plainly that its
		 * limits are "settled and proven here". It brakes on TIME instead
		 * -- ImageMagick's own clock, which set_time_limit() cannot touch
		 * because that does not count time spent in C.
		 *
		 * That is the right brake. A spill is slow, not wrong; a spill
		 * with no time limit is the hang. So the disk gets a bounded
		 * allowance and the clock stops the grind.
		 */
		$im->setResourceLimit(Imagick::RESOURCETYPE_DISK, 2 * $budget['mem']);
		if (defined('Imagick::RESOURCETYPE_TIME')) {
			/* 60s, the figure lib/image-cache-lib.php uses off the web
			   path. 20 was my own invention and it cut off work that was
			   merely slow, on a host that throttles CPU. The clock is
			   here to stop a hang, not to police a big picture. */
			@$im->setResourceLimit(constant('Imagick::RESOURCETYPE_TIME'), 60);
		}
		/* A huge JPEG can be decoded already scaled down, which skips the
		   full-size allocation entirely. PNG has no equivalent. */
		if ($max_width > 0) $im->setOption('jpeg:size', ($max_width * 2) . 'x' . ($max_width * 2));
		$im->readImage($tmp_path);
		if ($im->getNumberImages() > 1) {
			/* Coalesce first or every frame after the first resizes against
			   a partial canvas -- the same correction image-cache-lib.php
			   carries for animated NFT art. */
			$im = $im->coalesceImages();
			foreach ($im as $frame) if ($frame->getImageWidth() > $max_width)
				$frame->resizeImage($max_width, 0, Imagick::FILTER_LANCZOS, 1);
			$im = $im->deconstructImages();
			$ok = $im->writeImages($dest_path, true);
		} else {
			/*
			 * thumbnailImage(), NOT resizeImage(), AND THE DIFFERENCE IS
			 * THE WHOLE COST.
			 *
			 * resizeImage() runs a Lanczos pass over every source pixel.
			 * On an HDRI build that is four floats a pixel, and on a
			 * throttled shared host a 3.7MP page took longer than the 20s
			 * ImageMagick clock -- "time limit exceeded" at
			 * GetImagePixelCache, after the budget was already large
			 * enough to hold the image twice over. It was never short of
			 * room; it was short of CPU.
			 *
			 * thumbnailImage() samples the image down cheaply FIRST and
			 * only then filters, which is the operation this actually
			 * wants: mission art is never displayed above 1000px, so
			 * nothing is being thrown away that anybody sees. It also
			 * strips the image as part of the job.
			 */
			if ($im->getImageWidth() > $max_width)
				$im->thumbnailImage($max_width, 0);
			$im->stripImage();                       // drop EXIF and colour profiles
			if (strtolower(pathinfo($dest_path, PATHINFO_EXTENSION)) === 'jpg')
				$im->setImageCompressionQuality(86);
			$ok = $im->writeImage($dest_path);
		}
		if ($ok) return '';
		return admin_keep_original($tmp_path, $dest_path,
			'Imagick could not write ' . basename($dest_path) . '.');
	} catch (Throwable $ex) {
		/*
		 * NAME THE LIMITS THAT WERE IN FORCE. "cache resources exhausted"
		 * does not say WHICH resource ran out, and this failure has now
		 * been misdiagnosed twice from the message alone -- once as memory
		 * and once as the disk ban. Reading them back from ImageMagick
		 * also proves setResourceLimit() took effect, which was itself an
		 * assumption until it was printed.
		 */
		$lim = '';
		if (class_exists('Imagick')) {
			$names = array('AREA' => 'RESOURCETYPE_AREA', 'MEMORY' => 'RESOURCETYPE_MEMORY',
			               'MAP' => 'RESOURCETYPE_MAP', 'DISK' => 'RESOURCETYPE_DISK',
			               'TIME' => 'RESOURCETYPE_TIME');
			$parts = array();
			foreach ($names as $label => $const) {
				if (!defined('Imagick::' . $const)) continue;
				$v = @Imagick::getResourceLimit(constant('Imagick::' . $const));
				if ($v === false || $v === null) continue;
				$parts[] = $label . '=' . ($v > 4096 ? round($v / 1048576) . 'MB' : $v);
			}
			if ($parts) $lim = ' [asked for ' . round($budget['mem'] / 1048576) . 'MB; in force: '
			                 . implode(' ', $parts) . '; ' . adm_imagick_bpp() . ' bytes/px]';
		}
		return admin_keep_original($tmp_path, $dest_path, $ex->getMessage() . $lim);
	}
}

/**
 * KEEP THE ORIGINAL RATHER THAN REFUSE THE UPLOAD.
 *
 * THE RESIZE IS AN OPTIMISATION AND IT WAS ACTING LIKE A GATE. This file
 * already says, for a host with no Imagick at all, "Still better than
 * refusing: an unoptimised image renders" -- and then refused anyway the
 * moment Imagick was present but could not finish. That is the wrong way
 * round: a 1MB comic page displays perfectly well unresized, and the
 * person uploading it is blocked by a step whose only job was to make the
 * file smaller.
 *
 * It took three attempts at the resource limits to notice that. Each one
 * fixed something real and none of them was the point: the owner wanted
 * to add a mission, not to tune ImageMagick.
 *
 * The reason is kept for the page to show as a WARNING -- saved, and here
 * is what did not happen -- rather than swallowed. adm-msg.warn exists
 * for exactly this: nothing was refused, but something about what was
 * saved will matter later.
 */
function admin_keep_original($tmp_path, $dest_path, $why) {
	if (@copy($tmp_path, $dest_path)) {
		adm_image_note('Saved, but stored at its original size: ' . $why);
		return '';
	}
	return 'Could not process that image, and could not keep the original either: ' . $why;
}

/* ---------- reads and uploads, shared by the three admin pages ----------- *
 *
 * They take $conn as a parameter and this file still includes nothing, so
 * admin-harness.php keeps running the rules with no database.
 */
/* reads -------------------------------------------------------- */

function adm_projects($conn) {
	$out = array();
	$r = $conn->query("SELECT id, name, currency, discord_id, divider FROM projects ORDER BY name ASC");
	while ($r && $row = $r->fetch_assoc()) $out[(int)$row['id']] = $row;
	return $out;
}
function adm_collections($conn, $project_id) {
	$out = array(); $project_id = (int)$project_id;
	$r = $conn->query("SELECT id, name, policy, rate, blockchain_id, marketplace_slug
	                   FROM collections WHERE project_id = $project_id ORDER BY name ASC");
	while ($r && $row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}
function adm_missions($conn, $project_id) {
	$out = array(); $project_id = (int)$project_id;
	$r = $conn->query("SELECT id, title, description, extension, cost, reward, duration, level
	                   FROM quests WHERE project_id = $project_id ORDER BY level ASC");
	while ($r && $row = $r->fetch_assoc()) $out[(int)$row['level']] = $row;
	return $out;
}
/* Every title on the platform, because the art filename is the title and
   a collision silently overwrites another project's file. */
function adm_all_titles($conn, $except_id = 0) {
	$out = array(); $except_id = (int)$except_id;
	$r = $conn->query("SELECT id, title FROM quests" . ($except_id ? " WHERE id != $except_id" : ""));
	while ($r && $row = $r->fetch_assoc()) $out[admin_mission_slug($row['title'])] = (int)$row['id'];
	return $out;
}

/* ---------- uploads ------------------------------------------------------ */

/**
 * Accept one uploaded file into $dir/$basename.$ext, resized and stripped.
 * Returns '' or a message. Nothing is written until the type is confirmed
 * from the FILE, never from the name the browser supplied.
 */
/**
 * A POST OVER post_max_size ARRIVES AS NO POST AT ALL.
 *
 * PHP discards $_POST AND $_FILES before the script runs. There is no
 * warning and nothing in $_FILES['x']['error'], because $_FILES does not
 * exist either -- so the page sees an empty form and reports whichever
 * field it validates first. An oversized image gets reported as "the title
 * is required", or, on a page that validates nothing first, as nothing at
 * all: you press Save and it just sits there.
 *
 * CONTENT_LENGTH survives, which is the only reason this is detectable.
 *
 * SHARED, because it is not a property of any one form. This lived in
 * monstrocity-themes-lib.php and admin-missions.php did not have it, which
 * is the whole reason a mission upload could fail silently -- so the
 * Monstrocity one now delegates here rather than being a second copy.
 */
/**
 * A STAGE MARKER FOR A SAVE NOBODY CAN SEE FAIL.
 *
 * The missions form has now failed four different ways in one sitting --
 * silent refusals, a button that would not take a press, a submit that
 * said "Saving..." and saved nothing -- and every diagnosis so far has
 * been reasoning from the source rather than evidence from the request.
 * This is the evidence: each stage of the save appends a line, and the
 * page prints the last few back for a super-admin.
 *
 * WHY A FILE. The owner is usually on a phone and cannot read a server
 * log, and a request that dies mid-way cannot report on itself. A file
 * survives the request that wrote it. .htaccess already refuses to serve
 * .log, which was checked before choosing the extension.
 *
 * Deliberately crude and deliberately temporary: no rotation beyond a
 * size cap, no levels, no structure. It exists to answer one question --
 * how far does the save get -- and should come out once it has.
 */
function adm_trace($tag, $extra = '') {
	$dir = __DIR__ . '/logs';
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return;
	$file = $dir . '/admin-missions.log';
	/* Cap it rather than grow a 5.7MB debug.log like ajax/ once did. */
	if (@filesize($file) > 256 * 1024) @unlink($file);
	@file_put_contents($file,
		date('Y-m-d H:i:s') . '  ' . getmypid() . '  ' . $tag
		. ($extra === '' ? '' : '  ' . $extra) . "\n", FILE_APPEND | LOCK_EX);
}

/** The last few lines of that trace, newest last. */
function adm_trace_tail($lines = 12) {
	$file = __DIR__ . '/logs/admin-missions.log';
	if (!is_file($file)) return array();
	$all = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if (!$all) return array();
	return array_slice($all, -max(1, (int) $lines));
}

function adm_post_discarded($method, $post, $content_length) {
	return strtoupper((string) $method) === 'POST'
	    && empty($post)
	    && (int) $content_length > 0;
}

/**
 * The same thing, said to whoever is looking at the form.
 *
 * Names the two limits AND what was sent, because "too large" without a
 * number is a guessing game -- and the per-form and per-file limits are
 * different, so which one was hit decides whether you shrink the image or
 * post fewer files.
 */
function adm_post_discarded_message($content_length, $what = 'upload') {
	return 'That ' . $what . ' was too large, so the server discarded the whole form before '
	     . 'this page ran -- which is why nothing came back and nothing was saved. You sent '
	     . number_format(((int) $content_length) / 1048576, 1) . 'MB; the limit is '
	     . htmlspecialchars((string) ini_get('post_max_size')) . ' per form and '
	     . htmlspecialchars((string) ini_get('upload_max_filesize')) . ' per file. '
	     . 'Shrink the file, or raise both in .user.ini.';
}

function adm_accept_upload($field, $dir, $basename, $allowed_ext) {
	if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';
	$f = $_FILES[$field];
	if ($f['error'] !== UPLOAD_ERR_OK)  return 'Upload failed (code ' . (int)$f['error'] . ').';
	if ($f['size'] > 24 * 1024 * 1024)  return 'That file is over 24MB.';

	/* THE EXTENSION COMES FROM THE CONTENT. A browser-supplied name is a
	   claim, and this writes into a directory the web server serves. */
	$mime = function_exists('finfo_open')
		? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
	$by_mime = array('image/png' => 'png', 'image/jpeg' => 'jpg',
	                 'image/gif' => 'gif', 'video/mp4' => 'mp4');
	$ext = isset($by_mime[$mime]) ? $by_mime[$mime] : '';
	if ($ext === '') {
		/*
		 * NAME THE FIX, NOT JUST THE PROBLEM. QuickTime is the one
		 * people actually try, because it is what a phone and a screen
		 * recorder produce, and "that is not an MP4" leaves them to work
		 * out what to do about it.
		 *
		 * It is refused rather than accepted because a .mov from an
		 * iPhone is HEVC: it plays for whoever uploaded it and shows a
		 * black frame in Chrome and Firefox, with nothing logged. A
		 * visible refusal beats a break only some players see. This
		 * stops being true the moment ffmpeg is reachable -- see
		 * missions-media-probe.php -- because the .mov would then be
		 * transcoded rather than handed to the browser.
		 */
		$known = array(
			'video/quicktime' => 'A .mov cannot be used directly: an iPhone records HEVC, which plays in '
			                   . 'Safari and shows a black frame in Chrome and Firefox. Convert it to '
			                   . 'H.264 MP4 and upload that, plus a .gif for the still.',
			'image/webp'      => 'WebP is not one of the four formats the mission tiles render. Save it as PNG.',
			'image/avif'      => 'AVIF is not one of the four formats the mission tiles render. Save it as PNG.',
			'image/svg+xml'   => 'SVG is not rendered on mission tiles. Export it as a PNG.',
			'video/webm'      => 'WebM is not rendered on mission tiles. Convert it to H.264 MP4, plus a .gif.',
		);
		if (isset($known[$mime])) return $known[$mime];
		return 'That is not a PNG, JPG, GIF or MP4 (it looks like "' . htmlspecialchars($mime) . '").';
	}
	if (!in_array($ext, $allowed_ext, true))
		return 'A .' . $ext . ' is not accepted here. Allowed: .' . implode(', .', $allowed_ext) . '.';

	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'Could not create ' . basename($dir) . '/.';
	$dest = rtrim($dir, '/') . '/' . $basename . '.' . $ext;

	/* An mp4 is stored as uploaded -- Imagick cannot read one, and there
	   is no ffmpeg on this host to transcode with. See the pairing rule in
	   missions-economy.md section 6b. */
	if ($ext === 'mp4') return @move_uploaded_file($f['tmp_name'], $dest) ? '' : 'Could not write ' . basename($dest) . '.';
	return admin_write_image($f['tmp_name'], $dest);
}


/**
 * THE GATE. Called BEFORE header.php, and it renders no chrome.
 *
 * WHY NOT ONE FUNCTION THAT ALSO INCLUDES THE HEADER, which is what this
 * was: `include` inside a function body executes the included file in
 * that FUNCTION'S scope. header.php reads $name and $avatar_url, which
 * skulliance.php sets at global scope, so including it from inside a
 * function hands it an empty scope -- it renders its logged-out branch,
 * and the page comes out structurally different from every other page on
 * the platform. Includes of a page template belong at global scope, and
 * that is now the caller's job.
 */
function admin_require() {
	$r = adminRights();
	if ($r['ok']) return;
	/* Plain 404, no site chrome: an admin page should not confirm it
	   exists, and there is nothing useful to draw around the refusal. */
	http_response_code(404);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Not found.\n";
	exit;
}

/**
 * Opens the admin layout and draws the sub-nav. Call AFTER header.php.
 *
 * LAYOUT, AND WHY THE FIRST VERSION RENDERED NOTHING USEFUL. header.php
 * leaves a `<div class="container">` OPEN for the page to fill. The panel
 * opened a SECOND container inside it and then a `<div class="column">`,
 * and `.column` does not exist in dist/flexbox.css at all -- it has
 * .col1of2 and .col1of3 (flex: 33%) and nothing else. A classless div in
 * a flex row, inside a nested height:100% container, is why the first
 * version looked inert: the forms submitted, the pages came back, and the
 * content had no box to live in.
 */
/**
 * EVERY ADMIN PAGE, ONCE.
 *
 * This list was duplicated: admin_chrome()'s tab strip held one copy and
 * header.php's Admin dropdown held another, written out by hand. Adding
 * Daily Digest to the tabs left it missing from the dropdown, which is the
 * only way most people reach the panel at all -- reported as "it is not in
 * the menu". Same failure the platform has had four times now with gateway
 * lists and chain maps: two copies of a list are one list and one bug.
 *
 * header.php requires this file inside its user-1 branch. Safe because
 * nothing in here echoes or includes db.php -- see the header comment.
 */
/**
 * THE PANEL, IN GROUPS. This array is the authority for everything: the tab
 * strip, header.php's dropdown and the order of both.
 *
 * WHY IT IS GROUPED. It reached twenty-eight tabs in one flat strip that
 * wrapped onto two lines, and finding anything meant reading all of them --
 * Chains sat beside Projects sat beside Monstrocity sat beside Viewport,
 * with nothing saying which of those were the same kind of thing.
 *
 * THE GROUPS ARE THE SINGLE LIST, and admin_tabs() below flattens them.
 * They were nearly two lists -- a flat array plus a map of key => group --
 * and that is a tab that belongs to no group the day somebody adds one and
 * forgets the other. Here a tab cannot exist outside a group, because the
 * group is where it is written.
 *
 * PREVIEWS IS A REAL CATEGORY, not a leftovers bin. Each page in it says the
 * same thing in its own comment: it is not an admin page, it is the thing a
 * PLAYER sees, forced open, and it writes nothing. They are all reachable
 * exactly once in normal play -- a first realm, a won raid, a claimable day,
 * a first match-3 -- so this is the only way to look at any of them twice.
 * Grouping them says that out loud, where the flat strip had them scattered
 * among tools that do change things.
 *
 * ORDER WITHIN A GROUP STILL MATTERS. Bosses and DHC Bosses are adjacent
 * because they are the only two tabs about bosses, and admin-harness.php
 * asserts that adjacency rather than an index, so inserting above them is
 * free and separating them is not.
 */
function admin_tab_groups() {
	return array(
		/* The data the rest of the platform is built on: a chain holds
		   projects, projects hold collections, collections feed missions. */
		'Catalogue' => array(
			'blockchains' => array('admin-blockchains.php', 'Chains'),
			'projects'    => array('admin-projects.php',    'Projects'),
			'collections' => array('admin-collections.php', 'Collections'),
			'missions'    => array('admin-missions.php',    'Missions'),
		),
		/* The games themselves, and the two boss screens side by side. */
		'Games' => array(
			'monstrocity' => array('admin-monstrocity.php', 'Monstrocity'),
			'bosses'      => array('admin-monstrocity-boss.php', 'Bosses'),
			/* The DHC boss crew is a different game and a read-only report,
			   so it gets its own key rather than crowding that one. NEXT TO
			   IT, though, and the owner's call: the two tabs are the only
			   two about bosses, and with Daily Digest, Flyers and Nudges
			   between them the eye had to cross three unrelated tools to
			   get from one to the other. */
			'dhcbosses'   => array('admin-dhcbosses.php',   'DHC Bosses'),
			/* Targets, and the pending count. */
			'infiltration' => array('admin-infiltration.php', 'Infiltration'),
		),
		/* Everything that ends up as a Discord post or a nudge to somebody
		   who has gone quiet. Flyers and Outreach sit together because they
		   are one job in two halves -- the picture and the words that go
		   with it into the same post -- and each page links the other. */
		'Outreach' => array(
			'digest'      => array('admin-digest.php',      'Daily Digest'),
			'flyers'      => array('admin-flyers.php',      'Flyers'),
			'outreach'    => array('admin-outreach.php',    'Outreach'),
			'nudges'      => array('admin-nudges.php',      'Nudges'),
			'lapsed'      => array('admin-lapsed.php',      'Lapsed'),
			/* Newcomers is people who got in and stalled, Front Door is
			   people who could not get in at all. */
			'welcome'     => array('admin-welcome.php',     'Newcomers'),
			'frontdoor'   => array('admin-frontdoor.php',   'Front Door'),
		),
		/* Players in their own words, and their faces. Nothing a player
		   writes reaches the site or Discord until it is approved here. */
		'Players' => array(
			'testimonials' => array('admin-testimonials.php', 'Testimonials'),
			'collage'      => array('admin-collage.php',      'Avatar Wall'),
		),
		/*
		 * NOT ADMIN PAGES. Every one of these is a screen a PLAYER meets,
		 * forced open, and every one of them writes nothing -- no claim, no
		 * craft, no approval. They are grouped because they share the thing
		 * that makes them exist: each is reachable exactly once in normal
		 * play, so this is the only way to look at one a second time.
		 */
		'Previews' => array(
			/* Realms drawn the way somebody with no realm sees it, which is
			   the only view of it nobody on this side has ever had. */
			'onboarding'  => array('realms.php?preview=newcomer', 'Onboarding'),
			/* The ask modal, otherwise reachable only by winning a raid at
			   the right moment. */
			'testimonial-ask' => array('realms.php?preview=testimonial', 'Testimonial Ask'),
			/* The daily reward animation. It fires once a day for a player
			   with a claimable day. Claims nothing. */
			'chest' => array('admin-chest.php', 'Reward Reveal'),
			/* The match-3 slide intro, gated on having no score on record
			   for that game -- so the moment anybody has played once it is
			   unreachable to them. */
			'match3-intro' => array('admin-match3-intro.php', 'Match-3 Intro'),
			/* The three crafting sequences, playable without a balance.
			   Each only runs when somebody can afford it and spends it to
			   run. Crafts nothing. */
			'crafting' => array('admin-crafting.php', 'Crafting Reveals'),
			/* The boss crew resurrection cue. A playbackRate curve is a feel
			   decision and cannot be read off a page, so it plays the real
			   file through the Arena's own path. Plays audio, writes nothing. */
			'bossrez'  => array('admin-bossrez.php', 'Boss Rez Sound'),
			/* Skull Swap tile treatments side by side. A few lines of CSS
			   each, and the only question that matters is which reads right
			   at tile size on the board, which cannot be read off a diff. */
			'swaptiles' => array('admin-swaptiles.php', 'Swap Tiles'),
		),
		/* What the server, the data or an outside service is actually doing.
		   The owner is usually on a phone and cannot run anything, which is
		   why each of these is a page rather than a script. */
		'Diagnostics' => array(
			/* The two viewports a phone has, live. The back-to-top arrow is
			   exact at 390px in a desktop browser and wrong on a phone. */
			'viewport' => array('admin-viewport.php', 'Viewport'),
			/* What this server can do about blocking Discord posts. The
			   shell php here is a different build from the one serving the
			   site, so the web SAPI's capabilities can only be read from
			   the web. */
			'defer'    => array('admin-defer.php', 'Deferred Posts'),
			/* Who is holding half a tethered weapon. An orphan reads to a
			   player as a weapon that attaches and then will not save. */
			'tethers'  => array('admin-tethers.php', 'Tethers'),
			/* What X will tell us about a comment for nothing. The free
			   surfaces that might carry the four facts are undocumented, so
			   they get asked rather than assumed. Fetches, stores nothing. */
			'xprobe'   => array('admin-xprobe.php',   'X Probe'),
		),
	);
}

/**
 * The same tabs, flat, in group order.
 *
 * KEPT because this is what every caller already reads -- the harness walks
 * it to check each target exists, header.php built the dropdown from it, and
 * admin_chrome() marked the active one from it. Derived rather than written,
 * so there is no second list to fall out of step with the groups.
 */
function admin_tabs() {
	$flat = array();
	foreach (admin_tab_groups() as $tabs) {
		foreach ($tabs as $key => $t) $flat[$key] = $t;
	}
	return $flat;
}

/*
 * ONE ROW PER GROUP, every tab visible.
 *
 * Nothing is hidden behind a second click: at this size the panel is still
 * something to scan, not to search, and the owner knows what is in it -- the
 * problem was never that there were too many tabs to show, it was that
 * twenty-eight of them in one wrapped strip had no shape. A label down the
 * left gives each row a reason to exist, and a page is still one click from
 * any other page.
 *
 * NO JAVASCRIPT. The panel is reached from a phone as often as not, and a
 * nav that needs scripting to open is a nav that can fail to open.
 */
function admin_chrome($active) {
	echo '<div class="row"><div class="adm">';
	echo '<div class="adm-head"><h2>Admin</h2><nav class="adm-tabs">';
	foreach (admin_tab_groups() as $group => $tabs) {
		/* The label is aria-hidden and the row carries the name instead: read
		   aloud, "Catalogue Chains Projects" is a list of four links with a
		   stray word in front of it, where a labelled group is four links in
		   Catalogue. */
		printf('<div class="adm-tabgroup" role="group" aria-label="%s">', htmlspecialchars($group));
		printf('<span class="adm-tabcat" aria-hidden="true">%s</span>', htmlspecialchars($group));
		printf('<span class="adm-tabrow">');
		foreach ($tabs as $key => $t) {
			printf('<a href="%s" class="%s">%s</a>', $t[0], $key === $active ? 'on' : '', $t[1]);
		}
		echo '</span></div>';
	}
	echo '</nav></div>';
}

function admin_flash($msgs) {
	foreach ($msgs['err'] as $m) echo '<p class="adm-msg bad">' . $m . '</p>';
	/* 'warn' is optional, because most admin pages have no use for it and
	   every one of them calls this function. */
	if (!empty($msgs['warn'])) foreach ($msgs['warn'] as $m) echo '<p class="adm-msg warn">' . $m . '</p>';
	foreach ($msgs['ok']  as $m) echo '<p class="adm-msg good">' . $m . '</p>';
}

/**
 * The project picker every page carries.
 *
 * Submits on change, with the button only inside <noscript>. Choosing
 * from a dropdown and then confirming it is a click nobody should have
 * to make -- the button was added while the picker was a suspect for the
 * panel being unresponsive, and it was innocent: the cards were
 * <section>, which dist/flexbox.css hides at opacity 0.
 *
 * <noscript> is the right home for the fallback because its contents are
 * not parsed as elements at all when scripting is on, so there is no
 * stray button to hide with CSS and nothing to get out of step.
 */
function admin_project_picker($projects, $pid, $self) {
	echo '<form method="get" action="' . htmlspecialchars($self) . '" class="adm-pick">';
	echo '<label for="project">Project</label>';
	echo '<select name="project" id="project" onchange="this.form.submit()">';
	echo '<option value="0">&mdash; pick a project &mdash;</option>';
	foreach ($projects as $id => $p) {
		printf('<option value="%d"%s>%s (%s)</option>', $id, $id === $pid ? ' selected' : '',
			htmlspecialchars($p['name']), htmlspecialchars($p['currency']));
	}
	echo '</select><noscript><button type="submit">Open</button></noscript></form>';
}
