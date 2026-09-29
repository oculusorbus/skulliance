<?php
/**
 * homepage-data.php — read-only figures for the public homepage.
 *
 * WHY THIS EXISTS RATHER THAN include 'db.php'.
 *
 * The homepage is included by the WordPress theme (see the header of
 * homepage.php), so anything it pulls in is loaded INSIDE WordPress. db.php
 * must not be, for three reasons that are all demonstrable:
 *
 *   include 'credentials/db_credentials.php'
 *       Relative, so it resolves against the CURRENT WORKING DIRECTORY --
 *       which under WordPress is the WP root, not /staking. It fails
 *       outright: "Failed to open stream: No such file or directory".
 *
 *   ini_set('display_errors', 1) and display_startup_errors
 *       Turns error output ON for the public front page. Every notice from
 *       WordPress or any plugin after that point is printed to visitors.
 *       This is the one that would actually embarrass you.
 *
 *   session_start() when a session cookie is present
 *       Writes a session file per visitor, and most WordPress page caches
 *       refuse to cache a response once a session exists -- so the homepage
 *       could quietly stop being cached at all.
 *
 * Plus 704KB and 426 functions parsed on a page that needs five numbers.
 *
 * A NOTE ON NAME COLLISIONS, because it is the argument people reach for
 * first and it is the weakest one here: 420 of those 426 functions are
 * camelCase, and WordPress core is snake_case and mostly wp_-prefixed, so
 * core cannot clash. Only six are lowercase -- alert, burn, craft,
 * fireworks, shatter, array_sort_by_column -- and none are core functions.
 * A plugin could still define one, and a duplicate is a fatal, but that is a
 * possibility rather than the reason to keep db.php out.
 *
 * So this is a deliberately tiny, self-contained alternative: absolute
 * paths, its own connection, every name prefixed hp_, no sessions, no error
 * display, and read-only.
 *
 * NOTHING HERE MAY BREAK THE HOMEPAGE. Every function returns a caller-
 * supplied fallback if the database is unreachable, slow, or the query
 * fails. A marketing page that 500s because a stat could not be counted is
 * a far worse outcome than one showing a rounded number from last week.
 *
 * USAGE, from homepage.php:
 *
 *     require_once __DIR__ . '/homepage-data.php';
 *     $staked = hp_stat_nfts_staked();          // int, 0 if unavailable
 *     echo number_format($staked);
 */

if (!defined('HP_CACHE_TTL'))  define('HP_CACHE_TTL', 300);   // 5 minutes
if (!defined('HP_DB_TIMEOUT')) define('HP_DB_TIMEOUT', 3);    // seconds

/**
 * The connection, opened once per request and only if something asks.
 *
 * Returns null rather than throwing. A short connect timeout matters more
 * here than anywhere else on the platform: this runs on the page everyone
 * lands on, and a database having a bad minute must cost a stale number
 * rather than a hung request.
 */
function hp_db() {
	static $conn = false;
	if ($conn !== false) return $conn;
	$conn = null;
	try {
		$cred = __DIR__ . '/credentials/db_credentials.php';
		if (!is_readable($cred)) return null;
		/* Scoped so the credential variables do not leak into the caller --
		   this file is included into WordPress's global scope. */
		$vals = (static function ($path) {
			include $path;
			return compact('servername', 'username', 'password', 'dbname');
		})($cred);
		if (empty($vals['servername'])) return null;

		/* mysqli throws by default from PHP 8.1. Off for this connection only
		   -- a homepage stat is not worth an exception reaching a visitor. */
		$prev = mysqli_report(MYSQLI_REPORT_OFF);
		$c = mysqli_init();
		if (!$c) { mysqli_report($prev); return null; }
		$c->options(MYSQLI_OPT_CONNECT_TIMEOUT, HP_DB_TIMEOUT);
		$ok = @$c->real_connect($vals['servername'], $vals['username'],
		                        $vals['password'], $vals['dbname']);
		mysqli_report($prev);
		if (!$ok) return null;
		$conn = $c;
	} catch (Throwable $e) {
		$conn = null;
	}
	return $conn;
}

/**
 * One scalar, cached on disk.
 *
 * THE CACHE IS THE POINT, not an optimisation. Without it every visitor and
 * every crawler runs a COUNT over the nfts table, and the homepage is the
 * most-hit page on the domain. Five minutes is far fresher than the manual
 * copy-paste this replaces, which was however old the last deploy was.
 *
 * A stale value is served when the query fails, so a database blip shows
 * last week's number instead of a zero -- a homepage that suddenly claims
 * nothing is staked is worse than one that is slightly behind.
 */
function hp_cached($key, callable $query, $fallback = 0) {
	$key  = preg_replace('/[^a-z0-9_]/i', '', (string)$key);
	$file = sys_get_temp_dir() . '/hp-stat-' . $key . '.txt';

	$cached = null;
	if (is_readable($file)) {
		$raw = @file_get_contents($file);
		if ($raw !== false && $raw !== '') {
			$cached = $raw;
			if (time() - (int)@filemtime($file) < HP_CACHE_TTL) return $cached;
		}
	}

	try {
		$conn = hp_db();
		if ($conn !== null) {
			$val = $query($conn);
			if ($val !== null) {
				@file_put_contents($file, (string)$val, LOCK_EX);
				return $val;
			}
		}
	} catch (Throwable $e) { /* fall through to the stale value */ }

	/* Touch the file so a hard-down database is not re-queried on every hit
	   for the next five minutes. */
	if ($cached !== null) { @touch($file); return $cached; }
	return $fallback;
}

