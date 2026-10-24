<?php
/**
 * lib/return-gift.php -- a welcome-back package, derived not stored.
 *
 * One trait of every category for a lapsed member who signs back in:
 * background, torso, head, arms, weapon, headgear, effects and a
 * companion. Not three nice pictures -- a complete, finished Fighter
 * they can assemble on the page they land on.
 *
 * DERIVED FROM THE DISCORD ID, WHICH IS WHY THERE IS NO TABLE. The
 * package is a pure function of who they are, so it can be previewed
 * in the admin, pasted into a DM, and handed over weeks later without
 * anything being reserved, expired or reconciled. The preview cannot
 * disagree with what they receive because both are the same
 * calculation.
 *
 * RENDEZVOUS HASHING, NOT MODULO. The obvious version is
 * hash(id) % count(slugs), and it is subtly wrong: adding one trait to
 * a category changes the count and reshuffles EVERY player's pick. A
 * preview sent on Monday would be a different package by Friday, which
 * breaks the one promise the DM makes. Here each slug is scored by
 * hash(id|category|slug) and the lowest wins, so adding a trait only
 * changes somebody's result if the new slug happens to score lower --
 * roughly one player in N rather than all of them.
 *
 * COMMON AND UNCOMMON ONLY. A welcome-back present must not mint
 * mythics. The rarest tiers drive Fighter scoring and the Drop Ship
 * armour curve, and handing them out for signing in would devalue
 * every one somebody actually earned.
 *
 * ONCE PER LAPSE, NOT ONCE EVER, recorded as the trait rows
 * themselves in dhc_trait_drops under source 'returngift' -- no marker
 * row, no new table and no schema change. Signing in sets the distance
 * back to zero, so qualifying again means genuinely going another full
 * month without turning up; somebody willing to do that repeatedly to
 * farm common traits has chosen a remarkably slow way to do it.
 *
 * EVERYONE WHO COMES BACK, NOT EVERYONE WHO WAS INVITED. The owner's
 * lapsed panel is a list of people to write to personally; this is not
 * gated on it. Somebody who drifts back on their own after a month
 * gets the same package the DM would have promised, because the thing
 * being fixed is "there is nothing to do on arrival", and that is true
 * whether or not anybody wrote to them.
 *
 * EVERY READ FAILS CLOSED. A question that cannot be answered reads as
 * already-claimed, so the feature degrades to silence rather than to
 * handing out traits on repeat.
 */

require_once __DIR__ . '/../dhcfighters-lib.php';

/*
 * ONE OF EVERY CATEGORY. This started as the three slots a minimum
 * legal Fighter needs (DHCF_REQUIRED) and was deliberately widened:
 * the three-trait version got somebody to a plain Fighter, and the
 * point of the package is that coming back feels like a win. Eight
 * means they can build a FINISHED one -- armed, with a companion, with
 * effects -- on the page they land on.
 *
 * WRITTEN OUT RATHER THAN READ FROM THE RARITY TABLE, so adding a
 * ninth category is a decision somebody makes instead of a giveaway
 * that quietly grows. return-gift-harness.php compares this list
 * against the rarity table and fails when they diverge, so the
 * decision cannot be skipped by accident.
 */
if (!defined('RETURN_GIFT_SLOTS'))  define('RETURN_GIFT_SLOTS',
	'background,torso,head,arms,weapon,headgear,effects,companion');
/** How long away before signing in earns one. */
if (!defined('RETURN_GIFT_DAYS'))   define('RETURN_GIFT_DAYS', 30);
/** Tiers a gift may draw from. */
if (!defined('RETURN_GIFT_TIERS'))  define('RETURN_GIFT_TIERS', 'common,uncommon');
/** The one switch. Off makes return_gift_due() answer no, everywhere. */
if (!defined('RETURN_GIFT_ENABLED')) define('RETURN_GIFT_ENABLED', true);

/*
 * HOW THE RENDER IS ADDRESSED. Serial 0 is a namespace no saved Fighter
 * can occupy -- dhcf_next_serial() starts at 1 -- so a package can never
 * collide with somebody's real build. 500 is the unsuffixed size in
 * dhcf_render_fighter()'s cache, which is what Discord and the Arena
 * already ask for.
 */
if (!defined('RETURN_GIFT_SERIAL')) define('RETURN_GIFT_SERIAL', 0);
if (!defined('RETURN_GIFT_SIZE'))   define('RETURN_GIFT_SIZE', 500);

