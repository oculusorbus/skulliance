<?php
/**
 * TRAIT DROPS FOR DROP SHIP AND OCULUS LOUNGE.
 *
 * THE RULE, which is not the rule the other nine games use: a run drops a
 * trait only when it TAKES OR TIES THE LEAD of the current game, and only
 * when it also beats that player's own previous score in the same game. No
 * drop for a run that places second, and none for a run that does not improve
 * on your own. See the note beside 'dropship' in dhcfighters-config.php for
 * why these two invert the platform's usual "a genuine attempt qualifies".
 *
 * WHY BOTH GATES. Taking the lead alone is farmable here, because logScore()
 * REPLACES your row rather than keeping your best: score 1000, then 10 (you
 * are still top of a board with only your own 10 on it), then 1000 again, and
 * the third run has "taken the lead" from yourself. Requiring the run to beat
 * your own previous score closes that. A player who scores well, badly, then
 * well in a rush does collect twice -- the owner's explicit call, since a
 * deliberately bad run risks their actual position, which is deterrent enough.
 *
 * NOTHING HERE MAY DISTURB A SCORE SAVE. Every failure path returns quietly:
 * no linked account, Skulliance database unreachable, library missing, a
 * throw from anywhere. A trait is a bonus; the score is the game. Same
 * fail-quiet contract dropshipConnectedStakeAddresses() already follows.
 *
 * WHY THE READ IS SPLIT FROM THE AWARD. logScore() DELETES the player's
 * existing row before inserting the new one, so "their previous score" stops
 * existing partway through. dropshipDropContext() has to run BEFORE that
 * delete and dropshipDropAward() AFTER the insert succeeds -- awarding before
 * would hand out a trait for a score that failed to save.
 */

/**
 * The two numbers the rule needs, read before logScore() destroys one of them.
 *
 * 'prev'  the player's own score in this game, or null if this is their first
 *         run of it.
 * 'rival' the best score anybody ELSE has in this game, or null if nobody has
 *         played it yet.
 */