/** Helper: first column of the first row, or null. */
function hp_scalar($conn, $sql) {
	$r = @$conn->query($sql);
	if (!$r || !$r->num_rows) return null;
	$row = $r->fetch_row();
	return isset($row[0]) ? $row[0] : null;
}

/* ---- the figures ---------------------------------------------------- */

/** NFTs currently staked — a real owner, on any chain. */
function hp_stat_nfts_staked($fallback = 0) {
	return (int)hp_cached('nfts_staked', function ($c) {
		return hp_scalar($c, "SELECT COUNT(*) FROM nfts WHERE user_id > 0");
	}, $fallback);
}

/** Collections earning rewards. */
function hp_stat_collections($fallback = 0) {
	return (int)hp_cached('collections', function ($c) {
		return hp_scalar($c, "SELECT COUNT(*) FROM collections");
	}, $fallback);
}

/** Artists — projects with at least one collection, which is what a visitor
 *  would count. A project row with nothing under it is not an artist yet. */
function hp_stat_artists($fallback = 0) {
	return (int)hp_cached('artists', function ($c) {
		return hp_scalar($c, "SELECT COUNT(DISTINCT project_id) FROM collections");
	}, $fallback);
}

/** Wallets linked, across every chain. */
function hp_stat_wallets($fallback = 0) {
	return (int)hp_cached('wallets', function ($c) {
		return hp_scalar($c, "SELECT COUNT(*) FROM wallets");
	}, $fallback);
}

/* ---- DHC Fighters, for the public game page --------------------------- */

/**
 * Real player-built Fighters, newest first, for the scrolling strips on
 * dhcfighters-landing.php.
 *
 * REAL ONES, NOT MOCKUPS. The page's claim is that people build these, so
 * showing invented combinations would be both a lie and worse art -- players
 * make stranger and better Fighters than a random generator does.
 *
 * Returns a list of ['name' => string, 'traits' => array, 'layers' => array],
 * already ordered for drawing by dhcf_layer_order() when that is available.
 * Empty list on any failure, and the caller renders the section only when it
 * has something -- a marketing page with empty frames is worse than one
 * section shorter.
 *
 * Disassembled Fighters are excluded: their row survives so the build cannot
 * be replayed as new, but the Fighter no longer exists and showing it would
 * advertise something nobody can go and look at.
 */
function hp_dhc_fighters($limit = 24) {
	$limit = max(1, min(60, (int)$limit));
	$raw = hp_cached('dhc_fighters_' . $limit, function ($c) use ($limit) {
		$sql = "SELECT name, serial, traits FROM dhc_fighters
		         WHERE disassembled_at IS NULL AND invalid = 0 AND traits <> ''
		         ORDER BY id DESC LIMIT " . $limit;
		$r = @$c->query($sql);
		if (!$r) return null;
		$out = array();
		while ($row = $r->fetch_assoc()) {
			$t = json_decode($row['traits'], true);
			if (!is_array($t) || !$t) continue;
			$out[] = array(
				'name'   => ($row['name'] !== null && $row['name'] !== '')
				          ? $row['name'] : ('#' . $row['serial']),
				/* The canonical renderer names its file by serial + a hash of
				   the traits, so the serial has to travel with the Fighter. */
				'serial' => (int)$row['serial'],
				'traits' => $t,
			);
		}
		/* Cached as JSON because hp_cached() stores a scalar. */
		return json_encode($out);
	}, '');

	$list = $raw !== '' ? json_decode($raw, true) : array();
	if (!is_array($list)) return array();

	/* The draw order has per-trait exceptions -- a companion under the arms,
	   arms behind the torso -- so it comes from dhcf_layer_order() when the
	   config is loadable, and never from a hardcoded list here. Two lists
	   that can disagree about what a Fighter looks like is the bug that
	   already cost the Arena its effects. */
	$have_order = function_exists('dhcf_layer_order');
	if (!$have_order) {
		$cfg = __DIR__ . '/dhcfighters-config.php';
		if (is_readable($cfg)) { @include_once $cfg; $have_order = function_exists('dhcf_layer_order'); }
	}
	foreach ($list as $i => $f) {
		$list[$i]['layers'] = $have_order ? dhcf_layer_order($f['traits']) : array_keys($f['traits']);
	}
	return $list;
}

/** Blockchains actually carrying a collection, so this says 2 only once XRPL
 *  really has one rather than because a row exists in `blockchains`. */
function hp_stat_chains($fallback = 1) {
	return (int)hp_cached('chains', function ($c) {
		return hp_scalar($c, "SELECT COUNT(DISTINCT blockchain_id) FROM collections");
	}, $fallback);
}
