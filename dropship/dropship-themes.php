<?php
/**
 * THEMES AS DATA, so one can be added or edited without touching PHP.
 *
 * evaluateText() has always been a dictionary -- an exact whole-string
 * lookup, falling through to a substring pass for descriptions. The problem
 * was never the mechanism, it was that the dictionaries were an if/else
 * chain on project_id, 202 lines of it, so a new theme meant a code change
 * and a deploy. These functions read the same two dictionaries out of
 * theme_text instead.
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
 * THE RANK A SOLDIER GETS WHEN ITS COLLECTION DOES NOT SUPPLY ONE.
 *
 * Drop Ship's own NFTs carry real ranks in their metadata. Every other
 * collection gets this, and it has to be the GAME'S word rather than any one
 * theme's: it was "Neo Miami Citizen", which is Oculus Lounge's, so every
 * other theme had to override a term belonging to a game it has nothing to
 * do with before it could say anything at all.
 *
 * Rendered through evaluateText(), so a theme renames it in the admin
 * alongside SCRIP and Medkit. It lives in this file because it is a
 * VOCABULARY default and this file owns the wording -- it sat in
 * dropship-grunts.php first, which is about what a soldier is issued with,
 * and which the theme harness does not load.
 */
if (!defined('DROPSHIP_DEFAULT_RANK')) define('DROPSHIP_DEFAULT_RANK', 'Soldier');

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

/**
 * Do the theme tables exist? Asked once per connection, because nothing
 * creates them and the answer cannot change inside a request.
 *
 * PER CONNECTION, NOT ONCE GLOBALLY. It takes a $conn and the answer is a
 * property OF that connection, so a single static was wrong: the first
 * caller's answer was handed to every later one regardless of which
 * database it was asking about. Nothing in production has two Drop Ship
 * connections today, but the harness does, and a cache that ignores its
 * own key is a bug waiting for the second one.
 */
