<?php
/**
 * lib/infiltration-lib.php -- Infiltration: verified X replies pay traits.
 *
 * NOT PUBLIC, AND NOT COSMETICALLY SO. This feature must not appear in the
 * Skull Paper, on any public page, or in any announcement. The X posts it
 * runs on stay ordinary posts with no "comment to earn" wording: a promotion
 * scheme that advertises itself is worth nothing to the algorithm it is
 * courting and reads as farming to everybody else. The design record is
 * infiltration-design.md at the repo root, served as a 403 by the .htaccess
 * .md rule. Member-visible UI behind the Discord login is fine.
 *
 * NOTHING HERE ECHOES and nothing here includes db.php, so the whole file is
 * testable against a fake connection. See infiltration-harness.php.
 *
 * NOT ONE PAID CALL. X's v2 data endpoints need pay-per-use enrolment, which
 * is why lib/x-oauth.php sits disabled with a working PKCE flow in it. The
 * syndication endpoint below is public, unauthenticated, and the ONLY free
 * surface that reports a reply's parent -- measured against seven others by
 * admin-xprobe.php. That makes it a single point of failure, which is the
 * whole reason infil_fetch() distinguishes "X says no" from "X did not
 * answer". See INFIL_PENDING.
 */

/* ------------------------------------------------------------------ *
 * WHAT IS BEING VERIFIED
 * ------------------------------------------------------------------ */

/**
 * The pinned post every account link is checked against.
 *
 * Verified 2026-11-12: authored by @skulliance, and TOP LEVEL rather than
 * itself a reply, which is what makes in_reply_to_status_id_str on every code
 * comment equal to this id.
 *
 * IT MUST NEVER BE DELETED OR REPLACED. Links already made survive, because
 * x_id is written once and kept, but every future link breaks the moment this
 * id stops resolving.
 */
if (!defined('INFIL_LINK_STATUS')) define('INFIL_LINK_STATUS', '2120916830747602945');

/**
 * @skulliance, by immutable id.
 *
 * MATCHED ON THE ID, NEVER THE HANDLE -- the same rule lib/x-oauth.php
 * documents. A handle can be renamed and the released name registered by
 * somebody else, so matching on text would let a lookalike account stand in
 * as the reply target.
 */
if (!defined('INFIL_SKULLIANCE_UID')) define('INFIL_SKULLIANCE_UID', '1494210394541412352');

/* ------------------------------------------------------------------ *
 * THE WINDOWS
 *
 * Researched, not picked. Earlybird feeds X's ranker time-decayed engagement
 * counts with a half-life around 360 minutes, so a reply at 6h lands on a
 * post at half its peak visibility and at 24h a sixteenth of it.
 *
 * One hour is where a reply actually buys reach. Twenty-four hours is where
 * it still counts as showing up. That is exactly the +2 / +1 split.
 *
 * Both are DEFAULTS. Every target carries its own, because window length is
 * the only parameter here that can quietly create a caste -- a short window
 * rewards timezone and notification settings rather than effort.
 * ------------------------------------------------------------------ */
if (!defined('INFIL_EARLY_MINS')) define('INFIL_EARLY_MINS', 60);
if (!defined('INFIL_MAIN_MINS'))  define('INFIL_MAIN_MINS',  1440);

/* The reach half-life, used to show the owner how much of a target's window
   is left when deciding whether another reply is still worth chasing.
   NOT a worklist: he reads the Discord channel and reacts with an emoji to
   what he has answered, which needs no state on our side. */
if (!defined('INFIL_HALFLIFE_MINS')) define('INFIL_HALFLIFE_MINS', 360);

/* ------------------------------------------------------------------ *
 * THE LADDER
 * ------------------------------------------------------------------ */

/** Rung 9 is placement_1 and there is nothing above it. */
if (!defined('INFIL_MAX_TIER')) define('INFIL_MAX_TIER', 9);

/* Tiers gained. Early doubles, main is the ordinary good outcome, late pays
   the trait and cancels the miss without climbing, absent costs one. */
if (!defined('INFIL_GAIN_EARLY')) define('INFIL_GAIN_EARLY', 2);
if (!defined('INFIL_GAIN_MAIN'))  define('INFIL_GAIN_MAIN',  1);
if (!defined('INFIL_GAIN_LATE'))  define('INFIL_GAIN_LATE',  0);
if (!defined('INFIL_MISS_COST'))  define('INFIL_MISS_COST', -1);

/* ------------------------------------------------------------------ *
 * COMMENT QUALITY
 *
 * CALIBRATED AGAINST A REAL COMMENT, not chosen because it sounded strict.
 * The reply admin-xprobe.php verified was "I did and got a legendary
 * background!" -- 37 characters, 7 words, and obviously genuine. Any floor
 * that rejects that comment is wrong, which rules out the double-digit word
 * counts that look reasonable in the abstract.
 *
 * There is NO reply-length feature in X's ranker at all, so this is an
 * anti-farming floor and not an algorithmic one. Its only jobs are to stop
 * one-word replies and to leave the owner something worth answering.
 *
 * Characters are the primary gate because word counts are trivially gamed
 * with single letters; the word floor stops a wall of one repeated
 * character, which the duplicate check does not catch on a first offence.
 * ------------------------------------------------------------------ */
if (!defined('INFIL_MIN_CHARS')) define('INFIL_MIN_CHARS', 25);
if (!defined('INFIL_MIN_WORDS')) define('INFIL_MIN_WORDS', 4);

/**
 * How alike two comments may be before the later one is refused.
 *
 * 0.90 of similar_text()'s percentage. Deliberately loose: it is here to
 * catch one member pasting a variant of their own sentence, not to police
 * two people independently saying something short and similar. The design
 * record flags this as the one number wanting real submissions to tune
 * against -- raise it if honest comments get caught, lower it if variants
 * get through.
 */
if (!defined('INFIL_SIMILAR_MAX')) define('INFIL_SIMILAR_MAX', 90.0);

/* ------------------------------------------------------------------ *
 * OUTCOMES
 *
 * THE DISTINCTION THE WHOLE FEATURE RESTS ON. There is exactly one free
 * surface that proves a reply's parent and it is undocumented. A 403, a 404,
 * an empty body or a timeout means X DID NOT ANSWER -- it does not mean the
 * member is lying. Anything that cannot tell those apart will, on the day X
 * changes something, tell every honest player their comment is invalid.
 * ------------------------------------------------------------------ */
if (!defined('INFIL_OK'))      define('INFIL_OK',      'verified');
if (!defined('INFIL_PENDING')) define('INFIL_PENDING', 'pending');
if (!defined('INFIL_NO'))      define('INFIL_NO',      'rejected');

/* ------------------------------------------------------------------ *
 * READING WHAT THE OWNER OR A MEMBER PASTED
 * ------------------------------------------------------------------ */

/** The numeric status id out of a pasted URL, or '' if there is none. */
function infil_status_id($s) {
	$s = trim((string) $s);
	if (preg_match('~/status(?:es)?/(\d{5,25})~', $s, $m)) return $m[1];
	if (preg_match('~^\d{5,25}$~', $s)) return $s;
	return '';
}

/** The @handle a status URL claims, which is display text and proves nothing. */
function infil_url_handle($s) {
	if (preg_match('~(?:twitter|x)\.com/([A-Za-z0-9_]{1,15})/status~i', (string) $s, $m)) return $m[1];
	return '';
}

/*
 * JS's Number.prototype.toString(36), near enough, for the syndication
 * endpoint's `token`.
 *
 * MEASURED 2026-11-12: the token is REQUIRED but NOT VALIDATED. `token=a`
 * returns the full payload and an absent token returns `{}`. So nothing rests
 * on this matching JS bit for bit. Kept because it is what a browser sends,
 * and the day they start checking is the day this stops being free.
 */
