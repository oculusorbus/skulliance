<?php
/**
 * testimonials-lib.php — players saying why they play, in their words.
 *
 * WHAT IT IS FOR, which decides most of the design. The public map and
 * the onboarding page are both pitches with no voices in them. A
 * testimonial is worth having only if it tells a stranger something a
 * marketing line cannot: why somebody keeps coming back. So the ask is
 * a QUESTION rather than a blank box labelled "testimonial", which
 * reliably produces "its fun", and the modal says plainly what the
 * answer is for.
 *
 * NOTHING PUBLISHES ITSELF. Every submission lands at `pending` and an
 * owner approves it before it reaches the website or Discord. The
 * alternative was considered and rejected: whatever a player types
 * would otherwise appear under the Skulliance name, and a Discord post
 * cannot be unposted.
 *
 * AN EDIT RESETS APPROVAL, which is the non-obvious half of that. A
 * row is never updated in place -- editing writes a NEW row at
 * `pending` and retires the old one -- so somebody cannot have a kind
 * paragraph approved and then quietly swap it for something else.
 * See testimonials-schema.md.
 *
 * INERT WITHOUT ITS TABLES. Every read returns empty and every write
 * is a no-op until the schema is run, so shipping this cannot break
 * the raid screen it hangs off.
 */

/* How long a piece has to be to say anything, and short enough to read
   on a card. Measured in characters because that is what the textarea
   counts. */
if (!defined('TESTIMONIAL_MIN'))        define('TESTIMONIAL_MIN', 60);
if (!defined('TESTIMONIAL_MAX'))        define('TESTIMONIAL_MAX', 420);
/* Three asks, then never again. A modal that will not take no for an
   answer costs more goodwill than a testimonial is worth, and the last
   one says it is the last one so the choice is informed. */
if (!defined('TESTIMONIAL_MAX_NUDGES')) define('TESTIMONIAL_MAX_NUDGES', 3);
if (!defined('TESTIMONIAL_NUDGE_DAYS')) define('TESTIMONIAL_NUDGE_DAYS', 14);
/* Asked after a win, not on arrival: somebody who just took a realm is
   in the right mood and has something concrete to say. This is how
   recent that win has to be. */
if (!defined('TESTIMONIAL_WIN_HOURS'))  define('TESTIMONIAL_WIN_HOURS', 48);
/* And the platform-wide version of the same idea: somebody who has
   done this many things in this many days is playing, whatever they
   play. Counted over the activity log, which every feature writes to. */
if (!defined('TESTIMONIAL_ACTIVE_DAYS'))   define('TESTIMONIAL_ACTIVE_DAYS', 7);
if (!defined('TESTIMONIAL_ACTIVE_EVENTS')) define('TESTIMONIAL_ACTIVE_EVENTS', 8);

/**
 * The question. One, not a rotation.
 *
 * There were three, dealt out by user id, on the theory that variety
 * would produce variety. The founder replaced them with a single ask
 * and moved the other two inside the brief as prompts, which is
 * better: a reader answering "what would you tell someone who is
 * deciding whether to join" is already writing the thing the page
 * needs, and the follow-ups underneath give them somewhere to go if
 * they stall.
 *
 * Still keyed and still stored with the answer, because the public
 * card shows what was asked and a second question one day should not
 * silently relabel every testimonial written under this one.
 */
function testimonial_prompts() {
	return array(
		'join' => 'What would you tell someone who is deciding whether to join?',
	);
}

/** Deterministic per player, so the question does not change under them. */
function testimonial_prompt_for($user_id) {
	$keys = array_keys(testimonial_prompts());
	return $keys[intval($user_id) % count($keys)];
}

/**
 * Do the tables exist? Checked once per request.
 *
 * The feature ships before the schema is run, so every entry point
 * asks this first. Without it the first player to win a raid after
 * deploy gets a fatal error inside the raid screen.
 */
function testimonials_ready($conn) {
	static $ready = null;
	if ($ready !== null) return $ready;
	$ready = false;
	$a = $conn->query("SHOW TABLES LIKE 'testimonials'");
	$b = $conn->query("SHOW TABLES LIKE 'testimonial_nudges'");
	if ($a && $a->num_rows > 0 && $b && $b->num_rows > 0) $ready = true;
	return $ready;
}

