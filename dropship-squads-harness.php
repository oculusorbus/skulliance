<?php
/*
 * dropship-squads-harness.php — CLI only. Saved squads, with no database.
 *
 * The rules live in two pure functions on purpose -- dropshipSquadMemberState()
 * and dropshipSquadReadiness() take arrays and return arrays -- so this drives
 * the REAL ones rather than describing them. Everything that writes is a thin
 * wrapper around these two, so what is checked here is what the page decides.
 *
 * Usage: php dropship-squads-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/dropship/dropship-squads.php';

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok   $what\n"; return true; }
	$fail++;  echo "  FAIL $what\n";  return false;
}
function soldier($o) {
	return array_merge(array('id' => 7, 'user_id' => 1, 'active' => 0, 'deceased' => 0), $o);
}

/* ---------- what a member is ----------------------------------------------- */

echo "what one member is\n";
ok(dropshipSquadMemberState(soldier(array()), 1) === 'ready', 'an idle soldier of yours is ready');
ok(dropshipSquadMemberState(soldier(array('active' => 1)), 1) === 'fielded',
   'a deployed soldier reads as something other than fielded');
ok(dropshipSquadMemberState(soldier(array('deceased' => 1)), 1) === 'fallen',
   'a dead soldier reads as something other than fallen');
ok(dropshipSquadMemberState(soldier(array('user_id' => 0)), 1) === 'sold',
   'a soldier you no longer own does not read as sold');
ok(dropshipSquadMemberState(soldier(array('user_id' => 99)), 1) === 'sold',
   'a soldier owned by somebody else does not read as sold');
ok(dropshipSquadMemberState(null, 1) === 'gone', 'a missing soldier row does not read as gone');
/*
 * OWNERSHIP OUTRANKS EVERYTHING. A sold soldier can be deceased and standing
 * on the buyer's field at the same time, and none of that is the seller's
 * business -- reporting "already fought this game" about somebody else's
 * soldier would send a player looking for a soldier they cannot see.
 */
ok(dropshipSquadMemberState(soldier(array('user_id' => 0, 'deceased' => 1)), 1) === 'sold',
   'a sold soldier that is also dead reports the death instead of the sale');
ok(dropshipSquadMemberState(soldier(array('user_id' => 0, 'active' => 1)), 1) === 'sold',
   'a sold soldier on the buyer\'s field reports as fielded, which is not yours to say');
/* The id is compared as a number: the session writes strval() of it. */
ok(dropshipSquadMemberState(soldier(array('user_id' => '1')), '1') === 'ready',
   'a string user id from a restored session reads as somebody else');
/* ORDER INSIDE THE FUNCTION, pinned because both flags can be set at once
   and the answer differs. killSquad() writes deceased=1 and active=0 in one
   statement, so the pair is not reachable today -- but this function takes
   whatever row it is handed, and "dead" beating "on the field" is the
   decision, not an accident of which if came first. */
ok(dropshipSquadMemberState(soldier(array('active' => 1, 'deceased' => 1)), 1) === 'fallen',
   'a soldier that is both dead and flagged active reports as fielded');

/* ---------- when a squad may be fielded ------------------------------------ */

echo "\nwhen a squad may be fielded\n";
$four = array('ready', 'ready', 'ready', 'ready');
$r = dropshipSquadReadiness($four, 4);
ok($r['can'] === true, 'four ready soldiers and room for four cannot deploy');
ok($r['why'] === '', 'a deployable squad still explains itself');

/* THE OWNER'S CALL: refuse a partial squad rather than field three of four.
   A Super Soldier Squad is worth 80 because all four armour tiers are there;
   fielding three would hand somebody 74 under a name that promised 80. */
$r = dropshipSquadReadiness(array('ready', 'ready', 'ready', 'fallen'), 4);
ok($r['can'] === false, 'a squad with a fallen member deploys three of four anyway');
ok(strpos($r['why'], 'already fought this game') !== false,
   'a fallen member is reported as missing or broken rather than as having fought: ' . $r['why']);
ok($r['fix'] === 'wait', 'a fallen member suggests something other than waiting');

