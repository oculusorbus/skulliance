<?PHP
/*
 * ACTIVITY FEED -- the platform's own copy of what Skull Bot announces.
 *
 * WHY THIS IS A LOG AND NOT A QUERY. The obvious way to build a "recent
 * activity" page is to read the tables the events live in -- missions run,
 * battles fought, store purchases -- and merge them by date. That does not
 * work here, and the reason is worth keeping: a large share of what Skull
 * Bot announces is assembled at announce time and stored nowhere. A monthly
 * leaderboard standing, a raid outcome summary, a trait-drop line that names
 * the roll -- those exist as a sentence in a Discord embed and as nothing
 * else. Reconstructing them would mean re-deriving each one from whatever
 * partial state survived, differently for every feature, forever.
 *
 * So the feed is written where the sentence already exists: discordmsg()
 * hands us the embed it just posted. One writer, 100+ call sites covered,
 * and a new feature that announces anything is in the feed the day it ships
 * without touching this file.
 *
 * The cost is that history starts empty. There is no backfill and there
 * cannot be a faithful one, for the reason above.
 *
 * TWO RULES THIS FILE MUST NOT BREAK:
 *
 * 1. LOGGING CAN NEVER BREAK AN ANNOUNCE. By the time discordmsg() runs, the
 *    thing being announced has already happened -- the battle is resolved,
 *    the CARBON is paid, the trait is in the ledger. A warning from here
 *    would print into whatever page or AJAX response carried that result
 *    back to the player (display_errors is ON platform-wide), and a fatal
 *    would take the response down entirely. Everything below is wrapped and
 *    silenced, and a missing table simply means no rows -- the feature is
 *    inert until the table exists rather than noisy.
 *
 * 2. ONLY WHAT DISCORD ACTUALLY RECEIVED. The write happens after a 2xx, not
 *    before the post. An unconfigured webhook, a 401 from a rotated URL, a
 *    400 from an over-length embed -- none of those reached anybody, so none
 *    of them belong in a feed whose whole premise is "this was announced".
 *
 * NO mysqlnd on this server: mysqli_stmt::get_result() is a FATAL here, so
 * every read below is $conn->query() with intval()/real_escape_string().
 * See multichain.md for the same constraint written up at length.
 */

/* Days of history kept. Pruned by activity_prune(), called from the nightly
   job -- see verify.php. The feed is a "what's happening" wall, not an
   archive: the tables behind each event remain the record of it. */
define('ACTIVITY_RETAIN_DAYS', 120);

/* How many cards one page of the wall shows. */
define('ACTIVITY_PAGE_SIZE', 60);

/*
 * The acting user, for the "My activity" filter.
 *
 * Resolved from the session rather than threaded through 100+ discordmsg()
 * call sites. That is accurate for the overwhelming majority of announces
 * because of how they are triggered: a player runs a mission, wins a battle,
 * buys from the store, and the announce fires inside that player's own
 * request. Cron-driven announces (nightly verify, monthly leaderboards) have
 * no session at all and correctly record 0.
 *
 * It is NOT accurate for an announce made about player X from inside an
 * admin's request. Those are rare, and the consequence is bounded: one row
 * missing from one player's "Mine" filter. A caller that cares can pass
 * $actor_id to discordmsg() explicitly and override this.
 *
 * THE SECOND PLAYER IS NEVER GUESSABLE. A DHC Arena result is one
 * announcement about two players, and only one of them owns the request. The
 * opponent has to be passed in: discordmsg()'s $actor_id2, which lands in
 * activity.user_id2 and makes the row match BOTH players' "Mine" filter -- the
 * defender sees the battle they lost. A two-sided announce that doesn't pass it
 * still works; it just only shows up for the player who triggered it.
 */
function activity_actor() {
	if (!isset($_SESSION['userData']['user_id'])) return 0;
	$uid = (int) $_SESSION['userData']['user_id'];
	return $uid > 0 ? $uid : 0;
}