/**
 * The public address of the rendered package, WITHOUT rendering it.
 *
 * The admin list needs a paste-able link on every row, and rendering
 * four hundred Fighters to find out where they live is not a page load.
 * The filename is a pure function of the traits, so it can be computed;
 * the picture starts existing when something asks the endpoint for it.
 *
 * THIS RESTATES dhcf_render_fighter()'s NAMING, which is a duplication
 * and therefore a drift hazard: change the scheme there and every link
 * here becomes a 404 with nothing to notice it. lapsed-harness.php runs
 * both and fails on any disagreement, so the copy cannot rot quietly.
 */
function return_gift_url($discord_id) {
	$traits = return_gift_layout(return_gift_traits($discord_id));
	if (!$traits) return '';
	$name = 'f' . (int)RETURN_GIFT_SERIAL . '-' . substr(md5(json_encode($traits)), 0, 8)
	      . (RETURN_GIFT_SIZE === 500 ? '' : '@' . RETURN_GIFT_SIZE) . '.png';
	return 'https://skulliance.io/staking/dhcrenders/' . $name;
}

/**
 * Does $slug in $cat clash with something already allocated?
 *
 * ONE HOME FOR EVERY PAIRING RULE, because the platform has three and
 * they live in three different places -- a head that refuses headgear,
 * a head that refuses a companion, and a weapon drawn against the
 * torso's own arms. dhcf_save_fighter() refuses a Fighter on any of
 * them, and dhcf_layers() silently declines to draw the offending
 * piece, so a package that ignores them hands somebody a trait that
 * cannot be worn and does not appear.
 *
 * THE REAL FUNCTIONS, NOT A RESTATEMENT OF THE LISTS. Those tables get
 * edited; a copy of them here would be wrong the first time somebody
 * adds a head.
 */
function return_gift_clashes($cat, $slug, $chosen) {
	$head = isset($chosen['head']) ? $chosen['head'] : '';
	$arms = isset($chosen['arms']) ? $chosen['arms'] : '';
	if ($cat === 'headgear'  && function_exists('dhcf_headgear_blocked')) {
		return dhcf_headgear_blocked($head, $slug);
	}
	if ($cat === 'companion' && function_exists('dhcf_companion_blocked')) {
		return dhcf_companion_blocked($head, $slug);
	}
	if ($cat === 'weapon'    && function_exists('dhcf_arms_weapon_clash')) {
		return dhcf_arms_weapon_clash($arms, $slug);
	}
	return false;
}

/**
 * The package keyed by LAYER SLOT rather than by category, which is
 * what every renderer actually reads.
 *
 * THIS IS WHY THE EFFECTS NEVER SHOWED UP. dhcf_slots() has no slot
 * called 'effects' -- it has effects1 and effects2 -- and both
 * dhcf_clean_traits() and dhcf_layers() walk the SLOT list and ignore
 * any key that is not one. So an 'effects' key was granted, written to
 * the ledger correctly, and then silently dropped by everything that
 * draws, with no error anywhere.
 *
 * Derived from dhcf_slots() rather than written out: a slot whose name
 * is the category wins (weapon, not weaponBack), otherwise the first
 * slot of that category (effects1). Add a slot upstream and this
 * follows.
 */
function return_gift_layout($traits) {
	if (!function_exists('dhcf_slots')) return $traits;
	$slot_for = array();
	foreach (dhcf_slots() as $slot) {
		$cat = dhcf_slot_category($slot);
		if ($slot === $cat) { $slot_for[$cat] = $slot; continue; }
		if (!isset($slot_for[$cat])) $slot_for[$cat] = $slot;
	}
	$out = array();
	foreach ($traits as $cat => $slug) {
		$out[isset($slot_for[$cat]) ? $slot_for[$cat] : $cat] = $slug;
	}
	return $out;
}

/**
 * The package for this person: category => slug.
 *
 * Pure. Same answer every time, on any server, with no state read or
 * written. Returns array() if the rarity table cannot be loaded.
 */
