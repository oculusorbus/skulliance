<?php
/**
 * return-gift-harness.php — the welcome-back package.
 *
 * Run: php return-gift-harness.php
 *
 * THE FEATURE IS A PROMISE MADE IN A DM. The owner pastes a picture
 * into a personal invitation saying "sign in and this is yours", and
 * the player signs in days later. Three separate pieces of code have
 * to agree for that to be true: the derivation, the thing that
 * renders the preview, and the thing that grants on login. This file
 * runs all three against each other rather than reading them.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}

require_once __DIR__ . '/lib/return-gift.php';
require_once __DIR__ . '/harness-strip.php';

/* ---------------------------------------------------------------- *
 * THE DERIVATION
 * ---------------------------------------------------------------- */
echo "the package is a pure function of the discord id\n";

$ids = array();
for ($i = 0; $i < 400; $i++) $ids[] = (string)(900000000000000000 + $i * 7919);

$packs = array();
foreach ($ids as $id) $packs[$id] = return_gift_traits($id);

ok(count(array_filter($packs)) === count($ids),
   'some ids got no package at all');

/* Same answer twice, which is the whole reason there is no table. */
$stable = true;
foreach (array_slice($ids, 0, 50) as $id) {
	if (return_gift_traits($id) !== $packs[$id]) { $stable = false; break; }
}
ok($stable, 'the same id does not produce the same package twice');

/* Three slots, every time. Fewer is not a Fighter. */
$slots = explode(',', RETURN_GIFT_SLOTS);
$short = 0;
foreach ($packs as $p) {
	foreach ($slots as $c) if (!isset($p[$c])) { $short++; break; }
}
ok($short === 0, "$short package(s) are missing a slot, so they cannot build a Fighter");
printf("  %d slots per package: %s\n", count($slots), RETURN_GIFT_SLOTS);

/*
 * ONE OF EVERY CATEGORY, which is the deal. The list is written out in
 * the library rather than read from the rarity table so that adding a
 * ninth category is a decision; this is the check that makes somebody
 * take it, instead of the giveaway silently growing or silently
 * missing the new one.
 */
$cats_real = array_keys(dhcf_rarity());
$missing   = array_diff($cats_real, $slots);
$unreal    = array_diff($slots, $cats_real);
ok(!$missing, 'the package skips a category that exists: ' . implode(', ', $missing));
ok(!$unreal,  'the package names a category the rarity table does not have: ' . implode(', ', $unreal));

/* Every reveal has to fit in the session queue or some are never seen. */
ok(defined('DHCF_UNSEEN_MAX') && DHCF_UNSEEN_MAX >= count($slots),
   'the reveal queue (' . (defined('DHCF_UNSEEN_MAX') ? DHCF_UNSEEN_MAX : '?')
 . ') is smaller than a package (' . count($slots) . '), so some traits are never shown');

/*
 * TIERS. A welcome-back present that can mint a mythic devalues every
 * one somebody earned, and would be invisible until it happened to
 * somebody. Checked against the real rarity table, every id.
 */
$rarity = dhcf_rarity();
ok(!empty($rarity), 'the rarity table did not load, so nothing below means anything');
/*
 * THE LIST IS WRITTEN OUT HERE, NOT READ FROM RETURN_GIFT_TIERS. The
 * first version took the allowed tiers from the constant and then
 * checked the packages against it, which is a tautology: widening the
 * constant to include 'rare' widened the check at the same time and
 * the mutation sailed through. The invariant is not "matches the
 * setting", it is "a sign-in never mints anything above uncommon".
 */
$allowed = array('common' => 1, 'uncommon' => 1);
ok(RETURN_GIFT_TIERS === 'common,uncommon',
   'RETURN_GIFT_TIERS has been widened past uncommon: ' . RETURN_GIFT_TIERS);
$bad = array();
foreach ($packs as $id => $p) {
	foreach ($p as $cat => $slug) {
		$tier = isset($rarity[$cat][$slug][0]) ? $rarity[$cat][$slug][0] : '?';
		if (!isset($allowed[$tier])) $bad[] = "$cat/$slug ($tier)";
	}
}
ok(!$bad, 'a package contains a tier it must not: ' . implode(', ', array_slice($bad, 0, 4)));

