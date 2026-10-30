<?php
/**
 * admin-lapsed.php — who has stopped coming, warmest first.
 *
 * THE ORDER IS THE POINT. Somebody who lapsed six weeks ago still
 * remembers their password, their realm and what they were in the
 * middle of; somebody gone two years is a cold start. So the default
 * is LEAST inactive first, because that is the order you would work
 * down a list you intend to actually message.
 *
 * AND WHAT THEY WOULD BE COMING BACK TO. A list of names and dates
 * tells you who to contact and nothing about what to say. The columns
 * here are the reason to write: what they used to play, the NFTs still
 * sitting in their account, the CARBON they never spent, whether their
 * realm is still standing. "Your realm is still there" is a message; a
 * name and a date is a mail merge.
 *
 * READ ONLY. Nothing here writes, nothing is marked contacted, nothing
 * is emailed. It is a list to work from.
 *
 * last_login IS TRUSTWORTHY -- skulliance.php writes it at most once a
 * day on any signed-in page load, so it means last seen rather than
 * last Discord handshake.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/activity-sources-lib.php';
/* For the gift column. Pulls in dhcfighters-lib.php, which is fine on an
   admin page nobody loads in a loop. */
require_once __DIR__ . '/lib/return-gift.php';
/* Who can still be reached. Read from a cache file; nothing here calls
   Discord, which is why refreshing it is a separate endpoint. */
require_once __DIR__ . '/lib/guild-members.php';
$al_guild = guild_members_load();
require_once __DIR__ . '/lib/outreach-log.php';
$al_sent = outreach_load();
$al_gmsg  = null;
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['lap_guild_msg'])) {
	$al_gmsg = $_SESSION['lap_guild_msg'];
	unset($_SESSION['lap_guild_msg']);     /* shown once, not on every reload */
}

/*
 * TWO VIEWS OFF ONE PAGE. "Who should I write to" and "did writing to
 * them work" are the same campaign seen from either end, and splitting
 * them across two admin tabs would mean checking two places to answer
 * one question.
 */
$al_view = (isset($_GET['view']) && $_GET['view'] === 'back') ? 'back' : 'lapsed';

/*
 * WHO HAS COME BACK. The welcome-back package is the receipt: it is
 * granted once per lapse on a sign-in after a month away, so a
 * 'returngift' row IS a returning member, timestamped. Nothing else
 * had to be recorded to measure this.
 */
$al_back = array();
/*
 * DAYS COUNTED BY MYSQL, NOT BY PHP. awarded_at is written with MySQL's
 * NOW(), and this box runs MySQL and PHP on different clocks -- the
 * first version subtracted one from the other and rendered "-1 days
 * ago" for somebody who had returned that morning. Anything comparing
 * a stored timestamp against the current time has to ask the database,
 * which is the only clock that wrote it.
 */