function infil_base36($num) {
	$digits = '0123456789abcdefghijklmnopqrstuvwxyz';
	$int    = floor($num);
	$frac   = $num - $int;
	$whole  = '';
	if ($int <= 0) {
		$whole = '0';
	} else {
		while ($int > 0) {
			$whole = $digits[(int) fmod($int, 36)] . $whole;
			$int   = floor($int / 36);
		}
	}
	$f = '';
	for ($i = 0; $i < 13 && $frac > 0; $i++) {
		$frac *= 36;
		$d     = (int) floor($frac);
		$f    .= $digits[$d];
		$frac -= $d;
	}
	return $f === '' ? $whole : $whole . '.' . $f;
}

function infil_token($id) {
	return preg_replace('/(0+|\.)/', '', infil_base36(((float) $id / 1e15) * M_PI));
}

/* ------------------------------------------------------------------ *
 * ASKING X
 * ------------------------------------------------------------------ */

/**
 * Fetch one status from the syndication endpoint.
 *
 * Returns array('state' => INFIL_OK|INFIL_PENDING, 'http' => int,
 *               'json' => array|null, 'why' => string).
 *
 * NEVER RETURNS INFIL_NO. This function cannot refuse anything -- it only
 * reports whether X answered. Deciding is infil_check()'s job, on data that
 * actually arrived.
 *
 * $fetcher exists so the harness can drive every branch against real recorded
 * payloads without a network. Production passes nothing.
 */
function infil_fetch($status_id, $fetcher = null) {
	$status_id = infil_status_id($status_id);
	if ($status_id === '') {
		return array('state' => INFIL_PENDING, 'http' => 0, 'json' => null,
		             'why' => 'no status id');
	}
	$url = 'https://cdn.syndication.twimg.com/tweet-result?id=' . $status_id
	     . '&lang=en&token=' . urlencode(infil_token($status_id));

	if (is_callable($fetcher)) {
		$r = call_user_func($fetcher, $url, $status_id);
	} else {
		$r = infil_http($url);
	}

	$http = isset($r['http']) ? (int) $r['http'] : 0;
	$body = isset($r['body']) ? (string) $r['body'] : '';
	$json = json_decode($body, true);

	/*
	 * A 200 CARRYING `{}` IS NOT AN ANSWER. That is what an absent or
	 * rejected token returns, and it is also what a deleted status returns --
	 * indistinguishable from here, so it parks rather than refuses. A
	 * genuinely deleted comment gets refused by the owner, not by a guess.
	 */
	if ($http < 200 || $http >= 300 || !is_array($json) || !isset($json['id_str'])) {
		return array('state' => INFIL_PENDING, 'http' => $http, 'json' => null,
		             'why' => $http === 0 ? 'no reply from X' : ('X answered ' . $http
		                    . ($http >= 200 && $http < 300 ? ' with nothing usable' : '')));
	}
	return array('state' => INFIL_OK, 'http' => $http, 'json' => $json, 'why' => '');
}

/** The curl, separated so infil_fetch() stays testable. */
function infil_http($url) {
	if (!function_exists('curl_init')) return array('http' => 0, 'body' => '');
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 4,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_CONNECTTIMEOUT => 6,
		CURLOPT_HTTPHEADER     => array(
			/* A browser UA on purpose: the question is what the feature can
			   get, not what curl's default can. */
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) '
			. 'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
			'Accept-Language: en-US,en;q=0.9',
		),
	));
	$body = curl_exec($ch);
	$out  = array('http' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
	              'body' => $body === false ? '' : (string) $body);
	curl_close($ch);
	return $out;
}

/**
 * The facts, out of a syndication payload.
 *
 * Returns array('status','handle','uid','text','own','parent','parent_uid',
 *               'created'), every value a string, '' where absent.
 */
function infil_parse($json) {
	$g = function ($a, $k) { return isset($a[$k]) ? (string) $a[$k] : ''; };
	$user = isset($json['user']) && is_array($json['user']) ? $json['user'] : array();

	$text = $g($json, 'text');
	/*
	 * display_text_range IS X TELLING US WHERE THE MEMBER'S OWN WORDS START.
	 * A reply's `text` carries the "@skulliance " mention prefix, and every
	 * quality gate has to run on the words the member actually wrote -- a
	 * character floor counting our own handle would pass "@skulliance nice".
	 * mb_substr because the range is in CODE POINTS, not bytes.
	 */
	$own = $text;
	if (isset($json['display_text_range'][0], $json['display_text_range'][1])) {
		$a = (int) $json['display_text_range'][0];
		$b = (int) $json['display_text_range'][1];
		if ($b > $a && $a >= 0) $own = mb_substr($text, $a, $b - $a);
	}

	return array(
		'status'     => $g($json, 'id_str'),
		'handle'     => $g($user, 'screen_name'),
		'uid'        => $g($user, 'id_str'),
		'text'       => $text,
		'own'        => trim($own),
		'parent'     => $g($json, 'in_reply_to_status_id_str'),
		'parent_uid' => $g($json, 'in_reply_to_user_id_str'),
		'created'    => $g($json, 'created_at'),
	);
}

/* ------------------------------------------------------------------ *
 * COMMENT TEXT
 * ------------------------------------------------------------------ */

/**
 * Flatten a comment for comparison: lowercase, no punctuation, no emoji,
 * single spaces.
 *
 * So that "Great run!!" and "great run" are the same submission, which is the
 * whole point of a duplicate check.
 */
function infil_normalise($s) {
	$s = (string) $s;
	$s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
	/* Strip anything that is not a letter, a number or a space. \p{L} and
	   \p{N} keep non-Latin alphabets, which a [a-z0-9] class would delete
	   entirely and turn every such comment into the same empty string. */
	$s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
	$s = preg_replace('/\s+/u', ' ', $s);
	return trim((string) $s);
}

function infil_hash($normalised) { return sha1((string) $normalised); }

/** Words in the normalised text. */
function infil_words($normalised) {
	$n = trim((string) $normalised);
	if ($n === '') return 0;
	return count(preg_split('/\s+/u', $n));
}

/** Characters of own words, counted in code points rather than bytes. */
function infil_chars($own) {
	return function_exists('mb_strlen') ? mb_strlen((string) $own, 'UTF-8') : strlen((string) $own);
}

/**
 * Is this comment substantial enough? Returns '' or the reason it is not.
 *
 * Worded for the member, because this is the one refusal an honest person
 * will meet.
 */
function infil_quality($own) {
	$norm  = infil_normalise($own);
	$chars = infil_chars(trim((string) $own));
	$words = infil_words($norm);
	if ($norm === '') return 'that comment has no words in it';
	if ($chars < INFIL_MIN_CHARS) {
		return 'that comment is ' . $chars . ' characters; it needs at least '
		     . INFIL_MIN_CHARS . ' in your own words';
	}
	if ($words < INFIL_MIN_WORDS) {
		return 'that comment is ' . $words . ' word' . ($words === 1 ? '' : 's')
		     . '; it needs at least ' . INFIL_MIN_WORDS;
	}
	return '';
}

/** similar_text() as a percentage, order-independent. */
function infil_similarity($a, $b) {
	$a = (string) $a; $b = (string) $b;
	if ($a === '' || $b === '') return 0.0;
	if ($a === $b) return 100.0;
	$pct = 0.0;
	similar_text($a, $b, $pct);
	return (float) $pct;
}