/*
 * SPREAD. Every check above passes if the function returns one fixed
 * package for everybody, which is the most likely way the hashing
 * breaks. Four hundred ids should not collapse onto a handful.
 */
$distinct = count(array_unique(array_map('json_encode', $packs)));
ok($distinct > 100, "only $distinct distinct packages across 400 ids, so the derivation is barely using the id");
printf("  %d distinct packages across %d ids\n", $distinct, count($ids));

/*
 * RENDEZVOUS, NOT MODULO -- the property the whole no-table design
 * rests on. Adding one trait to a category must change almost nobody,
 * because a preview sent on Monday has to still be true on Friday.
 *
 * Driven through the REAL function by giving it a real category minus
 * one slug and then with it restored: removing a trait is the same
 * operation as adding one, seen backwards. Under modulo the count
 * changes and the whole population reshuffles.
 */
$cat = 'background';
$pool = array();
foreach ($rarity[$cat] as $slug => $info) {
	if (isset($allowed[$info[0]])) $pool[] = $slug;
}
function rg_pick($id, $cat, $pool) {
	$best = ''; $bestScore = '';
	foreach ($pool as $slug) {
		$score = hash('sha256', $id . '|' . $cat . '|' . $slug);
		if ($bestScore === '' || strcmp($score, $bestScore) < 0) { $bestScore = $score; $best = $slug; }
	}
	return $best;
}
$full   = array();
$minus  = array();
$shrunk = array_slice($pool, 0, count($pool) - 1);
foreach ($ids as $id) {
	$full[$id]  = rg_pick($id, $cat, $pool);
	$minus[$id] = rg_pick($id, $cat, $shrunk);
}
/* Sanity: the harness's own pick must match the real function, or the
   property below is being proved about the wrong code. */
$agrees = true;
foreach (array_slice($ids, 0, 40) as $id) {
	if ($full[$id] !== $packs[$id][$cat]) { $agrees = false; break; }
}
ok($agrees, 'the harness models the pick differently from return_gift_traits(), so the stability result is meaningless');

$moved = 0;
foreach ($ids as $id) if ($full[$id] !== $minus[$id]) $moved++;
$pct = $moved / count($ids) * 100;
/* One slug of N removed should move roughly 1/N of the population.
   Modulo moves nearly all of it. */
ok($pct < 15, sprintf('removing one trait moved %.1f%% of packages -- that is modulo behaviour, not rendezvous', $pct));
printf("  one trait of %d removed moved %.1f%% of packages\n", count($pool), $pct);

/* ---------------------------------------------------------------- *
 * THE LINK IN THE DM
 * ---------------------------------------------------------------- */
echo "\nthe previewed picture and the rendered one are the same file\n";
/*
 * return_gift_url() RESTATES dhcf_render_fighter()'s filename scheme so
 * the admin list can print four hundred links without compositing four
 * hundred Fighters. That duplication is the whole risk: change the
 * naming in the renderer and every link in every DM 404s, silently.
 * So both are RUN and compared.
 */
require_once __DIR__ . '/dhcfighters-notify.php';
if (!function_exists('imagecreatetruecolor')) {
	echo "  skip  no GD on this build, cannot compare against a real render\n";
} else {
	$have_art = false;
	foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
		if (is_dir(__DIR__ . '/' . $c . '/1000')) { $have_art = true; break; }
	}
	if (!$have_art) {
		echo "  skip  the master art is not on this machine (it ships by FTP)\n";
	} else {
		$mismatch = array();
		foreach (array_slice($ids, 0, 6) as $id) {
			$rendered = dhcf_render_fighter(return_gift_layout($packs[$id]),
			                                RETURN_GIFT_SERIAL, RETURN_GIFT_SIZE);
			$claimed  = return_gift_url($id);
			if ($rendered === '' || $rendered !== $claimed) {
				$mismatch[] = $id . ': link says ' . basename($claimed)
				            . ', renderer wrote ' . ($rendered === '' ? 'nothing' : basename($rendered));
			}
		}
		ok(!$mismatch, 'the DM link does not point at the file the renderer makes: '
		             . implode(' | ', array_slice($mismatch, 0, 2)));
	}
}