/*
 * Record one announcement. Called from discordmsg() AFTER a successful post.
 *
 * Returns true if a row was written, false otherwise -- and false is a
 * perfectly normal outcome (no table yet, no $conn in this context). The
 * caller ignores it; the return exists so the harness can assert on it.
 */
function activity_log($channel, $title, $description, $url, $imageurl, $thumbnail, $color, $author, $footer, $user_id = 0, $user_id2 = 0) {
	global $conn;

	try {
		if (!isset($conn) || !($conn instanceof mysqli)) return false;

		/* Discord renders an embed with no title and no description as an
		   empty box; a card with neither is equally useless, and a few
		   internal posts are exactly that. Nothing to mirror. */
		$title       = trim((string) $title);
		$description = trim((string) $description);
		if ($title === '' && $description === '') return false;

		$author_name = '';
		$author_icon = '';
		if (is_array($author)) {
			if (isset($author['name']))     $author_name = (string) $author['name'];
			if (isset($author['icon_url'])) $author_icon = (string) $author['icon_url'];
		}
		$footer_text = (is_array($footer) && isset($footer['text'])) ? (string) $footer['text'] : '';

		/* Column widths mirror the schema. Truncating here rather than
		   letting MySQL do it keeps the behaviour identical whether or not
		   the server runs in strict mode -- in strict mode an over-length
		   value is an ERROR, not a truncation, which would mean losing the
		   row for a long image URL. */
		$cols = array(
			'channel'     => array($channel,      32),
			'title'       => array($title,        255),
			'description' => array($description,  4000),
			'url'         => array($url,          512),
			'image_url'   => array($imageurl,     512),
			'thumbnail'   => array($thumbnail,    512),
			'color'       => array($color,        8),
			'author_name' => array($author_name,  190),
			'author_icon' => array($author_icon,  512),
			'footer_text' => array($footer_text,  255),
		);
		$esc = array();
		foreach ($cols as $k => $cv) {
			$v = (string) $cv[0];
			if (mb_strlen($v) > $cv[1]) $v = mb_substr($v, 0, $cv[1]);
			$esc[$k] = $conn->real_escape_string($v);
		}

		$uid  = (int) $user_id;
		$uid2 = (int) $user_id2;
		if ($uid  < 0) $uid  = 0;
		if ($uid2 < 0) $uid2 = 0;
		/* Same player on both sides is a caller slip, not a two-sided event.
		   Left in place it would be harmless for the OR filter but would make
		   "who else was involved" lie on the card. */
		if ($uid2 === $uid) $uid2 = 0;

		$sql = "INSERT INTO activity
			(channel, title, description, url, image_url, thumbnail, color,
			 author_name, author_icon, footer_text, user_id, user_id2, created_at)
			VALUES (
			 '".$esc['channel']."', '".$esc['title']."', '".$esc['description']."',
			 '".$esc['url']."', '".$esc['image_url']."', '".$esc['thumbnail']."',
			 '".$esc['color']."', '".$esc['author_name']."', '".$esc['author_icon']."',
			 '".$esc['footer_text']."', ".$uid.", ".$uid2.", NOW())";

		/* @ because the table may not exist yet: mysqli_report is OFF
		   platform-wide so query() returns false rather than throwing, but
		   display_errors is ON and a warning would still print into the
		   response that carried the player's result back to them. */
		return @$conn->query($sql) ? true : false;

	} catch (\Throwable $e) {
		/* Deliberately total. See rule 1 in the header. */
		return false;
	}
}

/*
 * One page of the wall, newest first.
 *
 * $channel '' means every channel. $scope narrows to one player -- see the
 * three-scope note in the body. Ordered and paged by id, not created_at: id is the
 * insertion order, it is unique, and two announces inside the same second
 * are common enough (a leaderboard run posts a dozen) that a created_at sort
 * would shuffle them between pages.
 */