/*
 * FALLEN IS NOT DAMAGE, IT IS TUESDAY, and this is the check that keeps the
 * wording honest. killSquad() kills the field after every run and nothing
 * revives anybody until an admin opens the next game, so a squad reads as
 * fallen for most of the week it was used in. Any wording that sounds like a
 * fault sends somebody hunting a bug that is not there.
 */
foreach (array('missing', 'no longer', 'error', 'problem', 'broken') as $alarming) {
	ok(stripos($r['why'], $alarming) === false,
	   "a squad resting between games says '$alarming' at the player");
}

$r = dropshipSquadReadiness(array('ready', 'ready', 'ready', 'sold'), 4);
ok($r['can'] === false, 'a squad with a sold member can still be deployed');
ok(strpos($r['why'], 'no longer yours') !== false, 'a sold member is not explained: ' . $r['why']);
ok($r['fix'] === 'edit', 'a sold member does not point at rebuilding');
/* The two causes must not be conflated: one waits, the other needs a decision. */
ok(dropshipSquadReadiness(array('ready', 'fallen'), 4)['why']
   !== dropshipSquadReadiness(array('ready', 'sold'), 4)['why'],
   'a soldier that fought and a soldier that was sold say the same thing');

/* ---------- the squad nobody can manage ------------------------------------ */

echo "\nthe squad you no longer own\n";
$r = dropshipSquadReadiness(array('sold', 'sold', 'sold', 'sold'), 4);
ok($r['can'] === false, 'a squad of soldiers you sold is deployable');
ok($r['fix'] === 'delete', 'a wholly sold squad offers something other than deleting it');
ok(strpos($r['why'], 'any of these') !== false, 'the all-sold case is not worded for all: ' . $r['why']);
/* ...and one lost soldier is not "any of these soldiers". */
ok(strpos(dropshipSquadReadiness(array('sold'), 4)['why'], 'this soldier') !== false,
   'a one-soldier squad is told about soldiers in the plural');
$r = dropshipSquadReadiness(array('gone', 'sold'), 4);
ok($r['fix'] === 'delete', 'a squad of missing and sold rows is not offered a delete');
$r = dropshipSquadReadiness(array(), 4);
ok($r['can'] === false && $r['fix'] === 'delete', 'an empty squad is deployable or cannot be removed');

/* ---------- the field's own ceiling ---------------------------------------- */

echo "\nthe four-soldier ceiling\n";
$r = dropshipSquadReadiness($four, 2);
ok($r['can'] === false, 'four ready soldiers deploy into two free slots');
ok($r['fix'] === 'withdraw', 'a full field does not say to withdraw somebody');
ok(strpos($r['why'], 'room for 2') !== false, 'the refusal does not say how much room there is');
ok(dropshipSquadReadiness(array('ready', 'ready'), 2)['can'] === true,
   'two soldiers will not go into exactly two slots');
/* Already standing there is not a reason to refuse. */
ok(dropshipSquadReadiness(array('fielded', 'fielded', 'ready'), 1)['can'] === true,
   'a squad mostly on the field already cannot send its last member');
$r = dropshipSquadReadiness(array('fielded', 'fielded'), 0);
ok($r['can'] === false && strpos($r['why'], 'already deployed') !== false,
   'a squad that is wholly deployed is offered a Deploy: ' . $r['why']);

/* ---------- counts, which the screen renders ------------------------------- */

echo "\nthe counts\n";
$r = dropshipSquadReadiness(array('ready', 'fallen', 'fallen', 'sold'), 4);
ok($r['counts']['fallen'] === 2 && $r['counts']['sold'] === 1 && $r['counts']['ready'] === 1,
   'the per-state counts are wrong: ' . json_encode($r['counts']));
ok(dropshipSquadPlural(1, 'member') === '1 member', 'one member is pluralised');
ok(dropshipSquadPlural(3, 'member') === '3 members', 'three members are not');
/* Singular all the way through the sentence, noun AND verb: "1 members are"
   and "1 member are" are both the mark of a page nobody finished. */