/* THE SWITCH. With the feature on, deleting the line that consults
   RETURN_GIFT_ENABLED changes no behaviour at all, so this is the only
   way to pin that the off position still exists and still works. */
$probe = escapeshellarg(
	"define('RETURN_GIFT_ENABLED', false);"
	. "require '" . __DIR__ . "/lib/return-gift.php';"
	/* The stub must answer "no claim on record", or return_gift_claimed()
	   fails closed and the probe passes for the wrong reason -- which it
	   did: deleting the ENABLED line changed nothing because a false from
	   query() already meant "already had one". */
	. "class R { public \$num_rows = 0; }"
	. "class C { public function query(\$s) { return new R; } }"
	. "echo return_gift_due(new C, 7, '2000-01-01 00:00:00') ? 'DUE' : 'NO';");
$out = trim((string)@shell_exec(PHP_BINARY . ' -r ' . $probe . ' 2>/dev/null'));
ok(substr($out, -2) === 'NO',
   'RETURN_GIFT_ENABLED false still hands out packages (got: ' . $out . ')');

/* ---------------------------------------------------------------- *
 * WHAT THE RENDERERS ACTUALLY READ
 * ---------------------------------------------------------------- */
echo "\nevery piece of the package is in a slot something will draw\n";
/*
 * THE BUG THIS EXISTS FOR. The package is keyed by CATEGORY, but
 * dhcf_clean_traits() and dhcf_layers() both walk dhcf_slots() and
 * ignore any key that is not a slot -- and there is no slot called
 * 'effects', only effects1 and effects2. So the effects trait was
 * granted, written to the ledger correctly, and silently never drawn.
 * Nothing errored; the picture was just quietly missing a layer.
 */
$slot_list = dhcf_slots();
$bad_slots = array();
foreach (array_slice($ids, 0, 60) as $id) {
	foreach (return_gift_layout($packs[$id]) as $slot => $slug) {
		if (!in_array($slot, $slot_list, true)) $bad_slots[] = $slot;
	}
}
ok(!$bad_slots, 'the package uses keys that are not layer slots, so they are silently never drawn: '
              . implode(', ', array_unique($bad_slots)));
/* Named explicitly, because this is the one that was wrong. */
$lay = return_gift_layout($packs[$ids[0]]);
ok(isset($lay['effects1']) && !isset($lay['effects']),
   'the effects trait is not mapped onto the effects1 slot, so it will not render');
/* And the layout must not lose or invent anything. */
ok(count($lay) === count($packs[$ids[0]]),
   'the category-to-slot mapping changed how many traits are in the package');

/*
 * THE RENDER MUST ACTUALLY CHANGE when the effects layer is removed.
 * Every check above passes if effects1 is a slot nothing happens to
 * draw -- which is exactly the failure being fixed, one level down.
 */
if (function_exists('imagecreatetruecolor') && is_dir(__DIR__ . '/dhc/web/1000')) {
	$drawn = 0; $tried = 0;
	foreach (array_slice($ids, 0, 3) as $id) {
		$L = return_gift_layout($packs[$id]);
		if (!isset($L['effects1'])) continue;
		$tried++;
		$a = dhcf_render_fighter($L, RETURN_GIFT_SERIAL, RETURN_GIFT_SIZE);
		$n = $L; unset($n['effects1']);
		$b = dhcf_render_fighter($n, RETURN_GIFT_SERIAL, RETURN_GIFT_SIZE);
		if ($a !== '' && $b !== '' && $a !== $b
		    && @file_get_contents(__DIR__ . '/dhcrenders/' . basename($a))
		       !== @file_get_contents(__DIR__ . '/dhcrenders/' . basename($b))) $drawn++;
	}
	ok($tried > 0 && $drawn === $tried,
	   "the effects layer changes nothing in the render ($drawn of $tried), so it is still not being drawn");
} else {
	echo "  skip  no GD or no master art, cannot prove the effects layer draws\n";
}

/* ---------------------------------------------------------------- *
 * PAIRINGS THE PLATFORM REFUSES
 * ---------------------------------------------------------------- */