function activity_recent($conn, $limit = ACTIVITY_PAGE_SIZE, $before_id = 0, $channel = '', $user_id = 0, $scope = 'all') {
	$out = array();
	if (!($conn instanceof mysqli)) return $out;

	$limit = (int) $limit;
	if ($limit < 1)   $limit = 1;
	if ($limit > 200) $limit = 200;

	$where = array();
	if ((int) $before_id > 0) $where[] = "id < " . (int) $before_id;
	if ($channel !== '')      $where[] = "channel = '" . $conn->real_escape_string($channel) . "'";
	/*
	 * THREE SCOPES, AND THE THIRD IS NOT THE SECOND.
	 *
	 *   all        -- the whole platform.
	 *   mine       -- things I did. user_id only.
	 *   involving  -- that, plus things done TO me. user_id OR user_id2.
	 *
	 * A DHC Arena result is the case that forces the split: the attacker
	 * owns the request, so the battle is "mine" for them. The defender never
	 * made a request at all and appears only in user_id2 -- a battle fought
	 * against them is something they were involved in, not something they
	 * did, and collapsing the two would put losses they never initiated in
	 * their own activity list.
	 */
	$uid = (int) $user_id;
	if ($uid > 0) {
		if ($scope === 'mine') {
			$where[] = "user_id = " . $uid;
		} else if ($scope === 'involving') {
			$where[] = "(user_id = " . $uid . " OR user_id2 = " . $uid . ")";
		}
		/* Any other $scope -- including 'all' -- means no user filter. */
	}
	$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

	$r = @$conn->query("SELECT id, channel, title, description, url, image_url,
		thumbnail, color, author_name, author_icon, footer_text, user_id, user_id2, created_at
		FROM activity" . $w . " ORDER BY id DESC LIMIT " . $limit);
	if (!$r) return $out;
	while ($row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}

/*
 * The channels that actually have rows, for the filter dropdown.
 *
 * Derived from the data rather than from discordmsg()'s hard-coded channel
 * list on purpose: that list carries dead channels (general, member, elite,
 * innercircle no longer post anything) and channels whose webhook credential
 * was never added, and offering a filter that can only ever return nothing
 * is worse than not offering it.
 */
function activity_channels($conn) {
	$out = array();
	if (!($conn instanceof mysqli)) return $out;
	$r = @$conn->query("SELECT channel, COUNT(*) AS n FROM activity
		WHERE channel != '' GROUP BY channel ORDER BY channel ASC");
	if (!$r) return $out;
	while ($row = $r->fetch_assoc()) $out[$row['channel']] = (int) $row['n'];
	return $out;
}

/* Drop anything past the retention window. Returns rows deleted, or -1 if
   the delete could not run at all (no table, no connection). */
function activity_prune($conn, $days = ACTIVITY_RETAIN_DAYS) {
	if (!($conn instanceof mysqli)) return -1;
	$days = (int) $days;
	if ($days < 1) $days = 1;
	$ok = @$conn->query("DELETE FROM activity
		WHERE created_at < DATE_SUB(NOW(), INTERVAL " . $days . " DAY)");
	if (!$ok) return -1;
	return (int) $conn->affected_rows;
}

/*
 * Human label for a channel. The raw values are webhook keys, not words a
 * player has ever seen -- "dhcfighters", "cryptconquest", "dailyrewards".
 * Anything not listed falls back to a title-cased version of the key, so a
 * new channel reads acceptably on day one without an edit here.
 */
function activity_channel_label($channel) {
	static $map = array(
		'realms'        => 'Realms',
		'raids'         => 'Raids',
		'dailyrewards'  => 'Daily Rewards',
		'missions'      => 'Missions',
		'skullswap'     => 'Skull Swap',
		'monstrocity'   => 'Monstrocity',
		'bossbattles'   => 'Boss Battles',
		'store'         => 'Store',
		'auctions'      => 'Auctions',
		'raffles'       => 'Raffles',
		'delegations'   => 'Delegations',
		'gauntlet'      => 'Gauntlets',
		'cryptcrawl'    => 'Crypt Crawl',
		'cryptconquest' => 'Crypt Conquest',
		'obscura'       => 'Obscura',
		'guardians'     => 'Realm Guardians',
		'dhcfighters'   => 'DHC Fighters',
		'dhcarena'      => 'DHC Arena',
		'skullracer'    => 'Skull Racer',
		'general'       => 'General',
		'member'        => 'Members',
		'elite'         => 'Elite',
		'innercircle'   => 'Inner Circle',
	);
	if ($channel === '') return 'Platform';
	if (isset($map[$channel])) return $map[$channel];
	return ucwords(str_replace(array('-', '_'), ' ', $channel));
}

/*
 * "3 minutes ago". Cards are read as a stream, so relative time is what
 * carries the meaning -- an absolute timestamp goes in the title attribute
 * for anyone who wants the exact moment.
 */
function activity_ago($datetime) {
	$t = strtotime((string) $datetime);
	if (!$t) return '';
	$d = time() - $t;
	/* No clamp on a negative $d, deliberately. A created_at in the future
	   (clock skew between the web and database hosts, which is a real thing
	   here) falls into the < 60 branch below and reads "just now", which is
	   the right answer. An `if ($d < 0) $d = 0;` guard was here first and
	   mutation testing proved it unreachable -- nothing could break it
	   because nothing depended on it. */
	if ($d < 60)    return 'just now';
	if ($d < 3600)  { $n = (int) floor($d / 60);    return $n . ' minute'  . ($n == 1 ? '' : 's') . ' ago'; }
	if ($d < 86400) { $n = (int) floor($d / 3600);  return $n . ' hour'    . ($n == 1 ? '' : 's') . ' ago'; }
	if ($d < 2592000) { $n = (int) floor($d / 86400); return $n . ' day'   . ($n == 1 ? '' : 's') . ' ago'; }
	return date('M j, Y', $t);
}

/*
 * Discord markdown -> HTML, for the card body.
 *
 * Every description in the platform is written for Discord, so they are full
 * of **bold**, *italic*, `code`, [text](url) and <@discord_id> mentions. Left
 * raw they render as literal asterisks. This handles the subset that actually
 * appears; anything else degrades to plain text, which is fine.
 *
 * ORDER MATTERS: htmlspecialchars() runs FIRST and the markdown is applied to
 * the escaped string afterwards, so the only tags in the output are the ones
 * built here. A description can contain a player-supplied name (realm names,
 * Fighter names), so this is the XSS boundary for the whole page.
 */
function activity_format($text) {
	$s = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

	/* <@1234...> -- a Discord mention. Nothing on the web can resolve it to
	   a name, and showing a raw snowflake is noise, so it becomes a neutral
	   word. The platform's own announces normally also name the player in
	   $author, which is what the card header shows. */
	$s = preg_replace('/&lt;@!?\d+&gt;/', 'a member', $s);
	/* <#123> channel and <:name:123> emoji refs, same reasoning. */
	$s = preg_replace('/&lt;#\d+&gt;/', '', $s);
	$s = preg_replace('/&lt;a?:(\w+):\d+&gt;/', ':$1:', $s);

	/* [text](url) -- only http(s), and the href is re-escaped. */
	$s = preg_replace_callback(
		'/\[([^\]]{1,200})\]\((https?:\/\/[^\s)]{1,500})\)/',
		function ($m) {
			return '<a href="' . htmlspecialchars(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8')
			     . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
		},
		$s);

	$s = preg_replace('/`([^`]{1,200})`/', '<code>$1</code>', $s);
	$s = preg_replace('/\*\*([^*]{1,500})\*\*/', '<strong>$1</strong>', $s);
	$s = preg_replace('/(?<!\*)\*([^*\n]{1,500})\*(?!\*)/', '<em>$1</em>', $s);
	$s = preg_replace('/__([^_]{1,500})__/', '<u>$1</u>', $s);

	return nl2br($s, false);
}