function return_gift_traits($discord_id) {
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	if ($id === '') return array();
	$rarity = dhcf_rarity();
	if (!$rarity) return array();

	$tiers = array_flip(explode(',', RETURN_GIFT_TIERS));
	$out   = array();
	foreach (explode(',', RETURN_GIFT_SLOTS) as $cat) {
		if (empty($rarity[$cat])) continue;
		$best = ''; $bestScore = '';
		foreach ($rarity[$cat] as $slug => $info) {
			/* $info is [tier, count, rate]. */
			if (!isset($tiers[$info[0]])) continue;
			/*
			 * A piece that cannot be worn with what is already in the
			 * package is not a candidate. Dropped from the POOL rather
			 * than from the package, so the next-best legal piece wins
			 * and the member still gets one of everything -- the whole
			 * point of the eight-slot version. A category with no legal
			 * candidate left is omitted, which is the only case where a
			 * package comes back short.
			 *
			 * ORDER MATTERS AND IS NOT ALPHABETICAL. background, torso
			 * and head come first in RETURN_GIFT_SLOTS because they are
			 * DHCF_REQUIRED and must never be the piece that yields;
			 * headgear, companion and weapon are decoration and give way
			 * to the head and arms already chosen.
			 */
			if (return_gift_clashes($cat, $slug, $out)) continue;
			$score = hash('sha256', $id . '|' . $cat . '|' . $slug);
			/* String compare: both are 64 hex chars, so this orders them
			   exactly as the numbers would, without touching floats. */
			if ($bestScore === '' || strcmp($score, $bestScore) < 0) {
				$bestScore = $score; $best = $slug;
			}
		}
		if ($best !== '') $out[$cat] = $best;
	}
	return $out;
}

/**
 * Which categories this person was given IN THE CURRENT LAPSE, as a
 * set. Returns null when the question cannot be answered.
 *
 * READ FROM THE TRAIT LEDGER, NOT FROM A MARKER ROW. The first version
 * wrote action = 'claimed' into the nudges table, and that table has
 * only ever held five values -- shown, clicked, snoozed, rested, none.
 * If the column is an ENUM of those, a sixth either fails the INSERT
 * (no gift, ever, silently) or in a non-strict server is coerced to
 * '' -- and then the claim check never matches and the package is
 * handed out again on EVERY login, forever. Neither failure announces
 * itself.
 *
 * dhc_trait_drops already records exactly this, in a `source` column
 * that holds a dozen different free-form values written by code, and
 * it is the actual evidence rather than a note about it. No new table,
 * no new column, no schema change anywhere.
 *
 * WINDOWED TO RETURN_GIFT_DAYS, WHICH IS WHAT MAKES IT REPEATABLE.
 * Looking at the whole history would mean one package ever; looking at
 * the last 30 days means a member who lapses a second time qualifies
 * again, because a repeat lapse is BY DEFINITION 30 days of not
 * signing in, so the previous package is necessarily older than the
 * window. The same read also stops a double grant inside one visit:
 * the package handed over seconds ago is inside the window, so a
 * second request in the same session sees it and declines.
 */
