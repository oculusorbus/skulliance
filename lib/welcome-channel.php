<?php
/**
 * lib/welcome-channel.php — who has arrived lately, and where they stalled.
 *
 * WHY THIS EXISTS. Working the welcome channel by hand means scrolling a
 * list that only goes one way, reading each name, remembering which of
 * them you already wrote to, and knowing off the top of your head whether
 * that person is still in the server at all. That is not a worklist, and
 * the thing it costs most is the people further up who get missed.
 *
 * THREE GROUPS, AND THEY NEED THREE DIFFERENT QUESTIONS -- the owner's
 * own split, from doing this by hand:
 *
 *   GONE       welcomed, and no longer in the server. Ask why they left.
 *   VISITOR    in the server, never passed the rules gate. Ask whether
 *              the questions were the problem -- and that one is worth
 *              asking, because the gate kicked a Polygon holder for
 *              knowing Polygon until 2026-10-30.
 *   CANDIDATE  through the gate and no further. Ask whether connecting a
 *              wallet or staking is where they got stuck.
 *
 * Anyone holding a role beyond Candidate has arrived properly and is not
 * a question; they are left off.
 *
 * TWO SOURCES, BECAUSE NEITHER ANSWERS IT ALONE. The channel says who
 * arrived and when; it cannot say who is still here, because somebody who
 * left leaves their welcome message behind. The guild roster says who is
 * here and what they hold; it cannot say who is NEW, because it has no
 * order. The interesting people are in the difference between them.
 *
 * READ ONLY. Nothing here writes to Discord.
 */
require_once __DIR__ . '/guild-members.php';

/* The channel the welcomes land in. Not a secret -- a channel id is
   visible to anyone in the server -- so it defaults here and only needs
   to appear in credentials if it ever moves. */
if (!defined('WELCOME_CHANNEL_ID')) define('WELCOME_CHANNEL_ID', '1188271977128005715');

/* Discord serves 100 messages a call and this is a page somebody opens
   to work from, not a archive. Five pages is a few hundred arrivals. */
if (!defined('WELCOME_MAX_PAGES')) define('WELCOME_MAX_PAGES', 5);

/**
 * The user ids welcomed in that channel, newest first.
 *
 * TWO MESSAGE SHAPES, because the channel may carry either and a reader
 * that knows only one silently returns nothing:
 *
 *   type 7 is Discord's own join message, and its AUTHOR is the person
 *     who joined.
 *   anything else is a bot or a human welcoming them, where the person
 *     is the first MENTION. The author there is the greeter, and taking
 *     it would build a list of one name repeated.
 *
 * Bots are dropped either way; a webhook greeting itself is not an
 * arrival.
 *
 * Returns array(ok, error, people), where people is
 * discord_id => array('at' => unix, 'name' => string), newest first.
 */
function welcome_arrivals($bot_token, $channel_id = null, $max_pages = null) {
	$out = array('ok' => false, 'error' => '', 'people' => array());
	$chan = preg_replace('/[^0-9]/', '',
		(string)($channel_id === null ? WELCOME_CHANNEL_ID : $channel_id));
	if ($chan === '')          { $out['error'] = 'No welcome channel id.';   return $out; }
	if (empty($bot_token))     { $out['error'] = 'No bot token.';            return $out; }
	$pages = (int)($max_pages === null ? WELCOME_MAX_PAGES : $max_pages);
	if ($pages < 1) $pages = 1;

	$people = array();
	$before = '';
	for ($p = 0; $p < $pages; $p++) {
		$url = 'https://discord.com/api/v10/channels/' . $chan . '/messages?limit=100'
		     . ($before !== '' ? '&before=' . $before : '');
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_HTTPHEADER     => array('Authorization: Bot ' . $bot_token),
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_SSL_VERIFYPEER => 0,
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($code === 403) {
			/* Said plainly: the symptom at the Discord end is an empty
			   list, which looks exactly like a quiet channel. */
			$out['error'] = 'Discord refused (403). The bot needs View Channel and '
			              . 'Read Message History on that channel.';
			return $out;
		}
		if ($code === 404)  { $out['error'] = 'That channel does not exist, or the bot cannot see it.'; return $out; }
		if ($code === 429)  { $out['error'] = 'Rate limited by Discord. Try again shortly.'; return $out; }
		if ($code !== 200)  { $out['error'] = 'Discord returned HTTP ' . $code . '.'; return $out; }

		$rows = json_decode((string)$body, true);
		if (!is_array($rows)) { $out['error'] = 'Could not read the channel.'; return $out; }
		if (!$rows) break;

		$last = '';
		foreach ($rows as $m) {
			$last = isset($m['id']) ? (string)$m['id'] : $last;
			$type = isset($m['type']) ? (int)$m['type'] : 0;
			$who  = null;
			if ($type === 7 && !empty($m['author']['id'])) {
				$who = $m['author'];
			} elseif (!empty($m['mentions'][0]['id'])) {
				$who = $m['mentions'][0];
			}
			if (!$who || !empty($who['bot'])) continue;
			$id = (string)$who['id'];
			/* First sighting wins: the newest page is read first, so that
			   is their most recent welcome. Somebody who left and rejoined
			   is one person on this list, at the later date. */
			if (isset($people[$id])) continue;
			$nm = '';
			if (isset($who['global_name']) && $who['global_name'] !== '') $nm = (string)$who['global_name'];
			elseif (isset($who['username'])) $nm = (string)$who['username'];
			$people[$id] = array(
				'at'   => isset($m['timestamp']) ? strtotime((string)$m['timestamp']) : 0,
				'name' => $nm,
			);
		}
		if (count($rows) < 100 || $last === '') break;
		$before = $last;
	}

	$out['ok'] = true;
	$out['people'] = $people;
	return $out;
}