function dropshipThemesTable($conn) {
	static $have = array();
	if (!is_object($conn)) return false;
	$k = spl_object_id($conn);
	if (!array_key_exists($k, $have)) {
		$r = @$conn->query("SHOW TABLES LIKE 'themes'");
		$have[$k] = (bool)($r && $r->num_rows);
	}
	return $have[$k];
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

	$r = @$conn->query("SELECT * FROM themes WHERE project_id = $pid LIMIT 1");
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
	$r = @$conn->query("SELECT match_type, find, replacement FROM theme_text
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
 * migrated: it answers from themes.extension when there is a row
 * and from the built-in chain when there is not.
 */
/**
 * The theme's OWN media extension, before the player has any say.
 *
 * Separate from dropshipMediaExtension() because the two answer different
 * questions and conflating them broke the Animation switch: the control
 * was shown only when the extension was not 'gif', but turning it ON makes
 * the resolved extension 'gif' -- so the switch hid itself on first use
 * and there was no way back. "Can this theme be animated" is a property of
 * the THEME; "is it animated right now" is a property of the session.
 */
function dropshipThemeMediaExtension() {
	if (!function_exists('evaluateText')) return 'gif';
	$ext = evaluateText('gif');
	return $ext !== '' ? strtolower($ext) : 'gif';
}

function dropshipMediaExtension() {
	$ext = dropshipThemeMediaExtension();

	/*
	 * THE PLAYER CAN TURN ANIMATION ON FOR A STILL THEME THAT HAS GIFS.
	 *
	 * themes.extension is what a theme is SET to, which is not
	 * the same as what it has: Oculus Lounge ships both, and the old 3D
	 * toggle was the only way to see the animated set. Retiring 3D took
	 * that with it. This puts it back as what it always really was -- an
	 * animation switch -- rather than a second format pretending to be a
	 * dimension.
	 *
	 * Gated on the files EXISTING, so a leftover session flag can never
	 * make a theme ask for art it does not have. A theme already set to
	 * gif has nothing to turn on.
	 */
	if ($ext !== 'gif' && isset($GLOBALS['slug']) && dropshipAnimationOn($GLOBALS['slug'])) {
		return 'gif';
	}
	return $ext;
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
	/*
	 * THE THEME CAN OVERRIDE, because the format is a good guess and not
	 * an answer. 'auto' keeps the original rule -- a still gets the pan, a
	 * gif does not -- and is what every theme starts on, so nothing
	 * changes until somebody chooses. 'on' and 'off' say so outright: a
	 * still whose art is already a composed scene does not want drifting,
	 * and a gif whose frames barely move might.
	 */
	$mode = 'auto';
	if (function_exists('dropshipThemeConfig') && isset($GLOBALS['conn'])
	    && isset($_SESSION['userData']['dropship_project_id'])) {
		$cfg = dropshipThemeConfig($GLOBALS['conn'], $_SESSION['userData']['dropship_project_id']);
		if ($cfg && !empty($cfg['ken_burns'])) $mode = $cfg['ken_burns'];
	}
	/* ONLY THESE TWO OVERRIDE. Anything else -- 'auto', a blank, a value
	   from a newer admin than this code -- falls through to the format
	   rule, which is the safe answer rather than a guess. Normalising
	   $mode before this point added a branch that could not change the
	   outcome, since both comparisons are already exact. */
	if ($mode === 'on')  return true;
	if ($mode === 'off') return false;
	return dropshipMediaExtension() !== 'gif';
}

/** Every theme row, for the admin list. Empty when the table is missing. */
function dropshipThemeAll($conn) {
	if (!dropshipThemesTable($conn)) return array();
	$out = array();
	$r = @$conn->query("SELECT t.*, p.name AS project_name,
	                           (SELECT COUNT(*) FROM theme_text x WHERE x.theme_id = t.id) AS text_count
	                      FROM themes t
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
 * Make a project, because a theme needs one to hang off and there was no way
 * to create one without a database client.
 *
 * admin-themes.php's own header says it never edits `projects`, and that was
 * the right boundary for editing: several themes legitimately share one
 * collection, so a theme must not own the collection. CREATING one is the
 * case the boundary did not cover -- a brand new game has no project at all,
 * and the whole point of the theme admin is that a reskin needs no PHP.
 *
 * INERT UNTIL A THEME IS SAVED AND TICKED. getProjects() is built from
 * dropshipActiveProjectIds(), which lists only the projects that have an
 * ACTIVE theme, so a project made here is invisible to players, to the game
 * picker and to the ?project_id validator until somebody deliberately
 * activates a theme for it. Adding one cannot put a half-built game in front
 * of anybody.
 *
 * THE COLUMNS ARE INSPECTED, NOT ASSUMED. This table predates everything
 * here, there is no schema doc for it, and a hardcoded INSERT that omits
 * somebody's NOT NULL column fails with a message nobody reads. So: ask
 * MySQL what the table wants, fill what we know, and if anything else is
 * required say which column rather than failing with a MySQL error.
 *
 * Returns array(ok, message, new_id).
 */
function dropshipProjectCreate($conn, $name, $policy_id) {
	if (!is_object($conn)) return array(false, 'No database.', 0);

	$name = trim(preg_replace('/\s+/', ' ', (string)$name));
	if ($name === '') return array(false, 'A project needs a name.', 0);
	if (function_exists('mb_strlen') ? mb_strlen($name) > 64 : strlen($name) > 64) {
		return array(false, 'That name is too long.', 0);
	}
	/* A policy id is 56 hex characters. Allowed to be blank -- a project can
	   be created before its collection is minted -- but not to be nonsense,
	   because every NFT lookup interpolates it. */
	$policy_id = trim((string)$policy_id);
	if ($policy_id !== '' && !preg_match('/^[0-9a-f]{56}$/i', $policy_id)) {
		return array(false, 'A policy id is 56 hexadecimal characters. Leave it blank '
		           . 'to fill in later.', 0);
	}

	$e = $conn->real_escape_string($name);
	$r = @$conn->query("SELECT id FROM projects WHERE name = '$e' LIMIT 1");
	if ($r && $r->num_rows) {
		$row = $r->fetch_assoc();
		return array(false, 'There is already a project called "' . $name . '" (#'
		           . (int)$row['id'] . ').', 0);
	}

	/* What the table actually wants. */
	$have = array('name' => $name, 'policy_id' => $policy_id);
	$cols = array(); $vals = array(); $needed = array();
	$c = @$conn->query("SHOW COLUMNS FROM projects");
	if (!$c) return array(false, 'Could not read the projects table.', 0);
	while ($col = $c->fetch_assoc()) {
		$f = $col['Field'];
		if (array_key_exists($f, $have)) {
			$cols[] = '`' . $f . '`';
			$vals[] = "'" . $conn->real_escape_string((string)$have[$f]) . "'";
			continue;
		}
		/* Anything else has to be able to look after itself: auto_increment,
		   nullable, or carrying a default. */
		$auto     = strpos((string)$col['Extra'], 'auto_increment') !== false;
		$nullable = (string)$col['Null'] === 'YES';
		$hasDef   = $col['Default'] !== null;
		if (!$auto && !$nullable && !$hasDef) $needed[] = $f;
	}
	if ($needed) {
		return array(false, 'The projects table also requires '
		           . implode(', ', $needed) . ', which this form does not set. '
		           . 'Add the row by hand, or give '
		           . (count($needed) === 1 ? 'that column' : 'those columns')
		           . ' a default.', 0);
	}
	if (!$cols) return array(false, 'The projects table has no name column.', 0);

	if (!@$conn->query('INSERT INTO projects (' . implode(',', $cols) . ') VALUES ('
	                   . implode(',', $vals) . ')')) {
		return array(false, 'Could not create that project: ' . $conn->error, 0);
	}
	$id = (int)$conn->insert_id;
	if ($id <= 0) return array(false, 'The project was not created.', 0);
	return array(true, '"' . $name . '" created as project #' . $id
	           . '. It is not playable until a theme is saved and ticked active.', $id);
}

/**
 * The collection a project draws its NFTs from, or '' if it has none.
 *
 * WHY THE ADMIN SHOWS THIS. Every way of loading a player's NFTs runs
 * through projects.policy_id: the Koios path filters the wallet's tokens by
 * it, and the Skulliance path queries collections.policy with it. So a
 * project with no policy set loads nothing on EITHER setting, silently --
 * no error, just an empty barracks and a theme that looks broken in a way
 * nothing on screen explains. It cost a debugging session once; the answer
 * is now printed next to the theme.
 *
 * Takes the id rather than reading the session, unlike getProjectPolicyId(),
 * because the admin is looking at a project it is not playing.
 */
function dropshipProjectPolicy($conn, $project_id) {
	if (!is_object($conn)) return '';
	$pid = (int)$project_id;
	if ($pid <= 0) return '';
	$r = @$conn->query("SELECT policy_id FROM projects WHERE id = $pid LIMIT 1");
	if ($r && ($row = $r->fetch_assoc())) return (string)$row['policy_id'];
	return '';
}

/**
 * Where to go and buy into this theme's collection, or '' if unknown.
 *
 * ONE LINK PREFIX PLUS THE POLICY ID, which the projects table already
 * holds for every theme because Koios is queried with it. Nothing new to
 * configure and nothing to keep in step: a theme that can be played has a
 * policy, and a policy is a Wayup collection.
 *
 * THE REASON IT IS DERIVED. instructions.php hardcoded Drop Ship's own
 * collection for every project except Oculus Lounge, so a Dread City or
 * Filthy Mermaid player following "where to buy" was sent to a different
 * game's NFTs. These are the people who have arrived at a game they cannot
 * play yet and do not know what the qualifying NFT even is, which is the
 * worst moment to be pointed somewhere else.
 *
 * SHAPE-CHECKED. A policy id is 28 bytes, so 56 hex characters, and it is
 * about to be the path of a url the page hands a player. An empty or
 * malformed column gets '' and the caller renders nothing, rather than a
 * link to a collection page that does not exist.
 */
function dropshipCollectionUrl($conn, $project_id) {
	$policy = function_exists('dropshipProjectPolicy')
	        ? dropshipProjectPolicy($conn, $project_id) : '';
	$policy = strtolower(trim((string)$policy));
	if (strlen($policy) !== 56 || !ctype_xdigit($policy)) return '';
	return 'https://www.wayup.io/collection/' . $policy;
}

/**
 * That link as a sentence, named in the theme's own words.
 *
 * $intro is the calling page's copy, in front of the link -- the Play gate
 * says you have nothing to play with, the instructions page says nothing
 * at all. HTML, because it is the page's own text and never user input.
 */
function dropshipCollectionLink($conn, $project_id, $intro = '', $name = '') {
	$url = dropshipCollectionUrl($conn, $project_id);
	if ($url === '') return '';
	/* The theme's word for itself: "Filthy Mermaid NFTs", not "Drop Ship
	   NFTs", on a page that has renamed everything else. */
	if ($name === '' && function_exists('evaluateText')) $name = (string)evaluateText('Drop Ship');
	$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
	return '<p class="ds-collection">'
	     . ($intro !== '' ? $intro . ' ' : '')
	     . '<a href="' . $h($url) . '" target="_blank" rel="noopener">See what '
	     . ($name !== '' ? $h($name) . ' NFTs' : 'NFTs')
	     . ' are for sale on Wayup</a>.</p>';
}

/**
 * The project ids a player may enter, or NULL to let the old rule decide.
 *
 * themes.active was written by the admin and read by nothing, so
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

	$r = @$conn->query("SELECT project_id, active FROM themes");
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

/**
 * A theme's folder under dropship/, relative. '' for Drop Ship itself.
 *
 * ONE DEFINITION. Drop Ship's art is flat and every reskin nests under its
 * own slug, and that rule had started appearing in a new function each
 * time something needed a path -- the background lookup, the background
 * slot, the gif probe, dropshipArtRoot(). A mutation breaking one of them
 * left the others passing, which is how the duplication got noticed.
 *
 * Lives here rather than in dropship-art.php because the GAME needs it
 * too, and dropship-art.php is only loaded by the admin.
 */
function dropshipThemeRoot($slug) {
	$slug = dropshipThemeSlug($slug);
	if ($slug === '') return null;
	return $slug === 'drop-ship' ? '' : $slug . '/';
}

/**
 * A theme's page background, or '' when it has none.
 *
 * THREE BUGS IN ONE LINE, which is why this is a function now. The markup
 * was repeated per project id and Filthy Mermaid's copy read
 * url('/filthy-mermaid/filthymermaidbackground.jpg') -- missing the
 * /staking/dropship prefix every other path on the page carries, so it
 * 404'd while the file sat there. The copy in dashboard.php only runs on
 * the dashboard, so even fixed it would have left every other page bare.
 * And header.php's copy handled project 4 and nothing else, so no new
 * theme could ever have one without an edit here.
 *
 * NAMED BY CONVENTION AND VERIFIED ON DISK: <slug>/<slug minus
 * hyphens>background.<ext>, with Drop Ship's own flat the way the rest of
 * its art is. The extension genuinely varies -- Oculus Lounge is .png,
 * Filthy Mermaid is .jpg -- so it is found rather than assumed, and a
 * theme with no file gets '' and keeps the stylesheet's own background
 * instead of a broken url().
 */
/**
 * The page background as an uploadable slot: dir, stem and current file.
 *
 * NOT IN THE MANIFEST, because the manifest describes what the RUN asks
 * for -- round art under images/ -- and this is page furniture that sits
 * beside it. It still has to be managed somewhere, and hand-FTPing one
 * file while every other image has an upload button is the kind of gap
 * that leaves a theme half-finished.
 *
 * Returns array('rel', 'abs', 'stem', 'dir', 'exists'), where 'rel' is the
 * CURRENT file when there is one and the stem plus the conventional
 * extension when there is not, so the slot can be rendered either way.
 */
function dropshipArtBackgroundSlot($slug) {
	$dir = dropshipThemeRoot($slug);
	if ($dir === null) return null;
	$stem = str_replace('-', '', dropshipThemeSlug($slug)) . 'background';
	$hits = @glob(rtrim(__DIR__, '/') . '/' . $dir . $stem . '.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
	$have = $hits ? basename($hits[0]) : '';
	return array(
		'dir'    => $dir,
		'stem'   => $stem,
		'rel'    => $dir . ($have !== '' ? $have : $stem . '.jpg'),
		'abs'    => rtrim(__DIR__, '/') . '/' . $dir . $have,
		'exists' => $have !== '',
	);
}

/**
 * The file a theme actually has for one art slot: its format, else the gif.
 *
 * FOR THE SLOTS THE PAGE ASKS FOR BY STEM, not by filename -- the game
 * over screen is the one of these. It was hardcoded images/gameover.gif,
 * so Dread City, which is a jpg theme, showed an animated game over even
 * with animation off, and the file it showed is byte-for-byte Drop Ship's.
 *
 * THE GIF IS A FALLBACK HERE AND NOT ELSEWHERE, deliberately. For the
 * death images the themed file already existed and falling back could only
 * ever regress to Drop Ship's copies, so there is no fallback there. No
 * theme has a non-gif game over AT ALL yet -- all four folders hold only
 * gameover.gif -- so asking strictly for the theme's extension would blank
 * the end-of-run screen everywhere the moment this shipped. It stays on
 * the gif until somebody uploads the still, and theme art shows the still
 * as missing until they do.
 *
 * Returns the path relative to the theme folder, or '' if it has neither.
 */
function dropshipThemeArtFile($slug, $stem, $ext) {
	$root = dropshipThemeRoot($slug);
	if ($root === null) return '';
	$base = rtrim(__DIR__, '/') . '/' . $root;
	foreach (array(strtolower($ext), 'gif') as $try) {
		if ($try === '') continue;
		if (is_file($base . $stem . '.' . $try)) return $stem . '.' . $try;
	}
	return '';
}

/**
 * Does this theme have EVERY frame in a numbered range, in one format?
 *
 * FOR A BAND OF ART THAT CAN BE ABSENT. The bunker is the only stage with
 * two: scenes 1-6 carry rounds 13 to 15 and 7-12 carry round 16 and
 * beyond. The second band was asked for by the run and declared by
 * nothing for a long time, so a theme can easily have the first six and
 * not the rest -- Dread City renders jpg and has jpgs for 1-6 only, which
 * is a blank frame for anybody who gets that deep.
 *
 * ALL OR NOTHING, because a partial band is the bug rather than a
 * half-fix: Dread City's gif set has 7 and 8 and stops, so "has some"
 * would still blank rounds that landed on 9 through 12. The caller falls
 * back to the first band, which is art every theme has.
 *
 * CACHED PER REQUEST. This is asked once a round inside the run loop, and
 * the answer cannot change while a page is being built.
 */
function dropshipThemeHasFrames($slug, $stem, $from, $to, $ext) {
	static $seen = array();
	$ext = strtolower((string)$ext);
	$key = $slug . '|' . $stem . '|' . $from . '-' . $to . '|' . $ext;
	if (isset($seen[$key])) return $seen[$key];

	$root = dropshipThemeRoot($slug);
	if ($root === null || $ext === '') return $seen[$key] = false;
	$base = rtrim(__DIR__, '/') . '/' . $root . $stem;
	for ($i = (int)$from; $i <= (int)$to; $i++) {
		if (!is_file($base . $i . '.' . $ext)) return $seen[$key] = false;
	}
	return $seen[$key] = true;
}

/**
 * A theme's copy of one asset, as an absolute url, or '' when it has none.
 *
 * FOR DISCORD, which cannot be handed a relative path and cannot be told
 * "that one 404'd, use the other". Every announcement built its image url
 * by hand out of the global $prefix, which meant two separate ways to be
 * wrong and both of them happened:
 *
 *   - $prefix is set by dropship.php, and automate.php -- the weekly cron
 *     that posts Game Over and New Game -- does not include dropship.php.
 *     Its own $prefix is a local inside automate(), so discordmsg()'s
 *     `global $prefix` found nothing and the thumbnail went out as
 *     "https://skulliance.ioimages/vip.gif". Discord drops an embed image
 *     it cannot fetch, silently, which is why the posts had no art.
 *
 *   - A theme that has no copy of the file still got a url, pointing into
 *     its own folder, which 404s. dread-city has no images/vip.gif.
 *
 * So the slug comes from the THEME ROW for the project being announced --
 * $project_id is the one thing every discordmsg() call already has -- and
 * the file is checked on disk before its url is handed over. '' means "do
 * not put an image on this embed", which is the right answer: Drop Ship's
 * own logo in Dread City's channel is the thing that was complained about.
 */
function dropshipThemeImageUrl($conn, $project_id, $rel) {
	$path = dropshipThemeImagePath($conn, $project_id, $rel);
	if ($path === '') return '';
	if (!is_file(rtrim(__DIR__, '/') . '/' . $path)) return '';
	return 'https://skulliance.io/staking/dropship/' . $path;
}

/**
 * Where that asset would live, whether or not it is there.
 *
 * SPLIT FROM THE URL so the path rule can be checked on its own. The art
 * is FTPed and is not in the repository, so the only way to exercise the
 * flat-vs-nested rule off the server is to ask for the path without the
 * on-disk test -- and the alternative, having a harness write into
 * dropship/images/, would overwrite the live file if it were ever run
 * there.
 *
 * Returns a path relative to the dropship directory, or '' if the slug
 * cannot be made into one.
 */
function dropshipThemeImagePath($conn, $project_id, $rel) {
	$slug = '';
	if (is_object($conn) && function_exists('dropshipThemeConfig')) {
		$cfg = dropshipThemeConfig($conn, $project_id);
		if ($cfg && !empty($cfg['slug'])) $slug = $cfg['slug'];
	}
	/* No theme row means Drop Ship's own flat art -- the cron can run for a
	   project that has none, and an empty slug would put an empty folder in
	   the middle of the url. */
	if ($slug === '') $slug = 'drop-ship';
	$root = dropshipThemeRoot($slug);
	if ($root === null) return '';
	return $root . ltrim($rel, '/');
}

function dropshipThemeBackground($slug) {
	/* ONE RULE, ONE PLACE. This and the slot builder both decided whether a
	   theme nests, and a mutation that broke the rule in one of them left
	   the other passing. */
	$slot = dropshipArtBackgroundSlot($slug);
	return ($slot && $slot['exists'])
		? '/staking/dropship/' . $slot['rel']
		: '';
}

/**
 * Does this theme actually have animated art on disk?
 *
 * A theme whose extension is 'png' may still ship gifs -- the extension
 * records what it is SET to, not what exists -- and if it does, a player
 * should be able to turn the animation on. Asking costs two stat calls:
 * the manifest is seventy files and checking all of them on every page
 * load, to answer a yes/no, would be seventy.
 *
 * TWO PROBES, NOT ONE. The first round of art and the first beach frame
 * come from different folders, so a theme that happens to have one stray
 * gif in an otherwise still set does not advertise itself as animated.
 */
function dropshipThemeHasGifArt($slug) {
	$dir = dropshipThemeRoot($slug);
	if ($dir === null) return false;
	foreach (dropshipThemeGifProbes() as $probe) {
		if (!is_file(rtrim(__DIR__, '/') . '/' . $dir . $probe)) return false;
	}
	return true;
}

/**
 * The two files that stand for "this theme has an animated set".
 *
 * TAKEN FROM THE MANIFEST, NOT WRITTEN DOWN. The first version of this
 * guessed at images/drop/1.gif -- a path that has never existed, because
 * that folder holds drop.gif and team.gif rather than numbered frames --
 * so the check returned false for every theme and the Animation switch
 * never appeared at all. The manifest is the list of what the run asks
 * for; anything else is a second copy of it that can be wrong.
 *
 * FIRST AND LAST of the gif entries, so a theme part-way through having
 * its animated set uploaded does not advertise itself as finished. Only
 * .gif entries: the manifest carries a .jpg intro frame in every format.
 */
function dropshipThemeGifProbes() {
	static $probes = null;
	if ($probes !== null) return $probes;
	$probes = array();
	require_once __DIR__ . '/dropship-art.php';
	/*
	 * THE ANIMATED SET IS WHAT CHANGES WITH THE EXTENSION, nothing else.
	 * Taking every .gif in the manifest also took the chrome that is asked
	 * for by literal name -- images/vip.gif is a .gif under a png theme
	 * too -- and because it is listed LAST it became the second probe, so
	 * "does this theme have animated art" started answering "does it have
	 * a Discord thumbnail". An entry that is spelled the same in the gif
	 * manifest and the png one is not part of either set.
	 */
	$fixed = array_flip(dropshipArtManifest('png'));
	$gifs = array();
	foreach (dropshipArtManifest('gif') as $rel) {
		if (substr($rel, -4) !== '.gif') continue;
		if (isset($fixed[$rel])) continue;
		$gifs[] = $rel;
	}
	if (!$gifs) return $probes;
	$probes = array_values(array_unique(array($gifs[0], $gifs[count($gifs) - 1])));
	return $probes;
}

/**
 * Has the player turned animation on for a still theme?
 *
 * Only ever true when the theme is a still AND it has gifs to show, so a
 * stale session flag cannot make a png theme ask for files that are not
 * there. The flag is a preference, not a fact about the theme.
 */
function dropshipAnimationOn($slug) {
	if (empty($_SESSION['userData']['animation'])) return false;
	if ($_SESSION['userData']['animation'] !== 'true') return false;
	return dropshipThemeHasGifArt($slug);
}

/**
 * Does this theme show its stage art in order, or shuffled?
 *
 * 'random' is what Drop Ship has always done: every round picks a frame
 * from the scene's set, never the same one twice running. That suits art
 * where the frames are interchangeable takes on "you survived the beach".
 * It is wrong for a theme whose frames tell a sequence, where shuffling
 * reads as the story jumping about.
 *
 * Defaults to 'random' so nothing changes for a theme that has not chosen.
 */
function dropshipThemeStageOrder($conn, $project_id) {
	$cfg = dropshipThemeConfig($conn, $project_id);
	if ($cfg && !empty($cfg['stage_order'])) {
		return $cfg['stage_order'] === 'linear' ? 'linear' : 'random';
	}
	return 'random';
}

/**
 * The next stage frame, honouring the theme's order.
 *
 * Takes the same (min, max, previous) verifyRandom() takes, because the
 * caller tracks "previous" either way -- random uses it to avoid a repeat,
 * linear uses it to know where it was.
 *
 * WRAPS, AND CLAMPS INTO RANGE. The bunker shares one counter across two
 * ranges (1-6 early, 7-12 late), so stepping from 4 into the 7-12 range
 * would land on 5, a frame that range does not own. Anything outside the
 * range restarts at its first frame, which is also what makes the opening
 * round work: every scene seeds "previous" with its LAST frame, so the
 * first step wraps neatly round to 1.
 */
function dropshipStageFrame($min, $max, $previous, $order = 'random') {
	if ($max <= $min) return $min;
	if ($order !== 'linear') return verifyRandom($min, $max, $previous);
	$next = (int)$previous + 1;
	if ($next < $min || $next > $max) $next = $min;
	return $next;
}

/**
 * A Drop Ship asset url, cache-busted by the FILE rather than by chance.
 *
 * dashboard.php asked for "dropship.js?var=" . rand(0, 999), and the
 * server sends Cache-Control: max-age=604800. A thousand possible urls,
 * each cached for a WEEK: every page load rolls a die and lands either on
 * a fresh copy or on one from before the last deploy. That is why a
 * javascript fix appeared to work, then not work, then work again, with
 * nothing having changed -- and why "it was fine earlier" was true.
 *
 * Every other page asked for it with no buster at all, so they served a
 * week-stale copy until the cache expired on its own.
 *
 * filemtime() is the whole fix: the url changes if and only if the file
 * does. Unchanged file, same url, still cached, no extra request; changed
 * file, new url, every browser fetches it exactly once.
 */
function dropshipAsset($file) {
	$abs = rtrim(__DIR__, '/') . '/' . ltrim($file, '/');
	$v = @filemtime($abs);
	/* No mtime means the file is missing or unreadable -- fall back to the
	   bare name rather than emitting "?v=" and caching that forever. */
	return $v ? $file . '?v=' . $v : $file;
}

/**
 * WHAT A THEME PAYS ITS TOP THREE, as a currency symbol.
 *
 * This fact was written down in three places and they had already drifted:
 *
 *   db.php (Skulliance)      dropshipRewardProjects(), theme -> project id,
 *                            which is what the payout actually credits
 *   instructions.php         $ds_pays, theme -> symbol, for the prize table
 *   automate.php             an if/else ladder building the game's NAME
 *
 * automate.php's ladder had no branch for theme 5, so a DHC2 Fighters game
 * would have been created called "1K, 500, 250 DREAD" and announced that way
 * in a channel whose players are paid MAXI. instructions.php had the right
 * symbol in its map and the wrong gate above it -- `project_id == 1 || == 4`
 * -- so Dread City, Filthy Mermaid and DHC2 Fighters were all told they win
 * SCRIP when the weekly cycle credits them real currency.
 *
 * db.php's own comment already warned about exactly this: "this used to be a
 * second hardcoded list of project ids, and Filthy Mermaid shipped a weekly
 * cycle that paid nobody because only one of the two lists was updated."
 *
 * TWO MAPS REMAIN, NOT ONE, and that is deliberate: this one lives in Drop
 * Ship and dropshipRewardProjects() lives in Skulliance, two apps with
 * separate databases and no shared include. They hold different facts -- a
 * symbol here, a project id there. What keeps them honest is a harness check
 * that both cover the same theme ids, so adding a sixth theme to one and not
 * the other fails a test instead of shipping a game that pays nobody.
 *
 * AN UNMAPPED THEME RETURNS '', meaning "pays no real currency". Callers
 * treat that as the in-game SCRIP economy rather than guessing a default,
 * which is how MOON became the "everything else" answer once before.
 */
function dropshipRewardTokens() {
	return array(
		1 => 'DREAD',   // Drop Ship       -> Skulliance project 2
		2 => 'DREAD',   // Dread City      -> Skulliance project 2
		3 => 'DREAD',   // Filthy Mermaid  -> Skulliance project 2
		4 => 'MOON',    // Oculus Lounge   -> Skulliance project 21
		5 => 'MAXI',    // DHC2 Fighters   -> Skulliance project 9
	);
}

function dropshipRewardToken($project_id) {
	$m = dropshipRewardTokens();
	$k = (int)$project_id;
	return isset($m[$k]) ? $m[$k] : '';
}

/**
 * The three weekly places, as the amounts actually credited.
 *
 * THE NUMBERS LIVE IN SKULLIANCE, in the handler at the top of its db.php:
 * rank 1 is 1000, rank 2 is 500, rank 3 is 250, and they are the same for
 * every theme -- only the currency changes. They are repeated here because
 * Drop Ship cannot see that file, and dropship-themes-harness.php reads the
 * real handler and asserts these three agree with it.
 */
function dropshipRewardAmounts() {
	return array(1000, 500, 250);
}

/**
 * The name a new game is given, which is also what Discord announces.
 *
 * "1K, 500, 250 MAXI" -- the established shape, built rather than typed so a
 * new theme cannot be announced in another theme's currency. Returns '' for
 * a theme that pays no real currency, which automate.php refuses to create a
 * game for rather than naming it after nothing.
 */
function dropshipRewardPrizeName($project_id) {
	$token = dropshipRewardToken($project_id);
	if ($token === '') return '';
	$a = dropshipRewardAmounts();
	/* 1000 reads as 1K in the name and as 1,000 in the prize table; both are
	   the same number and this is the only place that spelling is decided. */
	$first = ($a[0] % 1000 === 0) ? ($a[0] / 1000) . 'K' : (string)$a[0];
	return $first . ', ' . $a[1] . ', ' . $a[2] . ' ' . $token;
}

/**
 * How many places pay out. The three above, and createGame()'s second
 * argument, which checkLeaderboard() cuts the board at.
 */
function dropshipRewardPlaces() {
	return count(dropshipRewardAmounts());
}

/**
 * THE ORDER THE GAME PICKER INTRODUCES THE GAMES IN.
 *
 * Not getProjects()' order, which is the database's, and not anything that
 * can be derived -- not id, not name, not when a theme was added. It is a
 * judgement about which games a player should meet first, so it is written
 * down as a judgement rather than dressed up as a rule.
 *
 * A THEME NOT LISTED HERE STILL APPEARS, at the end, ordered by id. That is
 * the whole reason this is a sort key and not a hand-built list of games:
 * the picker renders whatever getProjects() returns, so forgetting to add a
 * new theme here costs it a good position and never its place in the grid.
 */
function dropshipGameOrder() {
	return array(1, 2, 3, 5, 4);   /* Drop Ship, Dread City, Filthy Mermaid, DHC2 Fighters, Oculus Lounge */
}

/**
 * Sort rows carrying an 'id' into dropshipGameOrder(), unlisted ones last.
 *
 * Takes and returns the array rather than sorting in place, so it can be
 * tested on its own -- the picker's order was previously an accident of the
 * query and there was nothing to assert against.
 */
function dropshipGameOrderSort($games) {
	$order = array_flip(dropshipGameOrder());
	usort($games, function ($a, $b) use ($order) {
		$ia = isset($order[$a['id']]) ? $order[$a['id']] : PHP_INT_MAX;
		$ib = isset($order[$b['id']]) ? $order[$b['id']] : PHP_INT_MAX;
		/* Tie means both are unlisted; id keeps that deterministic rather
		   than leaving it to the sort's own handling of equal keys. */
		if ($ia === $ib) return $a['id'] - $b['id'];
		return $ia - $ib;
	});
	return $games;
}