function return_gift_granted($conn, $user_id) {
	$uid = (int)$user_id;
	if ($uid <= 0) return null;
	$days = max(1, (int)RETURN_GIFT_DAYS);
	$r = @$conn->query("SELECT category FROM dhc_trait_drops
	                     WHERE user_id = $uid AND source = 'returngift'
	                       AND awarded_at > DATE_SUB(NOW(), INTERVAL $days DAY)");
	if (!$r) return null;
	$out = array();
	while ($row = $r->fetch_assoc()) $out[$row['category']] = true;
	return $out;
}

/** Has this person already had one during this lapse? */
function return_gift_claimed($conn, $user_id) {
	$had = return_gift_granted($conn, $user_id);
	/* Unanswerable means we cannot prove they have NOT had one, and
	   handing out duplicates forever is worse than handing out none. */
	if ($had === null) return true;
	return count($had) > 0;
}

/**
 * Should this sign-in earn a package?
 *
 * $prev_login is last_login AS IT WAS before this request updated it --
 * skulliance.php already reads that value for the invite payout, and
 * asking afterwards makes every returning player look active.
 */
function return_gift_due($conn, $user_id, $prev_login) {
	if (!RETURN_GIFT_ENABLED) return false;
	if (empty($prev_login)) return false;
	$was = strtotime((string)$prev_login);
	if (!$was) return false;
	if ((time() - $was) < RETURN_GIFT_DAYS * 86400) return false;
	return !return_gift_claimed($conn, $user_id);
}

/**
 * ONE POST, NOT EIGHT.
 *
 * dhcf_commit_drop() announces every trait it commits, which is right
 * for a drop that fell out of a game and wrong for a package of eight
 * arriving at once -- that is a wall of embeds, and it reads as a bug
 * rather than a welcome. So the per-trait announcements are suppressed
 * and this goes out instead, carrying the assembled package as its
 * picture. The render is the same file the admin preview links to, so
 * the post and any DM sent beforehand show the same Fighter.
 *
 * Swallows everything. A present is not worth a white page, and the
 * traits are already committed by the time this runs.
 */
function return_gift_announce($conn, $user_id, $traits, $discord_id) {
	try {
		/*
		 * LOAD THE NOTIFIER OURSELVES. It is normally pulled in by
		 * dhcf_commit_drop()'s announcement branch -- the exact branch
		 * this package skips by passing $announce false. So on the real
		 * login path neither skl_discordmsg() nor dhcf_author_block()
		 * existed, both guards below returned early, and the welcome-back
		 * post silently never fired. Nothing errored and nothing logged;
		 * the traits arrived and the announcement did not.
		 *
		 * Buffered around the require, because it pulls in webhooks.php
		 * and its credentials file, and this runs inside skulliance.php
		 * on every signed-in request including AJAX endpoints.
		 */
		if (defined('DHCF_NO_NOTIFY') && DHCF_NO_NOTIFY) return;
		if (!function_exists('skl_discordmsg') && is_file(__DIR__ . '/../dhcfighters-notify.php')) {
			ob_start();
			require_once __DIR__ . '/../dhcfighters-notify.php';
			ob_end_clean();
		}
		if (!function_exists('skl_discordmsg')) return;
		if (!function_exists('dhcf_author_block')) return;
		list($author, $name, $mention) = dhcf_author_block($conn, $user_id);

		$lines = array();
		foreach ($traits as $cat => $slug) {
			$lines[] = '**' . ucfirst($cat) . '** · ' . dhcf_trait_name($cat, $slug);
		}
		$desc = $name . ' has been away a while. Here is a whole Fighter to come back to: '
		      . 'one trait of every category, ready to assemble.' . "\n\n"
		      . implode("\n", $lines);

		skl_discordmsg(
			'Welcome back, ' . $name,
			$desc,
			return_gift_url($discord_id),      // the assembled package
			'https://skulliance.io/staking/dhcfighters.php',
			'dhcfighters',
			'',
			0x00c8a0,
			$author,
			array('text' => 'Welcome-back package · ' . count($traits) . ' traits'),
			$mention
		);
	} catch (Throwable $e) {
		error_log('return-gift announce: ' . $e->getMessage());
	}
}

/**
 * Hand it over. Returns the slugs granted, or array().
 *
 * THE LEDGER ROW IS WRITTEN FIRST. If the grant half fails partway,
 * the worst case is somebody owed traits; if the claim half failed
 * after granting, they could collect again on the next page load.
 */
function return_gift_grant($conn, $user_id, $discord_id) {
	$uid = (int)$user_id;
	$traits = return_gift_traits($discord_id);
	if ($uid <= 0 || !$traits) return array();

	/*
	 * ONLY WHAT THEY HAVE NOT HAD, which makes the grant self-healing:
	 * if the request died halfway through last time, the next sign-in
	 * finishes the package instead of either skipping it or starting
	 * again. Each trait's own ledger row IS the claim, so there is no
	 * separate marker that can disagree with what was handed over.
	 */
	$had = return_gift_granted($conn, $uid);
	if ($had === null) {
		error_log('return-gift: cannot read the trait ledger, not granting');
		return array();
	}

	$given = array();
	foreach ($traits as $cat => $slug) {
		if (isset($had[$cat])) continue;
		$info = dhcf_trait_info($cat, $slug);
		if (!$info) continue;
		/* The same function every other drop goes through, so the ledger
		   row and the reveal are identical to any other trait. The final
		   false suppresses the PER-TRAIT Discord post only: eight of them
		   in a row is a wall, and one post with the assembled Fighter
		   goes out below instead. */
		if (function_exists('dhcf_commit_drop')) {
			dhcf_commit_drop($conn, $uid, $cat, $slug, $info, 'returngift', 'welcome back', false);
		}
		$given[$cat] = $slug;
	}

	/* After the loop, so the post describes what was actually committed
	   rather than what was intended. */
	if ($given) return_gift_announce($conn, $uid, $given, $discord_id);
	return $given;
}