$q = @$conn->query("SELECT user_id, COUNT(*) AS traits, MIN(awarded_at) AS back_at,
                           DATEDIFF(NOW(), MIN(awarded_at)) AS days_ago
                      FROM dhc_trait_drops WHERE source = 'returngift'
                     GROUP BY user_id ORDER BY back_at DESC");
if ($q) while ($x = $q->fetch_assoc()) $al_back[(int)$x['user_id']] = $x;

/*
 * HOW MANY PACKAGES EACH PERSON HAS ALREADY HAD, so the preview shows
 * the one they would get NEXT rather than a repeat of their first.
 * Same window exclusion as return_gift_lapse(): a package handed over
 * inside the current window is the one in progress, not a past one.
 */
$al_lapse = array();
$q = @$conn->query("SELECT user_id, COUNT(DISTINCT DATE(awarded_at)) AS n
                      FROM dhc_trait_drops
                     WHERE source = 'returngift'
                       AND awarded_at <= DATE_SUB(NOW(), INTERVAL " . (int)RETURN_GIFT_DAYS . " DAY)
                     GROUP BY user_id");
if ($q) while ($x = $q->fetch_assoc()) $al_lapse[(int)$x['user_id']] = (int)$x['n'];

$al_bu = $al_built = array();
if ($al_back) {
	$bids = implode(',', array_map('intval', array_keys($al_back)));
	$q = @$conn->query("SELECT id, username, discord_id, avatar, last_login
	                      FROM users WHERE id IN ($bids)");
	if ($q) while ($x = $q->fetch_assoc()) $al_bu[(int)$x['id']] = $x;
	/*
	 * DID THEY BUILD ANYTHING WITH IT. This is the measure that matters:
	 * the package exists so somebody can assemble a Fighter the moment
	 * they arrive, so a Fighter dated after the package is the gift
	 * doing its job. Signing in and leaving again is not a win.
	 *
	 * Counted against each member's OWN return date in one query rather
	 * than one per member.
	 */
	$q = @$conn->query(
		"SELECT f.user_id, COUNT(*) AS n
		   FROM dhc_fighters f
		   JOIN (SELECT user_id, MIN(awarded_at) AS back_at FROM dhc_trait_drops
		          WHERE source = 'returngift' GROUP BY user_id) g ON g.user_id = f.user_id
		  WHERE f.created_at >= g.back_at
		  GROUP BY f.user_id");
	if ($q) while ($x = $q->fetch_assoc()) $al_built[(int)$x['user_id']] = (int)$x['n'];

	/*
	 * DID THEY DO ANYTHING, which is not the same question as did they
	 * sign in.
	 *
	 * This column used to read last_login, and the owner caught it saying
	 * "not since" for people who were visibly active. Two reasons, and
	 * the first is enough on its own:
	 *
	 *   last_login IS WRITTEN AT MOST ONCE A DAY, and the package is
	 *   granted on the very sign-in that sets it -- so back_at and
	 *   last_login start life as the same timestamp. Somebody returning
	 *   23 hours later trips the "less than a day old" guard on that
	 *   UPDATE and their last_login does not move at all.
	 *
	 *   AND IT COUNTS PAGE LOADS, NOT PLAY. Signing in and reading the
	 *   dashboard advances it; an hour of actually doing things does not
	 *   advance it again until tomorrow.
	 *
	 * So activity is read from what they actually DID, against each
	 * member's OWN return date -- the same join shape as Built since
	 * above, because one global cutoff cannot answer a per-member
	 * question.
	 *
	 * THREE TABLES, CHOSEN FOR COVERAGE PER QUERY. A trait drop catches
	 * every game that pays one, which is most of the platform; a
	 * transaction catches the daily reward, missions and the store; a
	 * Fighter is already counted above and folded in so this column
	 * cannot disagree with the one beside it.
	 *
	 * MACHINE-WRITTEN ROWS ARE THE WHOLE DIFFICULTY, and the first cut of
	 * this reported ALL SEVENTEEN as active today, which is how it was
	 * caught. Two separate sources of that:
	 *
	 *   transactions IS MOSTLY NOT MEMBER ACTIVITY. processSubtotals() in
	 *   db.php loops every user in the table and logCredit()s the nightly
	 *   staking payout, so a row exists for everybody holding anything,
	 *   every night. Leaderboard payouts do the same weekly. Only three
	 *   shapes are deliberate acts: a DEBIT (spending, crafting, burning),
	 *   bonus = 1 (the daily reward, which someone pressed a button for),
	 *   and a credit carrying a mission_id (they ran a mission).
	 *
	 *   AND NOT EVERY TRAIT IS EARNED BY PLAYING. returngift is the
	 *   package itself; backfill is dhc-trait-migrate.php handing out a
	 *   tethered half nobody asked for; referral pays the INVITER for
	 *   somebody else's arrival; digestcache is bookkeeping. All four
	 *   land in the ledger dated now and none of them means the member
	 *   did anything.
	 *
	 * Get this wrong in the lenient direction and the column says
	 * everybody is fine, which is worse than the last_login bug it
	 * replaced -- that one at least failed visibly.
	 */
	$al_act = array();
	$gj = "JOIN (SELECT user_id, MIN(awarded_at) AS back_at FROM dhc_trait_drops
	              WHERE source = 'returngift' GROUP BY user_id) g ON g.user_id = ";
	foreach (array(
		"SELECT d.user_id, MAX(d.awarded_at) AS t FROM dhc_trait_drops d
		   $gj d.user_id
		  WHERE d.source NOT IN ('returngift', 'backfill', 'referral', 'digestcache')
		    AND d.awarded_at > g.back_at GROUP BY d.user_id",
		"SELECT t.user_id, MAX(t.date_created) AS t FROM transactions t
		   $gj t.user_id
		  WHERE t.date_created > g.back_at
		    AND (t.type = 'debit' OR t.bonus = 1 OR t.mission_id > 0)
		  GROUP BY t.user_id",
		"SELECT f.user_id, MAX(f.created_at) AS t FROM dhc_fighters f
		   $gj f.user_id
		  WHERE f.created_at > g.back_at GROUP BY f.user_id",
	) as $sql) {
		/* Suppressed per query, not per batch: a table missing on one
		   install must not take the other two down with it. */
		$r = @$conn->query($sql);
		if (!$r) continue;
		while ($x = $r->fetch_assoc()) {
			$uid = (int)$x['user_id'];
			if (!isset($al_act[$uid]) || $x['t'] > $al_act[$uid]) $al_act[$uid] = $x['t'];
		}
	}
}

/*
 * WHO HAS ALREADY BEEN ASKED. Its own log, not the invite's -- see
 * outreach_path(). Loaded even on the lapsed view so the headline
 * figure is the same number on both.
 */
$al_asked = function_exists('outreach_load') ? outreach_load(OUTREACH_LIST_ASK) : array();

/* Headline numbers, counted here so the strip reads the same on both views. */
$al_n_back = count($al_back);
$al_n_week = $al_n_built = $al_n_again = 0;
foreach ($al_back as $uid => $b) {
	if ((int)$b['days_ago'] <= 7)  $al_n_week++;
	if (!empty($al_built[$uid]))   $al_n_built++;
	/* DID SOMETHING, not signed in -- see the $al_act block above for why
	   last_login cannot answer this. Any activity at all after their own
	   return date counts; the query already excluded the package itself. */
	if (!empty($al_act[$uid])) $al_n_again++;
}

/**
 * The invite, written from what this member actually did.
 *
 * PERSONALISATION OFF REAL NUMBERS, because "I noticed you were big on
 * Missions" lands and "we miss you" does not. The numbers are already
 * on the page in the What they played column; this turns the top one
 * or two into a sentence.
 *
 * GRADED BY VOLUME, AND THAT IS THE WHOLE TRICK. Telling somebody with
 * four missions behind them that they were putting up serious numbers
 * is worse than saying nothing: it reads as a mail merge, which is
 * exactly what the personal invite is meant not to be. Below the
 * bottom threshold the claim is dropped rather than softened.
 *
 * NO FABRICATED HISTORY. These go to members the owner knows of but
 * has never messaged, so nothing here says "I miss you" or implies a
 * conversation that never happened. It says what the records show.
 */
function lap_invite_line($did, $labels) {
	if (empty($did['by'])) return '';
	$by = $did['by'];
	arsort($by);
	/*
	 * DAILY STREAKS IS NOT SOMETHING YOU WERE BIG ON. It is a login
	 * counter, and "I remember you being big on Daily Streaks" is a
	 * sentence no human has written. Named activities only.
	 */
	$skip = array('daily' => 1);
	/*
	 * TWO LABELS ARE COUNTS, NOT PLACES. "Fighters Built" and "Drop
	 * Ship Games" are column headings; dropped into a sentence they
	 * produce "spending some time in Fighters Built", which is not
	 * English. Rephrased, not skipped: somebody whose whole thing was
	 * building Fighters should still get the personal line.
	 */
	$say = array('fighter' => 'building Fighters', 'dropship' => 'Drop Ship');
	$top = array();
	foreach ($by as $k => $n) {
		$n = (int)$n;
		if ($n <= 0 || isset($skip[$k])) continue;
		$top[] = array(isset($say[$k]) ? $say[$k] : (isset($labels[$k]) ? $labels[$k] : $k), $n);
		if (count($top) === 2) break;
	}
	if (!$top) return '';
	$n1 = $top[0][1];
	/*
	 * GRADED, AND THE BOTTOM TIER SAYS NOTHING. Telling somebody with
	 * four runs behind them that they were big on a game is worse than
	 * saying nothing: it reads as a mail merge, which is the one thing
	 * a personal invite must not do.
	 */
	if ($n1 < 10) return '';
	/* A second name only when it is not a rounding error beside the
	   first, or the sentence claims an interest they did not have. */
	$names = $top[0][0];
	if (count($top) === 2 && $top[1][1] >= max(3, (int)($n1 * 0.25))) {
		$names .= ' and ' . $top[1][0];
	}
	/* "I remember" rather than "the records show": he knows who these
	   people are, which is true, and it is warm without inventing a
	   conversation they never had. */
	/* "on" and "into" both take a game name AND "building Fighters";
	   "in" does not, which is how the broken version read. */
	return ($n1 >= 50)
		? 'I remember you being big on ' . $names . '.'
		: 'I remember you putting some time into ' . $names . '.';
}

/** The live SKULLIANCE invite, the same one info.php, site-header.php
    and the homepage carry. Checked against the Discord API rather than
    copied on faith: it resolves to the SKULLIANCE guild. The vanity
    form everyone assumes exists does not -- Discord answers Unknown
    Invite for it, which is how partner.php spent however long sending
    prospective partners to an error page. */
if (!defined('LAP_DISCORD_INVITE')) define('LAP_DISCORD_INVITE', 'https://discord.gg/JqqBZBrph2');

/** The whole message, ready to paste. */
function lap_invite_text($did, $labels, $has_realm, $left_server = false) {
	$line = lap_invite_line($did, $labels);
	$out  = "Hey! I hope all is well!\n\n";
	/*
	 * ONE CLAIM ABOUT THE PLATFORM, NOT TWO. "A ton has been built and
	 * there are a lot of new features" is the same sentence twice, and
	 * saying it twice is what makes copy read as generated.
	 */
	/*
	 * NOTHING THAT ADMITS TO A BATCH. The fallback used to open "I'm
	 * reaching out to folks who haven't been around in a while", which
	 * tells the reader they are one of a list -- the exact opposite of
	 * what a personal invitation is for, and worse than saying nothing
	 * about their absence at all. When there is no history worth
	 * naming, the message simply addresses them and gets to the point.
	 */
	$out .= ($line !== '')
		? $line . " Skulliance has a TON of new features since then."
		: "I wanted to let you know that Skulliance has a TON of new features "
		. "since you were last around.";
	/* Only when it is true, and it is the single strongest reason to
	   come back, so it gets its own line. */
	if ($has_realm) $out .= "\n\nYour realm is still standing, by the way.";
	$out .= "\n\nIf you decide to login and check it out, this fighter assembly will be "
	      . "ready and waiting for you. It's a collection of traits you can use to build "
	      . "however you want.";
	/*
	 * THE WAY IN, on its own line so Discord renders it as a link.
	 * Somebody who has been away a year may not remember the URL, and
	 * a message that asks them to go and find it is a message they do
	 * not act on.
	 *
	 * TRAILING SLASH: /staking 301s to /staking/, so without it every
	 * click pays a redirect.
	 *
	 * AND THE BARE HOST, NOT www. Both serve the site, but the login
	 * cookie has no domain set, so a session started on one host does
	 * not exist on the other. Picking one and always sending that one
	 * is what keeps a member who follows two different invites from
	 * being signed in on one and signed out on the other.
	 */
	$out .= "\n\nhttps://skulliance.io/staking/";
	/*
	 * THE WAY BACK INTO DISCORD, only for somebody who has left it.
	 *
	 * Worth saying even though most people who left cannot be DMed at
	 * all: the roster check only knows about THIS guild, so a member
	 * who left but shares another server with the owner, or is a
	 * friend, still receives the message -- and for them the invite is
	 * the difference between logging in and actually being back.
	 * Discord roles are granted in the guild, so a non-member gets
	 * none of them however much they stake.
	 *
	 * Not shown to current members, for whom it is noise.
	 *
	 * BARE, WITH NO SENTENCE IN FRONT OF IT. It used to open "Looks
	 * like you're not in the Discord server anymore", which reads as
	 * being told off for leaving. A second link under the first needs
	 * no explaining.
	 */
	if ($left_server) $out .= "\n\n" . LAP_DISCORD_INVITE;
	$out .= "\n\nTake care!";
	return $out;
}

/**
 * The follow-up, for somebody who took the package and then stalled.
 *
 * WHY THIS IS A SEPARATE MESSAGE AND NOT A SECOND INVITE. They already
 * came back -- the invite worked. 17 people took a welcome-back
 * package, 8 built a Fighter with it and 5 signed in again, so the
 * question is no longer "will you come back" but "what stopped you",
 * and those have nothing in common. An invite sent to somebody who
 * already accepted it reads as not having noticed.
 *
 * THREE SITUATIONS, THREE QUESTIONS, because they are three different
 * people and one message cannot ask all three without guessing wrong
 * about two of them:
 *
 *   took it, never built, never came back   the coldest. Something put
 *     them off on the day, or they never saw the traits at all.
 *   took it, came back, still no Fighter    they are around and the
 *     assembler is what is not landing. The most actionable of the
 *     three, and the only one where the answer is probably a UI fix.
 *   built one, then went quiet              the package did its job and
 *     something after it did not. Worth asking, gently.
 *
 * IT ASKS AND STOPS. No link, no pitch, nothing about new features --
 * this message exists to get an answer, and a question with a sales
 * paragraph under it gets the paragraph ignored and the question with
 * it. The invite does the selling; this does not repeat it.
 *
 * AND IT NAMES WHAT THEY ACTUALLY HAVE. "Your N traits are still
 * sitting there" is true, specific, and the one sentence most likely
 * to make somebody go and look.
 */
function lap_followup_text($name, $days, $traits, $built, $seen) {
	$traits = (int)$traits;
	$ago = $days <= 1 ? 'the other day'
	     : ($days <= 14 ? 'a couple of weeks ago'
	     : ($days <= 45 ? 'a while back' : 'a while ago'));

	/*
	 * LEAD WITH WHAT THEY DID RIGHT. The owner's own draft opens "I saw
	 * that you logged in and claimed your traits! That's awesome!" before
	 * it mentions the gap, and that order is the message: somebody who
	 * came back after months away did the hard part, and a note that
	 * opens on what they have NOT done reads as a telling-off.
	 */
	$out = "Hey! ";

	if ($built && !$seen) {
		/* The package worked and whatever came next did not. */
		$out .= "I saw that you came back " . $ago . " and put a fighter together! "
		      . "That's awesome!\n\n"
		      . "Looks like you haven't been back since, though. Was there something that "
		      . "didn't click? Were the games not intuitive enough?";
	} elseif ($seen) {
		/*
		 * Around and playing, still has not assembled anything. The only
		 * one of the three whose answer is probably a fix rather than a
		 * shrug, so it asks the owner's own question straight out.
		 */
		$out .= "I saw that you logged in and claimed your " . $traits . " trait"
		      . ($traits === 1 ? '' : 's') . "! That's awesome!\n\n"
		      . "It looks like you've been around since but haven't gotten a chance to "
		      . "build a fighter yet. Was the Fighter assembly confusing? Is there "
		      . "something missing that would make it worth doing?";
	} else {
		/*
		 * Took the package and vanished -- the owner's own wording for
		 * this one, verbatim.
		 *
		 * TWO REASONS OFFERED, NOT AN OPEN QUESTION, and that is the
		 * owner's refinement rather than mine. "What put you off" asks
		 * somebody to compose an answer; "was it X, or was it Y" asks
		 * them to pick one, and picking one is a reply they will
		 * actually send.
		 *
		 * AND IT IS "INTUITIVE", NOT "INTERESTING" -- also the owner's
		 * word, and the difference is the whole reason for writing. "Not
		 * interesting" is a matter of taste and leaves nothing to do
		 * about it; "not intuitive" names something that can be fixed and
		 * invites an answer saying what.
		 */
		$out .= "I saw that you logged in and claimed your " . $traits . " trait"
		      . ($traits === 1 ? '' : 's') . " " . $ago . "! That's awesome!\n\n"
		      . "It looks like you haven't gotten a chance to build a fighter yet or "
		      . "engage with the platform. Was the Fighter assembly confusing? Were the "
		      . "games not intuitive enough?";
	}

	/* The owner's own closer, and the right one: it turns a question into
	   an offer, which is what makes answering it feel useful rather than
	   like being surveyed. */
	$out .= "\n\nLet me know if there's anything I can assist with to make your "
	      . "experience better!";
	return $out;
}

/*
 * WHAT TO SHOW. Three hundred names is a grind mostly because two
 * large slices of it are not work: people who have already had their
 * invite, and people who left the server and cannot be sent one at
 * all. "todo" removes both and is the default, so the list is the
 * list of things still to do rather than everyone who has ever
 * lapsed.
 */
/*
 * 'left' EXISTS BECAUSE THE MESSAGE NOW HAS SOMETHING TO SAY TO THEM.
 * They are kept out of 'todo' -- most cannot be DMed at all, and a
 * worklist full of refusals is not a worklist -- but the roster check
 * only knows about THIS guild, so the ones who share another server
 * or are friends do receive it, and their message carries a server
 * invite. Without this view the only way to reach them was Everyone.
 */
$al_shows = array('todo', 'sent', 'left', 'all');
/* EVERYONE BY DEFAULT, deliberately. Filtering by default would be
   the better list, but it renumbers a run already in progress -- the
   owner was at 68 of 344 when this was built, and changing what the
   page shows underneath that is worse than an extra click. One word
   here flips it to 'todo' once a pass is finished. */
$al_show  = (isset($_GET['show']) && in_array($_GET['show'], $al_shows, true))
          ? $_GET['show'] : 'all';

/* Both in the query string so a view can be linked or bookmarked. */
$al_days = isset($_GET['days']) ? (int)$_GET['days'] : 90;
if (!in_array($al_days, array(30, 90, 180, 365), true)) $al_days = 90;
/*
 * FOUR WAYS TO WORK THE LIST, because "who should I message" has more
 * than one right answer.
 *
 *   warmest  least time away -- still remembers their password
 *   coldest  longest away -- deciding who is never coming back
 *   active   did the most while they were here. Somebody with two
 *            thousand missions behind them has a reason to look at
 *            what has been built since; somebody who played twice
 *            does not.
 *   quiet    barely engaged, for working out what put them off
 */
$al_sorts = array('warmest', 'coldest', 'active', 'quiet');
$al_sort  = (isset($_GET['sort']) && in_array($_GET['sort'], $al_sorts, true))
          ? $_GET['sort'] : 'warmest';

/*
 * ONE PASS EACH, not one query per player. Four hundred members and a
 * dozen things worth knowing about each is a page that takes a minute
 * if you ask per row.
 */
$al_rows = array();
/* DATEDIFF for the same reason as the returns query: last_login is
   written with MySQL's NOW() and this box runs the two clocks apart,
   so subtracting it from PHP's time() is off by the skew -- which at a
   day boundary is off by a whole day on every row. */
$al_sql = "SELECT id, username, discord_id, avatar, last_login, date_created,
                  DATEDIFF(NOW(), last_login) AS gone_days
             FROM users
            WHERE discord_id IS NOT NULL AND discord_id <> ''
              AND last_login IS NOT NULL
              AND last_login < NOW() - INTERVAL " . (int)$al_days . " DAY
            ORDER BY last_login " . ($al_sort === 'coldest' ? 'ASC' : 'DESC');
$r = @$conn->query($al_sql);
if ($r) while ($row = $r->fetch_assoc()) $al_rows[(int)$row['id']] = $row;

/* What is still sitting in the account. */
$al_nfts = $al_carbon = $al_realm = array();
if ($al_rows) {
	$ids = implode(',', array_map('intval', array_keys($al_rows)));
	$q = @$conn->query("SELECT user_id, COUNT(*) AS n FROM nfts WHERE user_id IN ($ids) GROUP BY user_id");
	if ($q) while ($x = $q->fetch_assoc()) $al_nfts[(int)$x['user_id']] = (int)$x['n'];
	/* Project 15 is CARBON, the platform-wide currency the games pay. */
	$q = @$conn->query("SELECT user_id, balance FROM balances WHERE project_id = 15 AND user_id IN ($ids)");
	if ($q) while ($x = $q->fetch_assoc()) $al_carbon[(int)$x['user_id']] = (float)$x['balance'];
	$q = @$conn->query("SELECT DISTINCT user_id FROM realms WHERE user_id IN ($ids)");
	if ($q) while ($x = $q->fetch_assoc()) $al_realm[(int)$x['user_id']] = true;
}

/*
 * WHAT THEY USED TO DO, all time rather than recent -- by definition
 * these people have done nothing recently, so a windowed view would
 * report zero for everybody and tell you nothing about who they were.
 */
$al_did = array();
foreach (activity_totals($conn, 36500) as $a) {
	$al_did[(int)$a['user_id']] = $a;
}
$al_labels = activity_labels();

/*
 * THE ACTIVITY SORTS HAPPEN HERE, NOT IN SQL, because the number being
 * sorted on does not exist in the database -- it is the weighted total
 * across fifteen sources, assembled a few lines up. Four hundred rows
 * in PHP is nothing; a sort key that disagrees with the column beside
 * it would be worse than slow.
 *
 * The recency sorts stay in SQL, where last_login already lives.
 */
/*
 * FILTERED IN PHP, AFTER THE QUERY. Neither fact lives in the
 * database -- the outreach log is a file and the roster is a cache --
 * so there is nothing to put in a WHERE clause. The counts are taken
 * before the filter, because "142 still to write to" is only
 * meaningful next to the total.
 */
$al_total = count($al_rows);
$al_n_sent = $al_n_gone = 0;
foreach ($al_rows as $uid => $u) {
	if (outreach_done($al_sent, $u['discord_id']))        $al_n_sent++;
	if (guild_member_in($al_guild, $u['discord_id']) === false) $al_n_gone++;
}
if ($al_show !== 'all') {
	foreach ($al_rows as $uid => $u) {
		$done = outreach_done($al_sent, $u['discord_id']);
		if ($al_show === 'sent') { if (!$done) unset($al_rows[$uid]); continue; }
		if ($al_show === 'left') {
			if (guild_member_in($al_guild, $u['discord_id']) !== false) unset($al_rows[$uid]);
			continue;
		}
		/* todo: drop the ones already written to, and the ones who
		   cannot be written to. Unknown stays -- see guild-members.php
		   on why absence of a roster is not absence from the server. */
		if ($done || guild_member_in($al_guild, $u['discord_id']) === false) unset($al_rows[$uid]);
	}
}

if ($al_sort === 'active' || $al_sort === 'quiet') {
	$al_dir = ($al_sort === 'active') ? -1 : 1;
	uasort($al_rows, function ($a, $b) use ($al_did, $al_dir) {
		$ta = isset($al_did[(int)$a['id']]) ? (int)$al_did[(int)$a['id']]['total'] : 0;
		$tb = isset($al_did[(int)$b['id']]) ? (int)$al_did[(int)$b['id']]['total'] : 0;
		if ($ta === $tb) {
			/* Ties broken by recency, so the second axis is still useful. */
			return strcmp((string)$b['last_login'], (string)$a['last_login']);
		}
		return ($ta < $tb ? -1 : 1) * $al_dir;
	});
}

include 'header.php';
admin_chrome('lapsed');
?>
<?php include 'admin-css.php'; ?>
<style>
/* The scoreboard. Four numbers, not a chart: the question is "is this
   working", and a sparkline over two data points would be decoration. */
.lap-score { display:flex; gap:28px; flex-wrap:wrap; margin:0 0 16px; }
.lap-score div { display:flex; flex-direction:column; }
.lap-score b { font-size:1.5rem; color:#e8eaed; font-variant-numeric:tabular-nums; line-height:1.1; }
.lap-score b.lap-good { color:#00c8a0; }
.lap-score span { font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; color:#5f7d90; margin-top:3px; }
.lap-views { margin:0 0 16px; }
.lap-sum { font-size:.8rem; color:#8fa8b8; margin:0 0 14px; }
.lap-sum b { color:#00c8a0; }
.lap-tools { display:flex; gap:16px; flex-wrap:wrap; margin:0 0 16px; align-items:center; }
.lap-range { display:flex; gap:0; }
.lap-range a { padding:7px 14px; font-size:.76rem; letter-spacing:.07em; text-transform:uppercase;
  color:#8fa8b8; border:1px solid rgba(255,255,255,.12); text-decoration:none; }
.lap-range a + a { border-left:0; }
.lap-range a.on { color:#06222b; background:#00c8a0; border-color:#00c8a0; }
.lap-t { width:100%; border-collapse:collapse; font-size:.82rem; }
.lap-t th { text-align:left; font-size:.68rem; letter-spacing:.09em; text-transform:uppercase;
  color:#5f7d90; padding:0 10px 7px; font-weight:normal; }
.lap-t td { padding:9px 10px; border-top:1px solid rgba(255,255,255,.06); vertical-align:top; }
.lap-who { display:flex; align-items:center; gap:8px; }
.lap-who img { width:26px; height:26px; border-radius:50%; object-fit:cover; background:#0d2030; }
.lap-gone { font-variant-numeric:tabular-nums; white-space:nowrap; }
.lap-gone b { color:#e8eaed; }
.lap-gone span { display:block; font-size:.7rem; color:#5f7d90; }
.lap-did { font-size:.72rem; color:#5f7d90; max-width:320px; }
.lap-has { font-size:.74rem; white-space:nowrap; }
.lap-has b { color:#00c8a0; font-variant-numeric:tabular-nums; }
.lap-has .none { color:#4f7488; }
/* The gift thumbnail. The art is transparent PNG over nothing, so it
   needs its own ground or it reads as a hole in the row. flexbox.css
   centres and teals every bare <a>, which would drag the picture off
   its cell -- block display pins it back. */
.lap-gift { width:60px; }
.lap-gift a { display:block; width:52px; line-height:0; }
/* No frame of its own either: the art already fills the square, so a
   background only shows as a rim when the aspect is not exactly 1:1. */
td.lap-gift img { width:52px; height:52px; object-fit:cover; display:block;
                  border:0; background:none; border-radius:3px; }
.lap-warm { color:#d69e2e; font-weight:bold; }
.lap-dc { font-size:.72rem; white-space:nowrap; }
.lap-in { color:#00c8a0; }
.lap-out { color:#c2566a; }
.lap-guild { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin:0 0 16px;
  font-size:.76rem; color:#8fa8b8; }
.lap-guild b { color:#e8eaed; }
.lap-gok { color:#00c8a0; }
.lap-gbad { color:#d69e2e; }
.lap-guild .lap-mc { margin-top:0; }
.lap-prog { font-size:.72rem; color:#5f7d90; margin-left:6px; align-self:center; }
.lap-prog b { color:#e8eaed; font-variant-numeric:tabular-nums; }
.lap-ck { width:28px; }
.lap-ck input { cursor:pointer; }
.lap-bulk { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:0 0 14px;
  padding:10px 14px; background:#0d2030; border:1px solid rgba(0,200,160,.35); border-radius:6px;
  font-size:.78rem; color:#8fa8b8; }
.lap-bulk[hidden] { display:none !important; }
.lap-bulk b { color:#00c8a0; font-variant-numeric:tabular-nums; }
.lap-bulk .lap-mc { margin-top:0; }
.lap-bulkhint { font-size:.7rem; color:#5f7d90; }
.lap-giftbtn[data-sent="1"] img { opacity:.4; }
.lap-mdone { background:#00c8a0; color:#06222b; }
.lap-mdone[data-on="1"] { background:#123049; color:#9fd8c8; }

/* ---- the package modal ---------------------------------------- *
   Its own element rather than a link to the PNG, because the job is
   not "look at a picture" -- it is read the traits, copy the link,
   write the DM. All three in one place.                            */
/* NEUTRALISED EXPLICITLY, NOT LEFT TO THE DEFAULTS. A <button> carries
   a background, a border and padding from the UA stylesheet before any
   of this platform's five stylesheets get a say, and the owner saw a
   bright teal frame around every thumbnail that none of them reproduce
   locally. So every property that can draw a box is set to nothing
   here, and the selector is specific enough to outrank a bare
   `button` rule from anywhere. */
td.lap-gift button.lap-giftbtn {
  display:block; padding:0; margin:0; border:0; outline:0;
  background:none; background-color:transparent; background-image:none;
  box-shadow:none; border-radius:0; -webkit-appearance:none; appearance:none;
  cursor:zoom-in; line-height:0; font:inherit; color:inherit; width:auto;
}
td.lap-gift button.lap-giftbtn:focus-visible { outline:2px solid #00c8a0; outline-offset:2px; }
.lap-mw { position:fixed; inset:0; z-index:9000; display:flex; align-items:center;
  justify-content:center; padding:20px; background:rgba(3,10,16,.86); }
.lap-mw[hidden] { display:none !important; }
.lap-mb { background:#0b1b26; border:1px solid rgba(255,255,255,.14); border-radius:8px;
  max-width:720px; width:100%; max-height:92vh; overflow:auto; padding:18px; }
.lap-mh { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin:0 0 12px; }
.lap-mh h3 { margin:0; font-size:1rem; color:#e8eaed; }
/* Off-screen, not hidden: select() is a no-op on display:none. */
.lap-offscreen { position:absolute; left:-9999px; width:1px; height:1px; opacity:0; }
.lap-mname { margin-right:auto; padding:4px 10px; font-size:.64rem; letter-spacing:.07em;
  text-transform:uppercase; color:#9fd8c8; background:#123049; border:0; border-radius:4px;
  cursor:pointer; white-space:nowrap; }
.lap-mx { background:none; border:0; color:#8fa8b8; font-size:1.4rem; line-height:1; cursor:pointer; padding:0 4px; }
/* ALIGN-ITEMS, NOT STRETCH. A flex row stretches its children to the
   tallest one by default, and the picture has no height of its own, so
   it was being pulled to the height of the message column beside it
   and rendered distorted. Only visible once the text column grew
   taller than 300px, which is why a preview with a short column
   looked perfectly fine. */
.lap-mg { display:flex; gap:18px; flex-wrap:wrap; align-items:flex-start; }
.lap-mg img { width:300px; max-width:100%; height:auto; aspect-ratio:1/1;
  object-fit:contain; align-self:flex-start; border-radius:6px;
  background:#0d2030; display:block; }
.lap-mi { flex:1; min-width:230px; }
.lap-mt { font-size:.78rem; color:#8fa8b8; line-height:1.7; margin:0 0 14px; white-space:pre-line; }
.lap-ml { font-size:.68rem; letter-spacing:.09em; text-transform:uppercase; color:#5f7d90;
  display:block; margin:0 0 5px; }
.lap-mu { width:100%; font-size:.72rem; padding:7px 8px; color:#e8eaed; background:#06151e;
  border:1px solid rgba(255,255,255,.14); border-radius:4px; font-family:monospace; }
.lap-mmsg { resize:vertical; line-height:1.55; font-family:inherit; min-height:252px; }
.lap-ml2 { margin-top:16px; }
.lap-mc { margin-top:9px; padding:8px 15px; font-size:.74rem; letter-spacing:.06em;
  text-transform:uppercase; color:#06222b; background:#00c8a0; border:0; border-radius:4px; cursor:pointer; }
/* AFTER .lap-mc, not before. Same specificity, so the later rule wins --
   written above it first, which made the secondary button identical to
   the primary one. The message is the thing being sent; the link is
   support, and should not compete with it. */
.lap-mc2 { background:#123049; color:#9fd8c8; }
/* The follow-up trigger on the Came back table. Quiet until pressed,
   then permanently muted -- the column is a to-do list and a row that
   is done should stop asking for attention. */
td.lap-ask { text-align:center; }
.lap-dim { color:#5f7d90; }
.lap-askbtn { background:#123049; color:#9fd8c8; border:1px solid rgba(255,255,255,.14);
  border-radius:6px; padding:4px 12px; font-size:.78rem; cursor:pointer; }
.lap-askbtn:hover { background:#17405f; }
.lap-askbtn:focus-visible { outline:2px solid #00c8a0; outline-offset:2px; }
.lap-askbtn.on { background:transparent; color:#5f7d90; border-style:dashed; }
/* After .lap-mc2 so it is not the one being overridden; the image is a
   primary action, the link is not. */
.lap-mcimg { background:#00c8a0; color:#06222b; }
.lap-mc[disabled] { opacity:.5; cursor:default; }
.lap-mnav { display:flex; align-items:center; gap:14px; margin-top:16px;
  padding-top:14px; border-top:1px solid rgba(255,255,255,.1); }
.lap-mnav span { font-size:.72rem; color:#5f7d90; font-variant-numeric:tabular-nums; }
.lap-mnav .lap-mc { margin-top:0; }
@media (max-width:560px) { .lap-mg img { width:100%; } }
</style>

<?php /* THE SCOREBOARD, on both views, because the reason to work the
         lapsed list is sitting in these four numbers. "Built a Fighter"
         is the one that means the campaign worked: the package exists so
         somebody can assemble one on arrival, and a sign-in that leads
         nowhere is not a win. */ ?>
<div class="lap-score">
	<?php /* THE LOGIN IS THE WIN. Somebody signing in after a personal
	         invite is the campaign working, whether or not they go on to
	         build anything, so this is the number that gets the colour. */ ?>
	<div><b class="<?php echo $al_n_back ? 'lap-good' : ''; ?>"><?php
		echo number_format($al_n_back); ?></b><span>came back</span></div>
	<div><b><?php echo number_format($al_n_week); ?></b><span>in the last 7 days</span></div>
	<div><b><?php echo number_format($al_n_built); ?></b><span>built a Fighter</span></div>
	<div><b><?php echo number_format($al_n_again); ?></b><span>did something since</span></div>
</div>

<div class="lap-range lap-views">
	<a href="admin-lapsed.php?days=<?php echo $al_days; ?>&amp;sort=<?php echo $al_sort; ?>"
	   class="<?php echo $al_view === 'lapsed' ? 'on' : ''; ?>">Still away</a>
	<a href="admin-lapsed.php?view=back" class="<?php
	   echo $al_view === 'back' ? 'on' : ''; ?>">Came back<?php
	   echo $al_n_back ? ' (' . number_format($al_n_back) . ')' : ''; ?></a>
</div>

<?php if ($al_view === 'back'): ?>
<p class="lap-sum">
	Everyone who signed in after a month away and collected a package.
	<b><?php echo number_format($al_n_built); ?></b> of
	<b><?php echo number_format($al_n_back); ?></b> have built a Fighter since.
</p>
<?php if (!$al_back): ?>
<p style="color:#8fa8b8">Nobody has come back yet. The first return will appear here.</p>
<?php else: ?>
<table class="lap-t">
	<thead><tr>
		<th>Member</th><th>Came back</th><th>Got</th><th>Built since</th><th>Active since</th><th>Ask</th>
	</tr></thead>
	<tbody>
	<?php foreach ($al_back as $uid => $b):
		$bu   = isset($al_bu[$uid]) ? $al_bu[$uid] : null;
		$bt   = strtotime($b['back_at']);
		/* From the database, for the reason in the query's own note.
		   Clamped anyway: a clock that drifts the other way must not
		   render a negative. */
		$days = max(0, (int)$b['days_ago']);
		/* What they DID since their own return, not whether a once-a-day
		   last_login happened to move. */
		$act  = isset($al_act[$uid]) ? $al_act[$uid] : '';
		$seen = ($act !== '');
	?>
	<tr>
		<td>
			<div class="lap-who">
				<?php if ($bu): ?>
				<img loading="lazy" alt=""
				     src="<?php echo htmlspecialchars(avatar_url($bu['discord_id'], $bu['avatar'], 64), ENT_QUOTES); ?>"
				     onerror="this.src='icons/skull.png'">
				<span title="<?php echo htmlspecialchars($bu['discord_id'], ENT_QUOTES); ?>"><?php
					echo htmlspecialchars($bu['username'], ENT_QUOTES); ?></span>
				<?php else: ?><span style="color:#5f7d90">user <?php echo (int)$uid; ?></span><?php endif; ?>
			</div>
		</td>
		<td class="lap-gone">
			<b><?php
				echo $days === 0 ? 'today'
				   : ($days === 1 ? 'yesterday' : number_format($days) . ' days ago'); ?></b>
			<span><?php echo htmlspecialchars(date('j M Y', $bt)); ?></span>
		</td>
		<td class="lap-gone"><b><?php echo (int)$b['traits']; ?></b> traits</td>
		<td class="lap-has"><?php
			echo !empty($al_built[$uid])
				? '<b>' . (int)$al_built[$uid] . '</b> Fighter' . ($al_built[$uid] > 1 ? 's' : '')
				: '<span class="none">not yet</span>';
		?></td>
		<td class="lap-has"><?php
			/* The DATE, not just a yes: "active, 3 Oct" tells you whether to
			   write to them, and "active" on its own does not. */
			echo $seen
				? 'active <span class="lap-dim">' . htmlspecialchars(date('j M', strtotime($act))) . '</span>'
				: '<span class="none">nothing yet</span>';
		?></td>
		<td class="lap-ask"><?php
			/*
			 * ONLY WHERE THERE IS SOMETHING TO ASK. Somebody who built a
			 * Fighter AND came back again is the package working exactly as
			 * intended -- there is no question to put to them, and a button
			 * offering one anyway turns a list of 17 into 17 pieces of work
			 * when only a handful are.
			 */
			$ask = $bu && (empty($al_built[$uid]) || !$seen);
			if (!$ask):
				echo '<span class="none">&mdash;</span>';
			else:
				$done = outreach_done($al_asked, $bu['discord_id']);
			?>
			<button type="button" class="lap-askbtn<?php echo $done ? ' on' : ''; ?>"
			        data-did="<?php echo htmlspecialchars($bu['discord_id'], ENT_QUOTES); ?>"
			        data-who="<?php echo htmlspecialchars($bu['username'], ENT_QUOTES); ?>"
			        data-sent="<?php echo $done ? '1' : ''; ?>"
			        data-msg="<?php echo htmlspecialchars(
			            lap_followup_text($bu['username'], $days, (int)$b['traits'],
			                              !empty($al_built[$uid]), $seen), ENT_QUOTES); ?>"
			        title="<?php echo $done ? 'Already asked' : 'Ask why'; ?>"><?php
			        echo $done ? 'asked' : 'ask'; ?></button>
			<?php endif; ?>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<?php else: ?>

<p class="lap-sum">
	<b><?php echo number_format(count($al_rows)); ?></b> members have not signed in for
	<?php echo (int)$al_days; ?> days or more.
	<?php
	$al_withstuff = 0;
	foreach ($al_rows as $uid => $u) {
		if (!empty($al_nfts[$uid]) || !empty($al_realm[$uid]) || !empty($al_carbon[$uid])) $al_withstuff++;
	}
	?>
	<b><?php echo number_format($al_withstuff); ?></b> of them still have something waiting.
</p>

<div class="lap-tools">
	<div class="lap-range">
		<?php foreach (array(30, 90, 180, 365) as $d): ?>
		<a href="admin-lapsed.php?days=<?php echo $d; ?>&amp;sort=<?php echo $al_sort; ?>"
		   class="<?php echo $d === $al_days ? 'on' : ''; ?>"><?php echo $d; ?>+ days</a>
		<?php endforeach; ?>
	</div>
	<div class="lap-range">
		<?php /* WARMEST IS THE DEFAULT, because that is the order you would
		         work a list you intend to message. The other three are
		         different questions asked of the same people. */
		foreach (array('warmest' => 'Warmest', 'coldest' => 'Coldest',
		               'active'  => 'Most active', 'quiet' => 'Least active') as $k => $label): ?>
		<a href="admin-lapsed.php?days=<?php echo $al_days; ?>&amp;sort=<?php echo $k; ?>"
		   class="<?php echo $al_sort === $k ? 'on' : ''; ?>"><?php echo $label; ?></a>
		<?php endforeach; ?>
	</div>
</div>

<div class="lap-range lap-views">
	<?php foreach (array('todo' => 'To write', 'sent' => 'Written',
	                     'left' => 'Left the server', 'all' => 'Everyone') as $k => $label): ?>
	<a href="admin-lapsed.php?days=<?php echo $al_days; ?>&amp;sort=<?php echo $al_sort; ?>&amp;show=<?php echo $k; ?>"
	   class="<?php echo $al_show === $k ? 'on' : ''; ?>"><?php echo $label; ?></a>
	<?php endforeach; ?>
	<span class="lap-prog"><b><?php echo number_format($al_n_sent); ?></b> written ·
		<b><?php echo number_format(count($al_rows)); ?></b> showing ·
		<?php echo number_format($al_n_gone); ?> left the server</span>
</div>

<?php /* THE CATCH-UP BAR. Seventy eight already sent before any of
         this existed, and ticking them one at a time is the problem
         this is meant to solve -- so the checkboxes take a shift-click
         range, which makes "the first 78, in order" two clicks. Hidden
         until something is selected so it is not permanent furniture. */ ?>
<div class="lap-bulk" id="lapBulk" hidden>
	<span><b id="lapBulkN">0</b> selected</span>
	<button type="button" class="lap-mc" id="lapBulkMark">Mark as written</button>
	<button type="button" class="lap-mc lap-mc2" id="lapBulkUnmark">Mark as not written</button>
	<button type="button" class="lap-mc lap-mc2" id="lapBulkClear">Clear</button>
	<span class="lap-bulkhint">Tip: click one, then shift-click another to take everything between.</span>
</div>

<?php /* THE ROSTER, and how old it is. Membership drifts slowly, so a
         day-old answer is fine and a six-month-old one is not. */ ?>
<form class="lap-guild" method="post" action="admin-guild-refresh.php">
	<?php if ($al_gmsg): ?>
		<span class="<?php echo $al_gmsg[0] === 'ok' ? 'lap-gok' : 'lap-gbad'; ?>"><?php
			echo htmlspecialchars($al_gmsg[1], ENT_QUOTES); ?></span>
	<?php elseif (!$al_guild): ?>
		<span class="lap-gbad">Discord membership has never been read, so every row says unknown.</span>
	<?php else: ?>
		<span><b><?php echo number_format((int)$al_guild['count']); ?></b> in the server,
		read <?php echo guild_members_stale($al_guild)
			? '<b class="lap-gbad">' . htmlspecialchars(date('j M', (int)$al_guild['at'])) . '</b>'
			: htmlspecialchars(date('j M, H:i', (int)$al_guild['at'])); ?>.</span>
	<?php endif; ?>
	<button type="submit" class="lap-mc lap-mc2">Refresh from Discord</button>
</form>

<?php if (!$al_rows): ?>
<p style="color:#8fa8b8">Nobody has been away that long.</p>
<?php else: ?>
<table class="lap-t">
	<thead><tr>
		<th class="lap-ck"><input type="checkbox" id="lapAll" title="Select everything shown"></th>
		<th>Member</th><th>Last seen</th><th>Played</th><th>What they played</th>
		<th>Still waiting</th><th>Discord</th><th>Waiting for them</th>
	</tr></thead>
	<tbody>
	<?php foreach ($al_rows as $uid => $u):
		$gone = max(0, (int)$u['gone_days']);
		$did  = isset($al_did[$uid]) ? $al_did[$uid] : null;
		$bits = array();
		if ($did) {
			$by = $did['by']; arsort($by);
			foreach ($by as $k => $n) {
				if ((int)$n <= 0) continue;
				$bits[] = (isset($al_labels[$k]) ? $al_labels[$k] : $k) . ' ' . number_format($n);
				if (count($bits) === 4) break;
			}
		}
		$has = array();
		if (!empty($al_nfts[$uid]))   $has[] = '<b>' . number_format($al_nfts[$uid]) . '</b> NFTs';
		if (!empty($al_realm[$uid]))  $has[] = 'a realm';
		if (!empty($al_carbon[$uid])) $has[] = '<b>' . number_format($al_carbon[$uid]) . '</b> CARBON';
	?>
	<tr>
		<td class="lap-ck">
			<input type="checkbox" class="lap-pick"
			       data-did="<?php echo htmlspecialchars($u['discord_id'], ENT_QUOTES); ?>"
			       <?php echo outreach_done($al_sent, $u['discord_id']) ? 'checked' : ''; ?>>
		</td>
		<td>
			<div class="lap-who">
				<img loading="lazy" alt=""
				     src="<?php echo htmlspecialchars(avatar_url($u['discord_id'], $u['avatar'], 64), ENT_QUOTES); ?>"
				     onerror="this.src='icons/skull.png'">
				<?php /* THE ID IS ON HOVER, NOT IN A COLUMN. A column of raw
				         snowflakes is noise -- you find people by name in
				         Discord. But a display name can change and the id
				         cannot, so it stays reachable for the one case where
				         the name no longer finds them. */ ?>
				<span title="<?php echo htmlspecialchars($u['discord_id'], ENT_QUOTES); ?>"><?php
					echo htmlspecialchars($u['username'], ENT_QUOTES); ?></span>
			</div>
		</td>
		<td class="lap-gone">
			<b class="<?php echo $gone < 180 ? 'lap-warm' : ''; ?>"><?php echo number_format($gone); ?> days</b>
			<span><?php echo htmlspecialchars(date('j M Y', strtotime($u['last_login']))); ?></span>
		</td>
		<?php /* THE NUMBER THE ACTIVE SORTS ORDER BY. Without it on screen
		         those two orderings look arbitrary. */ ?>
		<td class="lap-gone"><b><?php echo $did ? number_format((int)$did['total']) : '0'; ?></b></td>
		<td class="lap-did"><?php
			echo $bits ? htmlspecialchars(implode(' · ', $bits), ENT_QUOTES)
			           : '<span style="color:#4f7488">never played anything</span>';
		?></td>
		<td class="lap-has"><?php
			echo $has ? implode(' · ', $has) : '<span class="none">nothing</span>';
		?></td>
		<?php /*
		 * THE PACKAGE THEY WOULD WALK INTO, rendered as the Fighter it
		 * makes rather than three trait names, because the picture is
		 * the thing that goes in the DM.
		 *
		 * LAZY AND VIA AN ENDPOINT, not dhcf_render_fighter() inline.
		 * Compositing a stack of thousand-pixel layers per row would
		 * make a four-hundred-row list take minutes to open; this way
		 * only the rows actually scrolled to are ever built, once each.
		 *
		 * THE LINK IS THE PUBLIC URL AND THE IMG IS THE ADMIN ONE. The
		 * endpoint is behind the admin gate, so pasting its address
		 * into a DM would show the recipient "Not found." Loading the
		 * img is what causes the render to exist; the href is where it
		 * then lives, which is the address to paste. Right-click the
		 * picture, Copy Image Address, and the redirect means you get
		 * the public one either way.
		 */ ?>
		<?php /* CAN THEY EVEN BE REACHED. A DM to somebody who has left
		         the server is refused outright -- Discord answers "send a
		         friend request first", because you no longer share a
		         guild. No point writing the message.

		         THREE STATES, NOT TWO. With no cache the honest answer is
		         "unknown", and rendering that as "left" would write off
		         the entire list on a cache that was never built. */ ?>
		<td class="lap-dc"><?php
			$in = guild_member_in($al_guild, $u['discord_id']);
			if ($in === true)       echo '<span class="lap-in">in server</span>';
			elseif ($in === false)  echo '<span class="lap-out">left</span>';
			else                    echo '<span class="none">unknown</span>';
		?></td>
		<?php
			/* Derived once per row: return_gift_url() calls the same
			   derivation internally, so asking twice doubles the hashing
			   for nothing. */
			$gift_n  = isset($al_lapse[$uid]) ? (int)$al_lapse[$uid] : 0;
			$gift_t  = return_gift_traits($u['discord_id'], $gift_n);
			$gift_u  = return_gift_url($u['discord_id'], $gift_n);
			$gift_ep = 'return-gift-card.php?d=' . rawurlencode($u['discord_id']);
			$gift_l  = array();
			foreach ($gift_t as $gc => $gs) {
				$gift_l[] = ucfirst($gc) . ': ' . dhcf_trait_name($gc, $gs);
			}
		?>
		<td class="lap-gift">
			<?php /* A BUTTON, NOT A LINK. It opens the modal rather than
			         navigating, and the picture it shows is loaded from the
			         endpoint -- which is what causes the render to exist, so
			         the public URL beside it is live by the time anyone can
			         copy it. */ ?>
			<button type="button" class="lap-giftbtn"
			        data-img="<?php echo htmlspecialchars($gift_ep, ENT_QUOTES); ?>"
			        data-url="<?php echo htmlspecialchars($gift_u, ENT_QUOTES); ?>"
			        data-who="<?php echo htmlspecialchars($u['username'], ENT_QUOTES)
			            . ($gift_n > 0 ? ' (package ' . ($gift_n + 1) . ')' : ''); ?>"
			        data-traits="<?php echo htmlspecialchars(implode("\n", $gift_l), ENT_QUOTES); ?>"
			        data-msg="<?php echo htmlspecialchars(
			            lap_invite_text($did, $al_labels, !empty($al_realm[$uid]),
			                guild_member_in($al_guild, $u['discord_id']) === false), ENT_QUOTES); ?>"
			        data-did="<?php echo htmlspecialchars($u['discord_id'], ENT_QUOTES); ?>"
			        data-sent="<?php echo outreach_done($al_sent, $u['discord_id']) ? '1' : ''; ?>"
			        title="See the package and copy the link">
				<img loading="lazy" alt="welcome-back package for <?php
				     echo htmlspecialchars($u['username'], ENT_QUOTES); ?>"
				     src="<?php echo htmlspecialchars($gift_ep, ENT_QUOTES); ?>">
			</button>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
<?php endif; /* $al_view */ ?>
</div></div>

<?php /*
 * THE PACKAGE MODAL. One element reused by every row rather than one
 * per row -- four hundred hidden copies of the same markup is a lot of
 * DOM for a thing you open one at a time.
 *
 * The <img> is pointed at the ENDPOINT, not at the public render: the
 * endpoint is what causes the picture to exist, so by the time it has
 * drawn, the dhcrenders/ URL in the box beside it is live and safe to
 * paste. Pointing it straight at the public file would show a broken
 * image for any row whose thumbnail had not been scrolled into view.
 */ ?>
<div class="lap-mw" id="lapModal" hidden>
	<div class="lap-mb" role="dialog" aria-modal="true" aria-labelledby="lapModalWho">
		<div class="lap-mh">
			<h3 id="lapModalWho"></h3>
			<?php /* The Discord name, one click, because finding them in
			         Discord is the step before any of the rest of this.
			         The input is off-screen rather than display:none --
			         select() does nothing on a hidden field. */ ?>
			<button type="button" class="lap-mname" id="lapModalCopyName">Copy name</button>
			<input class="lap-offscreen" id="lapModalName" type="text" readonly aria-hidden="true" tabindex="-1">
			<button type="button" class="lap-mx" id="lapModalX" aria-label="Close">&times;</button>
		</div>
		<div class="lap-mg">
			<img id="lapModalImg" alt="">
			<div class="lap-mi">
				<?php /* THE MESSAGE FIRST, because it is the thing being
				         sent. The link and the trait list are supporting
				         detail for it, not the other way round. */ ?>
				<label class="lap-ml" for="lapModalMsg">The message</label>
				<textarea class="lap-mu lap-mmsg" id="lapModalMsg" rows="9" readonly></textarea>
				<button type="button" class="lap-mc" id="lapModalCopyMsg">Copy message</button>

				<?php /* THE IMAGE ITSELF, because what gets pasted into the
				         DM is a picture, not a URL. The link stays below as
				         a fallback for a browser that cannot write an image
				         to the clipboard, and is styled down so it does not
				         compete. */ ?>
				<button type="button" class="lap-mc lap-mcimg" id="lapModalCopyImg">Copy image</button>

				<label class="lap-ml lap-ml2" for="lapModalUrl">Image link (fallback)</label>
				<input class="lap-mu" id="lapModalUrl" type="text" readonly>
				<button type="button" class="lap-mc lap-mc2" id="lapModalCopy">Copy link</button>

				<p class="lap-mt" id="lapModalTraits"></p>
			</div>
		</div>
		<?php /* STEP THROUGH THE LIST WITHOUT LEAVING THE MODAL. With
		         three hundred people to work through, closing back to the
		         table between each one is most of the work. Disabled at
		         the ends rather than wrapping, so it is obvious where you
		         are in the list. Left and right arrows do the same. */ ?>
		<div class="lap-mnav">
			<button type="button" class="lap-mc lap-mc2" id="lapModalPrev">&larr; Prev</button>
			<span id="lapModalPos"></span>
			<?php /* THE ONE THAT ENDS THE GRIND: ticks this person off and
			         moves on in a single press, so the loop is copy, copy,
			         copy, paste, done. Marking is also what makes stopping
			         safe -- the list is shorter when you come back. */ ?>
			<button type="button" class="lap-mc lap-mdone" id="lapModalDone">Mark written &amp; next</button>
			<button type="button" class="lap-mc lap-mc2" id="lapModalNext">Next &rarr;</button>
		</div>
	</div>
</div>

<!--
	THE FOLLOW-UP DIALOG. Deliberately NOT the package modal with its
	picture and link hidden: that one exists to hand somebody a gift and
	this one exists to ask a question, so they share the copy helper and
	nothing else. Folding both into one would mean every change to either
	reasoning about the other.
-->
<div class="lap-mw" id="lapAsk" hidden>
	<div class="lap-mb" role="dialog" aria-modal="true" aria-labelledby="lapAskWho">
		<div class="lap-mh">
			<h3 id="lapAskWho"></h3>
			<button type="button" class="lap-mname" id="lapAskCopyName">Copy name</button>
			<input class="lap-offscreen" id="lapAskName" type="text" readonly aria-hidden="true" tabindex="-1">
			<button type="button" class="lap-mx" id="lapAskX" aria-label="Close">&times;</button>
		</div>
		<div class="lap-mi">
			<?php /* WHICH OF THE THREE SITUATIONS THIS IS, said plainly above
			         the message, because the message is written for one of
			         them and reading it without knowing which is how you send
			         the wrong one. */ ?>
			<p class="lap-mt" id="lapAskWhy"></p>
			<label class="lap-ml" for="lapAskMsg">The message</label>
			<textarea class="lap-mu lap-mmsg" id="lapAskMsg" rows="7" readonly></textarea>
			<button type="button" class="lap-mc" id="lapAskCopyMsg">Copy message</button>
		</div>
		<div class="lap-mnav">
			<button type="button" class="lap-mc lap-mc2" id="lapAskPrev">&larr; Prev</button>
			<span id="lapAskPos"></span>
			<button type="button" class="lap-mc lap-mdone" id="lapAskDone">Mark asked &amp; next</button>
			<button type="button" class="lap-mc lap-mc2" id="lapAskNext">Next &rarr;</button>
		</div>
	</div>
</div>
<script>
/* ---- the catch-up selection ------------------------------------- *
   Separate from the modal: it runs on the table whether or not the
   modal has ever been opened.                                        */
(function () {
	var picks = Array.prototype.slice.call(document.querySelectorAll('.lap-pick')),
	    bar   = document.getElementById('lapBulk'),
	    nEl   = document.getElementById('lapBulkN'),
	    allCk = document.getElementById('lapAll'),
	    anchor = null;                       // last box clicked WITHOUT shift
	if (!picks.length || !bar) return;

	function chosen() { return picks.filter(function (c) { return c.checked; }); }
	function sync() {
		var n = chosen().length;
		nEl.textContent = n;
		bar.hidden = (n === 0);
	}

	picks.forEach(function (c, i) {
		c.addEventListener('click', function (e) {
			/*
			 * SHIFT TAKES THE RANGE, which is the whole point: the first
			 * seventy eight were done in list order, so click the first,
			 * shift-click the last, and the work is two clicks instead of
			 * seventy eight. The range is set to the state of the box just
			 * clicked, matching how every file manager behaves.
			 */
			if (e.shiftKey && anchor !== null && anchor !== i) {
				var a = Math.min(anchor, i), b = Math.max(anchor, i);
				for (var j = a; j <= b; j++) picks[j].checked = c.checked;
			} else {
				anchor = i;
			}
			sync();
		});
	});

	if (allCk) allCk.addEventListener('change', function () {
		picks.forEach(function (c) { c.checked = allCk.checked; });
		anchor = null;
		sync();
	});

	document.getElementById('lapBulkClear').addEventListener('click', function () {
		picks.forEach(function (c) { c.checked = false; });
		if (allCk) allCk.checked = false;
		anchor = null;
		sync();
	});

	function send(on, btn) {
		var sel = chosen();
		if (!sel.length) return;
		var ids = sel.map(function (c) { return c.getAttribute('data-did'); }).filter(Boolean),
		    was = btn.textContent;
		btn.disabled = true;
		btn.textContent = 'Saving...';
		var body = 'ids=' + encodeURIComponent(ids.join(','));
		if (!on) body += '&off=1';
		fetch('ajax/admin-outreach.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (r) { return r.json(); }).then(function (d) {
			if (!d || !d.ok) throw new Error('refused');
			/*
			 * THE PAGE IS RELOADED RATHER THAN PATCHED. The counts at the
			 * top, the dimming on each thumbnail and the To write filter
			 * all read this, and keeping four things in step by hand is
			 * how one of them ends up lying about what was saved.
			 */
			location.reload();
		}).catch(function () {
			btn.disabled = false;
			btn.textContent = 'Could not save';
			setTimeout(function () { btn.textContent = was; }, 2200);
		});
	}
	document.getElementById('lapBulkMark').addEventListener('click', function () { send(true, this); });
	document.getElementById('lapBulkUnmark').addEventListener('click', function () { send(false, this); });

	sync();
})();

(function () {
	var wrap = document.getElementById('lapModal');
	if (!wrap) return;
	var img    = document.getElementById('lapModalImg'),
	    who    = document.getElementById('lapModalWho'),
	    traits = document.getElementById('lapModalTraits'),
	    url    = document.getElementById('lapModalUrl'),
	    copy   = document.getElementById('lapModalCopy'),
	    msg    = document.getElementById('lapModalMsg'),
	    copyM  = document.getElementById('lapModalCopyMsg'),
	    copyI  = document.getElementById('lapModalCopyImg'),
	    name   = document.getElementById('lapModalName'),
	    copyN  = document.getElementById('lapModalCopyName'),
	    prevB  = document.getElementById('lapModalPrev'),
	    nextB  = document.getElementById('lapModalNext'),
	    posEl  = document.getElementById('lapModalPos'),
	    doneB  = document.getElementById('lapModalDone'),
	    /* Snapshotted once: the table does not change under us, and
	       re-querying on every step would be the slow part of a
	       three-hundred-row loop. */
	    all    = Array.prototype.slice.call(document.querySelectorAll('.lap-giftbtn')),
	    at     = -1,
	    last   = null;

	function open(btn) {
		last = btn;
		at = all.indexOf(btn);
		posEl.textContent = (at >= 0) ? (at + 1) + ' of ' + all.length : '';
		prevB.disabled = (at <= 0);
		nextB.disabled = (at < 0 || at >= all.length - 1);
		var sent = btn.getAttribute('data-sent') === '1';
		doneB.setAttribute('data-on', sent ? '1' : '');
		doneB.textContent = sent ? 'Written (undo)' : 'Mark written & next';
		doneB.disabled = false;
		who.textContent    = btn.getAttribute('data-who') || 'Welcome-back package';
		name.value         = btn.getAttribute('data-who') || '';
		traits.textContent = btn.getAttribute('data-traits') || '';
		url.value          = btn.getAttribute('data-url') || '';
		msg.value          = btn.getAttribute('data-msg') || '';
		img.alt            = 'Welcome-back package for ' + who.textContent;
		/* Set last: assigning src starts the fetch, and on a row whose
		   thumbnail is already cached this completes before the rest of
		   the dialog would otherwise be filled in. */
		img.src            = btn.getAttribute('data-img') || '';
		copy.textContent   = 'Copy link';
		copyM.textContent  = 'Copy message';
		copyI.textContent  = 'Copy image';
		copyI.disabled     = false;
		copyN.textContent  = 'Copy name';
		wrap.hidden = false;
		copyM.focus();
	}
	function close() {
		wrap.hidden = true;
		at = -1;
		/* Stop the fetch and release the picture; reopening re-reads it
		   from cache. */
		img.removeAttribute('src');
		if (last) { last.focus(); last = null; }
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.lap-giftbtn') : null;
		if (btn) { e.preventDefault(); open(btn); return; }
		/* The backdrop is the wrapper itself; a click inside the panel
		   must not close it. */
		if (e.target === wrap || e.target.id === 'lapModalX') close();
	});
	function step(by) {
		if (at < 0) return;
		var n = at + by;
		if (n < 0 || n >= all.length) return;
		/* Bring the row into view too, so closing the modal leaves the
		   table where you were rather than back at the top. */
		if (all[n].scrollIntoView) all[n].scrollIntoView({ block: 'center' });
		open(all[n]);
	}
	/*
	 * MARK, THEN MOVE. The row is only updated once the server has
	 * confirmed the write -- showing it as done before that would
	 * quietly lose people from a list whose whole job is not losing
	 * anybody. Advancing is also skipped on failure, so the error is
	 * in front of you rather than two rows back.
	 */
	doneB.addEventListener('click', function () {
		if (at < 0 || !last) return;
		var btn = last,
		    turningOff = btn.getAttribute('data-sent') === '1',
		    body = 'discord_id=' + encodeURIComponent(btn.getAttribute('data-did') || '');
		if (turningOff) body += '&off=1';
		doneB.disabled = true;
		doneB.textContent = 'Saving...';
		fetch('ajax/admin-outreach.php', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (r) { return r.json(); }).then(function (d) {
			if (!d || !d.ok) throw new Error('refused');
			btn.setAttribute('data-sent', turningOff ? '' : '1');
			if (turningOff) { open(btn); return; }          // stay put, just flip the label
			if (at < all.length - 1) step(1); else close();
		}).catch(function () {
			doneB.disabled = false;
			doneB.textContent = 'Could not save';
			setTimeout(function () { if (last) open(last); }, 2200);
		});
	});

	prevB.addEventListener('click', function () { step(-1); });
	nextB.addEventListener('click', function () { step(1); });

	document.addEventListener('keydown', function (e) {
		if (wrap.hidden) return;
		if (e.key === 'Escape') { close(); return; }
		/* Not while typing or selecting inside a field. */
		var t = e.target && e.target.tagName;
		if (t === 'INPUT' || t === 'TEXTAREA') return;
		if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
		if (e.key === 'ArrowLeft')  { e.preventDefault(); step(-1); }
		/* d for done, so the loop never needs the mouse. */
		if (e.key === 'd' || e.key === 'D') { e.preventDefault(); doneB.click(); }
	});

	/* One handler, both fields. execCommand is deprecated but is the
	   only thing that works without a secure context or a permission
	   prompt, so it is the fallback rather than the first choice. */
	function wire(field, btn, label) {
		btn.addEventListener('click', function () {
			field.select();
			field.setSelectionRange(0, field.value.length);   // iOS needs the range
			function done() {
				btn.textContent = 'Copied';
				setTimeout(function () { btn.textContent = label; }, 1600);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(field.value).then(done, function () {
					try { document.execCommand('copy'); done(); } catch (err) {}
				});
			} else {
				try { document.execCommand('copy'); done(); } catch (err) {}
			}
		});
	}
	wire(msg, copyM, 'Copy message');
	wire(url, copy, 'Copy link');
	wire(name, copyN, 'Copy name');

	/* ------------------------------------------------------------------
	 * THE FOLLOW-UP DIALOG.
	 *
	 * Same shape as the package one above -- open, step, mark, close --
	 * against its own button list and its own log. It shares wire(), which
	 * is why it lives in this scope rather than its own block; everything
	 * else is separate on purpose, because the two dialogs do different
	 * jobs and folding them together would make every change to either
	 * require reasoning about the other.
	 * ------------------------------------------------------------------ */
	(function () {
		var aw   = document.getElementById('lapAsk');
		if (!aw) return;                       // lapsed view: no Came back table
		var aWho = document.getElementById('lapAskWho'),
		    aWhy = document.getElementById('lapAskWhy'),
		    aMsg = document.getElementById('lapAskMsg'),
		    aCp  = document.getElementById('lapAskCopyMsg'),
		    aNm  = document.getElementById('lapAskName'),
		    aCn  = document.getElementById('lapAskCopyName'),
		    aPv  = document.getElementById('lapAskPrev'),
		    aNx  = document.getElementById('lapAskNext'),
		    aPos = document.getElementById('lapAskPos'),
		    aDn  = document.getElementById('lapAskDone'),
		    aAll = Array.prototype.slice.call(document.querySelectorAll('.lap-askbtn')),
		    aAt  = -1, aLast = null;

		wire(aMsg, aCp, 'Copy message');
		wire(aNm,  aCn, 'Copy name');

		function why(btn) {
			/* Read off the row rather than passed down separately: the two
			   cells are already rendered and a third copy of the same facts
			   is a third thing that can disagree with them. */
			var tr = btn.closest ? btn.closest('tr') : null;
			if (!tr) return '';
			var td = tr.querySelectorAll('td'),
			    built = td[3] ? td[3].textContent.trim() : '',
			    seen  = td[4] ? td[4].textContent.trim() : '';
			return 'Built since: ' + (built || '?') + '  ·  Since then: ' + (seen || '?');
		}
		function aOpen(btn) {
			aLast = btn;
			aAt = aAll.indexOf(btn);
			aPos.textContent = (aAt >= 0) ? (aAt + 1) + ' of ' + aAll.length : '';
			aPv.disabled = (aAt <= 0);
			aNx.disabled = (aAt < 0 || aAt >= aAll.length - 1);
			var sent = btn.getAttribute('data-sent') === '1';
			aDn.setAttribute('data-on', sent ? '1' : '');
			aDn.textContent = sent ? 'Asked (undo)' : 'Mark asked & next';
			aDn.disabled = false;
			aWho.textContent = btn.getAttribute('data-who') || '';
			aNm.value        = btn.getAttribute('data-who') || '';
			aMsg.value       = btn.getAttribute('data-msg') || '';
			aWhy.textContent = why(btn);
			aCp.textContent  = 'Copy message';
			aCn.textContent  = 'Copy name';
			aw.hidden = false;
			aCp.focus();
		}
		function aClose() {
			aw.hidden = true; aAt = -1;
			if (aLast) { aLast.focus(); aLast = null; }
		}
		function aStep(by) {
			if (aAt < 0) return;
			var n = aAt + by;
			if (n < 0 || n >= aAll.length) return;
			if (aAll[n].scrollIntoView) aAll[n].scrollIntoView({ block: 'center' });
			aOpen(aAll[n]);
		}
		document.addEventListener('click', function (e) {
			var b = e.target.closest ? e.target.closest('.lap-askbtn') : null;
			if (b) { e.preventDefault(); aOpen(b); return; }
			if (e.target === aw || e.target.id === 'lapAskX') aClose();
		});
		aPv.addEventListener('click', function () { aStep(-1); });
		aNx.addEventListener('click', function () { aStep(1); });
		document.addEventListener('keydown', function (e) {
			if (aw.hidden) return;
			if (e.key === 'Escape')     { aClose(); return; }
			if (e.key === 'ArrowLeft')  { e.preventDefault(); aStep(-1); }
			if (e.key === 'ArrowRight') { e.preventDefault(); aStep(1); }
		});

		/*
		 * MARK, THEN MOVE -- and only once the server has confirmed it,
		 * for the same reason the package dialog does: a row shown as done
		 * before the write lands quietly drops somebody off a list whose
		 * whole job is not losing anybody.
		 */
		aDn.addEventListener('click', function () {
			if (aAt < 0) return;
			var btn = aAll[aAt],
			    did = btn.getAttribute('data-did'),
			    on  = aDn.getAttribute('data-on') !== '1',
			    body = 'discord_id=' + encodeURIComponent(did)
			         + '&list=' + encodeURIComponent('<?php echo OUTREACH_LIST_ASK; ?>')
			         + (on ? '' : '&off=1');
			aDn.disabled = true;
			aDn.textContent = on ? 'Marking...' : 'Undoing...';
			fetch('ajax/admin-outreach.php', {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			}).then(function (r) { return r.json(); }).then(function (j) {
				if (!j || !j.ok) throw new Error((j && j.why) || 'failed');
				btn.setAttribute('data-sent', on ? '1' : '');
				btn.textContent = on ? 'asked' : 'ask';
				btn.title = on ? 'Already asked' : 'Ask why';
				btn.classList.toggle('on', on);
				if (on && aAt < aAll.length - 1) aStep(1); else aOpen(btn);
			}).catch(function () {
				aDn.disabled = false;
				aDn.textContent = 'Could not save';
				setTimeout(function () {
					aDn.textContent = aDn.getAttribute('data-on') === '1'
						? 'Asked (undo)' : 'Mark asked & next';
				}, 2000);
			});
		});
	})();

	/*
	 * COPYING THE PICTURE, which is the thing that actually goes in the
	 * message. Three constraints shape this:
	 *
	 * THE PROMISE FORM OF ClipboardItem IS REQUIRED, not a convenience.
	 * Awaiting the fetch first and then writing loses the user gesture
	 * in Safari and the write is refused. Handing ClipboardItem the
	 * unresolved promise keeps the click attached to it.
	 *
	 * THE BLOB'S TYPE MUST MATCH THE KEY EXACTLY. A server that labels
	 * the render anything but image/png would make write() throw, so
	 * the type is re-stamped with slice(), which does not copy data.
	 *
	 * AND IT NEEDS A SECURE CONTEXT. navigator.clipboard is undefined
	 * on plain http, so the link below stays as the fallback rather
	 * than the button silently doing nothing.
	 */
	copyI.addEventListener('click', function () {
		/* THE PUBLIC RENDER, NOT THE ENDPOINT. Both are same-origin and
		   both work, but the dhcrenders file is static, is already in
		   the browser cache from the picture above, and skips the
		   redirect and the admin check. The endpoint is the fallback
		   for the moment before the link is known. */
		var src = url.value || img.getAttribute('src');
		if (!src) return;
		function fail(why) {
			copyI.textContent = why || 'Could not copy';
			setTimeout(function () { copyI.textContent = 'Copy image'; }, 2600);
		}
		if (!navigator.clipboard || !navigator.clipboard.write || !window.ClipboardItem) {
			fail('Not supported here');
			return;
		}
		copyI.textContent = 'Copying...';
		try {
			var blobP = fetch(src, { credentials: 'same-origin' })
				.then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.blob(); })
				.then(function (b) {
					return b.type === 'image/png' ? b : b.slice(0, b.size, 'image/png');
				});
			navigator.clipboard.write([new ClipboardItem({ 'image/png': blobP })]).then(
				function () {
					copyI.textContent = 'Copied';
					setTimeout(function () { copyI.textContent = 'Copy image'; }, 1600);
				},
				function () { fail(); }
			);
		} catch (e) { fail(); }
	});
})();
</script>
