<?php
/**
 * THEMES AS DATA, so one can be added or edited without touching PHP.
 *
 * evaluateText() has always been a dictionary -- an exact whole-string
 * lookup, falling through to a substring pass for descriptions. The problem
 * was never the mechanism, it was that the dictionaries were an if/else
 * chain on project_id, 202 lines of it, so a new theme meant a code change
 * and a deploy. These functions read the same two dictionaries out of
 * dropship_theme_text instead.
 *
 * ADDITIVE, AND SILENT UNTIL USED. Every lookup falls back to the hardcoded
 * chain when the tables are missing or the theme has no rows, so the schema
 * can sit empty on production and change nothing. A theme goes live when
 * somebody saves one in the admin and reverts by deleting its rows. That
 * matters because evaluateText() is on the path of every label on every
 * page: it cannot be allowed to fail, and it cannot be allowed to start
 * returning different words than it did yesterday without someone choosing
 * that.
 *
 * CACHED PER REQUEST, which is not optional. evaluateText() is called
 * hundreds of times rendering a dashboard; a query per call would be worse
 * than the chain it replaces. Each theme is read once and held.
 */

/**
 * Who may edit themes.
 *
 * ONE PLACE, because the game controls on dashboard.php gate on a Discord id
 * written inline and a second copy of that literal is how the two drift
 * apart. This accepts the same id plus the credential globals Drop Ship
 * already defines for its own staff checks, so adding somebody is a
 * credentials change rather than an edit here.
 *
 * Deliberately NOT the Skulliance admin_require(): this is a different app
 * with its own session shape, and pulling admin-lib.php in to reuse one
 * boolean would drag the platform's own db.php behind it.
 *
 * DROP SHIP USER 1 IS A VALID KEY, which is worth saying because it looks
 * like it should not be. The Drop Ship users table carries NO project_id --
 * checkUser() matches on discord_id alone and there is one row per person
 * for the whole app -- so an id is a stable global identity here, not a
 * per-project counter that means a different person on each theme.
 */
function dropshipThemeAdmin() {
	$ud = isset($_SESSION['userData']) ? $_SESSION['userData'] : array();

	/*
	 * The owner, by Drop Ship user id.
	 *
	 * ctype_digit BEFORE the cast, because PHP reads (int)'1x' as 1 and that
	 * would admit anything merely starting with the digit. checkUser() always
	 * writes a clean strval() of the row id, so a non-numeric value here only
	 * ever arrives from a restored session -- exactly the case worth being
	 * strict about.
	 */
	if (isset($ud['dropship_user_id'])) {
		$uid = $ud['dropship_user_id'];
		if ((is_int($uid) || ctype_digit((string)$uid)) && (int)$uid === 1) return true;
	}

	if (empty($ud['discord_id'])) return false;
	$me = (string)$ud['discord_id'];
	$allowed = array('772831523899965440');          /* same id dashboard.php gates on */
	foreach (array('discordid_oculusorbus', 'discordid_ohhmeed', 'discordid_kryptman') as $g) {
		if (!empty($GLOBALS[$g])) $allowed[] = (string)$GLOBALS[$g];
	}
	return in_array($me, $allowed, true);
}

/** Do the theme tables exist? Asked once, because nothing creates them. */
function dropshipThemesTable($conn) {
	static $have = null;
	if ($have === null) {
		if (!is_object($conn)) return false;
		$r = @$conn->query("SHOW TABLES LIKE 'dropship_themes'");
		$have = (bool)($r && $r->num_rows);
	}
	return $have;
}

/**
 * One theme's configuration row, or null when it has none.
 *
 * Null is the signal to fall back, and every caller has to treat it that
 * way -- a theme that is half in the table and half in the chain would read
 * as two different games on one page.
 */
function dropshipThemeConfig($conn, $project_id) {
	static $cache = array();
	$pid = (int)$project_id;
	if (array_key_exists($pid, $cache)) return $cache[$pid];
	$cache[$pid] = null;
	if ($pid <= 0 || !dropshipThemesTable($conn)) return null;

	$r = @$conn->query("SELECT * FROM dropship_themes WHERE project_id = $pid LIMIT 1");
	if ($r && $r->num_rows) $cache[$pid] = $r->fetch_assoc();
	return $cache[$pid];
}

/**
 * The two dictionaries for a theme, or null to fall back.
 *
 * array('exact' => array(find => replace), 'description' => array(...)).
 * Null when there is no theme row or it carries no text at all -- a theme
 * configured with labels but no overrides yet should not blank out the
 * wording the chain was providing.
 */