echo "\nno package contains a combination a Fighter could not be saved with\n";
/*
 * dhcf_save_fighter() refuses three pairings outright and dhcf_layers()
 * silently declines to draw the offending piece. A package that ignores
 * them hands somebody a trait that cannot be worn and does not appear
 * -- the worst shape of all, because the preview and the inventory both
 * look fine.
 *
 * Checked with the REAL functions over every id, not a restatement of
 * the lists, which would go stale the first time a head is added.
 */
$clashes = array();
foreach ($packs as $id => $p) {
	$head = isset($p['head']) ? $p['head'] : '';
	$arms = isset($p['arms']) ? $p['arms'] : '';
	if (isset($p['headgear'])  && dhcf_headgear_blocked($head, $p['headgear'])) {
		$clashes[] = "$head + {$p['headgear']}";
	}
	if (isset($p['companion']) && dhcf_companion_blocked($head, $p['companion'])) {
		$clashes[] = "$head + {$p['companion']}";
	}
	if (isset($p['weapon'])    && dhcf_arms_weapon_clash($arms, $p['weapon'])) {
		$clashes[] = "$arms + {$p['weapon']}";
	}
}
ok(!$clashes, count($clashes) . ' package(s) pair traits that cannot be worn together: '
            . implode(' | ', array_slice(array_unique($clashes), 0, 3)));

/*
 * AND THE FILTER MUST BITE. If none of the 400 ids would have hit a
 * clash anyway, the check above passes on a package that does no
 * filtering at all. Prove the rule actually rejects something by
 * offering it a pairing that is known bad.
 */
ok(return_gift_clashes('companion', 'demon-companion', array('head' => 'beheaded-cyborg')) === true,
   'return_gift_clashes() does not reject a pairing dhcf_save_fighter() refuses');
ok(return_gift_clashes('companion', 'demon-companion', array('head' => 'gas-mask')) === false,
   'return_gift_clashes() rejects a pairing that is perfectly legal');
ok(return_gift_clashes('weapon', 'plastic-blaster', array('arms' => 'robo-crusher-arms')) === true,
   'the arms/weapon clash is not being checked at all');

/* ---------------------------------------------------------------- *
 * THE ANNOUNCEMENT
 * ---------------------------------------------------------------- */
echo "\nthe package is announced once, not once per trait\n";
/*
 * Eight embeds in a row reads as a bug. The per-trait post is
 * suppressed at the commit and one post with the assembled Fighter
 * goes out instead -- and the SUPPRESSION MUST NOT REACH ANY OTHER
 * CALLER, which is why the flag defaults to true.
 */
$rg = harness_strip_comments(file_get_contents(__DIR__ . '/lib/return-gift.php'));
ok(preg_match("~dhcf_commit_drop\(.*'returngift'.*,\s*false\s*\)~", $rg) === 1,
   'the grant still posts one Discord embed per trait');
/* THE CALL, NOT THE DEFINITION. Both of the checks here first matched
   `return_gift_announce($conn` and `return_gift_url($discord_id)`,
   which are also the opening lines of those two functions -- so
   deleting the call sites changed nothing and both mutations passed.
   Anchored on syntax only a call can have. */
ok(preg_match('~if \(\$given\) return_gift_announce\(\$conn~', $rg) === 1,
   'the per-trait posts are suppressed and nothing replaces them, so the package is silent');
$dfl = harness_strip_comments(file_get_contents(__DIR__ . '/dhcfighters-lib.php'));
ok(preg_match('~function dhcf_commit_drop\([^)]*\$announce = true~', $dfl) === 1,
   'the announce flag does not default to true, so every other drop on the platform went quiet');
/* The picture on the post is the same file the admin preview links to,
   or the DM and the announcement show different Fighters. */
ok(preg_match('~return_gift_url\(\$discord_id\),~', $rg) === 1,
   'the announcement does not carry the assembled package as its image');

/* Serial 0 cannot collide with a saved Fighter. */
ok((int)RETURN_GIFT_SERIAL === 0,
   'the package serial is not 0, so a preview can overwrite a real Fighter render');

/* ---------------------------------------------------------------- *
 * THE GATE
 * ---------------------------------------------------------------- */
echo "\nthe grant fires for the right people and only once\n";

/* A mysqli stand-in. $had is the set of categories already granted,
   as the trait ledger would report them; $table false makes every
   query fail, which is the unreadable-ledger case. */