/** What this player has standing right now, or null. */
function testimonial_current($conn, $user_id) {
	if (!testimonials_ready($conn)) return null;
	$user_id = intval($user_id);
	if ($user_id <= 0) return null;
	$r = $conn->query("SELECT * FROM testimonials WHERE user_id = $user_id AND is_current = 1 LIMIT 1");
	if (!$r || $r->num_rows === 0) return null;
	return $r->fetch_assoc();
}

/**
 * Has this player been doing things lately, anywhere on the platform?
 *
 * THIS IS NOT A REALMS FEATURE. The first cut asked for a realm and a
 * won raid, because the modal was built onto the raid screen and the
 * raid screen was where the good moment was. That quietly made the
 * whole thing a Realms feature: somebody who plays the card games
 * every night, runs missions and never touches Realms could not be
 * asked, and they have as much to say as anybody.
 *
 * The `activity` table is the platform-wide answer. Every announced
 * event writes a row through activity_log() -- missions, battles,
 * purchases, streaks, raids -- so a count over a few days is "this
 * person is playing", whatever they play.
 *
 * IT FALLS BACK RATHER THAN FAILING CLOSED. If the activity table is
 * not there, the realms check is used instead, so the feature works
 * on an install where one of the two was never deployed. Both missing
 * means nobody is asked, which is the right silence.
 */