function dropshipThemeDictionaries($conn, $project_id) {
	static $cache = array();
	$pid = (int)$project_id;
	if (array_key_exists($pid, $cache)) return $cache[$pid];
	$cache[$pid] = null;

	$theme = dropshipThemeConfig($conn, $pid);
	if (!$theme) return null;

	$tid = (int)$theme['id'];
	$r = @$conn->query("SELECT match_type, find, replacement FROM dropship_theme_text
	                     WHERE theme_id = $tid ORDER BY sort_order ASC, id ASC");
	if (!$r) return null;

	$d = array('exact' => array(), 'description' => array());
	while ($row = $r->fetch_assoc()) {
		$type = $row['match_type'] === 'description' ? 'description' : 'exact';
		$d[$type][$row['find']] = $row['replacement'];
	}
	if (!$d['exact'] && !$d['description']) return null;   /* nothing to say; fall back */

	$cache[$pid] = $d;
	return $d;
}

/**
 * Apply a theme's dictionaries to one string, or return null to fall back.
 *
 * The ORDER mirrors evaluateText() exactly: exact match wins outright, and
 * the substring pass only runs when it misses. Reversing that would rewrite
 * a label that happened to contain a description word.
 */
function dropshipThemeText($conn, $project_id, $text) {
	$d = dropshipThemeDictionaries($conn, $project_id);
	if ($d === null) return null;

	/*
	 * AN ICON NAME IS DERIVED, NEVER STORED.
	 *
	 * BEFORE THE DICTIONARY, NOT AFTER. A theme saved before this change
	 * still HAS icon rows, and a stored row that wins is a stored row that
	 * can be stale -- which is exactly what happened: Oculus Lounge kept
	 * answering 'robe' for heavy-armor after the file became
	 * smoking-jacket.png, so the shop asked for a picture that had been
	 * renamed out from under it. Deriving first makes those leftover rows
	 * harmless whether or not anybody deletes them, and the chain in db.php
	 * already orders it this way.
	 *
	 * The markup asks for the icon by its Drop Ship filename -- the shop
	 * renders icons/<?= evaluateText('medkit') ?>.png -- and the answer is
	 * just the theme's own word made into a filename. Storing it as well
	 * meant the same fact in two rows: rename Medkit to "Sexy Nurse" and
	 * the icon row still said 'nurse' until somebody edited it too, and
	 * nothing on the screen said that was why the shop showed the wrong
	 * picture. The art screen is where the FILE is managed; the name it
	 * asks for comes from here.
	 */
	$label = dropshipThemeIconLabel($text);
	if ($label !== null) {
		$word = dropshipThemeText($conn, $project_id, $label);
		if ($word !== null && $word !== '' && $word !== $label) {
			return dropshipThemeSlug($word);
		}
		/* NOT null. Null means "this theme has nothing to say, use the
		   chain", and the chain still holds the OLD icon names -- so a
		   theme that keeps the Drop Ship word would be handed Dread City's
		   filename. Unchanged is the answer, exactly as the description
		   pass returns an untouched string rather than null. */
		return $text;
	}

	if (isset($d['exact'][$text])) return $d['exact'][$text];

	foreach ($d['description'] as $find => $replacement) {
		if ($find !== '' && str_contains($text, $find)) {
			$text = str_replace($find, $replacement, $text);
		}
	}
	return $text;
}

/**
 * The family word an icon filename belongs to, or null if it is not one.
 *
 * 'heavy-armor' -> 'Heavy', 'dropship' -> 'Super Soldier'. Built once from
 * the vocabulary: this sits on the path of every label on every page, so it
 * must not cost a scan per call.
 */
function dropshipThemeIconLabel($term) {
	static $map = null;
	if ($map === null) {
		$map = array();
		require_once __DIR__ . '/dropship-theme-vocab.php';
		if (function_exists('dropshipThemeVocabulary')) {
			foreach (dropshipThemeVocabulary() as $rows) {
				foreach ($rows as $r) {
					foreach ($r['icons'] as $ic) $map[$ic] = $r['label'];
				}
			}
		}
	}
	return isset($map[$term]) ? $map[$term] : null;
}

/**
 * The theme's MEDIA EXTENSION -- what its round art is stored as.
 *
 * Asked through evaluateText() rather than the theme row directly, because
 * that is the one accessor that works both before and after a theme is
 * migrated: it answers from dropship_themes.extension when there is a row
 * and from the built-in chain when there is not.
 */
function dropshipMediaExtension() {
	if (!function_exists('evaluateText')) return 'gif';
	$ext = evaluateText('gif');
	return $ext !== '' ? strtolower($ext) : 'gif';
}

/**
 * Does this theme's art need help moving?
 *
 * A gif animates itself, so anything laid over it fights it. A still -- png
 * for Oculus Lounge -- gets the Ken Burns pan already defined as .image-wrap
 * in dist/flexbox.css, which is a 40s drift across the frame.
 *
 * DRIVEN BY THE FORMAT, NOT BY THE PROJECT. The pan used to be switched on
 * for project 4 and nothing else, which was only ever right by coincidence:
 * project 4 is the one that happens to use stills. A new png theme would
 * have rendered a dead frame, and a gif theme given project 4's id would
 * have had a pan dragged across its own animation.
 */
function dropshipMediaIsStill() {
	return dropshipMediaExtension() !== 'gif';
}

/** Every theme row, for the admin list. Empty when the table is missing. */
function dropshipThemeAll($conn) {
	if (!dropshipThemesTable($conn)) return array();
	$out = array();
	$r = @$conn->query("SELECT t.*, p.name AS project_name,
	                           (SELECT COUNT(*) FROM dropship_theme_text x WHERE x.theme_id = t.id) AS text_count
	                      FROM dropship_themes t
	                      LEFT JOIN projects p ON p.id = t.project_id
	                     ORDER BY t.project_id ASC");
	if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}

/**
 * A slug safe to use as a directory name.
 *
 * The slug IS the image folder, so it has to survive being concatenated into
 * a path. dropship.php used to derive it from the project NAME, which meant
 * a rename moved the whole art directory; it is stored now, and this is what
 * keeps a typed one from escaping the folder.
 */
function dropshipThemeSlug($raw) {
	$s = strtolower(trim((string)$raw));
	$s = preg_replace('/[^a-z0-9]+/', '-', $s);
	return trim((string)$s, '-');
}

/**
 * Every project in the Drop Ship database, for the admin's picker.
 *
 * NOT getProjects(). That one is the PLAYER-facing list and it excludes
 * Filthy Mermaid and Dread City by name, which is why the theme admin could
 * not select the two themes most in need of editing. It is also the
 * allow-list dropship.php validates ?project_id against before that value is
 * interpolated into SQL, so widening it would both un-hide work in progress
 * and loosen an injection guard. Two different questions; two functions.
 *
 * Returns id => name, always an array -- getProjects() returns NULL when the
 * table is empty, which a foreach then warns on, and display_errors is on.
 */
function dropshipAdminProjects($conn) {
	$out = array();
	if (!is_object($conn)) return $out;
	$r = @$conn->query("SELECT id, name FROM projects ORDER BY id ASC");
	if ($r) while ($row = $r->fetch_assoc()) $out[(int)$row['id']] = $row['name'];
	return $out;
}

/**
 * The project ids a player may enter, or NULL to let the old rule decide.
 *
 * dropship_themes.active was written by the admin and read by nothing, so
 * ticking it did nothing at all; which projects a player saw was a pair of
 * names hardcoded in getProjects(). This is what makes the flag mean what
 * the column comment always said it meant.
 *
 * NULL, NOT AN EMPTY LIST, IN EVERY DOUBTFUL CASE. Three of them:
 *  - the table is missing, which is the pre-migration state;
 *  - the table exists but holds no themes at all;
 *  - themes exist but not one is active.
 * The first two keep this additive. The third is a judgement: an empty
 * picker is indistinguishable from an outage, and the flag is for choosing
 * BETWEEN themes, not for closing the game. Deactivating everything
 * therefore leaves the old list rather than locking every player out.
 */
function dropshipActiveProjectIds($conn) {
	/* NOT CACHED. It is one small query a couple of times a request, and a
	   static would hand admin-themes.php the list from BEFORE the save it
	   just did, on the very page whose job is changing this flag. */
	if (!is_object($conn) || !dropshipThemesTable($conn)) return null;

	$r = @$conn->query("SELECT project_id, active FROM dropship_themes");
	if (!$r) return null;
	$ids = array(); $any = false;
	while ($row = $r->fetch_assoc()) {
		$any = true;
		if (!empty($row['active'])) $ids[] = (int)$row['project_id'];
	}
	if (!$any || !$ids) return null;
	return $ids;
}

/**
 * Where a theme's NFT holdings come from: 'koios' or 'skulliance'.
 *
 * TWO REAL PIPELINES, not a preference. 'koios' reads the player's connected
 * wallets live through Koios' account_utxos, which is what every theme but
 * one does. 'skulliance' skips that round trip and reads holdings out of
 * Skulliance's own already-synced `nfts` table, which is what Oculus Lounge
 * does and why dropshipSyncFromSkulliance() exists.
 *
 * THIS WAS A HARDCODED project_id == 4 IN dashboard.php. The column existed
 * to replace exactly that and I removed it, on the reasoning that the trait
 * source was derivable from the slug -- which was true of a DIFFERENT thing
 * that happens to share the name: $source in dropship-traitdrop.php, the key
 * naming which GAME a Discord trait drop came from. Two unrelated facts, one
 * word. This one is a real choice and nothing derives it.
 *
 * FALLS BACK TO THE OLD HARDCODE when there is no theme row, so a project
 * that has not been seeded behaves exactly as it did.
 */
function dropshipThemeTraitSource($conn, $project_id) {
	$cfg = dropshipThemeConfig($conn, $project_id);
	if ($cfg && !empty($cfg['trait_source'])) {
		return $cfg['trait_source'] === 'skulliance' ? 'skulliance' : 'koios';
	}
	return ((int)$project_id === 4) ? 'skulliance' : 'koios';
}