$one = dropshipSquadReadiness(array('ready', 'sold'), 4)['why'];
ok(strpos($one, '1 member ') === 0, "a single lost member reads as plural: $one");
ok(strpos($one, ' is no longer yours') !== false, "the verb does not agree: $one");
$two = dropshipSquadReadiness(array('ready', 'sold', 'sold'), 4)['why'];
ok(strpos($two, '2 members ') === 0 && strpos($two, ' are no longer yours') !== false,
   "two lost members read as singular: $two");

/* ---------- the writes refuse a guessed id --------------------------------- */

echo "\nnothing takes a soldier id from the browser\n";
$src = file_get_contents(__DIR__ . '/dropship/dropship-squads.php');
$code = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '', $src);
/*
 * deploySoldier() carries its own comment admitting it never checks
 * ownership, so a brute-forced soldier_id deploys somebody else's soldier.
 * None of that may be copied in here: a saved squad reads its members FROM
 * THE FIELD, and every statement that writes is scoped to the player.
 */
ok(strpos($code, '$_POST') === false, 'the squad library reads $_POST directly');
ok(strpos($code, '$_GET') === false,  'the squad library reads $_GET directly');
/*
 * EVERY STATEMENT THAT WRITES, not merely every function that mentions $me.
 * The first version of this check looked for '$me' anywhere in the function
 * body and passed while the UPDATE had lost its scope entirely -- $me is all
 * over these functions for other reasons, so its presence proves nothing.
 * The statements are pulled out and each one is checked on its own.
 */
foreach (array('dropshipSquadDeploy', 'dropshipSquadDisperse', 'dropshipSquadDrop') as $fn) {
	$at = strpos($code, 'function ' . $fn);
	ok($at !== false, "$fn has gone");
	$body = $at === false ? '' : substr($code, $at, 1600);
	preg_match_all('/\b(UPDATE|DELETE)\b.*?(?=\'\)|";|\')/s', $body, $stmts);
	$writes = 0;
	foreach ($stmts[0] as $st) {
		$st = preg_replace('/\s+/', ' ', $st);
		/* squad_members is reached THROUGH a squad whose ownership was just
		   established, and carries no user_id of its own to test. */
		if (strpos($st, 'squad_members') !== false) continue;
		$writes++;
		ok(strpos($st, 'user_id = $me') !== false,
		   "$fn writes without scoping to the player: " . substr($st, 0, 90));
	}
	ok($writes > 0, "$fn no longer writes anything, so the checks above are vacuous");
}
/* The delete carries its own ownership test rather than trusting a SELECT
   that ran a statement earlier. */
$at = strpos($code, 'function dropshipSquadDisperse');
ok(strpos(substr($code, $at, 900), 'DELETE FROM squads WHERE id = $sid AND user_id = $me') !== false,
   'dispersing can delete a squad that is not yours');
/* Saving never takes a list of soldiers from the caller. */
ok(preg_match('/function dropshipSquadSave\(\$conn, \$name\)/', $code) === 1,
   'dropshipSquadSave() takes soldier ids from its caller, which is the browser');
ok(strpos($code, 'active = 1 AND deceased = 0') !== false,
   'saving does not read the squad off the field');

/*
 * AND THE BUTTON NEXT DOOR. deploySoldier() is what the single Deploy posts
 * to, and it carried a comment admitting it never checked ownership -- a
 * bare soldier_id from a form, straight into the WHERE. Squad deploys would
 * have sat beside it doing the right thing while the older path stayed open,
 * so it is fixed and pinned here rather than left as a known hole with a
 * nicer neighbour.
 */
echo "\nand the single Deploy is scoped too\n";
$dbsrc = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
                      (string)file_get_contents(__DIR__ . '/dropship/db.php'));
$at = strpos($dbsrc, 'function deploySoldier');
ok($at !== false, 'deploySoldier() has gone');
$body = $at === false ? '' : substr($dbsrc, $at, 900);
ok(strpos($body, "user_id = '\".\$_SESSION['userData']['dropship_user_id'].\"'") !== false,
   'deploySoldier() updates by id alone again, so a guessed id moves somebody else\'s soldier');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all squad checks passed\n");
exit($fail ? 1 : 0);