/*
 * HOW OLD THE ACCOUNT IS, FROM THE ID ITSELF.
 *
 * WHAT THIS IS INSTEAD OF. The obvious way to spot a throwaway is how
 * many servers they share with you, and a bot CANNOT SEE THAT -- there
 * is no endpoint that lists a user's guilds, deliberately, and the
 * count in your own client is something only your account can see. A
 * bot can ask "is this user in a guild I am also in", which for this
 * application is two guilds and tells you nothing.
 *
 * So this uses the one thing every Discord id carries for free: a
 * Discord snowflake is a 64-bit integer whose top 42 bits are the
 * milliseconds since 2015-01-01 at which it was created. No API call,
 * no rate limit, no permission, and it works for people who have
 * already left.
 *
 * AND IT IS THE BETTER SIGNAL ANYWAY. A spam account is usually days
 * old when it arrives; a real person's account is usually years old
 * whether or not they happen to share other servers with you.
 *
 * Needs 64-bit PHP, which this host is -- but a 32-bit build would
 * silently produce nonsense rather than fail, so it is checked.
 */
if (!defined('WELCOME_SNOWFLAKE_EPOCH')) define('WELCOME_SNOWFLAKE_EPOCH', 1420070400000);

/** Unix seconds the account was created, or 0 when it cannot be read. */
function discord_created_at($discord_id) {
	if (PHP_INT_SIZE < 8) return 0;
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	if ($id === '' || strlen($id) < 17) return 0;
	$ms = intdiv((int)$id, 4194304) + WELCOME_SNOWFLAKE_EPOCH;
	$s  = intdiv($ms, 1000);
	/* Anything before Discord existed or in the future is a bad read, and
	   returning it would put a nonsense age on the row. */
	if ($s < 1420070400 || $s > time() + 86400) return 0;
	return $s;
}

/**
 * How many days old the account was WHEN THEY ARRIVED, or null if unknown.
 *
 * Measured at arrival rather than today on purpose: somebody who joined
 * two years ago with a month-old account is not suspicious now, and an
 * age counted from today would quietly clear every old record.
 */
function discord_age_at($discord_id, $arrived_at) {
	$made = discord_created_at($discord_id);
	if (!$made || !$arrived_at) return null;
	return (int)floor(($arrived_at - $made) / 86400);
}

/* Younger than this when they turned up is worth a second look. Not an
   accusation -- plenty of real people make an account to join something
   -- which is why it marks the row rather than hiding it. */
if (!defined('WELCOME_FRESH_DAYS')) define('WELCOME_FRESH_DAYS', 30);

/**
 * Which of the three groups somebody is in, or '' when they are none of
 * them and need no message.
 *
 * $roles is what guild_member_roles() returned: an array of role ids, or
 * NULL when the roster cannot say. Null is not an empty list -- see that
 * function -- so it yields '' rather than guessing.
 *
 * ORDER MATTERS. Candidate is checked before Visitor because the rules
 * gate adds one and removes the other, and a failed removal leaves both;
 * the further-along answer is the right one to act on.
 */
function welcome_group($in_guild, $roles, $role_visitor, $role_candidate) {
	if (!$in_guild) return 'gone';
	if ($roles === null || !is_array($roles)) return '';
	if ($role_candidate !== '' && in_array((string)$role_candidate, $roles, true)) {
		/* Through the gate. Anything ELSE on top means they got further
		   than that under their own steam and are not stuck. */
		$extra = array_diff($roles, array((string)$role_candidate, (string)$role_visitor));
		return $extra ? '' : 'candidate';
	}
	if ($role_visitor !== '' && in_array((string)$role_visitor, $roles, true)) return 'visitor';
	/* In the server with neither role: either they predate the gate or
	   something granted them a role directly. Not a question we have. */
	return '';
}

/**
 * The message, written for which of the three it is.
 *
 * THE OWNER'S VOICE, the same as the lapsed follow-up: open on something
 * true and friendly, ask ONE thing with a way to answer it, and offer
 * help rather than asking for a favour. Two named possibilities beat an
 * open question -- picking one is a reply somebody actually sends.
 *
 * NO LINK IN ANY OF THEM. Somebody stuck at the rules gate cannot use a
 * staking link, and somebody who left is not being sold to again.
 */
function welcome_text($group, $name = '') {
	switch ($group) {
		case 'gone':
			/* ASKED WITHOUT A COMPLAINT IN IT. "You left" is a fact and
			   reads as one; anything warmer would be pretending not to
			   have noticed. */
			return "Hey! I noticed you joined the Skulliance Discord a while back and have "
			     . "since left.\n\n"
			     . "No hard feelings at all, I'm just trying to work out what we could do "
			     . "better. Was it not what you expected, or was something in the way?\n\n"
			     . "Either way, thanks for stopping by!";

		case 'visitor':
			/* The gate is the likeliest answer and it has a known history
			   of being wrong, so it is named first. */
			return "Hey! Welcome to the Skulliance!\n\n"
			     . "I saw you joined but haven't made it past the rules questions yet. "
			     . "Were they confusing, or did the bot not respond?\n\n"
			     . "We just reworked them so you pick whichever blockchain you know best, "
			     . "so it's worth another go. Let me know if there's anything I can assist "
			     . "with!";

		case 'candidate':
			return "Hey! Welcome to the Skulliance!\n\n"
			     . "I saw you made it through the rules but haven't connected a wallet or "
			     . "staked anything yet. Was the wallet connection giving you trouble, or "
			     . "was it not clear what to do next?\n\n"
			     . "Let me know if there's anything I can assist with to get you set up!";
	}
	return '';
}