function dropshipDropContext($conn) {
	$ctx = array('ok' => false, 'prev' => null, 'rival' => null);
	if (!isset($_SESSION['userData']['game_id'],
	           $_SESSION['userData']['dropship_user_id'],
	           $_SESSION['userData']['dropship_project_id'])) {
		return $ctx;
	}
	$game    = (int)$_SESSION['userData']['game_id'];
	$user    = (int)$_SESSION['userData']['dropship_user_id'];
	$project = (int)$_SESSION['userData']['dropship_project_id'];
	if ($game <= 0 || $user <= 0) return $ctx;

	try {
		/* MAX, not "the row", because nothing guarantees one row per player --
		   deleteResult() only removes the one it finds. If a duplicate ever
		   exists, the player's best is the honest thing to have to beat. */
		$r = $conn->query("SELECT MAX(score) AS s FROM results
		                    WHERE game_id = $game AND user_id = $user AND project_id = $project");
		if ($r && ($row = $r->fetch_assoc()) && $row['s'] !== null) $ctx['prev'] = (int)$row['s'];

		/* Everyone EXCEPT this player. Comparing against a board that includes
		   their own standing score would mean their own previous run blocks
		   them from ever taking the lead again. */
		$r = $conn->query("SELECT MAX(score) AS s FROM results
		                    WHERE game_id = $game AND user_id <> $user AND project_id = $project");
		if ($r && ($row = $r->fetch_assoc()) && $row['s'] !== null) $ctx['rival'] = (int)$row['s'];

		$ctx['ok'] = true;
	} catch (Throwable $e) {
		error_log('dropship trait drop: context read failed -- ' . $e->getMessage());
		return array('ok' => false, 'prev' => null, 'rival' => null);
	}
	return $ctx;
}

/**
 * Award the trait, if the run earned one. Call only after the score saved.
 *
 * Returns the awarded trait, or null. Callers ignore it; it is returned so the
 * harness can drive this without a database of its own.
 */
/**
 * Did this run earn a trait? The whole rule, and nothing else.
 *
 * Separated from the award so it can be driven directly -- the award opens the
 * platform database and writes a ledger row, and the part worth testing is
 * this.
 */
function dropshipDropEarned($ctx, $score) {
	if (empty($ctx['ok'])) return false;
	$score = (int)$score;

	/* TOOK OR TIED THE LEAD. An empty board (no rival) is a lead by default,
	   which is the point: the first player into a new game is rewarded for
	   turning up first. */
	if ($ctx['rival'] !== null && $score < $ctx['rival']) return false;

	/* AND BEAT THEIR OWN. First run of the game has nothing to beat.
	   A STANDING LEADER WHO IMPROVES DOES EARN ONE -- they beat the board and
	   they beat themselves. Three rising runs in a day therefore pay three
	   times, and the per-source daily cap in dhcf_award() is what bounds
	   that, not this rule. */
	if ($ctx['prev'] !== null && $score <= $ctx['prev']) return false;

	return true;
}

function dropshipDropAward($ctx) {
	$score = isset($_SESSION['userData']['score']) ? (int)$_SESSION['userData']['score'] : 0;
	if (!dropshipDropEarned($ctx, $score)) return null;

	/*
	 * WHICH GAME THE DROP CAME FROM, read off the theme rather than off a
	 * project id. $source is a key into the registry in
	 * dhcfighters-config.php, which supplies the label, the X tag and the
	 * link -- and those keys are the theme slug with the hyphens taken out
	 * ('oculus-lounge' -> 'oculuslounge'). Deriving it means a new theme
	 * gets its own credit by being registered, with no edit here; a theme
	 * nobody registered falls back to Drop Ship, which is both true and the
	 * only safe answer, since an unknown key would credit nobody.
	 */
	/*
	 * WHICH GAME THE DROP CAME FROM, read off the theme rather than off a
	 * project id. $source is a key into the game registry in
	 * dhcfighters-config.php, which supplies the label, the X tag and the
	 * link, and those keys are the theme slug with the hyphens taken out
	 * ('oculus-lounge' -> 'oculuslounge'). Deriving it means a new theme is
	 * credited by being registered there, with no edit here.
	 *
	 * CHECKED AGAINST THE REGISTRY BELOW, once it is loaded -- an unknown
	 * key would post a drop crediting nobody, and the per-source daily cap
	 * is keyed on this too.
	 */
	$project = (int)$_SESSION['userData']['dropship_project_id'];
	$source  = 'dropship';
	if (function_exists('dropshipThemeConfig') && isset($GLOBALS['conn'])) {
		$cfg = dropshipThemeConfig($GLOBALS['conn'], $project);
		if ($cfg && !empty($cfg['slug'])) {
			$slug = str_replace('-', '', dropshipThemeSlug($cfg['slug']));
			if ($slug !== '') $source = $slug;
		}
	}

	/* The Skulliance user id, straight off the shared session: Drop Ship runs
	   on the same session as the platform (see the restore at the top of
	   dropship/db.php) and refuses anyone who is not logged in, so this is
	   present for every real player. No discord_id round trip needed. */
	$uid = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
	if ($uid <= 0) return null;

	$drop = null;
	/*
	 * BUFFERED FOR THE WHOLE AWARD, not tidiness.
	 *
	 * This runs in the middle of dropship.php rendering the game, and
	 * display_errors is on platform-wide. Any warning out of this chain --
	 * the credentials include, a mysqli connect notice, anything the library
	 * emits -- would print into the page the player is looking at. The
	 * platform's own dhcf_award() buffers its notifier call for exactly this
	 * reason. Proven, not assumed: a missing credentials file printed a
	 * two-line warning into the output before this was here.
	 */
	ob_start();
	try {
		/*
		 * MUST BE SET BEFORE THE PLATFORM LIBRARY LOADS.
		 *
		 * Awarding posts the drop in the DHC Fighters channel, which means
		 * loading the platform's webhooks.php from inside a Drop Ship
		 * request. That file normally declares discordmsg(), and Drop Ship
		 * declares its own a moment later -- dashboard.php loads db.php, then
		 * dropship.php (where the score is written, so where we are now),
		 * then its own webhooks.php -- which would be a fatal "cannot
		 * redeclare" and would take the dashboard down, not just the drop.
		 *
		 * SKL_WEBHOOKS_NO_ALIAS skips that declaration, so the platform's
		 * sender arrives only as skl_discordmsg() -- a name Drop Ship does
		 * not use -- and Drop Ship's own discordmsg() still loads normally
		 * afterwards. It is the only name that collides between the two apps.
		 */
		if (!defined('SKL_WEBHOOKS_NO_ALIAS')) define('SKL_WEBHOOKS_NO_ALIAS', true);

		/*
		 * AND NO ANNOUNCEMENT WHILE getWebhook() IS STILL SHARED.
		 *
		 * SKL_WEBHOOKS_NO_ALIAS solved the discordmsg() collision. It is
		 * not the only one: BOTH credentials files declare a getWebhook(),
		 * Skulliance's taking no arguments and Drop Ship's taking a project
		 * id, and loading the notifier pulls the platform's in. This runs
		 * during the dropship.php include, which is BEFORE dashboard.php
		 * includes Drop Ship's own webhooks.php -- so that later include
		 * became "Cannot redeclare getWebhook()" and took the whole play
		 * page down with it, for every project, the moment anybody earned
		 * a drop.
		 *
		 * The fix is a rename in dropship/credentials/webhooks_credentials.php
		 * (to dropshipGetWebhook), which this file cannot make -- credentials
		 * are not in the repo. So: announce only once that rename has
		 * happened. The DROP IS STILL AWARDED either way; what is skipped is
		 * the post in the DHC Fighters channel, which is worth losing and a
		 * fatal on the play page is not. It starts working by itself the
		 * moment the rename lands.
		 */
		if (!function_exists('dropshipGetWebhook') && !defined('DHCF_NO_NOTIFY')) {
			define('DHCF_NO_NOTIFY', true);
		}
		require_once __DIR__ . '/../dhcfighters-lib.php';
		if (!function_exists('dhcf_award')) return null;

		/* The registry is loaded now, so the derived key can be checked.
		   A theme nobody has registered posts as Drop Ship: the drop still
		   lands, and crediting the game this one is a skin of is truer than
		   crediting a key with no label, no tag and no link. */
		if ($source !== 'dropship'
		    && (!function_exists('dhcf_game') || !dhcf_game($source))) {
			$source = 'dropship';
		}

		/* The platform database. Drop Ship's $conn is a different one
		   entirely, and dropshipSkullianceDb() is the only place the
		   Skulliance credentials are read -- see that file for why
		   including them anywhere else is a trap. */
		require_once __DIR__ . '/dropship-skulliance-db.php';
		$sk = dropshipSkullianceDb();
		if (!$sk) { ob_end_clean(); return null; }

		$detail = $source . ' ' . $score . ' (lead)';
		/* Announced in the DHC Fighters channel like every other drop:
		   dhcf_award() calls the notifier itself. */
		$drop = dhcf_award($sk, $uid, 'wildcard', $source, $detail, DHCF_TIERS['placement_1']);
		$sk->close();
	} catch (Throwable $e) {
		/* A trait is a bonus and the score is already saved. Never let this
		   surface to the player mid-game. */
		ob_end_clean();
		error_log('dropship trait drop: award failed -- ' . $e->getMessage());
		return null;
	}
	ob_end_clean();
	return $drop;
}