class RGConn {
	public $had = array(), $writes = array(), $table = true;
	public function query($sql) {
		$this->writes[] = preg_replace('~\s+~', ' ', trim($sql));
		if (!$this->table) return false;
		if (stripos($sql, 'SELECT') === 0) {
			/* Only the ledger question. dhcf_commit_drop() asks several
			   others through this same stub, and answering those with
			   category rows produced a screenful of undefined-key
			   warnings from real code being fed nonsense. */
			if (strpos($sql, "'returngift'") === false) return new RGRes();
			$rows = array();
			foreach ($this->had as $c) $rows[] = array('category' => $c);
			return new RGRes($rows);
		}
		return true;
	}
	public function real_escape_string($s) { return addslashes($s); }
}
class RGRes {
	public $num_rows = 0; private $rows, $i = 0;
	public function __construct($rows = array()) { $this->rows = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return isset($this->rows[$this->i]) ? $this->rows[$this->i++] : null; }
}

$old = date('Y-m-d H:i:s', time() - 45 * 86400);
$new = date('Y-m-d H:i:s', time() - 3  * 86400);
$edge_in  = date('Y-m-d H:i:s', time() - (RETURN_GIFT_DAYS + 1) * 86400);
$edge_out = date('Y-m-d H:i:s', time() - (RETURN_GIFT_DAYS - 1) * 86400);

$c = new RGConn();
ok(return_gift_due($c, 7, $old)      === true,  'a member away 45 days is not due a package');
ok(return_gift_due($c, 7, $new)      === false, 'a member here 3 days ago is due a package');
ok(return_gift_due($c, 7, $edge_in)  === true,  'the day after the window does not qualify');
ok(return_gift_due($c, 7, $edge_out) === false, 'the day before the window qualifies');
ok(return_gift_due($c, 7, null)      === false, 'a member with no previous login is due a package');
ok(return_gift_due($c, 0, $old)      === false, 'a signed-out request is due a package');

$c->had = array('background', 'torso');
ok(return_gift_due($c, 7, $old) === false, 'somebody who already had one is due another');

/* An unreadable ledger is the degrade-to-silence case. */
$c2 = new RGConn(); $c2->table = false;
ok(return_gift_due($c2, 7, $old) === false,
   'an unreadable trait ledger hands out packages it cannot record, forever');

/*
 * THE CLAIM IS THE TRAIT ROWS, NOT A MARKER. The first version wrote
 * action = 'claimed' into the nudges table, which has only ever held
 * shown/clicked/snoozed/rested/none -- an ENUM there would either kill
 * the feature silently or, on a non-strict server, coerce the value to
 * '' so the check never matches and the package is re-granted on every
 * single login. Nothing in this file may touch that table again.
 */
$rgsrc = harness_strip_comments(file_get_contents(__DIR__ . '/lib/return-gift.php'));
ok(stripos($rgsrc, 'nudges') === false,
   'the gift is writing to the nudges table again, where its action value may not be legal');
ok(strpos($rgsrc, "source = 'returngift'") !== false,
   'the claim is no longer read from the trait ledger');

/*
 * REPEATABLE, NOT ONCE EVER. Signing in sets the distance back to
 * zero, so qualifying again means genuinely going another full month
 * without turning up -- and each lapse needs the whole package again
 * to catch up. A ledger read over ALL history would silently make
 * this a one-time gift, and nothing on screen would look different.
 */
ok(preg_match('~awarded_at > DATE_SUB\(NOW\(\), INTERVAL \$days DAY\)~', $rgsrc) === 1,
   'the ledger read is not windowed, so the package is once ever instead of once per lapse');
ok(strpos($rgsrc, '$days = max(1, (int)RETURN_GIFT_DAYS);') !== false,
   'the claim window is not tied to RETURN_GIFT_DAYS, so the two can drift apart');

/* A member whose previous package has aged past the window qualifies
   again -- the behavioural half of the same claim. */
$c_again = new RGConn();
$c_again->had = array();                 /* older than the window, so not returned */
ok(return_gift_due($c_again, 7, $old) === true,
   'a member lapsing a second time does not qualify again');