/* ------------------------------------------------------------------ *
 * SPEED AND THE METER
 * ------------------------------------------------------------------ */

/**
 * Which window a reply landed in. 'early', 'main' or 'late'.
 *
 * Both timestamps as unix seconds. A reply BEFORE the target was posted is
 * impossible, and treated as 'late' rather than trusted -- a negative age
 * would otherwise read as the promptest possible reply.
 */
function infil_speed($target_posted, $replied_at, $early_mins = null, $main_mins = null) {
	$early = $early_mins === null ? INFIL_EARLY_MINS : (int) $early_mins;
	$main  = $main_mins  === null ? INFIL_MAIN_MINS  : (int) $main_mins;
	$age   = (int) $replied_at - (int) $target_posted;
	if ($age < 0)            return 'late';
	if ($age <= $early * 60) return 'early';
	if ($age <= $main  * 60) return 'main';
	return 'late';
}

/** Tiers a speed is worth. */
function infil_gain($speed) {
	if ($speed === 'early') return INFIL_GAIN_EARLY;
	if ($speed === 'main')  return INFIL_GAIN_MAIN;
	return INFIL_GAIN_LATE;
}

/**
 * Apply a tier change to a meter, overflowing into the bank.
 *
 * Returns array($tier, $bank).
 *
 * THE BANK IS UNCAPPED -- the owner's call. It pays nothing by itself,
 * because nobody rolls a trait without infiltrating a target, so overshoot
 * buys gaps without consequence rather than a stream of rare traits. A
 * dormant member with sixty pips still rolls exactly one trait when they come
 * back. The "is current" signal lives on the leaderboard instead, which ranks
 * tiers EARNED IN THE MONTH rather than tier held.
 *
 * Gains fill the meter first and spill over. Losses burn the bank first and
 * only then the meter, which is what makes a pip insurance rather than
 * decoration. The meter floors at 0 and the bank cannot go negative.
 */
function infil_apply($tier, $bank, $delta) {
	$tier  = max(0, min(INFIL_MAX_TIER, (int) $tier));
	$bank  = max(0, (int) $bank);
	$delta = (int) $delta;

	if ($delta > 0) {
		$room  = INFIL_MAX_TIER - $tier;
		$up    = min($room, $delta);
		$tier += $up;
		$bank += ($delta - $up);
	} else if ($delta < 0) {
		$down  = -$delta;
		$burn  = min($bank, $down);
		$bank -= $burn;
		$tier  = max(0, $tier - ($down - $burn));
	}
	return array($tier, $bank);
}

/** The odds a tier rolls on, straight through the platform's own resolver. */
function infil_table($tier) {
	return dhcf_table_for('infiltration', max(0, min(INFIL_MAX_TIER, (int) $tier)));
}

/** The table NAME a tier rolls on, for display and for the harness. */
function infil_table_name($tier) {
	$ladder = dhcf_ladder();
	$i      = max(0, min(INFIL_MAX_TIER, (int) $tier));
	return isset($ladder[$i]) ? $ladder[$i] : 'run';
}

/**
 * How much of a target's reach window is left, 1.0 down to 0.0.
 *
 * Shown on the admin target list: a target still inside its half-life is one
 * where another reply is worth chasing, and a three day old one is a trophy.
 */
function infil_heat($posted_at, $now = null) {
	$now = $now === null ? time() : (int) $now;
	$age = max(0, $now - (int) $posted_at) / 60.0;
	return (float) pow(0.5, $age / INFIL_HALFLIFE_MINS);
}

/* ================================================================== *
 * THE DATABASE
 *
 * Everything below takes $conn as a parameter rather than reaching for a
 * global, so the pure half of this file above stays testable without one.
 * mysqli with escaped literals, like the rest of the platform.
 * ================================================================== */

/**
 * The schema, as statements safe to run repeatedly.
 *
 * CREATE TABLE IF NOT EXISTS so that admin-infiltration.php can offer the
 * install as a button. The owner is usually on a phone and cannot open a SQL
 * console, which is why this is a page and not a migration file.
 */
function infil_schema() {
	return array(

		/* A target is a post of ours worth replying to. No cadence and no
		   expiry: it stays open forever and can always be infiltrated late.
		   Only the owner closes one, by retiring it. */
		'infil_targets' => "CREATE TABLE IF NOT EXISTS infil_targets (
			id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
			status_id   VARCHAR(25)  NOT NULL,
			label       VARCHAR(190) NOT NULL DEFAULT '',
			posted_at   DATETIME     NOT NULL,
			early_mins  INT UNSIGNED NOT NULL DEFAULT 60,
			main_mins   INT UNSIGNED NOT NULL DEFAULT 1440,
			retired     TINYINT      NOT NULL DEFAULT 0,
			settled     TINYINT      NOT NULL DEFAULT 0,
			created_at  DATETIME     NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY status_id (status_id),
			KEY settle (settled, retired, posted_at)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

		/*
		 * One row per attempt, verified or not.
		 *
		 * utf8mb4 on the text columns because a comment can contain anything
		 * a person can type, emoji included -- utf8 would truncate at the
		 * first astral character and the stored text would stop matching
		 * what X returned.
		 *
		 * UNIQUE on status_id stops one comment being claimed twice, by the
		 * same member or by two. UNIQUE on (user_id, target_id) is the "one
		 * submission per member per target" ration -- but a PENDING or
		 * REJECTED row has to be retryable, so infil_submit() updates in
		 * place rather than inserting a second.
		 */
		'infil_submissions' => "CREATE TABLE IF NOT EXISTS infil_submissions (
			id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id     INT UNSIGNED NOT NULL,
			target_id   INT UNSIGNED NOT NULL,
			status_id   VARCHAR(25)  NOT NULL,
			x_uid       VARCHAR(25)  NOT NULL DEFAULT '',
			x_handle    VARCHAR(32)  NOT NULL DEFAULT '',
			raw_text    TEXT         NULL,
			own_text    TEXT         NULL,
			norm_text   TEXT         NULL,
			text_hash   CHAR(40)     NOT NULL DEFAULT '',
			speed       ENUM('early','main','late') NOT NULL DEFAULT 'late',
			tiers       TINYINT      NOT NULL DEFAULT 0,
			state       ENUM('verified','pending','rejected') NOT NULL DEFAULT 'pending',
			why         VARCHAR(255) NOT NULL DEFAULT '',
			tries       INT UNSIGNED NOT NULL DEFAULT 0,
			trait_slug  VARCHAR(64)  NOT NULL DEFAULT '',
			trait_name  VARCHAR(96)  NOT NULL DEFAULT '',
			trait_tier  VARCHAR(16)  NOT NULL DEFAULT '',
			table_name  VARCHAR(24)  NOT NULL DEFAULT '',
			replied_at  DATETIME     NULL,
			verified_at DATETIME     NULL,
			created_at  DATETIME     NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY one_comment (status_id),
			UNIQUE KEY one_per_target (user_id, target_id),
			KEY text_hash (text_hash),
			KEY by_user (user_id, state),
			KEY by_target (target_id, state)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

		/*
		 * The meter, stored rather than derived, with infil_submissions as
		 * the audit trail.
		 *
		 * month_tiers / month_key are a self-resetting counter: the board
		 * ranks tiers EARNED IN THE MONTH rather than tier held, so that an
		 * uncapped bank cannot park a dormant member at the top of it. Keyed
		 * by month so nothing has to be scheduled to zero them.
		 */
		'infil_meter' => "CREATE TABLE IF NOT EXISTS infil_meter (
			user_id     INT UNSIGNED    NOT NULL,
			tier        TINYINT UNSIGNED NOT NULL DEFAULT 0,
			bank        INT UNSIGNED    NOT NULL DEFAULT 0,
			month_tiers INT             NOT NULL DEFAULT 0,
			month_key   CHAR(7)         NOT NULL DEFAULT '',
			total_tiers INT             NOT NULL DEFAULT 0,
			updated_at  DATETIME        NOT NULL,
			PRIMARY KEY (user_id),
			KEY board (month_key, month_tiers)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

		/* One live code per member, consumed the moment a link succeeds. */
		'infil_codes' => "CREATE TABLE IF NOT EXISTS infil_codes (
			user_id   INT UNSIGNED NOT NULL,
			code      VARCHAR(24)  NOT NULL,
			issued_at DATETIME     NOT NULL,
			PRIMARY KEY (user_id),
			UNIQUE KEY code (code)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
	);
}

