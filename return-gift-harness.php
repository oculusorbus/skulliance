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
			$rendered = dhcf_render_fighter($packs[$id], RETURN_GIFT_SERIAL, RETURN_GIFT_SIZE);
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

/* Serial 0 cannot collide with a saved Fighter. */
ok((int)RETURN_GIFT_SERIAL === 0,
   'the package serial is not 0, so a preview can overwrite a real Fighter render');

/* ---------------------------------------------------------------- *
 * THE GATE
 * ---------------------------------------------------------------- */
echo "\nthe grant fires for the right people and only once\n";

/* A mysqli stand-in: records writes, answers the claimed-check from
   whatever we put in it. */
class RGConn {
	public $claimed = false, $writes = array(), $table = true;
	public function query($sql) {
		$this->writes[] = preg_replace('~\s+~', ' ', trim($sql));
		if (!$this->table) return false;
		if (stripos($sql, 'SELECT') === 0) {
			$r = new RGRes(); $r->num_rows = $this->claimed ? 1 : 0; return $r;
		}
		return true;
	}
	public function real_escape_string($s) { return addslashes($s); }
}
class RGRes { public $num_rows = 0; public function fetch_assoc() { return null; } }

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

$c->claimed = true;
ok(return_gift_due($c, 7, $old) === false, 'somebody who already had one is due another');

/* No table is the degrade-to-silence case. */
$c2 = new RGConn(); $c2->table = false;
ok(return_gift_due($c2, 7, $old) === false,
   'a missing nudges table hands out packages it cannot record, forever');

/* ---------------------------------------------------------------- *
 * THE ORDER OF THE WRITE
 * ---------------------------------------------------------------- */
echo "\nthe claim is recorded before anything is handed over\n";
/*
 * If the grant runs first and the ledger write fails, the player
 * collects again on every page load until somebody notices. The other
 * way round, the worst case is one person owed three traits.
 */
$c3 = new RGConn(); $c3->table = false;   /* INSERT fails */
$got = return_gift_grant($c3, 7, $ids[0]);
ok($got === array(), 'the grant handed out traits after failing to record the claim');

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

/* ---------------------------------------------------------------- *
 * THE PREVIEW ENDPOINT
 * ---------------------------------------------------------------- */
echo "\nthe preview endpoint is not a public render farm\n";
/* COMMENTS STRIPPED FIRST. The first version of the ordering check
   below failed on a correct file: the header comment explains why the
   render is on demand and names dhcf_render_fighter() while doing it,
   so the "render" was found above the gate. */
require_once __DIR__ . '/harness-strip.php';
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