function testimonial_recently_active($conn, $user_id) {
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	$d = (int) TESTIMONIAL_ACTIVE_DAYS;
	$n = (int) TESTIMONIAL_ACTIVE_EVENTS;
	$r = @$conn->query(
		"SELECT COUNT(*) AS n FROM activity
		 WHERE user_id = $user_id AND created_at >= (NOW() - INTERVAL $d DAY)");
	if ($r) {
		$row = $r->fetch_assoc();
		return ((int) $row['n']) >= $n;
	}
	/* No activity table on this install: fall back to the signal the
	   feature started with rather than asking nobody. */
	return testimonial_recent_win($conn, $user_id);
}

/** The original signal, kept as the fallback. */
function testimonial_recent_win($conn, $user_id) {
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	$h = (int) TESTIMONIAL_WIN_HOURS;
	$r = @$conn->query(
		"SELECT 1 FROM raids r
		 INNER JOIN realms ON realms.id = r.offense_id
		 WHERE realms.user_id = $user_id AND r.outcome = '1'
		   AND r.created_date >= (NOW() - INTERVAL $h HOUR) LIMIT 1");
	return (bool) ($r && $r->num_rows > 0);
}

/**
 * Should this player be offered the modal, and is this the last time?
 *
 * Returns null when they should not be asked at all, so a caller can
 * emit nothing rather than emitting a disabled widget.
 *
 * ELIGIBILITY IS ABOUT INVESTMENT, NOT AGE. Signup date would catch
 * somebody who registered months ago and never played; a realm plus a
 * won raid is somebody with a week worth describing.
 */
function testimonial_offer($conn, $user_id) {
	if (!testimonials_ready($conn)) return null;
	$user_id = intval($user_id);
	if ($user_id <= 0) return null;
	if (testimonial_current($conn, $user_id)) return null;   /* already said their piece */

	$n = $conn->query("SELECT shown_count, last_shown, muted FROM testimonial_nudges WHERE user_id = $user_id LIMIT 1");
	$shown = 0; $muted = 0; $last = null;
	if ($n && $n->num_rows > 0) {
		$row = $n->fetch_assoc();
		$shown = (int) $row['shown_count'];
		$muted = (int) $row['muted'];
		$last  = $row['last_shown'];
	}
	if ($muted) return null;
	if ($shown >= TESTIMONIAL_MAX_NUDGES) return null;
	if ($last !== null && strtotime($last) > time() - (TESTIMONIAL_NUDGE_DAYS * 86400)) return null;
	if (!testimonial_recently_active($conn, $user_id)) return null;

	$key = testimonial_prompt_for($user_id);
	$prompts = testimonial_prompts();
	return array(
		'prompt_key' => $key,
		'prompt'     => $prompts[$key],
		/* The last ask says so, so "not now" is a decision rather than
		   a postponement somebody assumes will come round again. */
		'last_call'  => ($shown + 1) >= TESTIMONIAL_MAX_NUDGES,
		'min'        => (int) TESTIMONIAL_MIN,
		'max'        => (int) TESTIMONIAL_MAX,
	);
}

/**
 * The same decision, taken at most once every half hour per session.
 *
 * WHY IT HAS TO BE CACHED. The ask belongs on every page a player can
 * land on after finishing something, which is header.php -- 66 pages.
 * testimonial_offer() is three queries, and three queries on every
 * page view of the whole platform to find out that somebody was asked
 * last Tuesday is not a trade worth making.
 *
 * A "no" is cached for half an hour. A "yes" is kept until it is acted
 * on, because the answer cannot change in the player's favour while
 * they sit on it, and the endpoint clears the cache the moment they
 * send or dismiss.
 */
function testimonial_offer_cached($conn, $user_id) {
	$user_id = intval($user_id);
	if ($user_id <= 0) return null;
	if (isset($_SESSION['tst_offer']) && is_array($_SESSION['tst_offer'])) {
		$c = $_SESSION['tst_offer'];
		if (isset($c['at']) && ($c['offer'] !== null || $c['at'] > time() - 1800)) {
			return $c['offer'];
		}
	}
	$offer = testimonial_offer($conn, $user_id);
	$_SESSION['tst_offer'] = array('offer' => $offer, 'at' => time());
	return $offer;
}

/** Forget the cached decision: they have just acted on it. */
function testimonial_offer_forget() {
	unset($_SESSION['tst_offer']);
}

/** Record that we asked, so the next ask is a fortnight away. */
function testimonial_mark_shown($conn, $user_id) {
	if (!testimonials_ready($conn)) return false;
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	return (bool) $conn->query(
		"INSERT INTO testimonial_nudges (user_id, shown_count, last_shown, muted)
		 VALUES ($user_id, 1, NOW(), 0)
		 ON DUPLICATE KEY UPDATE shown_count = shown_count + 1, last_shown = NOW()");
}

/** Not now, or never again. */
function testimonial_dismiss($conn, $user_id, $never = false) {
	if (!testimonials_ready($conn)) return false;
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	$m = $never ? 1 : 0;
	return (bool) $conn->query(
		"INSERT INTO testimonial_nudges (user_id, shown_count, last_shown, muted)
		 VALUES ($user_id, 1, NOW(), $m)
		 ON DUPLICATE KEY UPDATE last_shown = NOW(), muted = GREATEST(muted, $m)");
}

/** '' when the text will do, otherwise why not, in words a player reads. */
function testimonial_validate($body) {
	$body = trim((string) $body);
	$len  = function_exists('mb_strlen') ? mb_strlen($body) : strlen($body);
	if ($len < TESTIMONIAL_MIN) {
		return 'A line or two more would help. ' . TESTIMONIAL_MIN . ' characters minimum, you have ' . $len . '.';
	}
	if ($len > TESTIMONIAL_MAX) {
		return 'That is longer than a card can hold. ' . TESTIMONIAL_MAX . ' characters maximum, you have ' . $len . '.';
	}
	/* A link in a testimonial is somebody advertising, every time. */
	if (preg_match('~https?://|www\.|discord\.gg~i', $body)) {
		return 'Links are not carried in testimonials. Say it in your own words instead.';
	}
	return '';
}

/**
 * TWO STATES, NOT THREE. You are in, or you have withdrawn.
 *
 * There was an 'anonymous' option. It went because it is the worst of
 * both: an unattributed quote is weak proof -- the handle and the face
 * are most of why a stranger believes it -- and it does not buy the
 * privacy it appears to, because the avatar alone is MORE identifying
 * than the username. avatar_url() builds
 * cdn.discordapp.com/avatars/<discord id>/..., so the image URL
 * carries the permanent Discord snowflake, while a username is
 * something its owner can change this afternoon.
 *
 * None of this is new exposure in any case: profile.php serves any
 * username and avatar without a login, and the public map and the
 * onboarding showcase both render them.
 *
 * 'private' is not an option at submit time -- nobody writes a
 * testimonial in order to hide it. It is the withdrawal, reachable
 * from your own profile afterwards.
 */
function testimonial_visibilities() {
	return array('named', 'private');
}

/**
 * Write one. Always a new row: the previous current row is retired
 * rather than overwritten, which is what keeps the archive and what
 * forces a re-review after an edit.
 */
function testimonial_save($conn, $user_id, $prompt_key, $body, $visibility, $consent = false) {
	if (!testimonials_ready($conn)) return 'Testimonials are not switched on yet.';
	$user_id = intval($user_id);
	if ($user_id <= 0) return 'Sign in first.';
	/*
	 * THE CONFIRMATION IS CHECKED HERE, NOT ONLY IN THE MODAL.
	 *
	 * A tick box is a user interface; anything can post to the
	 * endpoint without it. Publishing somebody's username and avatar
	 * because a checkbox was missing from a request is exactly the
	 * mistake worth refusing to make, so the save is the thing that
	 * requires it.
	 *
	 * The row's created_date is the record of when they agreed: it
	 * cannot exist without this having been true, so a separate
	 * consent column would record nothing the row does not.
	 */
	if (!$consent) return 'Tick the box to confirm your username and avatar can be published.';
	$err = testimonial_validate($body);
	if ($err !== '') return $err;
	if (!in_array($visibility, testimonial_visibilities(), true)) $visibility = 'named';
	$prompts = testimonial_prompts();
	if (!isset($prompts[$prompt_key])) $prompt_key = testimonial_prompt_for($user_id);

	$b = $conn->real_escape_string(trim((string) $body));
	$p = $conn->real_escape_string($prompt_key);
	$v = $conn->real_escape_string($visibility);
	$conn->query("UPDATE testimonials SET is_current = 0 WHERE user_id = $user_id AND is_current = 1");
	$ok = $conn->query(
		"INSERT INTO testimonials (user_id, prompt_key, body, visibility, status, is_current, created_date)
		 VALUES ($user_id, '$p', '$b', '$v', 'pending', 1, NOW())");
	if (!$ok) return 'That did not save. Try again in a moment.';
	/* Writing one also stops the asking, whatever happens at review. */
	$conn->query(
		"INSERT INTO testimonial_nudges (user_id, shown_count, last_shown, muted)
		 VALUES ($user_id, 1, NOW(), 1)
		 ON DUPLICATE KEY UPDATE last_shown = NOW(), muted = 1");
	return '';
}

/**
 * Change who it is attached to, without rewriting it.
 *
 * Visibility is the one field a player may change on an APPROVED row
 * without sending it back for review: it does not alter a word of what
 * was approved, and somebody who wants their name off something must
 * not have to wait on a queue for that.
 */
function testimonial_set_visibility($conn, $user_id, $visibility) {
	if (!testimonials_ready($conn)) return false;
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	if (!in_array($visibility, testimonial_visibilities(), true)) return false;
	$v = $conn->real_escape_string($visibility);
	return (bool) $conn->query(
		"UPDATE testimonials SET visibility = '$v' WHERE user_id = $user_id AND is_current = 1");
}

/**
 * The queue for one status, with who wrote each one.
 *
 * ANY STATUS, BECAUSE A VERDICT IS NOT FINAL. The first cut listed
 * `pending` only and rendered the approved ones through the public
 * shaping function, which drops the row id -- so once a testimonial
 * had been judged there was no control left anywhere to judge it
 * again. Rejecting something that turned out to be fine, or pulling
 * something that should not have gone out, both meant editing the
 * database by hand.
 */
function testimonial_queue($conn, $status = 'pending', $limit = 50) {
	if (!testimonials_ready($conn)) return array();
	$allowed = array('pending', 'approved', 'rejected');
	if (!in_array($status, $allowed, true)) $status = 'pending';
	$limit = max(1, min(200, intval($limit)));
	/* Pending oldest first -- it is a queue. Judged ones newest first,
	   because that is the one you just changed your mind about. */
	$order = ($status === 'pending') ? 'ASC' : 'DESC';
	$r = $conn->query(
		"SELECT t.*, users.username, users.discord_id, users.avatar
		 FROM testimonials t
		 INNER JOIN users ON users.id = t.user_id
		 WHERE t.status = '" . $conn->real_escape_string($status) . "' AND t.is_current = 1
		 ORDER BY t.created_date $order LIMIT $limit");
	$out = array();
	if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}

/** Still here: the pending queue is what most callers want. */
function testimonial_pending($conn, $limit = 50) {
	return testimonial_queue($conn, 'pending', $limit);
}

/** Everything ever written by one player, newest first. The archive. */
function testimonial_history($conn, $user_id, $limit = 20) {
	if (!testimonials_ready($conn)) return array();
	$user_id = intval($user_id);
	$limit = max(1, min(100, intval($limit)));
	$r = $conn->query(
		"SELECT * FROM testimonials WHERE user_id = $user_id
		 ORDER BY created_date DESC LIMIT $limit");
	$out = array();
	if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}

/** Approve or reject one. Returns the row, so a caller can announce it. */
function testimonial_review($conn, $id, $approve, $admin_id, $note = '') {
	if (!testimonials_ready($conn)) return null;
	$id = intval($id);
	$admin_id = intval($admin_id);
	if ($id <= 0) return null;
	$status = $approve ? 'approved' : 'rejected';
	$n = $conn->real_escape_string(substr((string) $note, 0, 255));
	$conn->query(
		"UPDATE testimonials SET status = '$status', reviewed_date = NOW(),
		        reviewed_by = $admin_id, review_note = '$n' WHERE id = $id");
	$r = $conn->query(
		"SELECT t.*, users.username, users.discord_id, users.avatar
		 FROM testimonials t INNER JOIN users ON users.id = t.user_id
		 WHERE t.id = $id LIMIT 1");
	return ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
}

/**
 * Announce an approved testimonial to the archive channel.
 *
 * OUT HERE RATHER THAN IN THE ADMIN PAGE, because a rule nothing can
 * call is a rule nothing can test. Buried in admin-testimonials.php,
 * "approving posts to Discord" and "approving twice does not post
 * twice" were both provable only by reading the file, and a harness
 * that greps for a function name passes just as happily on
 * `false && skl_discordmsg(...)`.
 *
 * Returns true only if it actually posted. The flag is set before the
 * post is attempted on purpose: a webhook that times out after
 * delivering must not leave the row looking unposted, because the
 * retry is what double-posts.
 */
function testimonial_announce($conn, $row) {
	if (!testimonials_ready($conn)) return false;
	if (!$row || !isset($row['id'])) return false;
	if ($row['status'] !== 'approved') return false;        /* approval is the trigger */
	if ($row['visibility'] === 'private') return false;      /* they asked us not to */
	if ((int) $row['discord_posted'] !== 0) return false;    /* already out there */
	/*
	 * LOAD THE POSTER IF THE CALLER HAS NOT.
	 *
	 * skl_discordmsg() is defined in webhooks.php, which is pulled in
	 * by verify.php -- and admin-testimonials.php has no reason to
	 * include verify.php, so it did not. function_exists() was false,
	 * this returned early, and an approval published to the site and
	 * posted nothing, silently, exactly like a missing credential.
	 *
	 * Fixed here rather than only in the admin page so that the next
	 * caller cannot repeat it. webhooks.php is safe to include twice
	 * and loads its own credentials.
	 */
	if (!function_exists('skl_discordmsg') && is_file(__DIR__ . '/webhooks.php')) {
		include_once __DIR__ . '/webhooks.php';
	}
	if (!function_exists('skl_discordmsg')) return false;

	$conn->query("UPDATE testimonials SET discord_posted = 1 WHERE id = " . (int) $row['id']);
	$prompts = testimonial_prompts();
	$asked   = isset($prompts[$row['prompt_key']]) ? $prompts[$row['prompt_key']] : '';
	$who     = isset($row['username']) ? $row['username'] : 'A Skulliance player';
	skl_discordmsg($who, '"' . $row['body'] . '"', '', '', 'testimonials', '',
	               '2ECC71', null, $asked, '', (int) $row['user_id'], 0);
	return true;
}

/**
 * The ones a stranger may see: approved, current, and not set private.
 *
 * READ LIVE, never cached into a page. A player who sets their piece to
 * private has to disappear from the website on the next load, and a
 * copy pasted into a template would outlive the decision.
 */
function testimonial_public($conn, $limit = 12) {
	if (!testimonials_ready($conn)) return array();
	$limit = max(1, min(50, intval($limit)));
	$r = $conn->query(
		"SELECT t.body, t.prompt_key, t.visibility, t.created_date,
		        users.username, users.discord_id, users.avatar,
		        projects.name AS faction_name
		 FROM testimonials t
		 INNER JOIN users ON users.id = t.user_id
		 LEFT JOIN realms ON realms.user_id = users.id AND realms.active = 1
		 LEFT JOIN projects ON projects.id = realms.project_id
		 WHERE t.status = 'approved' AND t.is_current = 1 AND t.visibility <> 'private'
		 GROUP BY t.id
		 ORDER BY t.created_date DESC LIMIT $limit");
	$out = array();
	$prompts = testimonial_prompts();
	/* Anything that reaches here is 'named': the query excludes
	   'private' and there is no third state. */
	if ($r) while ($row = $r->fetch_assoc()) {
		$out[] = array(
			'body'    => $row['body'],
			'asked'   => isset($prompts[$row['prompt_key']]) ? $prompts[$row['prompt_key']] : '',
			'name'    => $row['username'],
			'avatar'  => function_exists('avatar_url')
				? avatar_url($row['discord_id'], $row['avatar']) : '',
			'faction' => (string) $row['faction_name'],
			'when'    => $row['created_date'],
		);
	}
	return $out;
}

/**
 * The X intent URL.
 *
 * IT HAS TO MENTION THE ACCOUNT. The whole point is that Skulliance can
 * retweet it, and an untagged post is one the account never sees. The
 * link is the public map rather than the front page: it is the best
 * thing there is to look at, it needs no login, and the person sharing
 * is on it.
 */
function testimonial_share_url($body) {
	$body = trim((string) $body);
	/* Intent text is capped; trim on a word so it does not end mid-syllable. */
	$room = 180;
	if (function_exists('mb_strlen') ? mb_strlen($body) > $room : strlen($body) > $room) {
		$cut = substr($body, 0, $room);
		$sp  = strrpos($cut, ' ');
		$body = rtrim($sp ? substr($cut, 0, $sp) : $cut) . '...';
	}
	$text = '"' . $body . '" @skulliance';
	return 'https://x.com/intent/tweet?text=' . rawurlencode($text)
	     . '&url=' . rawurlencode('https://skulliance.io/staking/realms-map.php');
}

/**
 * The public wall, as markup.
 *
 * Here rather than in each page because three surfaces show it and a
 * second copy of this loop would drift. Prints nothing at all when
 * there is nothing approved, so a page can call it unconditionally and
 * get no empty heading on a quiet week.
 */
function testimonial_wall($conn, $limit = 6, $heading = 'Why people stay') {
	$rows = testimonial_public($conn, $limit);
	if (!$rows) return;
	echo '<div class="tst-section">';
	if ($heading !== '') echo '<h2>' . htmlspecialchars($heading) . '</h2>';
	echo '<div class="tst-wall">';
	foreach ($rows as $r) {
		echo '<figure class="tst-quote">';
		if ($r['asked'] !== '') echo '<p class="tst-asked">' . htmlspecialchars($r['asked']) . '</p>';
		echo '<blockquote>' . nl2br(htmlspecialchars($r['body'])) . '</blockquote>';
		echo '<figcaption class="tst-by">';
		if ($r['avatar'] !== '') {
			echo '<img src="' . htmlspecialchars($r['avatar']) . '" alt="" '
			   . 'onerror="this.src=\'icons/skull.png\'">';
		}
		echo '<b>' . htmlspecialchars($r['name']) . '</b>';
		if ($r['faction'] !== '') echo '<span>' . htmlspecialchars($r['faction']) . '</span>';
		echo '</figcaption></figure>';
	}
	echo '</div></div>';
}

/**
 * Your own testimonial, on your own profile: what happened to it, and
 * the two things you may do about it.
 *
 * REJECTION HAS TO SAY SOMETHING. Before this, a rejected piece sat
 * here reading "not published" with no reason and no way forward,
 * while the modal -- which refuses to ask anybody who already has a
 * current row -- never offered again. The review note was written,
 * stored, and shown to nobody. That is not a decision, it is a bin.
 *
 * So a rejection is an invitation to try again: the note is shown, and
 * the text is editable. Rewriting writes a NEW row at pending and
 * retires this one, which is the same rule that stops an approved
 * piece being swapped, working in the player's favour for once.
 *
 * VISIBILITY IS THE OTHER CONTROL AND IT IS INSTANT. It changes no
 * word of what was approved, and somebody taking their name off
 * something must not wait in a queue to do it.
 */
function testimonial_self_panel($conn, $user_id) {
	$t = testimonial_current($conn, $user_id);
	if (!$t) return;
	$state = $t['status'];
	$label = array('pending'  => 'waiting to be read',
	               'approved' => 'live on the site',
	               'rejected' => 'not published');
	echo '<div class="tst-mine">';
	echo '<h3>Your testimonial<span class="tst-state tst-state-' . htmlspecialchars($state) . '">'
	   . htmlspecialchars(isset($label[$state]) ? $label[$state] : $state) . '</span></h3>';

	if ($state === 'rejected') {
		echo '<p class="tst-reject">This one was not published.';
		if (trim((string) $t['review_note']) !== '') {
			echo ' <b>' . htmlspecialchars($t['review_note']) . '</b>';
		}
		echo ' You are welcome to rewrite it below and send it again.</p>';
	}

	/* THE TEXT, EDITABLE WHATEVER ITS STATE. Changing it sends it back
	   to be read again, which the note under the box says plainly so
	   nobody edits a live testimonial expecting it to stay live. */
	/*
	 * THE SHARE, ONCE IT IS ACTUALLY PUBLISHED.
	 *
	 * The thank-you card offers this the moment somebody submits and
	 * then it is gone for good: close that card and there is no link
	 * anywhere. Worse, it offers while the piece is still pending, so
	 * somebody can post a quote that is then rejected.
	 *
	 * Approved only, and here, where somebody who meant to share it
	 * and did not can come back and find it.
	 */
	if ($state === 'approved') {
		echo '<a class="tst-x" target="_blank" rel="noopener" href="'
		   . htmlspecialchars(testimonial_share_url($t['body'])) . '">Share it on X</a>';
	}

	echo '<form class="tst-edit" id="tst-edit-form">';
	echo '<input type="hidden" name="action" value="save">';
	echo '<input type="hidden" name="prompt_key" value="' . htmlspecialchars($t['prompt_key']) . '">';
	echo '<textarea name="body" maxlength="' . (int) TESTIMONIAL_MAX . '">'
	   . htmlspecialchars($t['body']) . '</textarea>';
	echo '<label class="tst-consent"><input type="checkbox" name="consent" value="1">'
	   . '<span>Publish this with my username and avatar.</span></label>';
	echo '<p class="tst-fine">Changing the words sends it back to be read again'
	   . ($state === 'approved' ? ', so it will come off the site until it is approved' : '')
	   . '. Your previous versions are kept.</p>';
	echo '<button type="submit" class="tst-go">Send the new version</button>';
	echo '<span class="tst-saved" id="tst-edit-said" hidden></span>';
	echo '</form>';

	echo '<form class="tst-vis" id="tst-vis-form">';
	echo '<input type="hidden" name="action" value="visibility">';
	foreach (array('named'   => 'Published, with my username and avatar',
	               'private' => 'Withdraw it from the site') as $v => $text) {
		echo '<label><input type="radio" name="visibility" value="' . $v . '"'
		   . ($t['visibility'] === $v ? ' checked' : '') . '> ' . htmlspecialchars($text) . '</label>';
	}
	echo '<button type="submit" class="tst-go">Save</button>';
	echo '<span class="tst-saved" id="tst-saved" hidden>Saved.</span>';
	echo '</form>';

	/* Both forms post to an endpoint that answers JSON, so neither is
	   a plain submit: left as one the player lands on a page of raw
	   JSON with no way back. */
	echo '<script>(function(){'
	   . 'function wire(id,said,done){var f=document.getElementById(id);if(!f)return;'
	   . 'f.addEventListener("submit",function(e){e.preventDefault();'
	   . 'var d=new URLSearchParams(new FormData(f));'
	   . 'fetch("ajax/testimonial.php",{method:"POST",credentials:"same-origin",'
	   . 'headers:{"Content-Type":"application/x-www-form-urlencoded"},body:d.toString()})'
	   . '.then(function(r){return r.json();}).then(function(j){var s=document.getElementById(said);'
	   . 'if(!s)return;s.hidden=false;s.textContent=(j&&j.success)?done:((j&&j.error)||"That did not save.");'
	   . 'if(j&&j.success&&id==="tst-edit-form")setTimeout(function(){location.reload();},900);});});}'
	   . 'wire("tst-vis-form","tst-saved","Saved.");'
	   . 'wire("tst-edit-form","tst-edit-said","Sent to be read again.");'
	   . '})();</script>';
	echo '</div>';
}
