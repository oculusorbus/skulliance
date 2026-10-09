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
	$need = max(64 * 1024 * 1024, (int)($w * $h * 16) * $frames);
	$cap  = ADMIN_IMAGE_MAX_MEM * 1024 * 1024;
	if ($need > $cap) {
		return array('ok' => false, 'mem' => 0, 'why' => sprintf(
			'That is %d frames at %d x %d, which needs about %dMB to process and the limit is '
			. '%dMB. Either cut the frame count or scale it down -- mission art is never shown '
			. 'above 1000px wide.',
			$frames, $w, $h, (int)($need / 1048576), ADMIN_IMAGE_MAX_MEM));
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
			if ($im->getImageWidth() > $max_width)
				$im->resizeImage($max_width, 0, Imagick::FILTER_LANCZOS, 1);
			$im->stripImage();                       // drop EXIF and colour profiles
			if (strtolower(pathinfo($dest_path, PATHINFO_EXTENSION)) === 'jpg')
				$im->setImageCompressionQuality(86);
			$ok = $im->writeImage($dest_path);
		}
		return $ok ? '' : 'Imagick could not write ' . basename($dest_path) . '.';
	} catch (Throwable $ex) {
		return 'Could not process that image: ' . $ex->getMessage();
	}
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
function admin_chrome($active) {
	$tabs = array(
		'projects'    => array('admin-projects.php',    'Projects'),
		'collections' => array('admin-collections.php', 'Collections'),
		'missions'    => array('admin-missions.php',    'Missions'),
		'blockchains' => array('admin-blockchains.php', 'Chains'),
		'flyers'      => array('admin-flyers.php',      'Flyers'),
		'digest'      => array('admin-digest.php',      'Daily Digest'),
	);
	echo '<div class="row"><div class="adm">';
	echo '<div class="adm-head"><h2>Admin</h2><nav class="adm-tabs">';
	foreach ($tabs as $key => $t) {
		printf('<a href="%s" class="%s">%s</a>', $t[0], $key === $active ? 'on' : '', $t[1]);
	}
	echo '</nav></div>';
}

function admin_flash($msgs) {
	foreach ($msgs['err'] as $m) echo '<p class="adm-msg bad">' . $m . '</p>';
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
