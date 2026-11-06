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

/**
 * The questions. Rotating, and stored with the answer so the public
 * card can show what was actually asked.
 *
 * Each one is a question somebody can answer from their own week. "Why
 * do you play Skulliance" is a question about a product; "what keeps
 * you coming back" is a question about them, and gets a real sentence.
 */
function testimonial_prompts() {
	return array(
		'back'   => 'What keeps you coming back to Skulliance?',
		'tell'   => 'What would you tell someone who is deciding whether to start?',
		'moment' => 'What is the best thing that has happened to you here?',
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

/** Has this player taken a realm recently enough to be asked about it? */
function testimonial_recent_win($conn, $user_id) {
	$user_id = intval($user_id);
	if ($user_id <= 0) return false;
	$h = (int) TESTIMONIAL_WIN_HOURS;
	$r = $conn->query(
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
	if (!testimonial_recent_win($conn, $user_id)) return null;

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
function testimonial_save($conn, $user_id, $prompt_key, $body, $visibility) {
	if (!testimonials_ready($conn)) return 'Testimonials are not switched on yet.';
	$user_id = intval($user_id);
	if ($user_id <= 0) return 'Sign in first.';
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

/** The review queue, oldest first, with who wrote each one. */
function testimonial_pending($conn, $limit = 50) {
	if (!testimonials_ready($conn)) return array();
	$limit = max(1, min(200, intval($limit)));
	$r = $conn->query(
		"SELECT t.*, users.username, users.discord_id, users.avatar
		 FROM testimonials t
		 INNER JOIN users ON users.id = t.user_id
		 WHERE t.status = 'pending' AND t.is_current = 1
		 ORDER BY t.created_date ASC LIMIT $limit");
	$out = array();
	if ($r) while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
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
 * The player's own piece, on their own profile: its state, and the one
 * control they may use without going back through review.
 *
 * Visibility is editable on an approved row because taking your name
 * off something you wrote must not wait on a queue. The TEXT is not:
 * editing it writes a new row at pending, which is what stops an
 * approval being swapped out for something else.
 */
function testimonial_self_panel($conn, $user_id) {
	$t = testimonial_current($conn, $user_id);
	if (!$t) return;
	$state = $t['status'];
	$label = array('pending' => 'waiting to be read', 'approved' => 'live on the site',
	               'rejected' => 'not published');
	echo '<div class="tst-mine">';
	echo '<h3>Your testimonial<span class="tst-state tst-state-' . htmlspecialchars($state) . '">'
	   . htmlspecialchars(isset($label[$state]) ? $label[$state] : $state) . '</span></h3>';
	echo '<blockquote>' . nl2br(htmlspecialchars($t['body'])) . '</blockquote>';
	echo '<form method="post" action="ajax/testimonial.php" class="tst-vis" id="tst-vis-form">';
	echo '<input type="hidden" name="action" value="visibility">';
	foreach (array('named'   => 'Published, with my username and avatar',
	               'private' => 'Withdraw it from the site') as $v => $text) {
		echo '<label><input type="radio" name="visibility" value="' . $v . '"'
		   . ($t['visibility'] === $v ? ' checked' : '') . '> ' . htmlspecialchars($text) . '</label>';
	}
	echo '<button type="submit" class="tst-go">Save</button>';
	echo '<span class="tst-saved" id="tst-saved" hidden>Saved.</span>';
	echo '</form>';
	/* The endpoint answers JSON, so the form is posted by fetch. Left as
	   a plain submit it navigates the player to a page of raw JSON. */
	echo '<script>(function(){var f=document.getElementById("tst-vis-form");if(!f)return;'
	   . 'f.addEventListener("submit",function(e){e.preventDefault();'
	   . 'var d=new URLSearchParams(new FormData(f));'
	   . 'fetch("ajax/testimonial.php",{method:"POST",credentials:"same-origin",'
	   . 'headers:{"Content-Type":"application/x-www-form-urlencoded"},body:d.toString()})'
	   . '.then(function(r){return r.json();}).then(function(j){'
	   . 'var s=document.getElementById("tst-saved");if(s){s.hidden=!j||!j.success;}});});})();</script>';
	echo '</div>';
}