$c_now = new RGConn();
$c_now->had = array('background');       /* inside the window: this visit */
ok(return_gift_due($c_now, 7, $old) === false,
   'a second request in the same visit would hand over a second package');

/* ---------------------------------------------------------------- *
 * THE ORDER OF THE WRITE
 * ---------------------------------------------------------------- */
echo "\nthe grant finishes a package it started\n";
/* Unreadable ledger: hand over nothing rather than risk repeating. */
$c3 = new RGConn(); $c3->table = false;
ok(return_gift_grant($c3, 7, $ids[0]) === array(),
   'the grant handed out traits without being able to check what was already given');

/*
 * SELF-HEALING. If the request died partway through last time, the
 * next sign-in must finish the package -- not skip it (they are short
 * five traits forever) and not restart it (duplicates every time).
 */
$c4 = new RGConn();
$c4->had = array('background', 'torso', 'head');
$resume = return_gift_grant($c4, 7, $ids[0]);
$full   = return_gift_traits($ids[0]);
ok(count($resume) === count($full) - 3,
   'a half-finished package is not resumed: expected ' . (count($full) - 3)
 . ' remaining, got ' . count($resume));
ok(!array_intersect(array_keys($resume), array('background', 'torso', 'head')),
   'the grant handed over categories the member already had, so returning twice duplicates traits');

/*
 * A TRAIT THAT DID NOT COMMIT IS NOT GIVEN. dhcf_commit_drop() returns
 * null when its INSERT fails, and the first version ignored that and
 * recorded the trait as handed over regardless -- so a column that
 * would not take 'returngift' would have produced a Discord post
 * announcing eight traits nobody received, under a picture promising
 * them. The stub below accepts the SELECT and refuses every INSERT.
 */
class RGFailWrite extends RGConn {
	public function query($sql) {
		if (stripos($sql, 'INSERT') === 0) return false;
		return parent::query($sql);
	}
}
$cf = new RGFailWrite();
ok(return_gift_grant($cf, 7, $ids[0]) === array(),
   'traits whose INSERT failed are still reported as given, so the announcement promises what was never granted');

/* ---------------------------------------------------------------- *
 * THE TWO COPIES OF THE WINDOW
 * ---------------------------------------------------------------- */
echo "\nthe login gate and the constant have not drifted apart\n";
/*
 * skulliance.php cannot read RETURN_GIFT_DAYS -- the constant lives in
 * the file its cheap pre-check exists to avoid loading -- so the number
 * is written twice. Lower the constant without lowering the inline
 * copy and the feature stops firing for everybody, with nothing in any
 * log to say why.
 */
$skl = file_get_contents(__DIR__ . '/skulliance.php');
ok(preg_match('~\(time\(\) - \$skl_was\) >= (\d+) \* 86400~', $skl, $m) === 1,
   'the inline window check in skulliance.php is gone or reshaped');
if (!empty($m)) {
	ok((int)$m[1] === (int)RETURN_GIFT_DAYS,
	   "skulliance.php gates at {$m[1]} days but RETURN_GIFT_DAYS is " . RETURN_GIFT_DAYS);
}
/* Presence, not reachability -- a grep cannot tell a live call from one
   behind a false. What it does catch is the call being deleted, which
   is how a feature usually stops existing. */
ok(preg_match('~^\s*return_gift_grant\(\$conn~m', $skl) === 1,
   'nothing on the login path actually hands the package over');
/* The read of last_login must still happen BEFORE the UPDATE, or every
   returning player looks like they were here today. */
$p_read  = strpos($skl, '$skl_prev_login = null;');
$p_write = strpos($skl, 'UPDATE users SET last_login = NOW()');
$p_gift  = strpos($skl, 'return_gift_due($conn');
ok($p_read !== false && $p_write !== false && $p_read < $p_write,
   'last_login is read after it is written, so nobody will ever look lapsed');
ok($p_gift !== false && $p_gift > $p_write,
   'the gift check is not on the login path at all');

/*
 * AND IT MUST NOT BE INSIDE THE REFERRAL BLOCK. The gift sits a few
 * lines below the invite payout and is indented the same; nesting it
 * one brace deeper would make the whole feature fire only for people
 * who arrived through an invite link, which is the opposite of the
 * intent and would look completely normal in a diff.
 *
 * Measured with the tokeniser rather than by reading indentation: if
 * the brace depth returns to the inside of the discord_id block
 * between the invite payout and the gift, the referral `if` closed and
 * the two are siblings.
 */