/** Which of the four tables are not there yet. */
function infil_tables_missing($conn) {
	$missing = array();
	foreach (array_keys(infil_schema()) as $t) {
		$r = @$conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
		if (!$r || $r->num_rows === 0) $missing[] = $t;
	}
	return $missing;
}

/** Create whatever is missing. Returns array(created, array(errors)). */
function infil_install($conn) {
	$made = 0; $errs = array();
	foreach (infil_schema() as $name => $sql) {
		if (@$conn->query($sql)) $made++;
		else $errs[] = $name . ': ' . $conn->error;
	}
	return array($made, $errs);
}

/* ------------------------------------------------------------------ *
 * LINKING AN X ACCOUNT
 * ------------------------------------------------------------------ */

/**
 * The member's live code, issued if they have none.
 *
 * Unambiguous alphabet on purpose: no 0/O, no 1/I/L. The member reads this
 * off a screen and types it into X, and a code that cannot be transcribed is
 * a support request.
 */
function infil_code($conn, $user_id, $regenerate = false) {
	$user_id = (int) $user_id;
	if ($user_id <= 0) return '';

	if (!$regenerate) {
		$r = @$conn->query("SELECT code FROM infil_codes WHERE user_id = $user_id LIMIT 1");
		if ($r && ($row = $r->fetch_assoc())) return (string) $row['code'];
	}

	$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
	for ($attempt = 0; $attempt < 6; $attempt++) {
		$code = 'SKL-';
		for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
		$e = $conn->real_escape_string($code);
		$ok = @$conn->query("INSERT INTO infil_codes (user_id, code, issued_at)
		                     VALUES ($user_id, '$e', NOW())
		                     ON DUPLICATE KEY UPDATE code = '$e', issued_at = NOW()");
		if ($ok) return $code;
		/* A collision on the UNIQUE code index. Try again rather than hand
		   back a code that belongs to somebody else. */
	}
	return '';
}

/** Whose code is this? 0 if nobody's. */
function infil_code_owner($conn, $code) {
	$e = $conn->real_escape_string(trim((string) $code));
	if ($e === '') return 0;
	$r = @$conn->query("SELECT user_id FROM infil_codes WHERE code = '$e' LIMIT 1");
	return ($r && ($row = $r->fetch_assoc())) ? (int) $row['user_id'] : 0;
}

/**
 * Verify a code comment and link the X account behind it.
 *
 * Returns array('state' => INFIL_OK|INFIL_PENDING|INFIL_NO, 'why' => string,
 *               'handle' => string).
 *
 * THIS IS THE ONLY WAY AN x_id IS WRITTEN BY THIS FEATURE, and it mirrors
 * what process-x-oauth.php would have done if /2/users/me were free: a member
 * already signed in with Discord proves an X account is theirs, in one moment
 * where both identities are present. Never creates an account.
 */
function infil_link($conn, $user_id, $comment_url, $fetcher = null) {
	$user_id = (int) $user_id;
	if ($user_id <= 0) return array('state' => INFIL_NO, 'why' => 'not signed in', 'handle' => '');

	$sid = infil_status_id($comment_url);
	if ($sid === '') {
		return array('state' => INFIL_NO, 'handle' => '',
		             'why' => 'that does not look like a link to a comment on X');
	}

	$f = infil_fetch($sid, $fetcher);
	if ($f['state'] !== INFIL_OK) {
		return array('state' => INFIL_PENDING, 'handle' => '',
		             'why' => 'X did not answer just now (' . $f['why'] . '). Try again in a minute.');
	}
	$d = infil_parse($f['json']);

	/* Replying to somebody ELSE'S comment under the post carries that comment
	   as the parent, not the post. The check refuses it correctly; worded as
	   a bare mismatch it reads as a broken feature, so it says what happened. */
	if ($d['parent'] !== INFIL_LINK_STATUS) {
		if ($d['parent'] !== '' && $d['parent_uid'] === INFIL_SKULLIANCE_UID) {
			return array('state' => INFIL_NO, 'handle' => '',
			             'why' => 'that is a reply to another comment, not to the post itself. '
			                    . 'Reply to the post and paste the new link.');
		}
		return array('state' => INFIL_NO, 'handle' => '',
		             'why' => 'that comment is not a reply to the verification post');
	}
	if ($d['parent_uid'] !== INFIL_SKULLIANCE_UID) {
		return array('state' => INFIL_NO, 'handle' => '',
		             'why' => 'that comment replies to a different account');
	}

	$owner = infil_code_owner($conn, infil_code_in($d['text']));
	if ($owner === 0) {
		return array('state' => INFIL_NO, 'handle' => '',
		             'why' => 'no verification code in that comment. It has to contain the code shown above, exactly.');
	}
	if ($owner !== $user_id) {
		/* Somebody else's code. Harmless to them -- see infiltration-design.md
		   -- but it is not a link we will make. */
		return array('state' => INFIL_NO, 'handle' => '',
		             'why' => 'that code belongs to a different member');
	}
	if ($d['uid'] === '') {
		return array('state' => INFIL_PENDING, 'handle' => '',
		             'why' => 'X did not say who wrote that comment. Try again in a minute.');
	}

	$xid = $conn->real_escape_string($d['uid']);
	$xh  = $conn->real_escape_string($d['handle']);

	/* ONE X ACCOUNT, ONE MEMBER -- x_id carries a UNIQUE index, the same
	   guarantee process-x-oauth.php relies on. */
	$r = @$conn->query("SELECT id FROM users WHERE x_id = '$xid' LIMIT 1");
	if ($r && ($row = $r->fetch_assoc()) && (int) $row['id'] !== $user_id) {
		return array('state' => INFIL_NO, 'handle' => $d['handle'],
		             'why' => 'that X account is already connected to another member');
	}

	$ok = @$conn->query("UPDATE users SET x_id = '$xid', x_username = '$xh', x_linked_at = NOW()
	                     WHERE id = $user_id");
	if (!$ok) {
		return array('state' => INFIL_PENDING, 'handle' => $d['handle'],
		             'why' => 'could not save that just now. Try again in a minute.');
	}
	/* Code consumed. A live code that still works after it has been used is a
	   code somebody can replay. */
	@$conn->query("DELETE FROM infil_codes WHERE user_id = $user_id");

	return array('state' => INFIL_OK, 'why' => '', 'handle' => $d['handle']);
}

/** The first SKL-XXXXXX in a comment, or ''. */
function infil_code_in($text) {
	if (preg_match('/SKL-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}/i', (string) $text, $m)) {
		return strtoupper($m[0]);
	}
	return '';
}

/** The member's linked X handle, or '' if they have none. */
function infil_handle($conn, $user_id) {
	$user_id = (int) $user_id;
	if ($user_id <= 0) return '';
	$r = @$conn->query("SELECT x_username FROM users WHERE id = $user_id LIMIT 1");
	return ($r && ($row = $r->fetch_assoc())) ? (string) $row['x_username'] : '';
}

/** The member's linked X id, or ''. This is the identity, not the handle. */
function infil_uid($conn, $user_id) {
	$user_id = (int) $user_id;
	if ($user_id <= 0) return '';
	$r = @$conn->query("SELECT x_id FROM users WHERE id = $user_id LIMIT 1");
	return ($r && ($row = $r->fetch_assoc())) ? (string) $row['x_id'] : '';
}

/* ------------------------------------------------------------------ *
 * TARGETS
 * ------------------------------------------------------------------ */

/**
 * Add a target. Returns array('id' => int, 'why' => string).
 *
 * The post is VERIFIED before it is stored: it has to exist, be ours, and
 * not itself be a reply. A target that is secretly a reply would make every
 * infiltration of it a nested reply, which the parent check then refuses --
 * a whole target's worth of members told their genuine comments are wrong.
 */
function infil_target_add($conn, $url, $label = '', $early = null, $main = null, $fetcher = null) {
	$sid = infil_status_id($url);
	if ($sid === '') return array('id' => 0, 'why' => 'no status id in that link');

	$f = infil_fetch($sid, $fetcher);
	if ($f['state'] !== INFIL_OK) {
		return array('id' => 0, 'why' => 'X did not answer (' . $f['why'] . '). Nothing was saved.');
	}
	$d = infil_parse($f['json']);

	if ($d['uid'] !== INFIL_SKULLIANCE_UID) {
		return array('id' => 0, 'why' => 'that post belongs to @' . $d['handle']
		                               . ', not to us. A target has to be our own post.');
	}
	if ($d['parent'] !== '') {
		return array('id' => 0, 'why' => 'that is a reply, not a post. Every infiltration of it '
		                               . 'would be a nested reply and would be refused.');
	}

	$posted = strtotime($d['created']);
	if (!$posted) $posted = time();

	$e_sid  = $conn->real_escape_string($sid);
	$e_lab  = $conn->real_escape_string(mb_substr(trim((string) $label), 0, 190));
	$e_when = date('Y-m-d H:i:s', $posted);
	$early  = $early === null ? INFIL_EARLY_MINS : max(1, (int) $early);
	$main   = $main  === null ? INFIL_MAIN_MINS  : max(1, (int) $main);
	if ($main < $early) $main = $early;   /* a main window inside the early one
	                                         would make 'main' unreachable */

	$ok = @$conn->query("INSERT INTO infil_targets
	        (status_id, label, posted_at, early_mins, main_mins, retired, settled, created_at)
	        VALUES ('$e_sid', '$e_lab', '$e_when', $early, $main, 0, 0, NOW())");
	if (!$ok) {
		if ($conn->errno === 1062) return array('id' => 0, 'why' => 'that post is already a target');
		return array('id' => 0, 'why' => 'could not save that: ' . $conn->error);
	}
	return array('id' => (int) $conn->insert_id, 'why' => '');
}

/** One target by id, or null. */
function infil_target($conn, $id) {
	$id = (int) $id;
	if ($id <= 0) return null;
	$r = @$conn->query("SELECT * FROM infil_targets WHERE id = $id LIMIT 1");
	return ($r && ($row = $r->fetch_assoc())) ? $row : null;
}

/** Targets, newest first, with each one's submission count. */
function infil_targets($conn, $include_retired = true) {
	$where = $include_retired ? '' : ' WHERE t.retired = 0';
	$sql = "SELECT t.*,
	               COALESCE(s.n, 0)  AS submissions,
	               COALESCE(s.ok, 0) AS verified
	        FROM infil_targets t
	        LEFT JOIN (SELECT target_id, COUNT(*) n,
	                          SUM(state = 'verified') ok
	                   FROM infil_submissions GROUP BY target_id) s
	          ON s.target_id = t.id
	        $where
	        ORDER BY t.posted_at DESC";
	$out = array();
	$r = @$conn->query($sql);
	if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}

/** Retire or un-retire. Retiring closes a target to new infiltrations. */
function infil_target_retire($conn, $id, $on = true) {
	$id = (int) $id;
	if ($id <= 0) return false;
	return (bool) @$conn->query("UPDATE infil_targets SET retired = " . ($on ? 1 : 0)
	                          . " WHERE id = $id");
}

/** Change a target's windows after the fact. */
function infil_target_windows($conn, $id, $early, $main) {
	$id    = (int) $id;
	$early = max(1, (int) $early);
	$main  = max($early, (int) $main);
	if ($id <= 0) return false;
	return (bool) @$conn->query("UPDATE infil_targets
	        SET early_mins = $early, main_mins = $main WHERE id = $id");
}

/* ------------------------------------------------------------------ *
 * THE METER
 * ------------------------------------------------------------------ */

/** The current month as the meter stores it. */
function infil_month() { return date('Y-m'); }

/**
 * A member's meter. Always returns a row, even for somebody with none.
 *
 * month_tiers is zeroed on READ when the stored month is not this one, so
 * nothing has to be scheduled to reset the board.
 */
function infil_meter($conn, $user_id) {
	$user_id = (int) $user_id;
	$row = array('user_id' => $user_id, 'tier' => 0, 'bank' => 0,
	             'month_tiers' => 0, 'month_key' => infil_month(), 'total_tiers' => 0);
	if ($user_id <= 0) return $row;

	$r = @$conn->query("SELECT * FROM infil_meter WHERE user_id = $user_id LIMIT 1");
	if ($r && ($got = $r->fetch_assoc())) {
		$row['tier']        = (int) $got['tier'];
		$row['bank']        = (int) $got['bank'];
		$row['total_tiers'] = (int) $got['total_tiers'];
		$row['month_key']   = (string) $got['month_key'];
		$row['month_tiers'] = ($row['month_key'] === infil_month()) ? (int) $got['month_tiers'] : 0;
	}
	return $row;
}

/**
 * Move a member's meter by $delta and record it.
 *
 * Returns the meter as it now stands.
 *
 * month_tiers counts tiers GAINED, never losses -- it is the board's "did
 * you turn up this month" number, and subtracting a penalty from it would
 * let a member finish a month on less than they earned in it.
 */
function infil_meter_move($conn, $user_id, $delta) {
	$user_id = (int) $user_id;
	$delta   = (int) $delta;
	if ($user_id <= 0) return infil_meter($conn, 0);

	$now  = infil_meter($conn, $user_id);
	list($tier, $bank) = infil_apply($now['tier'], $now['bank'], $delta);

	$month  = infil_month();
	$gained = max(0, $delta);
	$mt     = $now['month_tiers'] + $gained;
	$tt     = $now['total_tiers'] + $gained;
	$e_m    = $conn->real_escape_string($month);

	@$conn->query("INSERT INTO infil_meter
	        (user_id, tier, bank, month_tiers, month_key, total_tiers, updated_at)
	        VALUES ($user_id, $tier, $bank, $mt, '$e_m', $tt, NOW())
	        ON DUPLICATE KEY UPDATE
	          tier = $tier, bank = $bank, month_tiers = $mt,
	          month_key = '$e_m', total_tiers = $tt, updated_at = NOW()");

	return array('user_id' => $user_id, 'tier' => $tier, 'bank' => $bank,
	             'month_tiers' => $mt, 'month_key' => $month, 'total_tiers' => $tt);
}

/* ------------------------------------------------------------------ *
 * SUBMITTING AN INFILTRATION
 * ------------------------------------------------------------------ */

/**
 * Has this comment been said before? Returns '' or the reason it is refused.
 *
 * Exact duplicates are checked across EVERYBODY -- copying somebody else's
 * comment is the obvious way to farm, and it is the same sentence whoever
 * types it. Near-duplicates are checked only against the SAME member's own
 * history, because two people independently writing something short and
 * similar is not cheating and refusing it would be the worse error.
 */
function infil_duplicate($conn, $user_id, $norm, $exclude_sub_id = 0) {
	$user_id = (int) $user_id;
	$hash    = infil_hash($norm);
	$e_hash  = $conn->real_escape_string($hash);
	$skip    = (int) $exclude_sub_id > 0 ? ' AND id <> ' . (int) $exclude_sub_id : '';

	$r = @$conn->query("SELECT user_id FROM infil_submissions
	                    WHERE text_hash = '$e_hash' AND state = 'verified'$skip LIMIT 1");
	if ($r && ($row = $r->fetch_assoc())) {
		return ((int) $row['user_id'] === $user_id)
		     ? 'you have already used that comment'
		     : 'that comment has been submitted before';
	}

	$r = @$conn->query("SELECT norm_text FROM infil_submissions
	                    WHERE user_id = $user_id AND state = 'verified'$skip
	                    ORDER BY id DESC LIMIT 60");
	if ($r) {
		while ($row = $r->fetch_assoc()) {
			if (infil_similarity($norm, (string) $row['norm_text']) >= INFIL_SIMILAR_MAX) {
				return 'that is too close to a comment you have already submitted. '
				     . 'Write a new one in your own words.';
			}
		}
	}
	return '';
}

/**
 * Verify a submitted comment against a target and pay it.
 *
 * Returns array('state','why','speed','tiers','meter','trait','handle','own').
 *
 * EVERY REFUSAL IS NAMED. A member who meets one of these is almost always
 * honest and confused, so each says what to do instead.
 *
 * NOTHING IS PAID TWICE: the row is written BEFORE dhcf_award() is called,
 * and the award only fires on a fresh transition into 'verified'.
 */
function infil_submit($conn, $user_id, $target_id, $comment_url, $fetcher = null, $now = null) {
	$user_id = (int) $user_id;
	$now     = $now === null ? time() : (int) $now;
	$fail    = function ($state, $why) {
		return array('state' => $state, 'why' => $why, 'speed' => '', 'tiers' => 0,
		             'meter' => null, 'trait' => null, 'handle' => '', 'own' => '');
	};

	if ($user_id <= 0) return $fail(INFIL_NO, 'not signed in');

	$xid = infil_uid($conn, $user_id);
	if ($xid === '') {
		return $fail(INFIL_NO, 'connect your X account first');
	}

	$target = infil_target($conn, $target_id);
	if (!$target)                  return $fail(INFIL_NO, 'no such target');
	if ((int) $target['retired'])  return $fail(INFIL_NO, 'that target is closed');

	$sid = infil_status_id($comment_url);
	if ($sid === '') return $fail(INFIL_NO, 'that does not look like a link to a comment on X');
	if ($sid === (string) $target['status_id']) {
		return $fail(INFIL_NO, 'that is the target post itself. Paste the link to YOUR reply.');
	}

	/* Already claimed? Check before spending a network call. */
	$e_sid = $conn->real_escape_string($sid);
	$r = @$conn->query("SELECT id, user_id, state FROM infil_submissions
	                    WHERE status_id = '$e_sid' LIMIT 1");
	if ($r && ($own_row = $r->fetch_assoc())) {
		if ((int) $own_row['user_id'] !== $user_id) {
			return $fail(INFIL_NO, 'that comment has already been submitted by somebody else');
		}
		if ($own_row['state'] === INFIL_OK) {
			return $fail(INFIL_NO, 'you have already submitted that comment');
		}
	}

	$f = infil_fetch($sid, $fetcher);
	if ($f['state'] !== INFIL_OK) {
		/*
		 * X DID NOT ANSWER. This is NOT a refusal and must never be shown as
		 * one. The row is parked and can be retried; nothing about the
		 * member's comment has been judged.
		 */
		infil_park($conn, $user_id, (int) $target['id'], $sid, $f['why']);
		return $fail(INFIL_PENDING,
			'X did not answer just now, so this is saved and will be checked again. '
		  . 'Nothing is wrong with your comment.');
	}

	$d = infil_parse($f['json']);

	if ($d['uid'] !== $xid) {
		return $fail(INFIL_NO, 'that comment was written by @' . $d['handle']
		                     . ', which is not your connected account');
	}
	if ($d['parent'] !== (string) $target['status_id']) {
		if ($d['parent'] !== '' && $d['parent_uid'] === INFIL_SKULLIANCE_UID) {
			return $fail(INFIL_NO, 'that is a reply to another comment, not to the target post. '
			                     . 'Reply to the post itself and paste the new link.');
		}
		return $fail(INFIL_NO, 'that comment is not a reply to this target');
	}
	if ($d['parent_uid'] !== INFIL_SKULLIANCE_UID) {
		return $fail(INFIL_NO, 'that comment replies to a different account');
	}

	$bad = infil_quality($d['own']);
	if ($bad !== '') return $fail(INFIL_NO, $bad);

	$norm = infil_normalise($d['own']);
	$dup  = infil_duplicate($conn, $user_id, $norm);
	if ($dup !== '') return $fail(INFIL_NO, $dup);

	$replied = strtotime($d['created']);
	if (!$replied) $replied = $now;
	$speed = infil_speed(strtotime($target['posted_at']), $replied,
	                     (int) $target['early_mins'], (int) $target['main_mins']);
	$gain  = infil_gain($speed);

	/*
	 * THE TABLE IS THE METER AS IT STANDS BEFORE THIS INFILTRATION.
	 *
	 * Deliberate, and the alternative is worse: rolling on the tier this
	 * reply just earned would mean a member's FIRST ever infiltration pays
	 * two rungs up, and the ladder would always be one step ahead of what
	 * the meter showed them when they decided to reply. What is on screen
	 * is what they roll.
	 */
	$before = infil_meter($conn, $user_id);
	$table  = infil_table($before['tier']);
	$tname  = infil_table_name($before['tier']);

	$sub_id = infil_record($conn, $user_id, (int) $target['id'], $sid, $d, $norm, $speed, $gain);
	if ($sub_id <= 0) {
		return $fail(INFIL_PENDING, 'could not save that just now. Try again in a minute.');
	}

	$meter = infil_meter_move($conn, $user_id, $gain);

	$trait = null;
	if (function_exists('dhcf_award')) {
		/* 'wildcard' is this game's registered category: all eight slot
		   categories are spoken for by the games, and a wildcard suits a
		   prize that is not tied to one kind of trait. */
		$trait = dhcf_award($conn, $user_id, 'wildcard', 'infiltration',
		                    'target:' . (int) $target['id'], $table);

		/*
		 * WHAT WAS WON, not which row it is. dhcf_award() returns
		 * dhcf_drop_payload(), which carries no drop_id -- an earlier draft
		 * wrote $trait['drop_id'] into a column and silently stored nothing
		 * every time, because the key has never existed. The slug and tier
		 * ARE on the payload and are what the channel post and the admin
		 * table actually want to show.
		 */
		if (is_array($trait)) {
			@$conn->query("UPDATE infil_submissions SET
			        trait_slug = '" . $conn->real_escape_string((string) $trait['slug']) . "',
			        trait_name = '" . $conn->real_escape_string((string) $trait['name']) . "',
			        trait_tier = '" . $conn->real_escape_string((string) $trait['tier']) . "',
			        table_name = '" . $conn->real_escape_string($tname) . "'
			        WHERE id = $sub_id");
		}
	}

	return array('state' => INFIL_OK, 'why' => '', 'speed' => $speed, 'tiers' => $gain,
	             'meter' => $meter, 'trait' => $trait, 'handle' => $d['handle'],
	             'own' => $d['own'], 'table' => $tname, 'sub_id' => $sub_id,
	             'target' => $target, 'comment_status' => $sid, 'user_id' => $user_id);
}

/**
 * Park a submission X could not be asked about.
 *
 * Written as 'pending' with the reason, so it is visible to the member as
 * waiting rather than refused, and countable by the admin page -- a rising
 * pending count is how we find out the endpoint changed, rather than
 * hearing it in Discord.
 */
function infil_park($conn, $user_id, $target_id, $status_id, $why) {
	$user_id   = (int) $user_id;
	$target_id = (int) $target_id;
	$e_sid     = $conn->real_escape_string($status_id);
	$e_why     = $conn->real_escape_string(mb_substr((string) $why, 0, 255));

	@$conn->query("INSERT INTO infil_submissions
	        (user_id, target_id, status_id, state, why, tries, created_at)
	        VALUES ($user_id, $target_id, '$e_sid', 'pending', '$e_why', 1, NOW())
	        ON DUPLICATE KEY UPDATE
	          /* Same two-unique-key reason as infil_record(): a retry with a
	             different comment conflicts on one_per_target. */
	          status_id = '$e_sid',
	          state = IF(state = 'verified', 'verified', 'pending'),
	          why   = '$e_why',
	          tries = tries + 1");
	return (int) $conn->insert_id;
}

/** Write a verified submission. Returns its id, or 0. */
function infil_record($conn, $user_id, $target_id, $status_id, $d, $norm, $speed, $tiers) {
	$user_id   = (int) $user_id;
	$target_id = (int) $target_id;
	$e_sid     = $conn->real_escape_string($status_id);
	$e_uid     = $conn->real_escape_string($d['uid']);
	$e_handle  = $conn->real_escape_string($d['handle']);
	$e_raw     = $conn->real_escape_string($d['text']);
	$e_own     = $conn->real_escape_string($d['own']);
	$e_norm    = $conn->real_escape_string($norm);
	$e_hash    = $conn->real_escape_string(infil_hash($norm));
	$e_speed   = $conn->real_escape_string($speed);
	$tiers     = (int) $tiers;
	$replied   = strtotime($d['created']);
	$e_when    = $replied ? "'" . date('Y-m-d H:i:s', $replied) . "'" : 'NOW()';

	$ok = @$conn->query("INSERT INTO infil_submissions
	        (user_id, target_id, status_id, x_uid, x_handle, raw_text, own_text, norm_text,
	         text_hash, speed, tiers, state, why, tries, replied_at, verified_at, created_at)
	        VALUES ($user_id, $target_id, '$e_sid', '$e_uid', '$e_handle', '$e_raw', '$e_own',
	                '$e_norm', '$e_hash', '$e_speed', $tiers, 'verified', '', 1,
	                $e_when, NOW(), NOW())
	        ON DUPLICATE KEY UPDATE
	          /* status_id IS IN THIS LIST FOR A REASON. There are two unique
	             keys -- one_comment (status_id) and one_per_target (user_id,
	             target_id) -- and a member whose first attempt parked can
	             come back with a DIFFERENT comment on the same target. That
	             conflicts on one_per_target, not on status_id, so without
	             this the row would take the new comment's text while still
	             pointing at the old comment's id. */
	          status_id = '$e_sid',
	          target_id = $target_id, x_uid = '$e_uid', x_handle = '$e_handle',
	          raw_text = '$e_raw', own_text = '$e_own', norm_text = '$e_norm',
	          text_hash = '$e_hash', speed = '$e_speed', tiers = $tiers,
	          state = 'verified', why = '', tries = tries + 1,
	          replied_at = $e_when, verified_at = NOW()");
	if (!$ok) return 0;

	if ((int) $conn->insert_id > 0) return (int) $conn->insert_id;
	/* ON DUPLICATE KEY UPDATE leaves insert_id at 0 on a pure update. */
	$r = @$conn->query("SELECT id FROM infil_submissions WHERE status_id = '$e_sid' LIMIT 1");
	return ($r && ($row = $r->fetch_assoc())) ? (int) $row['id'] : 0;
}

/* ------------------------------------------------------------------ *
 * MISSES
 * ------------------------------------------------------------------ */

/**
 * Close out every target whose main window has passed, costing a tier to
 * members who were connected at the time and never replied.
 *
 * Returns array('targets' => int, 'docked' => int).
 *
 * WHY THIS IS CALLED FROM PAGE LOADS rather than scheduled: nothing in this
 * repo can schedule anything (see rewards.php), and the query is one indexed
 * lookup that returns no rows on almost every call. It is also idempotent --
 * `settled` is the flag, and a settled target is never re-read.
 *
 * ONLY MEMBERS LINKED BEFORE THE TARGET WAS POSTED are docked. Somebody who
 * connected their X account today has not missed anything that happened
 * before they could act on it, and docking them would open their first
 * screen on a penalty.
 */
function infil_settle_due($conn, $now = null) {
	$now   = $now === null ? time() : (int) $now;
	$stamp = date('Y-m-d H:i:s', $now);
	$done  = 0; $docked = 0;

	$r = @$conn->query("SELECT id, posted_at, main_mins FROM infil_targets
	                    WHERE settled = 0
	                      AND DATE_ADD(posted_at, INTERVAL main_mins MINUTE) <= '$stamp'
	                    ORDER BY posted_at ASC LIMIT 20");
	if (!$r) return array('targets' => 0, 'docked' => 0);

	$targets = array();
	while ($row = $r->fetch_assoc()) $targets[] = $row;

	foreach ($targets as $t) {
		$tid     = (int) $t['id'];
		$e_when  = $conn->real_escape_string($t['posted_at']);

		/* Claim it first. If two requests race here the second finds
		   settled = 1 and docks nobody, rather than docking twice. */
		@$conn->query("UPDATE infil_targets SET settled = 1 WHERE id = $tid AND settled = 0");
		if ($conn->affected_rows < 1) continue;
		$done++;

		$q = @$conn->query("SELECT u.id FROM users u
		        LEFT JOIN infil_submissions s
		               ON s.user_id = u.id AND s.target_id = $tid
		        WHERE u.x_id IS NOT NULL AND u.x_id <> ''
		          AND u.x_linked_at IS NOT NULL
		          AND u.x_linked_at <= '$e_when'
		          AND s.id IS NULL");
		if (!$q) continue;
		while ($row = $q->fetch_assoc()) {
			infil_meter_move($conn, (int) $row['id'], INFIL_MISS_COST);
			$docked++;
		}
	}
	return array('targets' => $done, 'docked' => $docked);
}

/* ------------------------------------------------------------------ *
 * WHAT A MEMBER SEES
 * ------------------------------------------------------------------ */

/**
 * Every target with this member's standing against it.
 *
 * One query rather than one per target: the page draws the whole list and an
 * N+1 here would be a query per post for the life of the feature.
 */
function infil_board($conn, $user_id, $now = null) {
	$user_id = (int) $user_id;
	$now     = $now === null ? time() : (int) $now;

	$sql = "SELECT t.*,
	               s.id    AS sub_id,
	               s.state AS sub_state,
	               s.speed AS sub_speed,
	               s.tiers AS sub_tiers,
	               s.why   AS sub_why,
	               (SELECT COUNT(*) FROM infil_submissions x
	                 WHERE x.target_id = t.id AND x.state = 'verified') AS crowd
	        FROM infil_targets t
	        LEFT JOIN infil_submissions s
	               ON s.target_id = t.id AND s.user_id = $user_id
	        ORDER BY t.retired ASC, t.posted_at DESC";

	$out = array();
	$r = @$conn->query($sql);
	if (!$r) return $out;
	while ($row = $r->fetch_assoc()) {
		$posted            = strtotime($row['posted_at']);
		$row['early_ends'] = $posted + ((int) $row['early_mins'] * 60);
		$row['main_ends']  = $posted + ((int) $row['main_mins'] * 60);
		$row['window']     = $now <= $row['early_ends'] ? 'early'
		                   : ($now <= $row['main_ends'] ? 'main' : 'late');
		$row['heat']       = infil_heat($posted, $now);
		$out[] = $row;
	}
	return $out;
}

/** This month's board: who has climbed the most tiers since the 1st. */
function infil_leaderboard($conn, $limit = 25, $month = null) {
	$month = $month === null ? infil_month() : (string) $month;
	$e_m   = $conn->real_escape_string($month);
	$limit = max(1, (int) $limit);

	$sql = "SELECT m.user_id, m.tier, m.bank, m.month_tiers, m.total_tiers,
	               u.username, u.discord_id, u.avatar, u.visibility, u.x_username
	        FROM infil_meter m
	        INNER JOIN users u ON u.id = m.user_id
	        WHERE m.month_key = '$e_m' AND m.month_tiers > 0
	        ORDER BY m.month_tiers DESC, m.tier DESC, m.total_tiers DESC
	        LIMIT $limit";
	$out = array();
	$r = @$conn->query($sql);
	if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}

/** How many submissions are parked waiting on X. The health number. */
function infil_pending_count($conn) {
	$r = @$conn->query("SELECT COUNT(*) n FROM infil_submissions WHERE state = 'pending'");
	return ($r && ($row = $r->fetch_assoc())) ? (int) $row['n'] : 0;
}

/** Recent submissions for the admin table. */
function infil_recent($conn, $limit = 50) {
	$limit = max(1, (int) $limit);
	$sql = "SELECT s.*, u.username, t.status_id AS target_status, t.label AS target_label
	        FROM infil_submissions s
	        INNER JOIN users u ON u.id = s.user_id
	        LEFT JOIN infil_targets t ON t.id = s.target_id
	        ORDER BY s.id DESC LIMIT $limit";
	$out = array();
	$r = @$conn->query($sql);
	if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}

/* ------------------------------------------------------------------ *
 * THE CHANNEL
 * ------------------------------------------------------------------ */

/** The public URL of a comment, which is also the owner's one-click route. */
function infil_comment_url($handle, $status_id) {
	$h = preg_match('/^[A-Za-z0-9_]{1,15}$/', (string) $handle) ? $handle : 'i';
	return 'https://x.com/' . $h . '/status/' . (string) $status_id;
}

/**
 * Announce a NEW TARGET to the Infiltrations channel.
 *
 * THIS IS THE ONE POST THAT HAS TO BE FAST. Everything else here reports
 * something that already happened; this one is a starting gun. X's ranker
 * weights early replies far above late ones -- Earlybird decays engagement
 * on roughly a six hour half-life -- so the minutes between the owner
 * pasting a link and members seeing it are the minutes worth the most.
 *
 * THE WINDOWS ARE DISCORD TIMESTAMPS, not text. <t:unix:R> renders as a
 * live relative countdown in every viewer's own locale and timezone, which
 * is the whole problem with writing "closes in 60 minutes" into a message
 * that people read an hour later. <t:unix:t> gives them the wall clock
 * beside it.
 */
function infil_announce_target($conn, $target) {
	if (!function_exists('skl_discordmsg')) return;
	if (!is_array($target) || empty($target['status_id'])) return;

	$posted = strtotime($target['posted_at']);
	if (!$posted) $posted = time();
	$early  = $posted + ((int) $target['early_mins'] * 60);
	$main   = $posted + ((int) $target['main_mins']  * 60);

	$label = trim((string) $target['label']);
	$url   = 'https://x.com/skulliance/status/' . (string) $target['status_id'];

	$body  = $label !== '' ? '**' . $label . "**\n\n" : '';
	$body .= "Reply in your own words, then paste the link on the Infiltration page.\n\n";
	$body .= '**+2 rungs** until <t:' . $early . ':t> (<t:' . $early . ':R>)' . "\n";
	$body .= '**+1 rung** until <t:' . $main  . ':t> (<t:' . $main  . ':R>)' . "\n";
	$body .= 'After that it still pays a trait, but it will not climb.';

	skl_discordmsg(
		'New target',
		$body,
		'',               /* no image: the embed url carries the post preview */
		$url,
		'infiltrations',  /* -> getInfiltrationsWebhook() */
		'', '6E4AA8'
	);
}

/**
 * Announce a verified infiltration to the Infiltrations channel.
 *
 * ONE POST DOING TWO JOBS, which is the owner's design: members see who is
 * infiltrating and what they won, and he reads the same channel as his
 * worklist, reacting with an emoji to whatever he has answered. Nothing on
 * our side tracks what he has replied to, because an emoji needs no schema.
 *
 * THE LINK IS THE POINT. It goes straight to the comment on X, so answering
 * one is a tap -- and an author reply is weighted 75 against the comment's
 * own 13.5, which makes it the highest-value action in the whole feature.
 *
 * Deferred like every other Discord post: skl_discordmsg() queues and the
 * drain happens after the response, so the member never waits on it.
 */
function infil_announce($conn, $res) {
	if (!function_exists('skl_discordmsg')) return;
	if (!is_array($res) || $res['state'] !== INFIL_OK) return;

	$name = '';
	$uid  = isset($res['user_id']) ? (int) $res['user_id'] : 0;
	if ($uid > 0) {
		$r = @$conn->query("SELECT username FROM users WHERE id = $uid LIMIT 1");
		if ($r && ($row = $r->fetch_assoc())) $name = (string) $row['username'];
	}

	/* THE MEMBER'S COMMENT, not the target post. Linking the target would
	   land the owner on the thread and leave him hunting for the reply he
	   came to answer, which is the job this post exists to remove. */
	$link = infil_comment_url($res['handle'], isset($res['comment_status'])
	                                        ? $res['comment_status'] : '');

	$speed = $res['speed'] === 'early' ? 'inside the first window'
	       : ($res['speed'] === 'main' ? 'inside the window' : 'late');
	$tier  = isset($res['meter']['tier']) ? (int) $res['meter']['tier'] : 0;
	$bank  = isset($res['meter']['bank']) ? (int) $res['meter']['bank'] : 0;

	$body  = '**@' . $res['handle'] . "**\n> " . trim((string) $res['own']) . "\n\n";
	if (is_array($res['trait']) && isset($res['trait']['name'])) {
		$body .= 'Pulled **' . $res['trait']['name'] . '** ('
		       . (string) $res['trait']['tier'] . ")\n";
	}
	$body .= 'Replied ' . $speed . '. Rung **' . $tier . '** of ' . INFIL_MAX_TIER;
	if ($bank > 0) $body .= ' with **' . $bank . '** banked';
	$body .= '.';

	skl_discordmsg(
		'Infiltration' . ($name !== '' ? ' by ' . $name : ''),
		$body,
		'',             /* no image -- the embed's url is the whole point */
		$link,
		'infiltrations',                      /* -> getInfiltrationsWebhook() */
		'', '6E4AA8', null, null, '', $uid
	);
}