$tk = token_get_all($skl);
$depth = 0; $seen_ref = false; $min_between = 99; $gift_d = null; $inside = null;
foreach ($tk as $tok) {
	if (is_string($tok)) {
		if ($tok === '{') $depth++; elseif ($tok === '}') $depth--;
		if ($seen_ref && $gift_d === null) $min_between = min($min_between, $depth);
		continue;
	}
	if (strpos($tok[1], 'discord_id') !== false && $inside === null) $inside = $depth + 1;
	if ($tok[1] === 'ref_credit') $seen_ref = true;
	if ($tok[1] === 'return_gift_due' && $gift_d === null) $gift_d = $depth;
}
ok($inside !== null && $gift_d !== null && $min_between <= $inside,
   'the gift is nested inside the referral block, so only invited members get one');

/*
 * THE ANNOUNCEMENT HAS TO WORK WITH ONLY WHAT THE LOGIN PATH LOADS.
 * It did not: dhcfighters-notify.php is pulled in by the very branch of
 * dhcf_commit_drop() that this package skips, so skl_discordmsg() and
 * dhcf_author_block() were both absent, return_gift_announce() hit its
 * own guards and returned, and the post silently never fired. The
 * harness missed it because IT required the notifier at the top.
 *
 * Run in a subprocess loading EXACTLY what skulliance.php loads, which
 * is the only way to see what production sees.
 */
$probe = escapeshellarg(
	"require '" . __DIR__ . "/dhcfighters-lib.php';"
	. "require '" . __DIR__ . "/lib/return-gift.php';"
	. "class R { public \$num_rows = 0; function fetch_assoc() { return null; } }"
	. "class C { function query(\$s) { return new R; } function real_escape_string(\$x) { return \$x; } }"
	. "@return_gift_announce(new C, 7, array('head' => 'gas-mask'), '931869374661423104');"
	/* DISTINCT MARKERS, NOT ONE INSIDE THE OTHER. This compared the tail
	   against 'SENDER' -- and 'NOSENDER' ends in 'SENDER', so the check
	   passed on the exact failure it was written to catch. */
	. "echo function_exists('skl_discordmsg') ? 'RG_HASSENDER' : 'RG_MISSING';");
$out = trim((string)@shell_exec(PHP_BINARY . ' -r ' . $probe . ' 2>/dev/null'));
ok(substr($out, -12) === 'RG_HASSENDER',
   'return_gift_announce() cannot reach a Discord sender with only the login path loaded, '
 . 'so the welcome-back post never fires in production (got: ' . substr($out, -12) . ')');

/* ---------------------------------------------------------------- *
 * THE PREVIEW ENDPOINT
 * ---------------------------------------------------------------- */
echo "\nthe preview endpoint is not a public render farm\n";
/* COMMENTS STRIPPED FIRST. The first version of the ordering check
   below failed on a correct file: the header comment explains why the
   render is on demand and names dhcf_render_fighter() while doing it,
   so the "render" was found above the gate. */
$card = harness_strip_comments(file_get_contents(__DIR__ . '/return-gift-card.php'));
$a_gate = strpos($card, 'admin_require()');
$a_rend = strpos($card, 'dhcf_render_fighter(');
ok($a_gate !== false && $a_rend !== false && $a_gate < $a_rend,
   'anyone can make this endpoint composite thousand-pixel layers by changing a number');
/* It redirects to the public file rather than streaming, so Copy Image
   Address in the admin yields an address the recipient can open. */
ok(strpos($card, 'header(\'Location: \' . $url') !== false,
   'the endpoint no longer redirects, so the address the owner copies is the admin-only one');
/* Buffered: display_errors is on platform-wide and a notice ahead of a
   Location header means no redirect at all. */
ok(strpos($card, 'ob_start()') !== false && strpos($card, 'ob_end_clean()') !== false,
   'the endpoint does not buffer, so one notice breaks every preview');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all return-gift checks passed\n");
exit($fail ? 1 : 0);
